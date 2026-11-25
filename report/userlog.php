<?php
/*
 * User log / sales history (DB-first).
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
$session = isset($_GET['session']) ? (string)$_GET['session'] : (isset($session) ? (string)$session : '');
if ($session === '' || $db->fetchOne('SELECT name FROM mikhmon_sessions WHERE name = ?', array($session)) === null) {
    echo "<script>window.location='./admin.php?id=sessions'</script>";
    return;
}

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
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$serverFilter = isset($_GET['server']) ? trim((string)$_GET['server']) : '';
$page = \Mikhmon\Support\Pagination::page(isset($_GET['page']) ? $_GET['page'] : 1);
$perPage = \Mikhmon\Support\Pagination::perPage(isset($_GET['per_page']) ? $_GET['per_page'] : 50);

$filter = array('date_from' => $dateKey, 'date_to' => $dateKey, 'month_key' => $monthKey, 'q' => $q, 'server' => $serverFilter);
$sales = new \Mikhmon\Storage\SalesRepository($db);
$serverNames = $sales->serverNames($session);
$data = $sales->paginate($session, $filter, $page, $perPage, 'date', 'desc');

$baseQuery = array('report' => 'userlog', 'session' => $session);
if ($dateKey !== null) { $baseQuery['date_key'] = $dateKey; }
if ($monthKey !== null) { $baseQuery['month_key'] = $monthKey; }
if ($q !== '') { $baseQuery['q'] = $q; }
if ($serverFilter !== '') { $baseQuery['server'] = $serverFilter; }
$baseUrl = './?' . http_build_query($baseQuery);
$periodLabel = $dateKey !== null
    ? date('d M Y', strtotime($dateKey))
    : ($monthKey !== null ? date('F Y', strtotime($monthKey . '-01')) : 'Semua data');
$csvUrl = './process/export_sales.php?' . http_build_query(array_merge($baseQuery, array(
    'token' => \Mikhmon\Http\Csrf::token(),
)));
?>

<div class="mm-page">
  <?= mikhmon_page_header('User Log', 'fa-users', '<a class="btn bg-primary" href="' . mikhmon_e($csvUrl) . '"><i class="fa fa-download"></i> CSV</a>', 'Periode: <b>' . mikhmon_e($periodLabel) . '</b>'); ?>

  <section class="mm-card">
    <div class="mm-card-head">
      <h2><i class="fa fa-list"></i> Riwayat Penjualan</h2>
      <span class="mm-muted">Total: <b><?= number_format($data['total'], 0, ',', '.'); ?></b></span>
    </div>
    <div class="mm-card-body">
      <form method="get" action="./" class="mm-toolbar">
        <input type="hidden" name="report" value="userlog">
        <input type="hidden" name="session" value="<?= mikhmon_e($session); ?>">
        <div class="mm-field">
          <label for="ul-date">Tanggal</label>
          <input class="mm-input" id="ul-date" type="date" name="date_key" value="<?= mikhmon_e($dateKey !== null ? $dateKey : ''); ?>">
        </div>
        <div class="mm-field">
          <label for="ul-month">Bulan</label>
          <input class="mm-input" id="ul-month" type="month" name="month_key" value="<?= mikhmon_e($monthKey !== null ? $monthKey : ''); ?>">
        </div>
        <div class="mm-field">
          <label for="ul-q">Cari</label>
          <input class="mm-input" id="ul-q" type="search" name="q" value="<?= mikhmon_e($q); ?>" placeholder="username / komentar / profil">
        </div>
        <div class="mm-field">
          <label for="ul-server">Server</label>
          <select class="mm-select" id="ul-server" name="server">
            <option value="">Semua server</option>
            <?php foreach ($serverNames as $srv): ?>
              <?php $srvValue = $srv === '' ? '__none__' : $srv; ?>
              <option value="<?= mikhmon_e($srvValue); ?>" <?= $serverFilter === $srvValue ? 'selected' : ''; ?>><?= $srv === '' ? 'Tanpa server (data lama)' : mikhmon_e($srv); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn bg-primary" type="submit"><i class="fa fa-search"></i> Filter</button>
        <a class="btn bg-secondary" href="./?report=userlog&session=<?= mikhmon_e($session); ?>"><i class="fa fa-eraser"></i> Reset</a>
        <span class="mm-per-page"><?= mikhmon_per_page($data['perPage'], $baseUrl); ?></span>
      </form>

      <?php if ($data['total'] === 0): ?>
        <?= mikhmon_empty_state('Belum ada data', 'Belum ada record penjualan yang tersimpan untuk filter ini. Jalankan sinkronisasi dari halaman Laporan Penjualan.'); ?>
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
                <th>Alamat</th>
                <th>MAC</th>
                <th>Validity</th>
                <th class="is-wrap">Komentar</th>
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
                  <td><?= mikhmon_e($row['address']); ?></td>
                  <td><?= mikhmon_e($row['mac']); ?></td>
                  <td><?= mikhmon_e($row['validity']); ?></td>
                  <td class="is-wrap"><?= mikhmon_e($row['sale_comment']); ?></td>
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
