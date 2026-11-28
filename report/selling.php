<?php
/*
 * Selling report (DB-first).
 *
 * The page never reads sales scripts synchronously. It reads SQLite through
 * SalesRepository; a background/chunked sync fills the DB from the router.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['mikhmon'])) {
    header('Location:../admin.php?id=login');
    exit;
}

require_once __DIR__ . '/../include/bootstrap.php';
require_once __DIR__ . '/../include/ui.php';
require_once __DIR__ . '/../include/report_fallback.php';

$session = isset($_GET['session']) ? (string)$_GET['session'] : (isset($session) ? (string)$session : '');
$sessionRow = mikhmon_safe_session_row($session);
if ($session === '' || $sessionRow === null) {
    echo "<script>window.location='./admin.php?id=sessions'</script>";
    return;
}

if ((!isset($currency) || $currency === '') && !empty($sessionRow['currency'])) {
    $currency = (string)$sessionRow['currency'];
}
if ((!isset($areload) || !is_numeric($areload) || (int)$areload <= 0) && !empty($sessionRow['areload']) && is_numeric($sessionRow['areload'])) {
    $areload = (string)$sessionRow['areload'];
}
$currency = isset($currency) && $currency !== '' ? (string)$currency : 'Rp';
$decimals = in_array(strtoupper($currency), array('RP', 'IDR', 'RP.'), true) ? 0 : 2;

$filterMeta = \Mikhmon\Support\SalesFilter::fromQuery($_GET);
$filter = $filterMeta['filter'];
$dateKey = $filterMeta['date_key'];
$dateFrom = $filterMeta['date_from'];
$dateTo = $filterMeta['date_to'];
$monthKey = $filterMeta['month_key'];
$monthName = $filterMeta['month_name'];
$year = $filterMeta['year'];
$q = $filterMeta['q'];
$serverFilter = $filterMeta['server'];
$page = \Mikhmon\Support\Pagination::page(isset($_GET['page']) ? $_GET['page'] : 1);
$perPage = \Mikhmon\Support\Pagination::perPage(isset($_GET['per_page']) ? $_GET['per_page'] : 50);

$autoReloadSeconds = isset($areload) && (int)$areload > 0 ? (int)$areload : 10;
$autoReloadMs = max(1000, $autoReloadSeconds * 1000);
$csrf = \Mikhmon\Http\Csrf::token();
$baseQuery = \Mikhmon\Support\SalesFilter::query($filterMeta, $session);
$baseUrl = './?' . http_build_query($baseQuery);
$periodLabel = \Mikhmon\Support\SalesFilter::label($filterMeta);

$dbError = '';
$db = null;
$sales = null;
$service = null;
$data = array('rows' => array(), 'total' => 0, 'sum' => 0.0, 'page' => 1, 'pages' => 1, 'perPage' => $perPage);
$stats = array('month_sum' => 0.0, 'prev_sum' => 0.0, 'today_count' => 0, 'today_sum' => 0.0, 'total_count' => 0, 'total_sum' => 0.0);
$status = array('empty' => true, 'in_sync' => false, 'db' => array('total' => 0, 'missing' => 0), 'router' => array('records' => 0, 'complete' => false));
$serverNames = array();
$yearKeys = array();

try {
    mikhmon_require_schema();
    $db = mikhmon_database();
    $sales = new \Mikhmon\Storage\SalesRepository($db);
    $service = new \Mikhmon\Sync\SalesSyncService($db);

    $data = $sales->paginate($session, $filter, $page, $perPage, 'date', 'desc');
    $stats = $sales->stats($session, $serverFilter !== '' ? $serverFilter : null);
    $status = $service->summary($session);
    $serverNames = $sales->serverNames($session);
    $yearKeys = $sales->yearKeys($session);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$filterTotal = (int)$data['total'];
$filterSum = (float)$data['sum'];
$filterAvg = $filterTotal > 0 ? $filterSum / $filterTotal : 0.0;
$delta = $stats['month_sum'] - $stats['prev_sum'];
$deltaPct = $stats['prev_sum'] > 0 ? ($delta / $stats['prev_sum'] * 100) : ($stats['month_sum'] > 0 ? 100 : 0);
$monthOptions = \Mikhmon\Support\SalesFilter::months();
$yearOptions = array_values(array_unique(array_merge($yearKeys, array(date('Y')))));
rsort($yearOptions);
$useLiveFallback = ($dbError !== '') || ($data['total'] === 0);

// Explicit delete: local DB and/or router scripts for the current filter.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_data'])) {
    @set_time_limit(0);
    $notice = '';
    if (!\Mikhmon\Http\Csrf::check(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null)) {
        $notice = 'csrf';
    } elseif ($service === null) {
        $notice = 'db_error';
    } else {
        $scope = isset($_POST['del_scope']) && $_POST['del_scope'] === 'all' ? 'all' : 'view';
        $deleteDb = isset($_POST['del_db']) && $_POST['del_db'] === '1';
        $deleteRouter = isset($_POST['del_router']) && $_POST['del_router'] === '1';
        $deleteFilter = $scope === 'all' ? array() : $filter;
        if ($deleteRouter && $q !== '') {
            $notice = 'q_router';
        } else {
            $deleted = $service->deleteData($session, $deleteFilter, $deleteDb, $deleteRouter);
            $notice = 'deleted:' . (int)$deleted['db'] . ':' . (int)$deleted['router'];
        }
    }
    $target = './?' . http_build_query(array_merge($baseQuery, array('notice' => $notice)));
    echo '<script>window.location=' . json_encode($target) . ';</script>';
    return;
}

$notice = isset($_GET['notice']) ? (string)$_GET['notice'] : '';
$noticeHtml = '';
if ($dbError !== '') {
    $noticeHtml = mikhmon_alert('warning', '<i class="fa fa-exclamation-triangle"></i> <b>Database bermasalah</b> (' . mikhmon_e($dbError) . '). Otomatis beralih ke data langsung dari script MikroTik.');
} elseif (strpos($notice, 'deleted:') === 0) {
    $parts = explode(':', $notice);
    $noticeHtml = mikhmon_alert('info', 'Dihapus: <b>' . (int)$parts[1] . '</b> baris database, <b>' . (int)$parts[2] . '</b> script MikroTik.');
} elseif ($notice === 'csrf') {
    $noticeHtml = mikhmon_alert('danger', 'Token keamanan tidak valid. Silakan coba lagi.');
} elseif ($notice === 'db_error') {
    $noticeHtml = mikhmon_alert('danger', 'Database sedang tidak tersedia untuk menghapus data.');
} elseif ($notice === 'q_router') {
    $noticeHtml = mikhmon_alert('warning', 'Hapus script MikroTik tidak diizinkan saat filter pencarian aktif. Hapus per tanggal/bulan.');
}

$fmt = function ($value) use ($currency, $decimals) {
    return mikhmon_money($value, $currency, $decimals);
};

$syncButton = '<a class="btn bg-primary" href="#mmSyncModal"><i class="fa fa-refresh"></i> Sinkronisasi</a>';
$csvUrl = './process/export_sales.php?' . http_build_query(array_merge($baseQuery, array('token' => $csrf)));
$printUrl = './report/print.php?' . http_build_query(array_merge($baseQuery, array('token' => $csrf)));
$deleteButton = '<a class="btn bg-danger" href="#deleteModal"><i class="fa fa-trash"></i> Hapus</a>';
$actions = $syncButton
    . ' <a class="btn bg-primary" href="' . mikhmon_e($csvUrl) . '"><i class="fa fa-download"></i> CSV</a>'
    . ' <a class="btn bg-primary" target="_blank" rel="noopener" href="' . mikhmon_e($printUrl) . '"><i class="fa fa-print"></i> Print</a>'
    . ' ' . $deleteButton;
?>

<div class="mm-page">
  <?= $noticeHtml; ?>
  <?= mikhmon_page_header('Laporan Penjualan', 'fa-money', $actions, 'Periode: <b>' . mikhmon_e($periodLabel) . '</b> &nbsp;|&nbsp; <span id="mmHeaderSummary"><b>' . number_format($filterTotal, 0, ',', '.') . '</b> transaksi · <b>' . mikhmon_e($fmt($filterSum)) . '</b></span> &nbsp;|&nbsp; <span id="mmPageSourceWrap">' . mikhmon_source_badge($status) . '</span>'); ?>

  <div class="mm-kpi-grid">
    <div class="mm-kpi">
      <div class="mm-kpi-icon bg-primary"><i class="fa fa-money"></i></div>
      <div class="mm-kpi-body">
        <div class="mm-kpi-label">Bulan Ini</div>
        <div class="mm-kpi-value" id="mmKpiMonthValue"><?= mikhmon_e($fmt($stats['month_sum'])); ?></div>
        <div class="mm-kpi-note" id="mmKpiMonthNote">
          <span class="<?= $delta >= 0 ? 'text-green' : 'text-red'; ?>"><i class="fa fa-arrow-<?= $delta >= 0 ? 'up' : 'down'; ?>"></i> <?= number_format(abs($deltaPct), 1, ',', '.'); ?>%</span>
          (<?= ($delta >= 0 ? '+' : '-') . mikhmon_e($fmt(abs($delta))); ?>) dari bulan lalu
        </div>
      </div>
    </div>
    <div class="mm-kpi">
      <div class="mm-kpi-icon bg-green"><i class="fa fa-calendar-check-o"></i></div>
      <div class="mm-kpi-body">
        <div class="mm-kpi-label">Hari Ini</div>
        <div class="mm-kpi-value" id="mmKpiTodayValue"><?= mikhmon_e($fmt($stats['today_sum'])); ?></div>
        <div class="mm-kpi-note" id="mmKpiTodayNote"><?= number_format($stats['today_count'], 0, ',', '.'); ?> voucher terjual</div>
      </div>
    </div>
    <div class="mm-kpi">
      <div class="mm-kpi-icon bg-indigo"><i class="fa fa-ticket"></i></div>
      <div class="mm-kpi-body">
        <div class="mm-kpi-label">Voucher Keseluruhan</div>
        <div class="mm-kpi-value" id="mmKpiTotalCount"><?= number_format($stats['total_count'], 0, ',', '.'); ?></div>
        <div class="mm-kpi-note" id="mmKpiTotalNote">total voucher tercatat</div>
      </div>
    </div>
    <div class="mm-kpi">
      <div class="mm-kpi-icon bg-blue"><i class="fa fa-database"></i></div>
      <div class="mm-kpi-body">
        <div class="mm-kpi-label">Nominal Keseluruhan</div>
        <div class="mm-kpi-value" id="mmKpiTotalSum"><?= mikhmon_e($fmt($stats['total_sum'])); ?></div>
        <div class="mm-kpi-note" id="mmKpiTotalSumNote">total penjualan tercatat</div>
      </div>
    </div>
  </div>

  <section class="mm-card">
    <div class="mm-card-head">
      <h2><i class="fa fa-list"></i> <?= mikhmon_e($periodLabel); ?></h2>
      <div class="mm-page-actions">
        <span class="mm-muted" id="mmReportTotal">Total: <b><?= number_format($filterTotal, 0, ',', '.'); ?></b> transaksi · <b><?= mikhmon_e($fmt($filterSum)); ?></b></span>
      </div>
    </div>
    <div class="mm-card-body">
      <form method="get" action="./" class="mm-toolbar">
        <input type="hidden" name="report" value="selling">
        <input type="hidden" name="session" value="<?= mikhmon_e($session); ?>">
        <div class="mm-field">
          <label for="f-date-from">Dari tanggal</label>
          <input class="mm-input" id="f-date-from" type="date" name="date_from" value="<?= mikhmon_e($dateFrom !== null ? $dateFrom : ''); ?>">
        </div>
        <div class="mm-field">
          <label for="f-date-to">Sampai tanggal</label>
          <input class="mm-input" id="f-date-to" type="date" name="date_to" value="<?= mikhmon_e($dateTo !== null ? $dateTo : ''); ?>">
        </div>
        <div class="mm-field">
          <label for="f-month-name">Bulan</label>
          <select class="mm-select" id="f-month-name" name="month_name">
            <option value="">Semua bulan</option>
            <?php foreach ($monthOptions as $mNum => $mLabel): ?>
              <?php $mValue = str_pad((string)$mNum, 2, '0', STR_PAD_LEFT); ?>
              <option value="<?= mikhmon_e($mValue); ?>" <?= $monthName === $mValue ? 'selected' : ''; ?>><?= mikhmon_e($mLabel); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mm-field">
          <label for="f-year">Tahun</label>
          <select class="mm-select" id="f-year" name="year">
            <option value="">Semua tahun</option>
            <?php foreach ($yearOptions as $y): ?>
              <option value="<?= mikhmon_e($y); ?>" <?= $year === $y ? 'selected' : ''; ?>><?= mikhmon_e($y); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mm-field">
          <label for="f-server">Server</label>
          <select class="mm-select" id="f-server" name="server">
            <option value="">Semua server</option>
            <?php foreach ($serverNames as $srv): ?>
              <?php $srvValue = $srv === '' ? '__none__' : $srv; ?>
              <option value="<?= mikhmon_e($srvValue); ?>" <?= $serverFilter === $srvValue ? 'selected' : ''; ?>><?= $srv === '' ? 'Tanpa server (data lama)' : mikhmon_e($srv); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mm-field">
          <label for="f-q">Cari</label>
          <input class="mm-input" id="f-q" type="search" name="q" value="<?= mikhmon_e($q); ?>" placeholder="username / komentar / profil">
        </div>
        <button class="btn bg-primary" type="submit"><i class="fa fa-search"></i> Filter</button>
        <a class="btn bg-secondary" href="./?report=selling&session=<?= mikhmon_e($session); ?>"><i class="fa fa-eraser"></i> Reset</a>
      </form>

      <?php if ($useLiveFallback): ?>
        <div id="mmLiveSales" class="mm-live-panel" data-session="<?= mikhmon_e($session); ?>">
          <div class="mm-live-actions">
            <span id="mmLiveInfo" class="mm-muted">Menghubungi router...</span>
          </div>
          <div id="mmLiveWarning" class="mm-alert mm-alert-warning" style="display:none;">
            <span id="mmLiveWarningText"></span>
            <button type="button" class="btn bg-primary" id="mmLiveSyncCta" style="margin-left:8px;"><i class="fa fa-refresh"></i> Sinkronkan</button>
          </div>
          <div class="mm-table-wrap" id="mmLiveWrap" style="display:none;">
            <table class="mm-table">
              <thead><tr><th>No</th><th>Tanggal</th><th>Jam</th><th>Server</th><th>Username</th><th>Profil</th><th>Komentar</th><th class="is-num">Harga</th></tr></thead>
              <tbody id="mmLiveBody"></tbody>
            </table>
          </div>
          <div id="mmLivePager" style="display:none;"></div>
          <p id="mmLiveEmpty" class="mm-muted" style="display:none;">Tidak ada record penjualan di MikroTik untuk filter ini.</p>
        </div>
        <script src="./js/mm-pagination.js"></script>
        <script>
        (function () {
          var panel = document.getElementById('mmLiveSales');
          if (!panel) { return; }
          var info = document.getElementById('mmLiveInfo');
          var body = document.getElementById('mmLiveBody');
          var wrap = document.getElementById('mmLiveWrap');
          var empty = document.getElementById('mmLiveEmpty');
          var pager = document.getElementById('mmLivePager');
          var controller = null;
          var refreshTimer = 0;
          var inFlight = false;
          var allRows = [];
          var page = 1;
          var perPage = 50;
          var pendingRender = 0;
          var autoReloadMs = <?= (int)$autoReloadMs; ?>;
          var serverToday = <?= json_encode(date('Y-m-d')); ?>;
          var serverMonth = <?= json_encode(date('Y-m')); ?>;
          var currency = <?= json_encode((string)$currency); ?>;
          var decimals = <?= (int)$decimals; ?>;
          function money(value) {
            return currency + ' ' + Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: decimals});
          }
          function setInfo(text) { if (info) { info.textContent = text; } }
          function showCompare(c) {
            window.MM_LIVE_COMPARE = c;
            var warn = document.getElementById('mmLiveWarning');
            var text = document.getElementById('mmLiveWarningText');
            if (warn && text) {
              if (c && c.complete && Number(c.missing || 0) > 0) {
                text.innerHTML = '<b>' + Number(c.missing).toLocaleString('id-ID') + ' record live belum masuk database</b> (' + Number(c.unsynced_percent || 0).toLocaleString('id-ID', {maximumFractionDigits: 1}) + '%). Kemungkinan webhook gagal atau belum disinkronkan.';
                warn.style.display = '';
              } else {
                warn.style.display = 'none';
              }
            }
            document.dispatchEvent(new CustomEvent('mm-live-compare', {detail: c}));
          }
          function renderPager() {
            if (!pager) { return; }
            if (!allRows.length) { pager.style.display = 'none'; pager.innerHTML = ''; return; }
            MMPagination.mount(pager, {
              total: allRows.length,
              page: page,
              perPage: perPage,
              onPage: function (next) { page = next; renderPage(); },
              onPerPage: function (size) { perPage = size; page = 1; renderPage(); }
            });
          }
          function updateKpis() {
            var total = 0, today = 0, month = 0, totalCount = allRows.length;
            var todayCount = 0, monthCount = 0;
            allRows.forEach(function (row) {
              var price = Number(row.price || 0); total += price;
              if (row.date_key === serverToday) { today += price; todayCount++; }
              if (String(row.date_key || '').slice(0, 7) === serverMonth) { month += price; monthCount++; }
            });
            var set = function (id, value) { var el = document.getElementById(id); if (el) { el.textContent = value; } };
            set('mmKpiMonthValue', money(month)); set('mmKpiMonthNote', monthCount.toLocaleString('id-ID') + ' voucher (live)');
            set('mmKpiTodayValue', money(today)); set('mmKpiTodayNote', todayCount.toLocaleString('id-ID') + ' voucher terjual (live)');
            set('mmKpiTotalCount', totalCount.toLocaleString('id-ID')); set('mmKpiTotalNote', 'total voucher (live)');
            set('mmKpiTotalSum', money(total)); set('mmKpiTotalSumNote', 'total penjualan (live)');
            var totalEl = document.getElementById('mmReportTotal');
            if (totalEl) { totalEl.innerHTML = 'Total: <b>' + totalCount.toLocaleString('id-ID') + '</b> transaksi · <b>' + money(total) + '</b>'; }
          }
          function renderPage() {
            if (!body) { return; }
            body.innerHTML = '';
            var start = (page - 1) * perPage;
            var end = Math.min(allRows.length, start + perPage);
            for (var i = start; i < end; i++) {
              var row = allRows[i];
              var tr = document.createElement('tr');
              [String(i + 1), row.date_key, row.time_key, row.server_name, row.username, row.profile, row.sale_comment, money(row.price)].forEach(function (value) {
                var td = document.createElement('td'); td.textContent = value || ''; tr.appendChild(td);
              });
              body.appendChild(tr);
            }
            if (wrap) { wrap.style.display = allRows.length ? '' : 'none'; }
            if (empty) { empty.style.display = allRows.length ? 'none' : ''; }
            updateKpis();
            renderPager();
          }
          function scheduleRender() {
            if (pendingRender) { return; }
            pendingRender = window.setTimeout(function () { pendingRender = 0; renderPage(); }, 120);
          }
          function scheduleRefresh() {
            if (refreshTimer) { clearTimeout(refreshTimer); }
            refreshTimer = window.setTimeout(loadLive, autoReloadMs);
          }
          function loadLive() {
            if (inFlight || document.hidden || window.MM_SYNC_BUSY) {
              scheduleRefresh();
              return;
            }
            if (controller) { try { controller.abort(); } catch (e) {} }
            controller = new AbortController();
            inFlight = true;
            var nextRows = [];
            var hadRows = allRows.length > 0;
            setInfo('Memperbarui data MikroTik...');
            var search = location.search.replace(/^\?/, '');
            var url = './process/live_sales.php?' + (search ? search + '&' : '') + 'compare=1';
            fetch(url, {credentials: 'same-origin', signal: controller.signal, headers: {'Accept': 'application/x-ndjson'}})
              .then(function (response) {
                if (!response.ok || !response.body) { throw new Error('HTTP ' + response.status); }
                var reader = response.body.getReader();
                var decoder = new TextDecoder();
                var buffer = '';
                function pump() {
                  return reader.read().then(function (part) {
                    if (part.done) { return; }
                    buffer += decoder.decode(part.value, {stream: true});
                    var lines = buffer.split('\n'); buffer = lines.pop();
                    lines.forEach(function (line) {
                      if (!line.trim()) { return; }
                      var event; try { event = JSON.parse(line); } catch (e) { return; }
                      if (event.type === 'row') {
                        nextRows.push(event.data);
                        // Initial load: show rows progressively. Refresh: keep the
                        // previous complete table/KPI until the new scan finishes.
                        if (!hadRows) { allRows.push(event.data); scheduleRender(); }
                      }
                      if (event.type === 'compare') { showCompare(event); }
                      if (event.type === 'progress') { setInfo('Membaca ' + Number(event.rows).toLocaleString('id-ID') + ' record...'); }
                      if (event.type === 'done') {
                        allRows = nextRows;
                        page = 1;
                        renderPage();
                        setInfo('Selesai: ' + Number(event.rows).toLocaleString('id-ID') + ' record dari MikroTik.');
                        window.MM_LIVE_COUNT = Number(event.rows) || 0;
                        document.dispatchEvent(new CustomEvent('mm-live-sales-loaded', {detail: {count: window.MM_LIVE_COUNT}}));
                        inFlight = false;
                        scheduleRefresh();
                      }
                      if (event.type === 'error') {
                        setInfo('Gagal membaca MikroTik: ' + event.error);
                        inFlight = false;
                        scheduleRefresh();
                      }
                    });
                    return pump();
                  });
                }
                return pump();
              })
              .catch(function (err) {
                inFlight = false;
                if (err && err.name === 'AbortError') { scheduleRefresh(); return; }
                setInfo('Gagal membaca MikroTik: ' + err.message + '. Data terakhir dipertahankan.');
                scheduleRefresh();
              });
          }
          var cta = document.getElementById('mmLiveSyncCta');
          if (cta) { cta.addEventListener('click', function () { document.dispatchEvent(new CustomEvent('mm-start-sync')); }); }
          loadLive();
        })();
        </script>
      <?php else: ?>
        <div id="mmSyncAlert" class="mm-alert mm-alert-warning" style="display:none;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
          <span id="mmSyncAlertText"></span>
          <span style="display:inline-flex;gap:8px;align-items:center;">
            <button type="button" class="btn bg-primary" id="mmSyncAlertBtn"><i class="fa fa-refresh"></i> Sinkronkan</button>
          </span>
        </div>
        <div class="mm-table-wrap">
          <table class="mm-table">
            <thead>
              <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jam</th>
                <th>Server</th>
                <th>Username</th>
                <th>Profil</th>
                <th class="is-wrap">Komentar</th>
                <th class="is-num">Harga</th>
              </tr>
            </thead>
            <tbody id="mmDbBody">
              <?php foreach ($data['rows'] as $i => $row): ?>
                <tr>
                  <td><?= (int)(($data['page'] - 1) * $data['perPage'] + $i + 1); ?></td>
                  <td><?= mikhmon_e($row['date_key']); ?></td>
                  <td><?= mikhmon_e($row['time_key']); ?></td>
                  <td><?= mikhmon_e($row['server_name']); ?></td>
                  <td><?= mikhmon_e($row['username']); ?></td>
                  <td><?= mikhmon_e($row['profile']); ?></td>
                  <td class="is-wrap"><?= mikhmon_e($row['sale_comment']); ?></td>
                  <td class="is-num"><?= mikhmon_e($fmt($row['price'])); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div id="mmDbPager"><?= mikhmon_pager($data['total'], $data['page'], $data['perPage'], $baseUrl); ?></div>
        <script>
        (function () {
          var autoReloadMs = <?= (int)$autoReloadMs; ?>;
          var sessionName = <?= json_encode((string)$session); ?>;
          var currency = <?= json_encode((string)$currency); ?>;
          var decimals = <?= (int)$decimals; ?>;
          var timer = 0;
          var inFlight = false;

          function money(value) {
            return currency + ' ' + Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: decimals});
          }

          function setText(id, value) {
            var el = document.getElementById(id);
            if (el) { el.textContent = value; }
          }

          function updateCompareBanner(c) {
            window.MM_LIVE_COMPARE = c;
            var box = document.getElementById('mmSyncAlert');
            var text = document.getElementById('mmSyncAlertText');
            if (box && text) {
              if (c && c.complete && Number(c.missing || 0) > 0) {
                var pct = Number(c.unsynced_percent || 0).toLocaleString('id-ID', {maximumFractionDigits: 1});
                text.innerHTML = '<i class="fa fa-exclamation-triangle"></i> <b>' + Number(c.missing).toLocaleString('id-ID')
                  + ' record penjualan di script MikroTik belum masuk database</b> (' + pct + '% · MikroTik: '
                  + Number(c.router_total || 0).toLocaleString('id-ID') + ', DB: '
                  + Number(c.db_total || 0).toLocaleString('id-ID') + ').';
                box.style.display = 'flex';
              } else {
                box.style.display = 'none';
              }
            }
            document.dispatchEvent(new CustomEvent('mm-live-compare', {detail: c}));
          }

          function renderDbSnapshot(d) {
            if (!d || !d.ok) { return; }
            var body = document.getElementById('mmDbBody');
            if (body) {
              body.innerHTML = '';
              var offset = ((Number(d.page || 1) - 1) * Number(d.perPage || 50));
              (d.rows || []).forEach(function (row, idx) {
                var tr = document.createElement('tr');
                var cols = [
                  String(offset + idx + 1),
                  row.date_key || '',
                  row.time_key || '',
                  row.server_name || '',
                  row.username || '',
                  row.profile || '',
                  row.sale_comment || '',
                  money(row.price || 0)
                ];
                cols.forEach(function (val, cIdx) {
                  var td = document.createElement('td');
                  if (cIdx === 6) { td.className = 'is-wrap'; }
                  if (cIdx === 7) { td.className = 'is-num'; }
                  td.textContent = val;
                  tr.appendChild(td);
                });
                body.appendChild(tr);
              });
            }
            var pagerWrap = document.getElementById('mmDbPager');
            if (pagerWrap && typeof d.pager_html === 'string') {
              pagerWrap.innerHTML = d.pager_html;
            }
            var totalHtml = '<b>' + Number(d.total || 0).toLocaleString('id-ID') + '</b> transaksi · <b>' + money(d.sum || 0) + '</b>';
            var headSummary = document.getElementById('mmHeaderSummary');
            if (headSummary) { headSummary.innerHTML = totalHtml; }
            var repTotal = document.getElementById('mmReportTotal');
            if (repTotal) { repTotal.innerHTML = 'Total: ' + totalHtml; }

            var st = d.stats || {};
            var monthSum = Number(st.month_sum || 0);
            var prevSum = Number(st.prev_sum || 0);
            var delta = monthSum - prevSum;
            var deltaPct = prevSum > 0 ? (delta / prevSum * 100) : (monthSum > 0 ? 100 : 0);
            setText('mmKpiMonthValue', money(monthSum));
            var monthNote = document.getElementById('mmKpiMonthNote');
            if (monthNote) {
              monthNote.innerHTML = '<span class="' + (delta >= 0 ? 'text-green' : 'text-red') + '"><i class="fa fa-arrow-' + (delta >= 0 ? 'up' : 'down') + '"></i> '
                + Math.abs(deltaPct).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + '%</span> ('
                + (delta >= 0 ? '+' : '-') + money(Math.abs(delta)) + ') dari bulan lalu';
            }
            setText('mmKpiTodayValue', money(st.today_sum || 0));
            setText('mmKpiTodayNote', Number(st.today_count || 0).toLocaleString('id-ID') + ' voucher terjual');
            setText('mmKpiTotalCount', Number(st.total_count || 0).toLocaleString('id-ID'));
            setText('mmKpiTotalSum', money(st.total_sum || 0));
          }

          function runCycle() {
            if (inFlight || document.hidden || window.MM_SYNC_BUSY) {
              scheduleNext();
              return;
            }
            inFlight = true;
            var qs = location.search.replace(/^\?/, '');
            var reportUrl = './process/sales_report_data.php?' + (qs ? qs : ('session=' + encodeURIComponent(sessionName)));
            var compareUrl = './process/live_sales.php?session=' + encodeURIComponent(sessionName) + '&summary=1&compare=1';

            var pDb = fetch(reportUrl, {credentials: 'same-origin'})
              .then(function (r) { return r.json().then(function (d) { return {status: r.status, data: d}; }); })
              .then(function (res) {
                if (res.data && res.data.db_error) {
                  location.reload();
                  return;
                }
                if (res.data && res.data.ok) {
                  renderDbSnapshot(res.data);
                }
              })
              .catch(function () { /* keep current DB snapshot */ });

            var pCmp = fetch(compareUrl, {credentials: 'same-origin'})
              .then(function (r) { return r.json(); })
              .then(function (d) {
                if (d && d.ok && d.compare) {
                  updateCompareBanner(d.compare);
                }
              })
              .catch(function () { /* keep last compare state */ });

            Promise.all([pDb, pCmp]).then(function () {
              inFlight = false;
              scheduleNext();
            }, function () {
              inFlight = false;
              scheduleNext();
            });
          }

          function scheduleNext() {
            if (timer) { clearTimeout(timer); }
            timer = window.setTimeout(runCycle, autoReloadMs);
          }

          var alertBtn = document.getElementById('mmSyncAlertBtn');
          if (alertBtn) {
            alertBtn.addEventListener('click', function () {
              document.dispatchEvent(new CustomEvent('mm-start-sync'));
            });
          }

          window.setTimeout(runCycle, 400);
        })();
        </script>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- Sync modal -->
