<?php
/*
 * Dashboard income card (DB-first).
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
include __DIR__ . '/../include/lang.php';
include __DIR__ . '/../lang/' . $langid . '.php';

$db = mikhmon_database();
$session = isset($_GET['session']) ? (string)$_GET['session'] : '';
$row = $session !== '' ? $db->fetchOne('SELECT * FROM mikhmon_sessions WHERE name = ?', array($session)) : null;
if ($row === null) {
    exit('Router session tidak ditemukan.');
}
$currency = isset($row['currency']) && $row['currency'] !== '' ? (string)$row['currency'] : 'Rp';
$decimals = in_array(strtoupper($currency), array('RP', 'IDR', 'RP.'), true) ? 0 : 2;
$livereport = isset($row['live_report']) ? (string)$row['live_report'] : 'enable';

if ($livereport === 'disable') {
    $logh = '457px';
    $lreport = "style='display:none;'";
} else {
    $logh = '350px';
    $lreport = "style='display:block;'";
    $sales = new \Mikhmon\Storage\SalesRepository($db);
    $stats = $sales->stats($session);

    $_SESSION[$session . 'dincome'] = number_format($stats['today_sum'], $decimals, ',', '.');
    $_SESSION[$session . 'mincome'] = number_format($stats['month_sum'], $decimals, ',', '.');
    $_SESSION[$session . 'totalBl'] = $stats['month_count'];
    $_SESSION[$session . 'totalHr'] = $stats['today_count'];
    $_SESSION[$session . 'idhr'] = date('Y-m-d');

    $totalHr = number_format((int)$stats['today_count'], 0, ',', '.');
    $totalBl = number_format((int)$stats['month_count'], 0, ',', '.');
    $dincome = $_SESSION[$session . 'dincome'];
    $mincome = $_SESSION[$session . 'mincome'];
    $empty = $stats['total_count'] === 0;
    session_write_close();
}
?>
<div id="r_4" class="row">
  <div <?= $lreport; ?> class="box bmh-75 box-bordered">
    <div class="box-group">
      <div class="box-group-icon"><i class="fa fa-money"></i></div>
      <div class="box-group-area">
        <span>
          <div id="reloadLreport">
            <?php if ($livereport === 'disable'): ?>
              <b><?= $_income ?? 'Income'; ?></b><br><span class="mm-muted">Live report dinonaktifkan.</span>
            <?php elseif (!empty($empty)): ?>
              <b><?= $_income ?? 'Income'; ?></b><br>
              <span class="mm-muted" id="mmDashLiveStatus"><i class="fa fa-circle-o-notch fa-spin"></i> Membaca data live dari MikroTik…</span>
              <div id="mmDashLiveValues" style="display:none;">
                <span><?= $_today ?? 'Hari ini'; ?> : <b id="mmDashTodayValue">-</b></span>
                <small class="mm-muted">(<span id="mmDashTodayCount">0</span> voucher)</small><br>
                <span><?= $_this_month ?? 'Bulan ini'; ?> : <b id="mmDashMonthValue">-</b></span>
                <small class="mm-muted">(<span id="mmDashMonthCount">0</span> voucher)</small><br>
                <small class="mm-muted">Data live MikroTik — belum diimpor</small>
              </div>
              <script src="./js/live-income.js"></script>
              <script>
              (function () {
                if (!window.MMLiveIncome) { return; }
                MMLiveIncome.start({
                  session: <?= json_encode((string)$session); ?>,
                  currency: <?= json_encode((string)$currency); ?>,
                  decimals: <?= (int)$decimals; ?>,
                  today: <?= json_encode(date('Y-m-d')); ?>,
                  month: <?= json_encode(date('Y-m')); ?>,
                  statusId: 'mmDashLiveStatus'
                });
              })();
              </script>
            <?php else: ?>
              <a href="./?report=selling&session=<?= mikhmon_e($session); ?>" style="color:inherit;text-decoration:none;" title="Lihat Laporan Penjualan">
                <b><?= $_income ?? 'Income'; ?></b><br>
                <?= $_today ?? 'Hari ini'; ?> : <b><?= mikhmon_e($currency); ?> <?= mikhmon_e($dincome); ?></b>
                <small class="mm-muted">(<?= $totalHr; ?> voucher)</small><br>
                <?= $_this_month ?? 'Bulan ini'; ?> : <b><?= mikhmon_e($currency); ?> <?= mikhmon_e($mincome); ?></b>
                <small class="mm-muted">(<?= $totalBl; ?> voucher)</small>
              </a>
            <?php endif; ?>
          </div>
        </span>
      </div>
    </div>
  </div>
</div>
