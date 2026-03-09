<?php
require_once('header.php');

/* =========================
   1. Data Fetching Logic
========================= */
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($project_id <= 0) { header("Location: projects.php"); exit; }

$stmt = $conn->prepare("SELECT p.* FROM projects p WHERE p.id = ? AND p.visibility = 'public' LIMIT 1");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) { header("Location: projects.php"); exit; }

$gallery_stmt = $conn->prepare("SELECT image_path FROM project_gallery WHERE project_id = ? ORDER BY sort_order ASC");
$gallery_stmt->bind_param("i", $project_id);
$gallery_stmt->execute();
$gallery_res = $gallery_stmt->get_result();
$gallery_images = [];
while ($row = $gallery_res->fetch_assoc()) { $gallery_images[] = $row['image_path']; }
$gallery_stmt->close();

$page_title = htmlspecialchars($project['project_name']);
$current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    :root {
        --bg-paper: #FDFCFB;
        --text-primary: var(--accent);
        --text-secondary: #333333;
        --font-head: 'DM Serif Display', serif;
        --font-body: 'Outfit', sans-serif;
    }

    body { background-color: var(--bg-paper); color: var(--text-primary); font-family: var(--font-body); }

    /* === HEADER & TRIO === */
    .project-header { padding: 120px 0 60px; }
    h1 { font-family: var(--font-head); font-size: clamp(3rem, 10vw, 6rem); font-weight: 400; line-height: 0.9; }
    
    .meta-box { border-top: 1px solid #eee; border-bottom: 1px solid #eee; padding: 30px 0; margin-top: 50px; }
    .meta-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px; color: var(--text-secondary); display: block; }
    .meta-value { font-weight: 500; font-size: 1rem; }

    .trio-wrapper { margin-top: -30px; margin-bottom: 100px; }
    .trio-card { overflow: hidden; border-radius: 4px; position: relative; }
    .trio-img { width: 100%; aspect-ratio: 3/4; object-fit: cover; transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1); }
    .trio-card:hover .trio-img { transform: scale(1.08); }

    /* === DESCRIPTION === */
    .description-container { max-width: 700px; margin: 0 auto 120px; }
    .desc-text { font-size: 1.25rem; font-weight: 300; color: var(--text-secondary); line-height: 1.8; max-height: 220px; overflow: hidden; transition: max-height 0.8s ease; position: relative; }
    .desc-text.expanded { max-height: 3000px; }
    .desc-text:not(.expanded)::after { content: ""; position: absolute; bottom: 0; left: 0; width: 100%; height: 100px; background: linear-gradient(transparent, var(--bg-paper)); }
    #readMoreBtn { background: none; border: none; color: var(--accent); font-weight: 600; text-transform: uppercase; cursor: pointer; display: block; margin: 25px auto 0; }

    /* === ASYMMETRIC GALLERY === */
    .art-gallery { padding-bottom: 150px; }
    .gallery-item { margin-bottom: 60px; position: relative; }
    .gallery-img-wrapper { overflow: hidden; border-radius: 14px;border: 1px solid #e0e0e0; background: #f0f0f0; box-shadow: 0 20px 50px rgba(0,0,0,0.09); }
    .gallery-img { width: 100%; height: auto; display: block; transition: transform 1.5s ease; }
    .gallery-item:hover .gallery-img { transform: scale(1.03); }
    
    /* Offset classes for a curated look */
    @media (min-width: 768px) {
        .offset-lg-down { transform: translateY(80px); }
        .offset-lg-up { transform: translateY(-80px); }
    }

    /* === FOOTER SHARE === */
    .share-footer { background: #121212; color: #fff; padding: 100px 0; }
    .share-link { width: 60px; height: 60px; border: 1px solid rgba(255,255,255,0.2); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 10px; color: #fff; transition: 0.4s; text-decoration: none; font-weight: 300; }
    .share-link:hover { background: #fff; color: #000; border-color: #fff; transform: translateY(-5px); }

    .reveal { opacity: 0; transform: translateY(40px); transition: all 1s cubic-bezier(0.2, 1, 0.3, 1); }
    .reveal.visible { opacity: 1; transform: translateY(0); }
</style>

<div class="container project-header text-center">
    <h1 class="reveal text-capitalize"><?= $page_title ?></h1>
    <div class="container d-flex justify-content-center">
        <div class="row meta-box w-100 reveal" style="max-width: 800px;">
            <div class="col-4"><span class="meta-label">Year</span><span class="meta-value"><?= date('Y', strtotime($project['start_date'] ?? 'now')) ?></span></div>
            <div class="col-4"><span class="meta-label">Discipline</span><span class="meta-value">Visual Arts</span></div>
            <div class="col-4"><span class="meta-label">Current</span><span class="meta-value"><?= htmlspecialchars($project['status']) ?></span></div>
        </div>
    </div>
</div>

<div class="container trio-wrapper">
    <div class="row g-3">
        <?php for($i=0; $i<3; $i++): if(isset($gallery_images[$i])): ?>
            <div class="col-md-4">
                <div class="trio-card reveal">
                    <img src="admin/<?= htmlspecialchars($gallery_images[$i]) ?>" class="trio-img" alt="Focus">
                </div>
            </div>
        <?php endif; endfor; ?>
    </div>
</div>

<div class="container description-container reveal">
    <div class="desc-text" id="descText">
        <?= $project['public_description'] ?>
    </div>
    <button id="readMoreBtn">Details &plus;</button>
</div>

<div class="container art-gallery">
    <div class="row align-items-center">
        <?php 
        $remaining = array_slice($gallery_images, 0); 
        foreach($remaining as $index => $img): 
            // Cycle through layout patterns: Full, 2-col Offset, 1-col Narrow centered
            $pattern = $index % 4; 
            
            if ($pattern == 0): // Full Width
                echo '<div class="col-12 gallery-item reveal"><div class="gallery-img-wrapper"><img src="admin/'.htmlspecialchars($img).'" class="gallery-img"></div></div>';
            
            elseif ($pattern == 1): // Left Side Offset
                echo '<div class="col-md-7 gallery-item reveal"><div class="gallery-img-wrapper"><img src="admin/'.htmlspecialchars($img).'" class="gallery-img"></div></div>';
            
            elseif ($pattern == 2): // Right Side Offset Small
                echo '<div class="col-md-4 offset-md-1 gallery-item reveal offset-lg-down"><div class="gallery-img-wrapper"><img src="admin/'.htmlspecialchars($img).'" class="gallery-img"></div></div>';
            
            elseif ($pattern == 3): // Narrow Center
                echo '<div class="col-md-8 offset-md-2 gallery-item reveal"><div class="gallery-img-wrapper"><img src="admin/'.htmlspecialchars($img).'" class="gallery-img"></div></div>';
            
            endif;
        endforeach; ?>
    </div>
</div>

<footer class="share-footer text-center">
    <div class="container">
        <p class="meta-label mb-4" style="color: #888;">Share Perspective</p>
        <div class="d-flex justify-content-center mb-5">
            <a href="https://twitter.com/intent/tweet?url=<?= urlencode($current_url) ?>" class="share-link">TW</a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($current_url) ?>" class="share-link">LI</a>
            <a href="mailto:?body=<?= $current_url ?>" class="share-link">EM</a>
        </div>
        <a href="contact.php" class="text-white text-decoration-none" style="font-size: 1.5rem; font-family: var(--font-head);">Start a Project &rarr;</a>
    </div>
</footer>

<script>
    // Read More Logic
    const descText = document.getElementById('descText');
    const readBtn = document.getElementById('readMoreBtn');
    if (descText.scrollHeight <= 220) readBtn.style.display = 'none';

    readBtn.addEventListener('click', () => {
        descText.classList.toggle('expanded');
        readBtn.innerHTML = descText.classList.contains('expanded') ? 'Collapse &minus;' : 'Details &plus;';
    });

    // Intersection Observer
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('visible');
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>

<?php require_once('footer.php'); ?>