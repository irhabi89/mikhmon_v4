/*
 * Sales sync UI: status polling, start/cancel, chunked month scan and import.
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

  function setProgress(percent) {
    var bar = $("mmSyncBar");
    if (bar) { bar.style.width = Math.max(0, Math.min(100, percent || 0)) + "%"; }
  }

  function render(data) {
    if (!data) { return; }
    var info = $("mmSyncInfo");
    var badge = $("mmSyncBadge");
    var router = data.router || {};
    var db = data.db || {};
    var run = data.run || null;

    if (info) {
      var parts = [];
      parts.push("MikroTik: <b>" + (router.records != null ? fmt(router.records) : "?") + "</b> record");
      parts.push("Database: <b>" + fmt(db.total) + "</b> transaksi");
      parts.push("Cocok: <b>" + fmt(db.matched) + "</b>");
      if (db.missing) { parts.push("<b class='text-red'>" + fmt(db.missing) + "</b> belum diimpor"); }
      if (db.extra !== null && typeof db.extra !== "undefined" && db.extra > 0) { parts.push("<b>" + fmt(db.extra) + "</b> arsip di database"); }
      if (db.invalid) { parts.push("<b class='text-red'>" + fmt(db.invalid) + "</b> invalid"); }
      if (data.coverage !== null) { parts.push("Tercakup <b>" + pct(data.coverage) + "</b>"); }
      if (data.exact !== null && (!db.extra || db.extra <= 0)) { parts.push("Sinkron <b>" + pct(data.exact) + "</b>"); }
      if (run && run.status) { parts.push("Status: <b>" + esc(run.status) + "</b>" + (run.phase ? " / " + esc(run.phase) : "")); }
      info.innerHTML = parts.join(" &nbsp;|&nbsp; ");
    }

    if (badge) {
      if (data.empty) {
        badge.className = "mm-badge bg-grey text-white";
        badge.innerHTML = "Belum ada data";
      } else if (data.in_sync) {
        badge.className = "mm-badge bg-green text-white";
        badge.innerHTML = '<i class="fa fa-check"></i> 100% Sinkron';
      } else {
        badge.className = "mm-badge bg-yellow text-dark";
        badge.innerHTML = '<i class="fa fa-refresh"></i> ' + (data.coverage !== null ? pct(data.coverage) + " tercakup" : "Belum diverifikasi");
      }
    }

    var expected = Number(router.expected_scripts || 0);
    var seen = Number(router.scanned_scripts || 0);
    if (expected > 0) {
      setProgress(Math.min(100, seen / expected * 100));
    } else if (run && run.status === "done") {
      setProgress(100);
    }

    if (run && run.status === "failed" && info) {
      info.innerHTML += "<br><b class='text-red'>" + esc(run.error || "sync gagal") + "</b>";
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
            setTimeout(function () { location.reload(); }, 700);
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
    }, 2000);
  }

  function setRunning(value) {
    running = value;
    var start = $("mmSyncStart");
    var cancel = $("mmSyncCancel");
    var importBtn = $("mmSyncImport");
    if (start) { start.style.display = value ? "none" : ""; }
    if (cancel) { cancel.style.display = value ? "" : "none"; }
    if (importBtn) { importBtn.disabled = value; }
  }

  function showError(message) {
    var info = $("mmSyncInfo");
    if (info) { info.innerHTML = "<b class='text-red'>" + esc(message) + "</b>"; }
  }

  function start() {
    setRunning(true);
    showError("");
    post("start", {}).then(function (data) {
      render(data);
      var run = data.run || null;
      if (!run) { setRunning(false); return; }
      if (run.worker === "web" && run.mode === "full" && data.months && data.months.length) {
        return scanMonths(data.months, run.id);
      }
      pollUntilDone();
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
        setProgress(index / months.length * 60);
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
        setProgress(60 + Math.min(35, 60 - pending));
        return importAll(runId);
      }
      return post("verify", { run_id: runId }).then(function (result) {
        render(result);
        setRunning(false);
        setProgress(100);
        setTimeout(function () { location.reload(); }, 700);
      });
    });
  }

  function cancel() {
    var run = null;
    loadStatus().then(function (data) {
      run = data.run;
      if (!run) { setRunning(false); return; }
      return post("cancel", { run_id: run.id });
    }).then(function () {
      setRunning(false);
      pollUntilDone();
    }).catch(function (err) {
      setRunning(false);
      showError(err.message);
    });
  }

  function probe() {
    showError("");
    post("probe", {}).then(function (data) {
      var info = $("mmSyncInfo");
      if (info) {
        info.innerHTML = data.ok
          ? "Koneksi MikroTik OK. Script: <b>" + fmt(data.scripts) + "</b>"
          : "<b class='text-red'>Koneksi gagal: " + esc(data.error || "") + "</b>";
      }
    }).catch(function (err) { showError(err.message); });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var startBtn = $("mmSyncStart");
    var cancelBtn = $("mmSyncCancel");
    var probeBtn = $("mmSyncProbe");
    if (startBtn) { startBtn.addEventListener("click", start); }
    if (cancelBtn) { cancelBtn.addEventListener("click", cancel); }
    if (probeBtn) { probeBtn.addEventListener("click", probe); }
    if (session) {
      loadStatus().catch(function () { /* ignore initial failure */ });
    }
  });
})();