<div class="modal-window" id="mmSyncModal" aria-hidden="true">
  <div>
    <header><h1><i class="fa fa-refresh"></i> Sinkronisasi Penjualan</h1></header>
    <a class="modal-close" href="#" title="Close" aria-label="Close">X</a>
    <div id="mmSyncInfo" class="mm-muted" style="line-height:1.5;">Memuat status...</div>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:12.5px;margin-top:8px;">
      <span id="mmSyncPhase" class="mm-muted">Siap sinkronisasi</span>
      <b id="mmSyncPercent">0%</b>
    </div>
    <div class="mm-progress"><span id="mmSyncBar"></span></div>
    <p class="mm-muted" style="font-size:12px;">
      Sinkronisasi membaca <b>semua</b> script MikroTik (bukan hanya satu halaman), lalu mengimpor per batch.
      Proses manual ini dapat dilanjutkan per batch.
    </p>
    <label style="display:block;font-weight:normal;margin:8px 0;">
      <input type="checkbox" id="mmDeleteAfterImport" name="delete_after_import" value="1">
      Setelah semua record penjualan berhasil diimpor, hapus <b>script record penjualan</b> dari MikroTik.
    </label>
    <p class="mm-muted" style="font-size:11.5px;margin-top:-4px;">
      Hanya script record penjualan yang dihapus; script MikroTik lain tidak disentuh.
    </p>
    <div class="mm-page-actions">
      <button type="button" class="btn bg-primary" id="mmSyncStart"><i class="fa fa-play"></i> Mulai Sinkronisasi</button>
      <button type="button" class="btn bg-warning" id="mmSyncCancel" style="display:none;"><i class="fa fa-stop"></i> Batalkan</button>
      <button type="button" class="btn bg-secondary" id="mmSyncProbe"><i class="fa fa-plug"></i> Tes Koneksi</button>
      <a class="btn bg-secondary" href="#" title="Close">Tutup</a>
    </div>
    <p style="margin-top:10px;"><span id="mmSyncBadge"><?= mikhmon_source_badge($status); ?></span></p>
  </div>
