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
mikhmon_require_schema();

$db = mikhmon_database();
$sales = new \Mikhmon\Storage\SalesRepository($db);
$service = new \Mikhmon\Sync\SalesSyncService($db);

$session = isset($_GET['session']) ? (string)$_GET['session'] : (isset($session) ? (string)$session : '');
if ($session === '' || $db->fetchOne('SELECT name FROM mikhmon_sessions WHERE name = ?', array($session)) === null) {
    echo "<script>window.location='./admin.php?id=sessions'</script>";
    return;
}

$currency = isset($currency) && $currency !== '' ? (string)$currency : 'Rp';
$decimals = in_array(strtoupper($currency), array('RP', 'IDR', 'RP.'), true) ? 0 : 2;

$dateKey = null;
if (!empty($_GET['date_key']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['date_key'])) {
    $dateKey = (string)$_GET['date_key'];
} elseif (!empty($_GET['idhr'])) {
    $dateKey = \Mikhmon\Support\RecordParser::dateKeyFromLegacy((string)$_GET['idhr']);
}
$monthKey = null;
if (!empty($_GET['month_key']) && preg_match('/^\d{4}-\d{2}$/', (string)$_GET['month_key'])) {
    $monthKey = (string)$_GET['month_key'];
} elseif (!empty($_GET['idbl'])) {
    $monthKey = \Mikhmon\Support\RecordParser::monthKeyFromBucket((string)$_GET['idbl']);
}
if ($dateKey !== null) {
    $monthKey = null;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : (isset($_GET['prefix']) ? trim((string)$_GET['prefix']) : '');
$serverFilter = isset($_GET['server']) ? trim((string)$_GET['server']) : '';
$page = \Mikhmon\Support\Pagination::page(isset($_GET['page']) ? $_GET['page'] : 1);
$perPage = \Mikhmon\Support\Pagination::perPage(isset($_GET['per_page']) ? $_GET['per_page'] : 50);

$filter = array(
    'date_from' => $dateKey,
    'date_to' => $dateKey,
    'month_key' => $monthKey,
    'q' => $q,
    'server' => $serverFilter,
);
$data = $sales->paginate($session, $filter, $page, $perPage, 'date', 'desc');
$stats = $sales->stats($session, $serverFilter !== '' ? $serverFilter : null);
$status = $service->summary($session);
$serverNames = $sales->serverNames($session);
$csrf = \Mikhmon\Http\Csrf::token();

$baseQuery = array('report' => 'selling', 'session' => $session);
if ($dateKey !== null) {
    $baseQuery['date_key'] = $dateKey;
}
if ($monthKey !== null) {
    $baseQuery['month_key'] = $monthKey;
}
if ($q !== '') {
    $baseQuery['q'] = $q;
}
if ($serverFilter !== '') {
    $baseQuery['server'] = $serverFilter;
}
$baseUrl = './?' . http_build_query($baseQuery);
$periodLabel = $dateKey !== null
    ? date('d M Y', strtotime($dateKey))
    : ($monthKey !== null ? date('F Y', strtotime($monthKey . '-01')) : 'Semua data');

// Explicit delete: local DB and/or router scripts for the current filter.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_data'])) {
    @set_time_limit(0);
    $notice = '';
    if (!\Mikhmon\Http\Csrf::check(isset($_POST['csrf']) ? (string)$_POST['csrf'] : null)) {
        $notice = 'csrf';
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
if (strpos($notice, 'deleted:') === 0) {
    $parts = explode(':', $notice);
    $noticeHtml = mikhmon_alert('info', 'Dihapus: <b>' . (int)$parts[1] . '</b> baris database, <b>' . (int)$parts[2] . '</b> script MikroTik.');
} elseif ($notice === 'csrf') {
    $noticeHtml = mikhmon_alert('danger', 'Token keamanan tidak valid. Silakan coba lagi.');
} elseif ($notice === 'q_router') {
    $noticeHtml = mikhmon_alert('warning', 'Hapus script MikroTik tidak diizinkan saat filter pencarian aktif. Hapus per tanggal/bulan.');
}

$delta = $stats['month_sum'] - $stats['prev_sum'];
$deltaPct = $stats['prev_sum'] > 0 ? ($delta / $stats['prev_sum'] * 100) : ($stats['month_sum'] > 0 ? 100 : 0);
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
  <?= mikhmon_page_header('Laporan Penjualan', 'fa-money', $actions, 'Periode: <b>' . mikhmon_e($periodLabel) . '</b> &nbsp;|&nbsp; ' . mikhmon_source_badge($status)); ?>

  <div class="mm-kpi-grid">
    <div class="mm-kpi">
      <div class="mm-kpi-icon bg-primary"><i class="fa fa-money"></i></div>
      <div class="mm-kpi-body">
        <div class="mm-kpi-label">Bulan Ini</div>
        <div class="mm-kpi-value"><?= mikhmon_e($fmt($stats['month_sum'])); ?></div>
        <div class="mm-kpi-note">
          <span class="<?= $delta >= 0 ? 'text-green' : 'text-red'; ?>"><i class="fa fa-arrow-<?= $delta >= 0 ? 'up' : 'down'; ?>"></i> <?= number_format(abs($deltaPct), 1, ',', '.'); ?>%</span>
          (<?= ($delta >= 0 ? '+' : '-') . mikhmon_e($fmt(abs($delta))); ?>) dari bulan lalu
        </div>
      </div>
    </div>
    <div class="mm-kpi">
      <div class="mm-kpi-icon bg-green"><i class="fa fa-calendar-check-o"></i></div>
      <div class="mm-kpi-body">
        <div class="mm-kpi-label">Hari Ini</div>
        <div class="mm-kpi-value"><?= mikhmon_e($fmt($stats['today_sum'])); ?></div>
        <div class="mm-kpi-note"><?= number_format($stats['today_count'], 0, ',', '.'); ?> voucher terjual</div>
      </div>
    </div>
    <div class="mm-kpi">
      <div class="mm-kpi-icon bg-indigo"><i class="fa fa-ticket"></i></div>
      <div class="mm-kpi-body">
        <div class="mm-kpi-label">Voucher Keseluruhan</div>
        <div class="mm-kpi-value"><?= number_format($stats['total_count'], 0, ',', '.'); ?></div>
        <div class="mm-kpi-note">total voucher tercatat</div>
      </div>
    </div>
    <div class="mm-kpi">
      <div class="mm-kpi-icon bg-blue"><i class="fa fa-database"></i></div>
      <div class="mm-kpi-body">
        <div class="mm-kpi-label">Nominal Keseluruhan</div>
        <div class="mm-kpi-value"><?= mikhmon_e($fmt($stats['total_sum'])); ?></div>
        <div class="mm-kpi-note">total penjualan tercatat</div>
      </div>
    </div>
  </div>

  <section class="mm-card">
    <div class="mm-card-head">
      <h2><i class="fa fa-list"></i> <?= mikhmon_e($periodLabel); ?></h2>
      <div class="mm-page-actions">
        <span class="mm-muted">Total: <b><?= number_format($data['total'], 0, ',', '.'); ?></b> transaksi</span>
      </div>
    </div>
    <div class="mm-card-body">
      <form method="get" action="./" class="mm-toolbar">
        <input type="hidden" name="report" value="selling">
        <input type="hidden" name="session" value="<?= mikhmon_e($session); ?>">
        <div class="mm-field">
          <label for="f-date">Tanggal</label>
          <input class="mm-input" id="f-date" type="date" name="date_key" value="<?= mikhmon_e($dateKey !== null ? $dateKey : ''); ?>">
        </div>
        <div class="mm-field">
          <label for="f-month">Bulan</label>
          <input class="mm-input" id="f-month" type="month" name="month_key" value="<?= mikhmon_e($monthKey !== null ? $monthKey : ''); ?>">
        </div>
        <div class="mm-field">
          <label for="f-q">Cari</label>
          <input class="mm-input" id="f-q" type="search" name="q" value="<?= mikhmon_e($q); ?>" placeholder="username / komentar / profil">
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
        <button class="btn bg-primary" type="submit"><i class="fa fa-search"></i> Filter</button>
        <a class="btn bg-secondary" href="./?report=selling&session=<?= mikhmon_e($session); ?>"><i class="fa fa-eraser"></i> Reset</a>
        <span class="mm-per-page"><?= mikhmon_per_page($data['perPage'], $baseUrl); ?></span>
      </form>

      <?php if ($data['total'] === 0): ?>
        <?= mikhmon_empty_state(
            'Belum ada data penjualan',
            'Database belum berisi record untuk filter ini. Jalankan sinkronisasi untuk membaca semua script penjualan dari MikroTik.',
            '<button type="button" class="btn bg-primary" onclick="location.href=\'#mmSyncModal\'"><i class="fa fa-refresh"></i> Sinkronisasi sekarang</button>'
        ); ?>
      <?php else: ?>
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
            <tbody>
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
        <?= mikhmon_pager($data['total'], $data['page'], $data['perPage'], $baseUrl); ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- Sync modal -->
<div class="modal-window" id="mmSyncModal" aria-hidden="true">
  <div>
    <header><h1><i class="fa fa-refresh"></i> Sinkronisasi Penjualan</h1></header>
    <a class="modal-close" href="#" title="Close" aria-label="Close">X</a>
    <p id="mmSyncInfo" class="mm-muted">Memuat status...</p>
    <div class="mm-progress"><span id="mmSyncBar"></span></div>
    <p class="mm-muted" style="font-size:12px;">
      Sinkronisasi membaca <b>semua</b> script MikroTik (bukan hanya satu halaman), lalu mengimpor per batch.
      Proses berjalan di background; halaman tetap bisa dipakai.
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
      <label style="display:block;font-weight:normal;"><input type="checkbox" name="del_router" value="1"> Hapus <b>script di MikroTik</b> (lambat, hanya filter tanggal/bulan)</label>
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
