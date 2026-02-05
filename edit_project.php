<?php
require_once('config.php');
include('dashboard_header.php');

$id = $_GET['id'];

$query = "SELECT * FROM projects WHERE project_id = '$id'";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (isset($_POST['update'])) {

    $title = $_POST['project_title'];
    $client = $_POST['client'];
    $date = $_POST['creation_date'];
    $time_alloted = $_POST['time_alloted'];
    $budget = $_POST['budget'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];

    $update = "UPDATE projects SET
        project_title = '$title',
        client = '$client',
        creation_date = '$date',
        time_alloted = '$time_alloted',
        budget = '$budget',
        status = '$status',
        notes = '$notes'
        WHERE project_id = '$id'";

    mysqli_query($conn, $update);

    header("Location: projects.php");
    exit();
}
?>

<div class="height-100 dashboard-container">
    <h2 class="page-title">Edit Project</h2>

    <form method="POST" class="dash-form">
        
        <label>Project Title</label>
        <input type="text" name="project_title" value="<?php echo $data['project_title']; ?>" required>

        <label>Client</label>
        <input type="text" name="client" value="<?php echo $data['client']; ?>" required>

        <label>Creation Date</label>
        <input type="date" name="creation_date" value="<?php echo $data['creation_date']; ?>" required>

        <label>Time Allotted (in days)</label>
        <input type="number" name="time_alloted" value="<?php echo $data['time_alloted']; ?>" required>

        <label>Budget</label>
        <input type="number" name="budget" value="<?php echo $data['budget']; ?>">

        <label>Status</label>
        <select name="status">
            <option value="pending" <?php if($data['status']=="pending") echo "selected"; ?>>Pending</option>
            <option value="inprogress" <?php if($data['status']=="inprogress") echo "selected"; ?>>In Progress</option>
            <option value="completed" <?php if($data['status']=="completed") echo "selected"; ?>>Completed</option>
        </select>

        <label>Notes</label>
        <textarea name="notes"><?php echo $data['notes']; ?></textarea>

        <button type="submit" name="update" class="btn-submit">Update Project</button>
    </form>
</div>

<?php include('dashboard_footer.php'); ?>
