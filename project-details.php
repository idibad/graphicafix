<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    die("Project not found");
}

$id = intval($_GET['id']);

$query = "SELECT * FROM projects WHERE id = $id LIMIT 1";
$result = mysqli_query($conn, $query);
$project = mysqli_fetch_assoc($result);

if (!$project) {
    die("Project not found");
}

$gallery = json_decode($project['gallery_images'], true);
?>
<div class="project-details">
    <h1><?= $project['project_name'] ?></h1>

    <p class="project-description">
        <?= nl2br($project['public_description']) ?>
    </p>

    <div class="project-thumbnail">
        <img src="<?= $project['thumbnail'] ?>" alt="">
    </div>

    <h2>Gallery</h2>
    <div class="row gallery">
        <?php foreach ($gallery as $img): ?>
            <div class="col-md-4">
                <img src="<?= $img ?>" class="gallery-img">
            </div>
        <?php endforeach; ?>
    </div>
</div>
