<?php
if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
} else {
    $mikhmonVersionData = json_decode((string)@file_get_contents(__DIR__ . '/../verson.txt'), true);
    $_SESSION["v"] = isset($mikhmonVersionData['version']) ? (string)$mikhmonVersionData['version'] : 'v4.0.0';
    $_SESSION["vbranch"] = isset($mikhmonVersionData['branch']) ? (string)$mikhmonVersionData['branch'] : '';
}
