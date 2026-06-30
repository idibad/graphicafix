<?php
include_once('config.php');
$projects_query = "SELECT * FROM projects ORDER BY creation_date ";
$projects_result = mysqli_query($conn, $projects_query);

$table = ' <div class="main-table"><table  border="1" cellpadding="5" cellspacing="0">
  <th>
    <td>ID</td>
    <td>Project Title</td>
    <td>Client</td>
    <td>Creation Date</td>
    <td>Time Allotted</td>
    <td>Budget</td>
    <td>Status</td>
    <td>Notes</td>
  </th>';

while($data = mysqli_fetch_array($projects_result)){
  $table .= '
  <tr>
    <td>' . $data['project_id'] . '</td>
    <td>' . $data['project_title'] . '</td>
    <td>' . $data['client'] . '</td>
    <td>' . $data['creation_date'] . '</td>
    <td>' . $data['time_alloted'] . '</td>
    <td>' . $data['budget'] . '</td>
    <td>' . $data['status'] . '</td>
    <td>' . $data['notes'] . '</td>
  </tr>';
}
header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=projects.xls");

echo $table . "</table></div>";
?>