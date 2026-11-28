/*
 * Sales sync UI: status polling, start/cancel, fast single-pass or chunked scan & import.
 * Config is provided by the report page as window.MM_SYNC.
 */
(function () {
  "use strict";

  var cfg = window.MM_SYNC || {};
  var session = cfg.session || "";
  var endpoint = cfg.endpoint || "./process/sales_sync.php";
  var csrf = cfg.csrf || "";
  var polling = false;
  var running = false;
  var liveCompare = null;
  var lastRun = null;

  function $(id) { return document.getElementById(id); }
  function esc(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (ch) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
    });
  }
  function fmt(n) {
    n = Number(n || 0);
    return n.toLocaleString("id-ID");
  }
  function pct(v) {
    return (v === null || typeof v === "undefined") ? "-" : (Number(v).toLocaleString("id-ID", { maximumFractionDigits: 1 }) + "%");
  }

  function post(action, extra) {
    var params = new URLSearchParams();
    params.set("action", action);
    params.set("session", session);
    params.set("csrf", csrf);
    if (extra) {
      Object.keys(extra).forEach(function (k) { params.set(k, extra[k]); });
    }
    return fetch(endpoint, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
      body: params.toString()
    }).then(function (r) {
      return r.text().then(function (text) {
        var data;
        try { data = JSON.parse(text); } catch (e) {
          throw new Error("Respons tidak valid: " + text.slice(0, 120));
        }
        if (!r.ok || !data || data.ok === false) {
          throw new Error((data && data.error) ? data.error : ("HTTP " + r.status));
        }
        if (data.csrf) { csrf = data.csrf; }
        return data;
      });
    });
  }

  function setProgress(percent, indeterminate, phaseLabel) {
    var bar = $("mmSyncBar");
    var pctEl = $("mmSyncPercent");
    var phaseEl = $("mmSyncPhase");
    var clamped = Math.max(0, Math.min(100, Number(percent || 0)));
    if (bar) {
      var wrap = bar.parentNode;
      if (indeterminate) {
        if (wrap) { wrap.classList.add("is-indeterminate"); }
        bar.style.width = "35%";
      } else {
        if (wrap) { wrap.classList.remove("is-indeterminate"); }
        bar.style.width = clamped + "%";
      }
    }
    if (pctEl) {
      pctEl.textContent = indeterminate && clamped <= 0
        ? "..."
        : (clamped.toLocaleString("id-ID", { maximumFractionDigits: 1 }) + "%");
    }
    if (phaseEl && phaseLabel) {
      phaseEl.textContent = phaseLabel;
    }
  }

  function updateStartButton(missing, inSync) {
    var startBtn = $("mmSyncStart");
    if (!startBtn || running) { return; }
    if (Number(missing || 0) > 0) {
      startBtn.innerHTML = '<i class="fa fa-play"></i> Sinkronkan (' + fmt(missing) + ')';
    } else if (inSync) {
      startBtn.innerHTML = '<i class="fa fa-refresh"></i> Sinkronkan Ulang';
    } else {
      startBtn.innerHTML = '<i class="fa fa-play"></i> Mulai Sinkronisasi';
    }
  }

  function reloadClean() {
    if (window.location.hash === "#mmSyncModal") {
      window.location.hash = "_";
      if (window.history && window.history.replaceState) {
        window.history.replaceState(null, document.title, window.location.pathname + window.location.search);
      }
    }
    location.reload();
  }

  function renderLiveCompare(c) {
    if (!c || running || polling) { return; }
    var info = $("mmSyncInfo");
    var badge = $("mmSyncBadge");
    var missing = Number(c.missing || 0);
    var invalid = Number(c.invalid || 0);
    if (info) {
      info.innerHTML = "MikroTik live: <b>" + fmt(c.router_total) + "</b> record valid"
        + (invalid > 0 ? " <span class='mm-muted'>(" + fmt(invalid) + " invalid)</span>" : "")
        + " &nbsp;|&nbsp; Database: <b>" + fmt(c.db_total) + "</b>"
        + " &nbsp;|&nbsp; Cocok: <b>" + fmt(c.matched) + "</b>"
        + " &nbsp;|&nbsp; Belum: <b class='" + (missing > 0 ? "text-orange" : "text-green") + "'>" + fmt(missing) + "</b>"
        + (c.unsynced_percent !== null && typeof c.unsynced_percent !== "undefined" ? " (" + pct(c.unsynced_percent) + ")" : "")
        + (c.archive_extra ? "<br><span class='mm-muted'>Arsip DB: " + fmt(c.archive_extra) + " · bukan kegagalan sync</span>" : "")
        + (c.checked_at ? "<br><span class='mm-muted'>Perbandingan live: " + esc(c.checked_at) + "</span>" : "");
    }
    if (badge) {
      badge.className = "mm-badge " + (missing === 0 ? "bg-green text-white" : "bg-orange text-white");
      badge.innerHTML = '<i class="fa fa-refresh"></i> ' + (missing === 0 ? "100% Sinkron" : "Belum tersinkron " + fmt(missing) + " (" + pct(c.unsynced_percent) + ")");
    }
    var pageWrap = $("mmPageSourceWrap");
    if (pageWrap) {
      var cls = missing === 0 ? "bg-green text-white" : "bg-orange text-white";
      var label = missing === 0
        ? '<i class="fa fa-check"></i> 100% Sinkron' + (c.archive_extra ? ' · ' + fmt(c.archive_extra) + ' arsip DB' : '')
        : '<i class="fa fa-refresh"></i> Belum tersinkron ' + fmt(missing) + (c.unsynced_percent !== null ? ' (' + pct(c.unsynced_percent) + ')' : '');
      pageWrap.innerHTML = '<span class="mm-badge ' + cls + '">' + label + '</span>';
    }
    if (c.synced_percent !== null && typeof c.synced_percent !== "undefined") {
      setProgress(Number(c.synced_percent), false, missing === 0 ? "Data live sudah sinkron" : "Ditemukan data belum tersinkron");
    }
    updateStartButton(missing, missing === 0);
  }

  function render(data) {
    if (!data) { return; }
    var info = $("mmSyncInfo");
    var badge = $("mmSyncBadge");
    var router = data.router || {};
    var db = data.db || {};
    var run = data.run || null;
    var prog = data.progress || null;
    lastRun = run;

    var active = run && ["queued", "starting", "scanning", "importing", "verifying"].indexOf(String(run.status)) !== -1;

    if (!active && liveCompare && (!run || (run.status === "done" && (!run.finished_at || (liveCompare.checked_at && liveCompare.checked_at >= run.finished_at))))) {
      renderLiveCompare(liveCompare);
      return;
    }

    var liveCount = Number(window.MM_LIVE_COUNT || 0);
    if (info) {
      if (liveCount > 0 && !Number(db.total || 0) && !run) {
        info.innerHTML = "MikroTik live: <b>" + fmt(liveCount) + "</b> record &nbsp;|&nbsp; Database: <b>0</b> &nbsp;|&nbsp; <b class='text-orange'>Belum diimpor</b>";
        setProgress(0, false, "Siap impor dari MikroTik");
        updateStartButton(liveCount, false);
        return;
      }
      var validRouter = Math.max(0, Number(router.records || 0) - Number(db.invalid || 0));
      var parts = [];
      parts.push("MikroTik: <b>" + (router.records != null ? fmt(validRouter) : "?") + "</b> record valid");
      if (Number(router.scanned_scripts || 0) > 0) {
        parts.push("Scan: <b>" + fmt(router.scanned_scripts) + "</b>" + (Number(router.expected_scripts || 0) > 0 ? " / " + fmt(router.expected_scripts) : "") + " script");
      }
      parts.push("Database: <b>" + fmt(db.total) + "</b>");
      parts.push("Cocok: <b>" + fmt(db.matched) + "</b>");
      if (db.missing) { parts.push("<b class='text-orange'>" + fmt(db.missing) + "</b> belum diimpor"); }
      if (db.invalid) { parts.push("<span class='mm-muted'>" + fmt(db.invalid) + " invalid</span>"); }
      if (data.unsynced_percent !== null && typeof data.unsynced_percent !== "undefined") {
        parts.push("<b class='" + (Number(data.unsynced || 0) > 0 ? "text-orange" : "text-green") + "'>" + pct(data.unsynced_percent) + "</b> belum tersinkron");
      } else if (!active) {
        parts.push("<b>Belum diverifikasi</b>");
      }
      if (data.archive_extra !== null && typeof data.archive_extra !== "undefined" && data.archive_extra > 0) {
        parts.push("<span class='mm-muted'>" + fmt(data.archive_extra) + " arsip DB</span>");
      }
      info.innerHTML = parts.join(" &nbsp;|&nbsp; ");
    }

    if (badge) {
      if (data.empty) {
        badge.className = "mm-badge bg-grey text-white";
        badge.innerHTML = "Belum ada data";
      } else if (data.in_sync || (data.verified && Number(data.unsynced || 0) === 0)) {
        badge.className = "mm-badge bg-green text-white";
        badge.innerHTML = '<i class="fa fa-check"></i> 100% Sinkron' + (data.archive_extra ? ' · ' + fmt(data.archive_extra) + ' arsip DB' : '');
      } else if (data.unsynced_percent !== null && typeof data.unsynced_percent !== "undefined") {
        badge.className = "mm-badge " + (Number(data.unsynced || 0) === 0 ? "bg-blue text-white" : "bg-orange text-white");
        badge.innerHTML = '<i class="fa fa-refresh"></i> Belum tersinkron ' + pct(data.unsynced_percent) + ' (' + fmt(data.unsynced || 0) + ')';
      } else {
        badge.className = "mm-badge bg-yellow text-dark";
        badge.innerHTML = '<i class="fa fa-refresh"></i> Belum diverifikasi';
      }
    }

    if (prog) {
      setProgress(prog.percent, !!prog.indeterminate, prog.label || "");
    } else if (active) {
      setProgress(10, true, "Memproses sinkronisasi…");
    } else if (run && run.status === "done") {
      setProgress(100, false, "Sinkronisasi selesai");
    }

    if (run && (run.status === "failed" || run.status === "stalled") && info) {
      info.innerHTML += "<br><b class='text-red'>" + esc(run.error || "sync gagal") + "</b>";
    }
    if (run && Number(run.deleted_router || 0) > 0 && info) {
      info.innerHTML += "<br><span class='text-green'>Script penjualan dihapus dari MikroTik: <b>" + fmt(run.deleted_router) + "</b></span>";
    }
    if (run && run.delete_error && info) {
      info.innerHTML += "<br><span class='text-red'>Sebagian penghapusan gagal: " + esc(run.delete_error) + "</span>";
    }
    if (!active) {
      updateStartButton(Number(data.unsynced || db.missing || 0), !!data.in_sync || (!!data.verified && Number(data.unsynced || 0) === 0));
    }
  }

  function loadStatus() {
    return post("status", {}).then(function (data) {
      render(data);
      return data;
    });
  }

  function pollUntilDone() {
    if (polling) { return; }
    polling = true;
    var attempts = 0;
    var timer = setInterval(function () {
      attempts++;
      loadStatus().then(function (data) {
        var run = data.run || null;
        var status = run ? run.status : "done";
        if (!run || ["done", "failed", "canceled", "stalled"].indexOf(status) !== -1) {
          clearInterval(timer);
          polling = false;
          setRunning(false);
          if (data.in_sync || (run && status === "done")) {
            setProgress(100, false, "Sinkronisasi selesai · memuat ulang…");
            setTimeout(reloadClean, 700);
          }
        }
      }).catch(function (err) {
        clearInterval(timer);
        polling = false;
        setRunning(false);
        showError(err.message);
      });
      if (attempts > 900) {
        clearInterval(timer);
        polling = false;
        setRunning(false);
      }
    }, 1000);
    loadStatus().catch(function () { /* first immediate refresh */ });
  }

  function setRunning(value) {
    running = value;
    window.MM_SYNC_BUSY = !!value;
    var startBtn = $("mmSyncStart");
    var cancelBtn = $("mmSyncCancel");
    var probeBtn = $("mmSyncProbe");
    if (startBtn) { startBtn.style.display = value ? "none" : ""; }
    if (cancelBtn) { cancelBtn.style.display = value ? "" : "none"; }
    if (probeBtn) { probeBtn.disabled = value; }
  }

  function showError(message) {
    var info = $("mmSyncInfo");
    if (info && message) { info.innerHTML = "<b class='text-red'>" + esc(message) + "</b>"; }
    if (message) { setProgress(0, false, "Terjadi kesalahan"); }
  }

  function start() {
    setRunning(true);
    liveCompare = null;
    var deleteAfter = $("mmDeleteAfterImport");
    if (deleteAfter && deleteAfter.checked && !window.confirm("Hapus script record penjualan dari MikroTik setelah import berhasil? Script lain tidak akan dihapus.")) {
      setRunning(false);
      return;
    }
    setProgress(2, true, "Memulai sinkronisasi…");
    post("start", {delete_after_import: (deleteAfter && deleteAfter.checked) ? "1" : "0"}).then(function (data) {
      render(data);
      var run = data.run || null;
      if (!run) { setRunning(false); return; }
      if (run.worker === "web" && run.mode === "full") {
        return runWebSync(run.id, data.months || []);
      }
      pollUntilDone();
    }).catch(function (err) {
      setRunning(false);
      showError(err.message);
    });
  }

  function runWebSync(runId, fallbackMonths) {
    setProgress(5, true, "Memindai script MikroTik…");
    return post("scan_full", { run_id: runId }).then(function (data) {
      render(data);
      if (data.use_months) {
        var months = (data.months && data.months.length) ? data.months : fallbackMonths;
        if (months && months.length) {
          return scanMonths(months, runId);
        }
      }
      return importAll(runId);
    }).catch(function (err) {
      setRunning(false);
      showError(err.message);
    });
  }

  function scanMonths(months, runId) {
    var index = 0;
    function next() {
      if (index >= months.length) {
        return importAll(runId);
      }
      var month = months[index++];
      return post("scan_month", { run_id: runId, month: month }).then(function (data) {
        render(data);
        setProgress(5 + (index / months.length) * 55, false, "Memindai bulan " + month + " (" + index + "/" + months.length + ")…");
        return next();
      });
    }
    return next();
  }

  function importAll(runId) {
    return post("import_chunk", { run_id: runId }).then(function (data) {
      render(data);
      var pending = data.db && data.db.pending ? Number(data.db.pending) : 0;
      if (pending > 0) {
        return importAll(runId);
      }
      setProgress(96, false, "Memverifikasi data & rekonsiliasi…");
      return post("verify", { run_id: runId }).then(function (result) {
        render(result);
        setRunning(false);
        setProgress(100, false, "Sinkronisasi selesai · memuat ulang…");
        setTimeout(reloadClean, 700);
      });
    });
  }

  function cancel() {
    var run = lastRun;
    var p = run ? Promise.resolve({ run: run }) : loadStatus();
    p.then(function (data) {
      var r = data.run;
      if (!r) { setRunning(false); return; }
      return post("cancel", { run_id: r.id });
    }).then(function () {
      setRunning(false);
      pollUntilDone();
    }).catch(function (err) {
      setRunning(false);
      showError(err.message);
    });
  }

  function probe() {
    var info = $("mmSyncInfo");
    if (info) { info.innerHTML = "<span class='mm-muted'>Menguji koneksi ke MikroTik…</span>"; }
    post("probe", {}).then(function (data) {
      if (info) {
        info.innerHTML = data.ok
          ? "Koneksi MikroTik OK. Total script di router: <b>" + fmt(data.scripts) + "</b>"
          : "<b class='text-red'>Koneksi gagal: " + esc(data.error || "") + "</b>";
      }
    }).catch(function (err) { showError(err.message); });
  }

  document.addEventListener("mm-live-sales-loaded", function (event) {
    if (running || polling) { return; }
    window.MM_LIVE_COUNT = Number(event.detail && event.detail.count ? event.detail.count : window.MM_LIVE_COUNT || 0);
    if (!liveCompare) { loadCachedLiveCompare(); }
  });

  document.addEventListener("mm-live-compare", function (event) {
    if (running || polling) { return; }
    liveCompare = event.detail || null;
    if (liveCompare) { renderLiveCompare(liveCompare); }
  });

  document.addEventListener("mm-open-sync-modal", function () {
    window.location.hash = "#mmSyncModal";
  });

  document.addEventListener("mm-start-sync", function () {
    window.location.hash = "#mmSyncModal";
    if (!running && !polling) {
      start();
    }
  });

  function loadCachedLiveCompare() {
    if (!session) { return; }
    fetch("./process/live_compare.php?session=" + encodeURIComponent(session), {credentials: "same-origin"})
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.router_total >= 0) {
          liveCompare = data;
          renderLiveCompare(liveCompare);
        }
      })
      .catch(function () { /* live comparison is optional */ });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var startBtn = $("mmSyncStart");
    var cancelBtn = $("mmSyncCancel");
    var probeBtn = $("mmSyncProbe");
    if (startBtn) { startBtn.addEventListener("click", start); }
    if (cancelBtn) { cancelBtn.addEventListener("click", cancel); }
    if (probeBtn) { probeBtn.addEventListener("click", probe); }
    if (session) {
      loadStatus().then(function (data) {
        var run = data && data.run;
        if (run && ["queued", "starting", "scanning", "importing", "verifying"].indexOf(String(run.status)) !== -1) {
          setRunning(true);
          if (run.worker !== "web") {
            pollUntilDone();
          }
        }
      }).catch(function () { /* ignore initial failure */ });
      loadCachedLiveCompare();
    }
  });
})();
