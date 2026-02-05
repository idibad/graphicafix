<?php
require_once('config.php');

$id = $_GET['id'];

$query = "DELETE FROM projects WHERE project_id = '$id'";
mysqli_query($conn, $query);

header("Location: projects.php");
exit();
?>
