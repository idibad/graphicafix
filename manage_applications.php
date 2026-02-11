<?php
include('dashboard_header.php');
$result = mysqli_query($conn, "SELECT * FROM career_applications ORDER BY applied_at DESC");
?>

<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Position</th>
            <th>Email</th>
            <th>CV</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?= $row['name'] ?></td>
            <td><?= $row['position'] ?></td>
            <td><?= $row['email'] ?></td>
            <td><a href="<?= $row['cv'] ?>" target="_blank">View CV</a></td>
            <td><?= $row['applied_at'] ?></td>
        </tr>
        <?php } ?>
    </tbody>
</table>
