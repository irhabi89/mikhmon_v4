<?php
/*
 * Printable selling report, DB-first and streamed.
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
$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
$row = $session !== '' ? $db->fetchOne('SELECT * FROM mikhmon_sessions WHERE name = ?', array($session)) : null;
if ($row === null) {
    exit('Router session tidak ditemukan.');
}

$currency = isset($row['currency']) && $row['currency'] !== '' ? (string)$row['currency'] : 'Rp';
$hotspotname = isset($row['hotspot_name']) ? (string)$row['hotspot_name'] : $session;
$decimals = in_array(strtoupper($currency), array('RP', 'IDR', 'RP.'), true) ? 0 : 2;
$fmt = function ($value) use ($currency, $decimals) {
    return mikhmon_money($value, $currency, $decimals);
};

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

$filter = array(
    'date_from' => $dateKey,
    'date_to' => $dateKey,
    'month_key' => $monthKey,
    'q' => $q,
    'server' => $serverFilter,
);
$sales = new \Mikhmon\Storage\SalesRepository($db);
$total = $sales->countFiltered($session, $filter);
$sum = $sales->sumFiltered($session, $filter);
$cap = 20000;
$st = $sales->exportStatement($session, $filter);
$periodLabel = $dateKey !== null
    ? date('d M Y', strtotime($dateKey))
    : ($monthKey !== null ? date('F Y', strtotime($monthKey . '-01')) : 'Semua data');
$csvUrl = './process/export_sales.php?' . http_build_query(array(
    'report' => 'selling',
    'session' => $session,
    'date_key' => $dateKey,
    'month_key' => $monthKey,
    'q' => $q,
    'server' => $serverFilter,
    'token' => \Mikhmon\Http\Csrf::token(),
));
$backUrl = './?report=selling&session=' . urlencode($session);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan Penjualan - <?= mikhmon_e($hotspotname); ?></title>
<link rel="stylesheet" href="../css/font-awesome/css/font-awesome.min.css">
<style>
  body { font-family: Arial, Helvetica, sans-serif; color: #111; background: #fff; margin: 18px; font-size: 12px; }
  h1 { margin: 0 0 3px; font-size: 18px; }
  .sub { color: #555; margin: 0 0 12px; }
  .toolbar { margin: 0 0 14px; display: flex; gap: 8px; flex-wrap: wrap; }
  .btn { display: inline-block; padding: 6px 11px; border: 1px solid #bbb; border-radius: 4px; color: #111; text-decoration: none; background: #f5f5f5; font-size: 12px; cursor: pointer; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #999; padding: 4px 6px; vertical-align: top; }
  thead th { background: #eee; text-align: left; font-size: 11px; text-transform: uppercase; }
  td.num, th.num { text-align: right; }
  tfoot td { font-weight: bold; background: #f7f7f7; }
  .warning { border: 1px solid #d39e00; background: #fff8db; padding: 8px 10px; margin: 0 0 12px; }
  @page { size: auto; margin: 8mm; }
  @media print {
    .toolbar { display: none; }
    body { margin: 0; font-size: 10px; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <button class="btn" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
  <a class="btn" href="<?= mikhmon_e($csvUrl); ?>"><i class="fa fa-download"></i> CSV semua data</a>
  <a class="btn" href="<?= mikhmon_e($backUrl); ?>"><i class="fa fa-arrow-left"></i> Kembali</a>
</div>

<h1><?= mikhmon_e($hotspotname); ?> — Laporan Penjualan</h1>
<p class="sub">Periode: <b><?= mikhmon_e($periodLabel); ?></b> &nbsp;|&nbsp; Total: <b><?= number_format($total, 0, ',', '.'); ?></b> transaksi &nbsp;|&nbsp; Nominal: <b><?= mikhmon_e($fmt($sum)); ?></b></p>

<?php if ($total > $cap): ?>
  <div class="warning"><b>Data terlalu besar untuk dicetak sekaligus.</b> Hanya <?= number_format($cap, 0, ',', '.'); ?> baris pertama yang ditampilkan. Gunakan <b>CSV semua data</b> untuk seluruh <?= number_format($total, 0, ',', '.'); ?> baris.</div>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>No</th>
      <th>Tanggal</th>
      <th>Jam</th>
      <th>Server</th>
      <th>Username</th>
      <th>Profil</th>
      <th>Komentar</th>
      <th class="num">Harga</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $n = 0;
    while (($data = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
        $n++;
        if ($n > $cap) {
            break;
        }
        echo '<tr>';
        echo '<td>' . $n . '</td>';
        echo '<td>' . mikhmon_e($data['date_key']) . '</td>';
        echo '<td>' . mikhmon_e($data['time_key']) . '</td>';
        echo '<td>' . mikhmon_e($data['server_name']) . '</td>';
        echo '<td>' . mikhmon_e($data['username']) . '</td>';
        echo '<td>' . mikhmon_e($data['profile']) . '</td>';
        echo '<td>' . mikhmon_e($data['sale_comment']) . '</td>';
        echo '<td class="num">' . mikhmon_e($fmt($data['price'])) . '</td>';
        echo '</tr>';
    }
    if ($n === 0) {
        echo '<tr><td colspan="8" style="text-align:center;">Tidak ada data.</td></tr>';
    }
    ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="7" style="text-align:right;">Total</td>
      <td class="num"><?= mikhmon_e($fmt($sum)); ?></td>
    </tr>
  </tfoot>
</table>
</body>
</html>
