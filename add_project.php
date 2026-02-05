<?php
require_once('config.php');
include('dashboard_header.php');

if (isset($_POST['add'])) {

    $title = $_POST['project_title'];
    $client = $_POST['client'];
    $date = $_POST['creation_date'];
    $time_alloted = $_POST['time_alloted'];
    $budget = $_POST['budget'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];

    $query = "INSERT INTO projects 
    (project_title, client, creation_date, time_alloted, budget, status, notes) 
    VALUES 
    ('$title', '$client', '$date', '$time_alloted', '$budget', '$status', '$notes')";

    mysqli_query($conn, $query);

    header("Location: projects.php");
    exit();
}
?>

<div class="height-100 dashboard-container">
    <h2 class="page-title">Add New Project</h2>

    <form method="POST" class="dash-form">
        
        <label>Project Title</label>
        <input type="text" name="project_title" required>

        <label>Client</label>
        <input type="text" name="client" required>

        <label>Creation Date</label>
        <input type="date" name="creation_date" required>

        <label>Time Allotted (in days)</label>
        <input type="number" name="time_alloted" required>

        <label>Budget</label>
        <input type="number" name="budget">

        <label>Status</label>
        <select name="status">
            <option value="pending">Pending</option>
            <option value="inprogress">In Progress</option>
            <option value="completed">Completed</option>
        </select>

        <label>Notes</label>
        <textarea name="notes"></textarea>

        <button type="submit" name="add" class="btn-submit">Add Project</button>
    </form>
</div>

<?php include('dashboard_footer.php'); ?>
