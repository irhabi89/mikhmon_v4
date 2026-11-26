/* Client-side pagination that mirrors include/ui.php mikhmon_pager(). */
(function () {
  "use strict";
  if (window.MMPagination) { return; }
  var SIZES = [25, 50, 100, 200];

  function windowNumbers(page, pages) {
    if (pages <= 1) { return [1]; }
    var start = Math.max(1, page - 2), end = Math.min(pages, page + 2), out = [];
    if (start > 1) { out.push(1); if (start > 2) { out.push('...'); } }
    for (var i = start; i <= end; i++) { out.push(i); }
    if (end < pages) { if (end < pages - 1) { out.push('...'); } out.push(pages); }
    return out;
  }

  function mount(container, opts) {
    if (!container) { return; }
    var total = Math.max(0, Number(opts.total || 0));
    var perPage = Number(opts.perPage || 50);
    var pages = Math.max(1, Math.ceil(total / perPage));
    var page = Math.min(Math.max(1, Number(opts.page || 1)), pages);
    if (!total) { container.innerHTML = ''; container.style.display = 'none'; return; }
    var from = ((page - 1) * perPage) + 1, to = Math.min(total, page * perPage);
    var options = SIZES.map(function (n) { return '<option value="' + n + '"' + (n === perPage ? ' selected' : '') + '>' + n + '</option>'; }).join('');
    var nav = '';
    if (page > 1) {
      nav += '<button type="button" class="mm-page-btn" data-page="1" aria-label="Halaman pertama">&laquo;</button><button type="button" class="mm-page-btn" data-page="' + (page - 1) + '" aria-label="Halaman sebelumnya">&lsaquo;</button>';
    } else {
      nav += '<span class="mm-page-btn is-disabled">&laquo;</span><span class="mm-page-btn is-disabled">&lsaquo;</span>';
    }
    windowNumbers(page, pages).forEach(function (item) {
      if (item === '...') { nav += '<span class="mm-page-ellipsis">…</span>'; return; }
      nav += '<button type="button" class="mm-page-btn' + (item === page ? ' is-active' : '') + '" data-page="' + item + '"' + (item === page ? ' aria-current="page"' : '') + '>' + item + '</button>';
    });
    if (page < pages) {
      nav += '<button type="button" class="mm-page-btn" data-page="' + (page + 1) + '" aria-label="Halaman berikutnya">&rsaquo;</button><button type="button" class="mm-page-btn" data-page="' + pages + '" aria-label="Halaman terakhir">&raquo;</button>';
    } else {
      nav += '<span class="mm-page-btn is-disabled">&rsaquo;</span><span class="mm-page-btn is-disabled">&raquo;</span>';
    }
    container.innerHTML = '<div class="mm-pagination-wrap"><span class="mm-page-info">Menampilkan <b>' + from.toLocaleString('id-ID') + '–' + to.toLocaleString('id-ID') + '</b> dari <b>' + total.toLocaleString('id-ID') + '</b></span><div class="mm-pagination-tools"><label class="mm-per-page">Baris <select data-per-page>' + options + '</select></label><nav class="mm-pagination" aria-label="Pagination">' + nav + '</nav></div></div>';
    container.style.display = '';
    var select = container.querySelector('[data-per-page]');
    if (select) { select.onchange = function () { if (opts.onPerPage) { opts.onPerPage(Number(select.value) || perPage); } }; }
    container.querySelectorAll('button[data-page]').forEach(function (btn) {
      btn.onclick = function () { var next = Number(btn.getAttribute('data-page')); if (next >= 1 && next <= pages && opts.onPage) { opts.onPage(next); } };
    });
  }

  window.MMPagination = {mount: mount};
})();
