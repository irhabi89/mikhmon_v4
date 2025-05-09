<?php

session_start();
// hide all error
// error_reporting(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once("../core/page_route.php");
include_once("../core/no_cache.php");
include_once("../core/routeros_api.class.php");

if (isset($_POST['router_']) && isset($_SESSION["mikhmon"])) {
	$do = $_POST['do'];
	$config_file = "../config/config.php";
	$router = $_POST['router_'];
	if ($do == "add" && explode("_", $router)[0] == "sess") {
		$router = str_replace("sess_", "session", $router . rand(100, 999));
		$data = '$data';
		$f = fopen('../config/config.php', 'a');
		fwrite($f, "\n'$'data['" . $router . "'] = array ('1'=>'" . $router . "!','" . $router . "@|@','" . $router . "#|#','" . $router . "%','" . $router . "^','" . $router . "&Rp','" . $router . "*','" . $router . "(','" . $router . ")','" . $router . "=30','" . $router . "@!@disable','" . $router . "#!#');");
		if (false === $f) {
			$mess = array(
				"message" => "Error, cannot write config file, please check folder or file permissions."
			);
			echo json_encode($mess);
		} else {

			fclose($f);
			$search = "'$'data";
			$replace = (string)"$data";
			$file = file($config_file);
			$content = file_get_contents($config_file);
			$newcontent = str_replace((string)$search, (string)$replace, "$content");
			$storenewcontent = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $newcontent);
			file_put_contents($config_file, "$storenewcontent");


			$mess = array(
				"message" => "Success",
				"sesname" => $router
			);
			echo json_encode($mess);
		}
	} else if ($do == "remove") {
		$session = $_POST['router_']; // Tambahkan baris ini
		$fc = file($config_file);
		$f = fopen($config_file, "w");
		$q = "'";
		if (false === $f) {
			$mess->message = "Error, cannot write config file, please check folder or file permissions.";
			echo json_encode($mess);
		} else {
			$rem = '$data[' . $q . $router . $q . ']';
			foreach ($fc as $line) {
				if (!strstr($line, $rem))
					fputs($f, $line);
			}
			fclose($f);
			$logo_file = "../assets/img/logo-" . $session . ".png";
			if (file_exists($logo_file)) {
				unlink($logo_file);
			}

			$mess = array(
				"message" => "Success"
			);
			echo json_encode($mess);
		}
	} else if ($do == "save") {
		$session = $_POST['router_'];
		$sesname = str_replace(" ", "", preg_replace("/[^a-zA-Z0-9]/", "", $_POST['session']));

		// Inisialisasi sesnamer agar tidak undefined
		$sesnamer = "";

		if (is_file($config_file) && !is_writable($config_file)) {
			$mess->message = "Error, cannot write config file, please check folder or file permissions.";
			$mess->sess = "__err_";
			echo json_encode($mess);
		} else if (is_file($config_file) && is_writable($config_file)) {
			foreach (file('../config/config.php', FILE_SKIP_EMPTY_LINES) as $line) {
				$parts = explode("'", $line);
				$ss = isset($parts[1]) ? $parts[1] : '';

				if (($ss == $sesname && $sesname !== $session)) {
					$sesnamer = rand(100, 999); // Definisikan di sini
					$mess = array(
						"message" => "Session name [" . $sesname . "] already exists,\nwill be renamed to [" . $sesname . $sesnamer . "].",
						"sess" => $sesname . $sesnamer
					);
					echo json_encode($mess);
				} else if ($ss == $session) {

					$iphost = get_config($line, $session . '!', "'");
					$userhost = get_config($line, $session . '@|@', "'");
					$passwdhost = get_config($line, $session . '#|#', "'");
					$hotspotname = get_config($line, $session . '%', "'");
					$dnsname = get_config($line, $session . '^', "'");
					$currency = get_config($line, $session . '&', "'");
					$phone = get_config($line, $session . '*', "'");
					$email = get_config($line, $session . '(', "'");
					$infolp = get_config($line, $session . ')', "'");
					$idleto = get_config($line, $session . '=', "'");
					$report = get_config($line, $session . '@!@', "'");
					$token = get_config($line, $session . '#!#', "'");

					$siphost = preg_replace('/\s+/', '', decode($_POST['ipmik'] ?? ''));
					$suserhost = decode($_POST['usermik'] ?? '');
					$spasswdhost = enc_rypt(decode($_POST['passmik'] ?? ''));
					$shotspotname = str_replace("'", "", $_POST['hotspotname'] ?? '');
					$sdnsname = $_POST['dnsname'] ?? '';
					$scurrency = $_POST['currency'] ?? '';
					$semail = decode($_POST['email'] ?? '');
					$sinfolp = $_POST['infolp'] ?? '';
					$sinfobin = $sinfolp ? implode(unpack("H*", $sinfolp)) : '';
					$sidleto = $_POST['idleto'] ?? '';
					$sphone = decode($_POST['phone'] ?? '');
					$sreport = $_POST['report'] ?? '';
					$stoken = "";

					// Pastikan sesname digunakan dengan benar
					$new_sesname = str_replace(' ', '', preg_replace("/[^a-zA-Z0-9]/", "", $_POST['session'] ?? '')) . ($sesnamer ?: '');

					$search = array(
						"$session!$iphost",
						"$session@|@$userhost",
						"$session#|#$passwdhost",
						"$session%$hotspotname",
						"$session^$dnsname",
						"$session&$currency",
						"$session*$phone",
						"$session($email",
						"$session)$infolp",
						"$session=$idleto",
						"'$session'",
						"$session@!@$report",
						"$session#!#$token"
					);

					$replace = array(
						"$new_sesname!$siphost",
						"$new_sesname@|@$suserhost",
						"$new_sesname#|#$spasswdhost",
						"$new_sesname%$shotspotname",
						"$new_sesname^$sdnsname",
						"$new_sesname&$scurrency",
						"$new_sesname*$sphone",
						"$new_sesname($semail",
						"$new_sesname)$sinfobin",
						"$new_sesname=$sidleto",
						"'$new_sesname'",
						"$new_sesname@!@$sreport",
						"$new_sesname#!#$stoken"
					);

					for ($i = 0; $i < count($search); $i++) {
						$content = file_get_contents($config_file);
						$newcontent = str_replace((string)$search[$i], (string)$replace[$i], $content);
						file_put_contents($config_file, $newcontent);
					}

					// Rename logo jika file ada
					$old_logo = "../assets/img/logo-" . $session . ".png";
					$new_logo = "../assets/img/logo-" . $new_sesname . ".png";

					if (file_exists($old_logo)) {
						rename($old_logo, $new_logo);
					}

					if ($sesnamer === "") {
						$mess = array(
							"message" => "Success",
							"sess" => $new_sesname
						);
						echo json_encode($mess);
					}
				}
			}
		}
	} else if ($do == "saveAdmin") {

		$suseradm = str_replace("'", '', decode($_POST['username']));
		$spassadm = enc_rypt(decode($_POST['password']));
		if (is_file($config_file) && !is_writable($config_file)) {
			$mess->message = "Error, cannot write config file, please check folder or file permissions.";
			echo json_encode($mess);
		} else if (is_file($config_file) && is_writable($config_file)) {
			foreach (file('../config/config.php', FILE_SKIP_EMPTY_LINES) as $line) {
				$parts = explode("'", $line);
				$ss = isset($parts[1]) ? $parts[1] : '';
				$useradm = get_config($line, 'mikhmon<|<', "'");
				$passadm = get_config($line, 'mikhmon>|>', "'");

				if ($ss == "mikhmon") {

					$cari = array('1' => "mikhmon<|<$useradm", "mikhmon>|>$passadm");
					$ganti = array('1' => "mikhmon<|<$suseradm", "mikhmon>|>$spassadm");

					for ($i = 1; $i < 3; $i++) {
						$file = file($config_file);
						$content = file_get_contents($config_file);
						$newcontent = str_replace((string)$cari[$i], (string)$ganti[$i], "$content");
						file_put_contents($config_file, "$newcontent");
					}

					$mess = array(
						"message" => "Success"
					);
					echo json_encode($mess);
				}
			}
		}
	}
} else {
	// protect .php
	$get_self = explode("/", $_SERVER['PHP_SELF']);
	$self[] = $get_self[count($get_self) - 1];

	if ($self[0] !== "index.php"  && $self[0] !== "") {
		include_once("../core/page_route.php");
		include_once("../core/route.php");
	}
}
// }