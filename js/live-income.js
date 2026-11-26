/*
 * Progressive dashboard income fallback (read-only, no sync).
 * Consumes process/live_sales.php NDJSON and updates income spans as rows arrive.
 */
(function () {
  "use strict";
  if (window.MMLiveIncome) { return; }
  var active = null;

  function money(currency, decimals, value) {
    return currency + ' ' + Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: decimals});
  }

  function set(id, value) {
    var el = document.getElementById(id);
    if (el) { el.textContent = value; }
  }

  function start(opts) {
    if (active) { try { active.abort(); } catch (e) {} }
    active = new AbortController();
    var currency = opts.currency || 'Rp';
    var decimals = Number(opts.decimals || 0);
    var today = opts.today || new Date().toISOString().slice(0, 10);
    var month = opts.month || today.slice(0, 7);
    var todayCount = 0, monthCount = 0, totalCount = 0;
    var todaySum = 0, monthSum = 0, totalSum = 0;
    var timer = 0;
    function status(html) {
      var el = document.getElementById(opts.statusId || 'mmDashLiveStatus');
      if (el) { el.innerHTML = html; }
    }
    function render() {
      timer = 0;
      set('mmDashTodayValue', money(currency, decimals, todaySum));
      set('mmDashTodayCount', todayCount.toLocaleString('id-ID'));
      set('mmDashMonthValue', money(currency, decimals, monthSum));
      set('mmDashMonthCount', monthCount.toLocaleString('id-ID'));
      var vals = document.getElementById('mmDashLiveValues');
      if (vals) { vals.style.display = ''; }
    }
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
                schedule();
              }
              if (event.type === 'progress') {
                status('<i class="fa fa-circle-o-notch fa-spin"></i> Membaca ' + Number(event.rows || 0).toLocaleString('id-ID') + ' record…');
              }
              if (event.type === 'done') {
                render();
                status('<b>Data live MikroTik</b> · ' + totalCount.toLocaleString('id-ID') + ' record — belum diimpor');
              }
              if (event.type === 'error') {
                status('<span class="text-red">Data live gagal dimuat: ' + String(event.error || '') + '</span>');
              }
            });
            return pump();
          });
        }
        return pump();
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') { return; }
        status('<span class="text-red">Data live gagal dimuat: ' + String(err.message) + '</span>');
      });
  }

  window.MMLiveIncome = {start: start};
})();
