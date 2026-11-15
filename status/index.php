<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  MIKHMON v4 - Hotspot Voucher Check Page
 */
session_start();
// hide all error
error_reporting(0);

$session = $_GET['session'] ?? '';

include_once(__DIR__ . '/../config/config.php');
include_once(__DIR__ . '/../core/routeros_api.class.php');
include_once(__DIR__ . '/../core/format_bytes.php');

$sessions = array();
foreach ($data as $k => $v) {
    if ($k !== 'mikhmon' && !empty($k)) {
        $sessions[] = $k;
    }
}

if (empty($session) && !empty($sessions)) {
    $session = $sessions[0];
}

$hotspotname = "Hotspot";
$currency = "";

if (isset($data[$session])) {
    $iphost = explode('!', $data[$session][1])[1];
    $userhost = explode('@|@', $data[$session][2])[1];
    $passwdhost = explode('#|#', $data[$session][3])[1];
    $hotspotname = explode('%', $data[$session][4])[1] ?? 'Hotspot';
    $dnsname = explode('^', $data[$session][5])[1] ?? '';
    $currency = explode('&', $data[$session][6])[1] ?? '';
}

$user_data = null;
$searched = false;
$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nama'])) {
    $searched = true;
    $name = trim($_POST['nama']);

    if (isset($data[$session])) {
        $API = new RouterosAPI();
        $API->debug = false;

        if ($API->connect($iphost, $userhost, dec_rypt($passwdhost))) {
            $getuser = $API->comm("/ip/hotspot/user/print", array("?name" => "$name"));
            if (!empty($getuser[0])) {
                $user_data = $getuser[0];
                $profile_name = $user_data['profile'] ?? '';
                $getprof = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$profile_name"));
                if (!empty($getprof[0])) {
                    $user_data['profile_data'] = $getprof[0];
                }
            } else {
                $error_msg = "Voucher / username [" . htmlspecialchars($name) . "] not found.";
            }
        } else {
            $error_msg = "Could not connect to router.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher Status - <?= htmlspecialchars($hotspotname); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/css/mikhmon-ui.light.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .status-card {
            max-width: 440px;
            margin: 20px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .status-header {
            background: #20a8d8;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .status-header h2 { margin: 0 0 5px 0; font-size: 20px; }
        .status-body { padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 15px;
            box-sizing: border-box;
        }
        .btn-check {
            width: 100%;
            padding: 10px;
            background: #20a8d8;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-check:hover { background: #1b8eb7; }
        .info-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .info-table td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        .info-table td:first-child { font-weight: bold; color: #555; width: 40%; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-success { background: #4dbd74; color: #fff; }
        .badge-danger { background: #f86c6b; color: #fff; }
    </style>
</head>
<body>
    <div class="status-card">
        <div class="status-header">
            <h2><i class="fa fa-ticket"></i> <?= htmlspecialchars($hotspotname); ?></h2>
            <div>Voucher Status Check</div>
        </div>
        <div class="status-body">
            <form method="post" action="">
                <?php if (count($sessions) > 1): ?>
                <div class="form-group">
                    <select name="session" class="form-control" onchange="window.location.href='?session='+this.value">
                        <?php foreach ($sessions as $s): ?>
                        <option value="<?= htmlspecialchars($s); ?>" <?= $s === $session ? 'selected' : ''; ?>>Session: <?= htmlspecialchars(strtoupper($s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <input type="text" name="nama" class="form-control" placeholder="Enter Voucher Code / Username" value="<?= htmlspecialchars($_POST['nama'] ?? ''); ?>" required autofocus />
                </div>
                <button type="submit" class="btn-check"><i class="fa fa-search"></i> Check Status</button>
            </form>

            <?php if (!empty($error_msg)): ?>
            <div style="margin-top: 15px; padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px; font-size: 14px;">
                <i class="fa fa-exclamation-circle"></i> <?= $error_msg; ?>
            </div>
            <?php endif; ?>

            <?php if ($user_data): ?>
            <?php
                $bytes_in = (float)($user_data['bytes-in'] ?? 0);
                $bytes_out = (float)($user_data['bytes-out'] ?? 0);
                $bytes_total = $bytes_in + $bytes_out;
                $limit_bytes = (float)($user_data['limit-bytes-total'] ?? 0);
                $uptime = $user_data['uptime'] ?? '0s';
                $comment = $user_data['comment'] ?? '';
                $disabled = ($user_data['disabled'] ?? 'false') === 'true';

                $validity = "";
                if (!empty($user_data['profile_data']['on-login'])) {
                    $parts = explode(',', $user_data['profile_data']['on-login']);
                    if (isset($parts[3])) {
                        $validity = $parts[3];
                    }
                }
            ?>
            <table class="info-table">
                <tr>
                    <td>Status</td>
                    <td>
                        <?php if ($disabled): ?>
                        <span class="badge badge-danger">Disabled</span>
                        <?php else: ?>
                        <span class="badge badge-success">Active</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Username</td>
                    <td><b><?= htmlspecialchars($user_data['name'] ?? ''); ?></b></td>
                </tr>
                <tr>
                    <td>Profile</td>
                    <td><?= htmlspecialchars($user_data['profile'] ?? '-'); ?></td>
                </tr>
                <?php if (!empty($validity)): ?>
                <tr>
                    <td>Validity</td>
                    <td><?= htmlspecialchars($validity); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Uptime</td>
                    <td><?= htmlspecialchars($uptime); ?></td>
                </tr>
                <tr>
                    <td>Data Used</td>
                    <td><?= formatBytes($bytes_total, 2); ?></td>
                </tr>
                <?php if ($limit_bytes > 0): ?>
                <tr>
                    <td>Data Limit</td>
                    <td><?= formatBytes($limit_bytes, 2); ?></td>
                </tr>
                <tr>
                    <td>Remaining Data</td>
                    <td><?= formatBytes(max(0, $limit_bytes - $bytes_total), 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($comment)): ?>
                <tr>
                    <td>Expiration / Info</td>
                    <td><?= htmlspecialchars($comment); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
