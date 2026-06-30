<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/graphicafix/config.php';
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
