<?php

if(!$conn){
    include __DIR__ . '/../core/config.php';
}
   
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($project_id <= 0) { header("Location: ../portfolio.php"); exit; }

// Fetch project details joined with client name
$stmt = $conn->prepare("
    SELECT p.*, c.client_name 
    FROM projects p 
    LEFT JOIN clients c ON p.client_id = c.id 
    WHERE p.id = ? AND p.visibility = 'public' 
    LIMIT 1
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) { header("Location: ../portfolio.php"); exit; }

// Fetch gallery images
$gallery_stmt = $conn->prepare("SELECT image_path FROM project_gallery WHERE project_id = ? ORDER BY sort_order ASC");
$gallery_stmt->bind_param("i", $project_id);
$gallery_stmt->execute();
$gallery_res = $gallery_stmt->get_result();
$gallery_images = [];
while ($row = $gallery_res->fetch_assoc()) { 
    $gallery_images[] = $row['image_path']; 
}
$gallery_stmt->close();

// Fetch next project ID for navigation loop
$next_stmt = $conn->prepare("
    SELECT id, project_name 
    FROM projects 
    WHERE id > ? AND visibility = 'public' 
    ORDER BY id ASC 
    LIMIT 1
");
$next_stmt->bind_param("i", $project_id);
$next_stmt->execute();
$next_project = $next_stmt->get_result()->fetch_assoc();
$next_stmt->close();

if (!$next_project) {
    // Loop back to first project
    $first_stmt = $conn->prepare("
        SELECT id, project_name 
        FROM projects 
        WHERE visibility = 'public' 
        ORDER BY id ASC 
        LIMIT 1
    ");
    $first_stmt->execute();
    $next_project = $first_stmt->get_result()->fetch_assoc();
    $first_stmt->close();
}

$page_title = htmlspecialchars($project['project_name']) . " | Graphicafix";
$project_title = htmlspecialchars($project['project_name']);
$current_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

require_once __DIR__ . '/../templates/header.php';
?>
<!-- Outfit and Serif fonts -->
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --bg-paper: #f8fafc;
        --text-primary: #024442;
        --text-secondary: #475569;
        --font-head: 'DM Serif Display', serif;
        --font-body: 'Outfit', sans-serif;
    }

    body { background-color: var(--bg-paper); color: var(--text-primary); font-family: var(--font-body); }

    /* === Hero Banner === */
    .project-hero {
        position: relative;
        background: #ffffff;
        padding: 140px 0 80px;
        border-bottom: 1px solid #e2e8f0;
        overflow: hidden;
        z-index: 1;
    }
    .project-hero::before {
        content: '';
        position: absolute;
        width: 350px;
        height: 350px;
        background: rgba(184, 243, 90, 0.1);
        border-radius: 50%;
        filter: blur(80px);
        top: -100px;
        left: -100px;
        z-index: -1;
    }
    .project-hero h1 {
        font-family: var(--font-head);
        font-size: clamp(32px, 8vw, 72px);
        font-weight: 500;
        line-height: 1.1;
        margin-bottom: 24px;
        color: #0f172a;
    }

    /* === Metadata Grid === */
    .meta-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 10px 30px rgba(2, 68, 66, 0.04);
        margin-top: 40px;
    }
    .meta-item {
        text-align: center;
        padding: 12px;
    }
    .meta-item:not(:last-child) {
        border-right: 1px solid #e2e8f0;
    }
    .meta-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #64748b;
        font-weight: 600;
        display: block;
        margin-bottom: 6px;
    }
    .meta-value {
        font-weight: 700;
        font-size: 16px;
        color: #024442;
    }

    @media (max-width: 767px) {
        .meta-item:not(:last-child) {
            border-right: none;
            border-bottom: 1px solid #e2e8f0;
        }
    }

    /* === Case Study Mockup Grid === */
    .showcase-section {
        padding: 80px 0;
    }
    .showcase-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 48px;
        align-items: center;
        background: #ffffff;
        border-radius: 32px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(2, 68, 66, 0.05);
    }
    @media (max-width: 991px) {
        .showcase-grid {
            grid-template-columns: 1fr;
            gap: 24px;
        }
    }
    .showcase-image-wrapper {
        position: relative;
        overflow: hidden;
        aspect-ratio: 16/10;
        background: #f1f5f9;
        border-radius: 32px 0 0 32px;
    }
    @media (max-width: 991px) {
        .showcase-image-wrapper {
            border-radius: 32px 32px 0 0;
        }
    }
    .showcase-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }
    .showcase-image-wrapper:hover .showcase-img {
        transform: scale(1.03);
    }
    .showcase-content {
        padding: clamp(24px, 5vw, 64px);
    }

    /* === Description Panel === */
    .description-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 40px;
        box-shadow: 0 10px 35px rgba(2, 68, 66, 0.03);
        margin-bottom: 80px;
    }
    .desc-text {
        font-size: 18px;
        font-weight: 400;
        color: var(--text-secondary);
        line-height: 1.8;
        max-height: 280px;
        overflow: hidden;
        transition: max-height 0.8s ease;
        position: relative;
    }
    .desc-text.expanded { max-height: 3000px; }
    .desc-text:not(.expanded)::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 120px;
        background: linear-gradient(transparent, #ffffff);
    }
    #readMoreBtn {
        background: transparent;
        border: 1px solid #024442;
        color: #024442;
        font-weight: 700;
        padding: 10px 24px;
        border-radius: 30px;
        text-transform: uppercase;
        cursor: pointer;
        display: block;
        margin: 30px auto 0;
        transition: all 0.3s;
    }
    #readMoreBtn:hover {
        background: #024442;
        color: #ffffff;
    }

    /* === Responsive Masonry Gallery === */
    .masonry-gallery {
        padding-bottom: 120px;
    }
    .gallery-item {
        margin-bottom: 30px;
        position: relative;
        cursor: pointer;
    }
    .gallery-img-wrapper {
        overflow: hidden;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .gallery-img {
        width: 100%;
        height: auto;
        display: block;
        transition: transform 0.6s ease;
    }
    .gallery-item:hover .gallery-img-wrapper {
        transform: translateY(-8px);
        box-shadow: 0 20px 45px rgba(2, 68, 66, 0.08);
    }
    .gallery-item:hover .gallery-img {
        transform: scale(1.04);
    }

    /* === Next Case Study Link === */
    .next-case-study {
        background: #024442;
        padding: 80px 0;
        color: #ffffff;
        text-align: center;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    .next-link-title {
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 3px;
        color: #B6F763;
        font-weight: 700;
        margin-bottom: 12px;
    }
    .next-link-name {
        font-family: var(--font-head);
        font-size: clamp(28px, 5vw, 48px);
        color: #ffffff;
        text-decoration: none;
        transition: opacity 0.3s;
        display: inline-block;
    }
    .next-link-name:hover {
        opacity: 0.8;
        color: #B6F763;
    }

    /* === Sharing === */
    .share-link {
        width: 50px;
        height: 50px;
        border: 1px solid rgba(2,68,66,0.1);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 6px;
        color: #024442;
        transition: all 0.3s;
        text-decoration: none;
        background: #ffffff;
    }
    .share-link:hover {
        background: #024442;
        color: #ffffff;
        transform: translateY(-4px);
    }

    .reveal { opacity: 0; transform: translateY(30px); transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
    .reveal.visible { opacity: 1; transform: translateY(0); }
</style>

<!-- Hero Section -->
<section class="project-hero">
    <div class="container text-center">
        <h1 class="reveal text-capitalize"><?= $project_title ?></h1>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="meta-card d-flex flex-column flex-md-row justify-content-around reveal">
                    <div class="meta-item flex-fill">
                        <span class="meta-label">Client</span>
                        <span class="meta-value"><?= htmlspecialchars($project['client_name'] ?? 'Direct Client') ?></span>
                    </div>
                    <div class="meta-item flex-fill">
                        <span class="meta-label">Category</span>
                        <span class="meta-value"><?= ucwords(str_replace('-', ' ', htmlspecialchars($project['category']))) ?></span>
                    </div>
                    <div class="meta-item flex-fill">
                        <span class="meta-label">Year</span>
                        <span class="meta-value"><?= date('Y', strtotime($project['start_date'] ?? 'now')) ?></span>
                    </div>
                    <div class="meta-item flex-fill">
                        <span class="meta-label">Status</span>
                        <span class="meta-value" style="color: <?= $project['status'] === 'Completed' ? '#10b981;' : '#d97706;' ?>"><?= htmlspecialchars($project['status']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Showcase Section -->
<section class="showcase-section">
    <div class="container">
        <div class="showcase-grid reveal">
            <!-- Mockup Showcase Image -->
            <div class="showcase-image-wrapper">
                <img src="<?= BASE_URL ?>admin/<?= htmlspecialchars($project['thumbnail']) ?>" class="showcase-img" alt="<?= $project_title ?>">
            </div>
            <!-- Introduction / Brief Case Study details -->
            <div class="showcase-content">
                <span class="meta-label" style="margin-bottom:12px;">The Project Brief</span>
                <h2 style="font-family: var(--font-head); font-size: 32px; color: #024442; margin-bottom: 20px; font-weight: 500;">Overview & Objective</h2>
                <div style="font-size: 16px; line-height: 1.8; color: #475569;">
                    <?= !empty($project['description']) ? nl2br(htmlspecialchars(strip_tags($project['description']))) : 'This case study showcases the custom creative strategy designed and deployed by Graphicafix to achieve outstanding results for ' . htmlspecialchars($project['client_name'] ?? $project_title) . '.' ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Description & Details Section -->
<?php if (!empty($project['public_description'])): ?>
<div class="container">
    <div class="description-card reveal">
        <span class="meta-label text-center mb-3">Case Study Details</span>
        <div class="desc-text" id="descText">
            <?= $project['public_description'] ?>
        </div>
        <button id="readMoreBtn">Show Details</button>
    </div>
</div>
<?php endif; ?>

<!-- Masonry Showcase Gallery -->
<?php if (!empty($gallery_images)): ?>
<section class="masonry-gallery">
    <div class="container">
        <div class="row align-items-center">
            <?php 
            foreach($gallery_images as $index => $img): 
                // Dynamic layout pattern
                $pattern = $index % 4; 
                
                if ($pattern == 0): // Full Width
                    echo '<div class="col-12 gallery-item reveal" onclick="openLightbox(\'' . BASE_URL . 'admin/' . htmlspecialchars($img) . '\')"><div class="gallery-img-wrapper"><img src="' . BASE_URL . 'admin/' . htmlspecialchars($img) . '" class="gallery-img"></div></div>';
                
                elseif ($pattern == 1): // Left Side Offset
                    echo '<div class="col-md-7 gallery-item reveal" onclick="openLightbox(\'' . BASE_URL . 'admin/' . htmlspecialchars($img) . '\')"><div class="gallery-img-wrapper"><img src="' . BASE_URL . 'admin/' . htmlspecialchars($img) . '" class="gallery-img"></div></div>';
                
                elseif ($pattern == 2): // Right Side Offset Small
                    echo '<div class="col-md-4 offset-md-1 gallery-item reveal offset-lg-down" onclick="openLightbox(\'' . BASE_URL . 'admin/' . htmlspecialchars($img) . '\')"><div class="gallery-img-wrapper"><img src="' . BASE_URL . 'admin/' . htmlspecialchars($img) . '" class="gallery-img"></div></div>';
                
                elseif ($pattern == 3): // Narrow Center
                    echo '<div class="col-md-8 offset-md-2 gallery-item reveal" onclick="openLightbox(\'' . BASE_URL . 'admin/' . htmlspecialchars($img) . '\')"><div class="gallery-img-wrapper"><img src="' . BASE_URL . 'admin/' . htmlspecialchars($img) . '" class="gallery-img"></div></div>';
                
                endif;
            endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Social Share Section -->
<section style="padding: 60px 0; background: #ffffff; border-top: 1px solid #e2e8f0; text-align: center;">
    <div class="container">
        <p class="meta-label mb-3" style="color: #64748b;">Share This Case Study</p>
        <div class="d-flex justify-content-center gap-2">
            <!-- X (formerly Twitter) -->
            <a href="https://twitter.com/intent/tweet?url=<?= urlencode($current_url) ?>" class="share-link" target="_blank" title="Share on X">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.253 5.622 5.911-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                </svg>
            </a>
            <!-- Facebook -->
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($current_url) ?>" class="share-link" target="_blank" title="Share on Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <!-- LinkedIn -->
            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($current_url) ?>" class="share-link" target="_blank" title="Share on LinkedIn">
                <i class="fab fa-linkedin-in"></i>
            </a>
            <!-- Email -->
            <a href="mailto:?body=<?= urlencode($current_url) ?>" class="share-link" title="Share via Email">
                <i class="fas fa-envelope"></i>
            </a>
            <!-- Copy Link -->
            <button class="share-link" id="copyLinkBtn" onclick="copyLink()" title="Copy link">
                <i class="fas fa-link" id="copyIcon"></i>
            </button>
        </div>
    </div>
</section>

<!-- Next Case Study Looping Footer -->
<?php if ($next_project): ?>
<section class="next-case-study">
    <div class="container">
        <div class="next-link-title">Next Project</div>
        <a href="<?= BASE_URL ?>projects/project_details.php?id=<?= $next_project['id'] ?>" class="next-link-name">
            <?= htmlspecialchars($next_project['project_name']) ?> &rarr;
        </a>
    </div>
</section>
<?php endif; ?>

<!-- Case Study Image Lightbox Modal Overlay -->
<div id="lightboxOverlay" onclick="if(event.target===this)closeLightbox()" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(2, 68, 66, 0.95); align-items:center; justify-content:center; cursor:pointer;">
    <span onclick="closeLightbox()" style="position:absolute; right:30px; top:30px; font-size:40px; color:#ffffff; font-weight:bold; cursor:pointer; z-index:10001; transition:0.3s;">&times;</span>
    <div style="position:relative; max-width:90%; max-height:85%;">
        <div id="lightboxSpinner" style="display:flex; align-items:center; justify-content:center; position:absolute; left:0; right:0; top:0; bottom:0;">
            <i class="fas fa-spinner fa-spin" style="font-size: 40px; color: #ffffff;"></i>
        </div>
        <img id="lightboxImg" src="" alt="Case Study Image" style="max-width:100%; max-height:80vh; object-fit:contain; border-radius:12px; display:none; border:2px solid rgba(255,255,255,0.1);" onload="document.getElementById('lightboxSpinner').style.display='none'; this.style.display='block';">
    </div>
</div>

<script>
// Copy Case Study Link
function copyLink() {
    navigator.clipboard.writeText('<?= addslashes($current_url) ?>').then(function() {
        const icon = document.getElementById('copyIcon');
        const btn  = document.getElementById('copyLinkBtn');
        icon.className = 'fas fa-check';
        btn.style.color = '#B6F763';
        btn.style.background = '#024442';
        setTimeout(() => {
            icon.className = 'fas fa-link';
            btn.style.color = '';
            btn.style.background = '';
        }, 2000);
    });
}

// Lightbox triggers
function openLightbox(src) {
    document.getElementById('lightboxImg').style.display = 'none';
    document.getElementById('lightboxSpinner').style.display = 'flex';
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxOverlay').style.display = 'flex';
}

// Close lightbox on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLightbox();
    }
});

function closeLightbox() {
    document.getElementById('lightboxOverlay').style.display = 'none';
}

// Read More logic
const descText = document.getElementById('descText');
const readBtn = document.getElementById('readMoreBtn');
if (descText && readBtn) {
    if (descText.scrollHeight <= 280) {
        readBtn.style.display = 'none';
    }
    readBtn.addEventListener('click', () => {
        descText.classList.toggle('expanded');
        readBtn.innerHTML = descText.classList.contains('expanded') ? 'Show Less' : 'Show Details';
    });
}

// Intersection Observer for scroll animations
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.05 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>