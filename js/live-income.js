/*
 * Progressive dashboard income fallback (read-only, no sync).
 * Consumes process/live_sales.php NDJSON and updates income spans as rows arrive.
 */
(function () {
  "use strict";
  if (window.MMLiveIncome) { return; }
  var active = null;
  var lastOpts = null;
  var hasRendered = false;
  var inFlight = false;

  function money(currency, decimals, value) {
    return currency + ' ' + Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: decimals});
  }

  function set(id, value) {
    var el = document.getElementById(id);
    if (el) { el.textContent = value; }
  }

  function start(opts) {
    lastOpts = opts;
    if (inFlight) { return; }
    if (active) { try { active.abort(); } catch (e) {} }
    active = new AbortController();
    inFlight = true;
    var currency = opts.currency || 'Rp';
    var decimals = Number(opts.decimals || 0);
    var today = opts.today || new Date().toISOString().slice(0, 10);
    var month = opts.month || today.slice(0, 7);
    var todayCount = 0, monthCount = 0, totalCount = 0;
    var todaySum = 0, monthSum = 0, totalSum = 0;
    var timer = 0;
    var cacheKey = 'mm_live_income:' + (opts.session || '') + ':' + today;
    function showError(message) {
      var el = document.getElementById('mmDashLiveError');
      if (el) { el.textContent = message; el.style.display = ''; }
    }
    function clearError() {
      var el = document.getElementById('mmDashLiveError');
      if (el) { el.style.display = 'none'; el.textContent = ''; }
    }
    function renderValues(tc, ts, mc, ms) {
      set('mmDashTodayValue', money(currency, decimals, ts));
      set('mmDashTodayCount', tc.toLocaleString('id-ID'));
      set('mmDashMonthValue', money(currency, decimals, ms));
      set('mmDashMonthCount', mc.toLocaleString('id-ID'));
      var vals = document.getElementById('mmDashLiveValues');
      if (vals) { vals.style.display = ''; }
      hasRendered = true;
      clearError();
    }
    function render() {
      timer = 0;
      renderValues(todayCount, todaySum, monthCount, monthSum);
    }
    try {
      var rawIncome = sessionStorage.getItem(cacheKey);
      if (rawIncome) {
        var c = JSON.parse(rawIncome);
        if (c && typeof c.todayCount === 'number') {
          renderValues(c.todayCount, c.todaySum || 0, c.monthCount || 0, c.monthSum || 0);
        }
      }
    } catch (e) {}
    function schedule() {
      if (timer) { return; }
      timer = window.setTimeout(render, 120);
    }
    var url = './process/live_sales.php?session=' + encodeURIComponent(opts.session || '');
    fetch(url, {credentials: 'same-origin', signal: active.signal, headers: {'Accept': 'application/x-ndjson'}})
      .then(function (response) {
        if (!response.ok || !response.body) { throw new Error('HTTP ' + response.status); }
        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';
        function pump() {
          return reader.read().then(function (part) {
            if (part.done) { return; }
            buffer += decoder.decode(part.value, {stream: true});
            var lines = buffer.split('\n');
            buffer = lines.pop();
            lines.forEach(function (line) {
              if (!line.trim()) { return; }
              var event;
              try { event = JSON.parse(line); } catch (e) { return; }
              if (event.type === 'row' && event.data) {
                var price = Number(event.data.price || 0);
                totalCount++; totalSum += price;
                if (event.data.date_key === today) { todayCount++; todaySum += price; }
                if (String(event.data.date_key || '').slice(0, 7) === month) { monthCount++; monthSum += price; }
                if (!hasRendered) { schedule(); }
              }
              if (event.type === 'done') {
                inFlight = false;
                try { sessionStorage.setItem(cacheKey, JSON.stringify({ts: Date.now(), todayCount: todayCount, todaySum: todaySum, monthCount: monthCount, monthSum: monthSum})); } catch (e) {}
                render();
              }
              if (event.type === 'error') { inFlight = false; showError('Data live gagal dimuat: ' + String(event.error || '')); }
            });
            return pump();
          });
        }
        return pump();
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') { inFlight = false; return; }
        inFlight = false;
        showError('Data live gagal dimuat: ' + String(err.message));
      });
  }

  window.MMLiveIncome = {
    start: start,
    refresh: function () {
      if (!inFlight && lastOpts) { start(lastOpts); }
    }
  };
})();
