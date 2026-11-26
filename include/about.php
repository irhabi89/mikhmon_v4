<?php
/*
 *  Original Mikhmon: Copyright (C) 2018 Laksamadi Guko.
 *  GHAIB-NETWORK Edition modifications: Copyright (C) 2026 PT GHAIB-NETWORK.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

$versionFile = __DIR__ . '/../verson.txt';
$versionData = json_decode((string)@file_get_contents($versionFile), true);
if (!is_array($versionData)) { $versionData = array(); }
$version = htmlspecialchars((string)($versionData['version'] ?? ($_SESSION['v'] ?? 'v4.0.0')), ENT_QUOTES, 'UTF-8');
$branch = htmlspecialchars((string)($versionData['branch'] ?? ($_SESSION['vbranch'] ?? '')), ENT_QUOTES, 'UTF-8');
$updated = htmlspecialchars((string)($versionData['updated'] ?? '2026-11-26'), ENT_QUOTES, 'UTF-8');
$developer = htmlspecialchars((string)($versionData['developer'] ?? 'quiqxiq'), ENT_QUOTES, 'UTF-8');
$edition = htmlspecialchars((string)($versionData['edition'] ?? 'GHAIB-NETWORK'), ENT_QUOTES, 'UTF-8');
?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-info-circle"></i> About MIKHMON</h3>
      </div>
      <div class="card-body">
        <h3>MIKHMON <?= $version; ?> — <?= $edition; ?> Edition</h3>
        <p>
          Aplikasi manajemen hotspot MikroTik untuk pengusaha hotspot.
          Edisi ini dikembangkan dan dipelihara oleh <b>PT GHAIB-NETWORK</b>.
        </p>
        <ul>
          <li><b>Modified and maintained by:</b> PT GHAIB-NETWORK</li>
          <li><b>Developer:</b> <?= $developer; ?></li>
          <li><b>Branch:</b> <?= $branch !== '' ? $branch : 'feat/report-sqlite-ui'; ?></li>
          <li><b>Version:</b> <?= $version; ?> (<?= $updated; ?>)</li>
          <li><b>Original author:</b> Laksamadi Guko</li>
          <li><b>License:</b> <a href="https://github.com/laksa19/mikhmonv3/blob/master/LICENSE" target="_blank" rel="noopener">GPLv2</a></li>
          <li><b>GHAIB-NETWORK:</b> <a href="https://www.ghaib.net/" target="_blank" rel="noopener">www.ghaib.net</a></li>
        </ul>
        <p>
          <b>Tentang GHAIB-NETWORK</b><br>
          GHAIB-NETWORK adalah perusahaan teknologi (PT) yang menyediakan solusi
          IT, administrasi jaringan, konfigurasi MikroTik, pemasangan internet
          dan CCTV, penarikan kabel fiber optik, serta pengembangan web.
          Berdiri sejak 2018 dan berbasis di Karduluk, Pragaan, Sumenep,
          Jawa Timur.
        </p>
        <p>
          <i>Copyright &copy; 2018 Laksamadi Guko. Modifikasi &copy; 2026 PT GHAIB-NETWORK. Developer: <?= $developer; ?>.</i>
        </p>
      </div>
    </div>
  </div>
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-list-alt"></i> Changelog v4.0.0</h3>
      </div>
      <div class="card-body">
        <ul>
          <li>Laporan penjualan SQLite: filter tanggal/bulan/tahun/server, total transaksi dan nominal, CSV/print.</li>
          <li>Pencatatan server hotspot pada penjualan baru, filter per-server, dan dashboard server.</li>
          <li>On-login server-aware, upgrade profile lama, backup dan rollback.</li>
          <li>UI responsif, ukuran button konsisten, modal dan tabel mobile-friendly.</li>
          <li>Optimasi performa: cache pendek, async dashboard server cards, pengurangan panggilan API.</li>
          <li>Branch <b><?= $branch !== '' ? $branch : 'feat/report-sqlite-ui'; ?></b> dapat memiliki fitur tambahan (PPP/auto-isolir atau voucher Print New).</li>
        </ul>
        <p>
          Changelog lengkap: <a href="../CHANGELOG.md" target="_blank" rel="noopener">CHANGELOG.md</a>
          &nbsp;|&nbsp; Atribusi: <a href="../NOTICE.md" target="_blank" rel="noopener">NOTICE.md</a>
          &nbsp;|&nbsp; <a href="https://laksa19.github.io/mikhmonv3" target="_blank" rel="noopener">Changelog Mikhmon asli</a>
        </p>
      </div>
    </div>
  </div>
</div>
