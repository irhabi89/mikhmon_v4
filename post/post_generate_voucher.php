<?php

session_start();
set_time_limit(0);
error_reporting(0);
// ini_set('display_errors', 1);

include_once("../core/page_route.php");
include_once("../core/no_cache.php");
include_once("../core/generator_functions.php");

if (isset($_POST['qty']) && isset($_SESSION["mikhmon"])) {

	// Ambil semua POST dengan null coalescing
	$qty = $_POST['qty'];
	$server = $_POST['server'];
	$user = $_POST['user'];
	$userl = $_POST['userl'];
	$prefix = $_POST['prefix'];
	$char = $_POST['char'];
	$profile = $_POST['profile'];
	$timelimit = $_POST['timelimit'];
	$datalimit = $_POST['datalimit'];
	$gcomment = $_POST['gcomment'];
	$gencode = $_POST['gencode'];



	// Hitung data limit
	// Hitung data limit dengan validasi
	if (!empty($datalimit)) {
		$last_char = strtolower(substr($datalimit, -1));
		if (in_array($last_char, ['m', 'g'])) {
			$value = substr($datalimit, 0, -1);
			if (is_numeric($value)) {
				$dlimit = (int)$value;
			} else {
				$dlimit = 0;
			}
		} else {
			// Bisa jadi hanya angka byte
			$dlimit = is_numeric($datalimit) ? (int)$datalimit : 0;
		}

		$mbgb = ($last_char == "g") ? 1073741824 : 1048576;
	} else {
		$dlimit = 0;
		$mbgb = 1048576;
	}

	$datalimit = $dlimit * $mbgb;

	// Buat comment
	$commt = $user . "-" . $gencode . "-" . date("m.d.y") . "-" . $gcomment;

	// Koneksi ke MikroTik
	include_once('../core/routeros_api.class.php');
	include_once("../config/config.php");

	$m_user = explode("?", $_POST['sessname'])[1];
	$iphost = explode('!', $data[$m_user][1])[1];
	$userhost = explode('@|@', $data[$m_user][2])[1];
	$passwdhost = explode('#|#', $data[$m_user][3])[1];

	$API = new RouterosAPI();
	$API->debug = false;

	if (!$API->connect($iphost, $userhost, dec_rypt($passwdhost))) {
		echo json_encode(["message" => "error", "data" => ["error" => "Gagal terhubung ke MikroTik"]]);
		exit;
	}

	// Proses generate user biasa atau voucher
	if ($user == "up") {
		for ($i = 1; $i <= $qty; $i++) {
			if ($char == "lower") {
				$u[$i] = randLC($userl);
			} elseif ($char == "upper") {
				$u[$i] = randUC($userl);
			} elseif ($char == "upplow") {
				$u[$i] = randULC($userl);
			} elseif ($char == "mix") {
				$u[$i] = randNLC($userl);
			} elseif ($char == "mix1") {
				$u[$i] = randNUC($userl);
			} elseif ($char == "mix2") {
				$u[$i] = randNULC($userl);
			}
			if ($userl == 4) {
				$p[$i] = randN(4);
			} elseif ($userl == 5) {
				$p[$i] = randN(5);
			} elseif ($userl == 6) {
				$p[$i] = randN(6);
			} elseif ($userl == 7) {
				$p[$i] = randN(7);
			} elseif ($userl == 8) {
				$p[$i] = randN(8);
			}

			$u[$i] = "$prefix$u[$i]";
		}

		for ($i = 1; $i <= $qty; $i++) {
			$API->comm("/ip/hotspot/user/add", array(
				"server" => "$server",
				"name" => "$u[$i]",
				"password" => "$p[$i]",
				"profile" => "$profile",
				"limit-uptime" => "$timelimit",
				"limit-bytes-total" => "$datalimit",
				"comment" => "$commt",
			));
		}
	}

	if ($user == "vc") {
		// fallback default value
		$shuf = max(1, $userl - ($a[$userl] ?? 0)); // pastikan tidak negatif

		for ($i = 1; $i <= $qty; $i++) {
			// inisialisasi awal
			$u[$i] = "";
			$p[$i] = "";

			// tentukan username
			if ($char == "lower1") {
				$u[$i] = randLC($shuf);
			} elseif ($char == "upper1") {
				$u[$i] = randUC($shuf);
			} elseif ($char == "upplow1") {
				$u[$i] = randULC($shuf);
			} else {
				// fallback jika char tidak sesuai
				$u[$i] = randNULC($shuf);
			}

			// tentukan password
			if ($userl >= 4 && $userl <= 5) {
				$p[$i] = randN(2);
			} elseif ($userl >= 6 && $userl <= 7) {
				$p[$i] = randN(3);
			} elseif ($userl == 8) {
				$p[$i] = randN(4);
			} else {
				$p[$i] = randN(2); // default
			}

			// gabungkan
			$u[$i] = "$prefix" . $u[$i] . $p[$i];

			// override jika char == num / mix / dll
			if ($char == "num") {
				$p[$i] = match (true) {
					$userl == 3 => randN(3),
					$userl == 4 => randN(4),
					$userl == 5 => randN(5),
					$userl == 6 => randN(6),
					$userl == 7 => randN(7),
					$userl == 8 => randN(8),
					default => randN(4)
				};
				$u[$i] = "$prefix" . $p[$i];
			} elseif ($char == "mix") {
				$u[$i] = "$prefix" . randNLC($userl);
			} elseif ($char == "mix1") {
				$u[$i] = "$prefix" . randNUC($userl);
			} elseif ($char == "mix2") {
				$u[$i] = "$prefix" . randNULC($userl);
			}
		}

		// tambahkan ke hotspot
		for ($i = 1; $i <= $qty; $i++) {
			$API->comm("/ip/hotspot/user/add", array(
				"server" => "$server",
				"name" => "$u[$i]",
				"password" => "$u[$i]",
				"profile" => "$profile",
				"limit-uptime" => "$timelimit",
				"limit-bytes-total" => "$datalimit",
				"comment" => "$commt",
			));
		}
	}

	// Print hasil
	$get_hotspotusers = $API->comm("/ip/hotspot/user/print", ["?comment" => "$commt"]);
	$_SESSION[$m_user . $commt] = $get_hotspotusers;

	if (!empty($get_hotspotusers['!trap'][0]['message'])) {
		$message = $get_hotspotusers['!trap'][0]['message'];
		echo json_encode(["message" => "error", "data" => ["error" => $message]]);
	} else {
		echo json_encode(["message" => "success", "data" => ["count" => count($get_hotspotusers), "comment" => $commt, "profile" => $profile]]);
	}
} else {
	$get_self = explode("/", $_SERVER['PHP_SELF']);
	$self[] = $get_self[count($get_self) - 1];

	if ($self[0] !== "index.php"  && $self[0] !== "") {
		include_once("../core/route.php");
	}
}
