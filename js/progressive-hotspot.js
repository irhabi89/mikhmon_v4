/* Read-only progressive list for hosts, cookies and IP bindings. */
(function () {
  "use strict";
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text != null) { e.textContent = String(text); } return e; }
  function link(href, icon, title, onclick) { var a = document.createElement('a'); a.href = href || '#'; a.title = title || ''; if (onclick) { a.setAttribute('onclick', onclick); } var i = document.createElement('i'); i.className = icon; a.appendChild(i); return a; }
  function start(opts) {
    var body = document.getElementById(opts.bodyId);
    var pager = document.getElementById(opts.pagerId);
    var countEl = opts.countId ? document.getElementById(opts.countId) : null;
    if (!body) { return; }
    var rows = [], page = 1, perPage = 50, timer = 0;
    var session = encodeURIComponent(opts.session || '');
    var type = opts.type;
    function cell(text, align) { var td = el('td', null, text); if (align) { td.style.textAlign = align; } return td; }
    function renderRow(r, index) {
      var tr = el('tr'), td;
      if (type === 'hosts') {
        td = el('td', null, ''); td.style.textAlign = 'center';
        td.appendChild(link('./?remove-host=' + encodeURIComponent(r.id) + '&session=' + session, 'fa fa-minus-square text-danger', 'Remove ' + r.mac, "return confirm('Remove " + String(r.mac).replace(/'/g, "\\'") + "?')"));
        tr.appendChild(td);
        td = cell(r.status_text, 'center'); td.className = r.status_class; td.title = r.status_title; tr.appendChild(td);
        tr.appendChild(cell(r.mac)); tr.appendChild(cell(r.address)); tr.appendChild(cell(r.to_address)); tr.appendChild(cell(r.server)); tr.appendChild(cell(r.comment));
      } else if (type === 'cookies') {
        td = cell('', 'center');
        td.appendChild(link('./?remove-cookie=' + encodeURIComponent(r.id) + '&session=' + session, 'fa fa-minus-square text-danger', 'Remove ' + r.user, "return confirm('Remove " + String(r.user).replace(/'/g, "\\'") + "?')"));
        tr.appendChild(td); tr.appendChild(cell(r.user)); tr.appendChild(cell(r.mac)); tr.appendChild(cell(r.domain)); tr.appendChild(cell(r.expires));
      } else {
        td = cell('', 'center');
        td.appendChild(link('./?remove-ip-binding=' + encodeURIComponent(r.id) + '&mac=' + encodeURIComponent(r.mac) + '&addr=' + encodeURIComponent(r.address) + '&session=' + session, 'fa fa-minus-square text-danger', 'Remove ' + r.mac, "return confirm('Remove " + String(r.mac).replace(/'/g, "\\'") + "?')"));
        td.appendChild(document.createTextNode(' '));
        if (String(r.disabled) === 'true') {
          td.appendChild(link('./?enable-ip-binding=' + encodeURIComponent(r.id) + '&session=' + session, 'fa fa-lock text-warning', 'Enable ' + r.address));
        } else {
          td.appendChild(link('./?disable-ip-binding=' + encodeURIComponent(r.id) + '&session=' + session, 'fa fa-unlock', 'Disable ' + r.address));
        }
        tr.appendChild(td);
        tr.appendChild(cell(String(r.bypassed) === 'true' ? 'P' : ''));
        tr.appendChild(cell(r.comment)); tr.appendChild(cell(r.mac)); tr.appendChild(cell(r.address)); tr.appendChild(cell(r.to_address)); tr.appendChild(cell(r.server));
      }
      return tr;
    }
    function render() {
      body.innerHTML = '';
      var start = (page - 1) * perPage, end = Math.min(rows.length, start + perPage);
      for (var i = start; i < end; i++) { body.appendChild(renderRow(rows[i], i)); }
      if (countEl) { countEl.textContent = rows.length.toLocaleString('id-ID'); }
      if (!pager) { return; }
      if (!rows.length) { pager.innerHTML = ''; return; }
      MMPagination.mount(pager, {
        total: rows.length,
        page: page,
        perPage: perPage,
        onPage: function (next) { page = next; render(); },
        onPerPage: function (size) { perPage = size; page = 1; render(); }
      });
    }
    function schedule() { if (timer) { return; } timer = setTimeout(function () { timer = 0; render(); }, 120); }
    var cacheKey = 'mm_live_hotspot:' + (opts.session || '') + ':' + type + ':' + location.search;
    var hadCached = false;
    try {
      var rawCache = sessionStorage.getItem(cacheKey);
      if (rawCache) {
        var parsedCache = JSON.parse(rawCache);
        if (parsedCache && Array.isArray(parsedCache.rows)) {
          rows = parsedCache.rows;
          hadCached = true;
          render();
        }
      }
    } catch (e) {}
    if (!hadCached) {
      body.innerHTML = '<tr><td colspan="' + (opts.colspan || 8) + '" class="mm-muted"><i class="fa fa-circle-o-notch fa-spin"></i> Memuat data…</td></tr>';
    }
    var url = './process/live_hotspot.php?' + location.search.replace(/^\?/, '') + '&type=' + encodeURIComponent(type);
    var nextRows = [];
    fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/x-ndjson'}})
      .then(function (res) { if (!res.ok || !res.body) { throw new Error('HTTP ' + res.status); } var reader = res.body.getReader(), dec = new TextDecoder(), buf = '';
        function pump() { return reader.read().then(function (part) { if (part.done) { return; } buf += dec.decode(part.value, {stream: true}); var lines = buf.split('\n'); buf = lines.pop(); lines.forEach(function (line) { if (!line.trim()) { return; } var e; try { e = JSON.parse(line); } catch (x) { return; } if (e.type === 'row') { nextRows.push(e.data); if (!hadCached) { rows.push(e.data); schedule(); } } if (e.type === 'done') { rows = nextRows; hadCached = true; try { sessionStorage.setItem(cacheKey, JSON.stringify({ts: Date.now(), rows: rows})); } catch (err) {} render(); } if (e.type === 'error' && !hadCached) { body.innerHTML = '<tr><td colspan="' + (opts.colspan || 8) + '" class="text-red">Gagal memuat: ' + String(e.error) + '</td></tr>'; } }); return pump(); }); } return pump(); })
      .catch(function (err) { if (!hadCached) { body.innerHTML = '<tr><td colspan="' + (opts.colspan || 8) + '" class="text-red">Gagal memuat: ' + String(err.message) + '</td></tr>'; } });
  }
  window.MMProgressiveHotspot = {start: start};
})();
