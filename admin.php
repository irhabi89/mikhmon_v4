<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
// hide all error
error_reporting(0);

ob_start("ob_gzhandler");

// check url
$url = $_SERVER['REQUEST_URI'];

// load session MikroTik
$session = isset($_GET['session']) ? $_GET['session'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';
$c = isset($_GET['c']) ? $_GET['c'] : '';
$router = isset($_GET['router']) ? $_GET['router'] : '';
$logo = isset($_GET['logo']) ? $_GET['logo'] : '';

$ids = array(
  "editor",
  "uplogo",
  "settings",
);

// lang
include('./lang/isocodelang.php');
include('./include/lang.php');
include('./lang/'.$langid.'.php');

// quick bt
include('./include/quickbt.php');

// theme
include('./include/theme.php');
include('./settings/settheme.php');
include('./settings/setlang.php');
if (empty($_SESSION['theme'])) {
    $theme = isset($theme) ? $theme : 'dark';
    $themecolor = isset($themecolor) ? $themecolor : '#3a4149';
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = isset($_SESSION['themecolor']) ? $_SESSION['themecolor'] : '#3a4149';
}


// load config
include_once('./include/headhtml.php');
include('./include/config.php');
include('./include/readcfg.php');
require_once(__DIR__ . '/include/bootstrap.php');
require_once(__DIR__ . '/include/ui.php');

include_once('./lib/routeros_api.class.php');
include_once('./lib/formatbytesbites.php');
?>
    
<?php
require __DIR__ . '/src/Http/routes/admin.routes.php';

?>
<script src="js/mikhmon-ui.<?= $theme; ?>.min.js"></script>
<script src="js/app.js?t=<?= file_exists("js/app.js") ? filemtime("js/app.js") : "1"; ?>"></script>
<script src="js/mikhmon.js?t=<?= file_exists("js/mikhmon.js") ? filemtime("js/mikhmon.js") : "1"; ?>"></script>
<?php if (file_exists('./include/info.php')) { include('./include/info.php'); } ?>
</body>
</html>