</div>

<!-- Delete modal -->
<div class="modal-window" id="deleteModal" aria-hidden="true">
  <div>
    <header><h1><i class="fa fa-trash"></i> Hapus Data</h1></header>
    <a class="modal-close" href="#" title="Close" aria-label="Close">X</a>
    <form method="post" action="<?= mikhmon_e($baseUrl); ?>">
      <?= mikhmon_csrf_field(); ?>
      <input type="hidden" name="delete_data" value="1">
      <p class="mm-muted">Filter aktif: <b><?= mikhmon_e($periodLabel); ?></b><?= $q !== '' ? ' · pencarian: ' . mikhmon_e($q) : ''; ?></p>
      <label style="display:block;font-weight:normal;"><input type="checkbox" name="del_db" value="1" checked> Hapus dari <b>database</b></label>
      <label style="display:block;font-weight:normal;"><input type="checkbox" name="del_router" value="1"> Hapus <b>script di MikroTik</b> (lambat, mengikuti filter tanggal/bulan/server)</label>
      <hr style="border:none;border-top:1px solid #ccc;margin:8px 0;">
      <label style="display:block;font-weight:normal;"><input type="radio" name="del_scope" value="view" checked> Hanya data yang sedang terfilter</label>
      <label style="display:block;font-weight:normal;"><input type="radio" name="del_scope" value="all"> Semua data sesi ini</label>
      <div class="mm-page-actions" style="margin-top:10px;">
        <button type="submit" class="btn bg-danger"><i class="fa fa-trash"></i> Hapus</button>
        <a class="btn bg-secondary" href="#" title="Close">Batal</a>
      </div>
    </form>
  </div>
</div>

<script>
window.MM_SYNC = {
  session: <?= json_encode($session, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
  csrf: <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
  endpoint: './process/sales_sync.php'
};
</script>
<script src="./js/sales-sync.js?t=<?= is_file(__DIR__ . '/../js/sales-sync.js') ? filemtime(__DIR__ . '/../js/sales-sync.js') : '1'; ?>"></script>
