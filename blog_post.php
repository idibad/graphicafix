<?php 
include 'header.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    echo "<script>window.location.href='blogs.php';</script>";
    exit;
}

$stmt = $conn->prepare("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON u.user_id = b.author_id WHERE b.slug = ? AND b.status = 'published'");
if ($stmt) {
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $blog = $stmt->get_result()->fetch_assoc();
} else {
    $blog = null;
}

if (!$blog) {
    echo "<div style='text-align:center; padding:100px 0; font-size:24px; color:#666;'>Blog post not found.</div>";
    include 'footer.php';
    exit;
}

// Increment views
$update_views = $conn->prepare("UPDATE blogs SET views = views + 1 WHERE id = ?");
if ($update_views) {
    $update_views->bind_param("i", $blog['id']);
    $update_views->execute();
}

$img_src = !empty($blog['image']) ? htmlspecialchars($blog['image']) : "https://placehold.co/1200x600/024442/ffffff?text=" . urlencode($blog['category']);

// Related posts
$related_stmt = $conn->prepare("SELECT title, slug, category, created_at FROM blogs WHERE category = ? AND id != ? AND status = 'published' ORDER BY created_at DESC LIMIT 3");
$related = [];
if ($related_stmt) {
    $related_stmt->bind_param("si", $blog['category'], $blog['id']);
    $related_stmt->execute();
    $related_result = $related_stmt->get_result();
    while ($row = $related_result->fetch_assoc()) {
        $related[] = $row;
    }
}
?>

<style>
:root {
    --primary: #024442;
    --accent: #e8c97a;
}

/* ── Post Hero ─────────────────────────────────────────────── */
.post-hero {
    background: var(--primary);
    padding: clamp(3rem, 7vw, 5rem) 0 0;
    position: relative;
    overflow: hidden;
}

.post-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
    background-size: 48px 48px;
    pointer-events: none;
}

.post-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: rgba(255,255,255,.5);
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.post-breadcrumb a {
    color: rgba(255,255,255,.55);
    text-decoration: none;
    transition: color .2s;
}

.post-breadcrumb a:hover { color: var(--accent); }
.post-breadcrumb span   { color: rgba(255,255,255,.25); }

.post-category-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: var(--primary);
    background: var(--accent);
    padding: .3rem .9rem;
    border-radius: 100px;
    margin-bottom: 1rem;
}

.post-hero-title {
    font-size: clamp(1.6rem, 4.5vw, 2.8rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    max-width: 780px;
    margin-bottom: 1.25rem;
}

.post-hero-meta {
    display: flex;
    align-items: center;
    gap: 18px;
    font-size: 13.5px;
    color: rgba(255,255,255,.6);
    flex-wrap: wrap;
    padding-bottom: clamp(1.5rem, 4vw, 2.5rem);
}

.post-hero-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.post-hero-meta .divider {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(255,255,255,.25);
}

/* Thumbnail sits at the bottom of hero, overlapping into content */
.post-thumbnail-wrap {
    position: relative;
    margin-top: 0;
}

.post-thumbnail-wrap img {
    width: 100%;
    max-height: 480px;
    object-fit: cover;
    border-radius: 14px 14px 0 0;
    display: block;
}

/* ── Main layout ───────────────────────────────────────────── */
.post-body-section {
    background: #f9f9f9;
    padding: clamp(2.5rem, 6vw, 4rem) 0;
}

/* ── Article content ───────────────────────────────────────── */
.post-content-card {
    background: #fff;
    border-radius: 0 0 14px 14px;
    border: 1px solid #ebebeb;
    border-top: none;
    padding: clamp(1.75rem, 5vw, 3rem);
    margin-bottom: 24px;
}

.post-content-card h2 {
    font-size: clamp(1.15rem, 2.5vw, 1.45rem);
    font-weight: 800;
    color: #024442;
    margin: 2rem 0 .85rem;
    padding-left: 14px;
    border-left: 3px solid #e8c97a;
}

.post-content-card h2:first-child { margin-top: 0; }

.post-content-card p {
    font-size: 16px;
    color: #444;
    line-height: 1.8;
    margin-bottom: 1.15rem;
}

.post-content-card ul,
.post-content-card ol {
    padding-left: 1.4rem;
    margin-bottom: 1.25rem;
}

.post-content-card li {
    font-size: 15.5px;
    color: #444;
    line-height: 1.75;
    margin-bottom: .4rem;
}

.post-content-card blockquote {
    background: #f0f5f4;
    border-left: 4px solid #024442;
    border-radius: 0 8px 8px 0;
    padding: 18px 22px;
    margin: 1.75rem 0;
    font-size: 16px;
    font-style: italic;
    color: #333;
    line-height: 1.7;
}

.post-content-card img {
    width: 100%;
    border-radius: 10px;
    margin: 1.5rem 0;
    display: block;
}

/* ── Tags & Share ──────────────────────────────────────────── */
.post-footer-card {
    background: #fff;
    border: 1px solid #ebebeb;
    border-radius: 12px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.post-tags {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.post-tag-label {
    font-size: 12px;
    font-weight: 700;
    color: #888;
    text-transform: uppercase;
    letter-spacing: .1em;
}

.post-tag {
    padding: 5px 13px;
    border: 1px solid #e0e0e0;
    border-radius: 100px;
    font-size: 12px;
    color: #555;
    text-decoration: none;
    transition: all .2s;
}

.post-tag:hover { background: #024442; color: #fff; border-color: #024442; }

.post-share {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.post-share-label {
    font-size: 12px;
    font-weight: 700;
    color: #888;
    text-transform: uppercase;
    letter-spacing: .1em;
}

.share-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    cursor: pointer;
    text-decoration: none;
    transition: all .2s;
    color: #555;
}

.share-btn:hover { background: #024442; color: #fff; border-color: #024442; }

/* ── Author card ───────────────────────────────────────────── */
.post-author-card {
    background: #fff;
    border: 1px solid #ebebeb;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    gap: 18px;
    align-items: flex-start;
    margin-bottom: 24px;
}

.post-author-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #024442;
    color: #fff;
    font-size: 24px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.post-author-info h4 {
    font-size: 15px;
    font-weight: 800;
    color: #111;
    margin-bottom: 3px;
}

.post-author-role {
    font-size: 12px;
    color: #024442;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .1em;
    margin-bottom: 8px;
}

.post-author-bio {
    font-size: 14px;
    color: #666;
    line-height: 1.65;
    margin: 0;
}

/* ── Navigation (prev/next) ────────────────────────────────── */
.post-nav {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 575px) { .post-nav { grid-template-columns: 1fr; } }

.post-nav-item {
    background: #fff;
    border: 1px solid #ebebeb;
    border-radius: 12px;
    padding: 18px 20px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: box-shadow .2s, transform .2s, border-color .2s;
}

.post-nav-item:hover {
    box-shadow: 0 6px 24px rgba(2,68,66,.1);
    transform: translateY(-2px);
    border-color: #024442;
}

.post-nav-item.next { text-align: right; }

.post-nav-direction {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .15em;
    color: #aaa;
}

.post-nav-title {
    font-size: 14px;
    font-weight: 700;
    color: #024442;
    line-height: 1.4;
}

/* ── Related posts ─────────────────────────────────────────── */
.post-related {
    margin-bottom: 0;
}

.post-section-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .18em;
    text-transform: uppercase;
    color: #024442;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.post-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}

.related-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

@media (max-width: 767px) { .related-grid { grid-template-columns: 1fr; } }
@media (max-width: 991px) and (min-width: 768px) { .related-grid { grid-template-columns: repeat(2, 1fr); } }

.related-card {
    background: #fff;
    border: 1px solid #ebebeb;
    border-radius: 10px;
    overflow: hidden;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    transition: box-shadow .2s, transform .2s;
}

.related-card:hover {
    box-shadow: 0 6px 24px rgba(2,68,66,.1);
    transform: translateY(-3px);
}

.related-card-img {
    aspect-ratio: 16/9;
    overflow: hidden;
}

.related-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .35s;
}

.related-card:hover .related-card-img img { transform: scale(1.05); }

.related-card-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.related-card-cat {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #024442;
}

.related-card-title {
    font-size: 14px;
    font-weight: 700;
    color: #111;
    line-height: 1.4;
    margin: 0;
}

.related-card-meta {
    font-size: 12px;
    color: #aaa;
    margin-top: auto;
    padding-top: 8px;
}

/* ── Sidebar ───────────────────────────────────────────────── */
.sidebar-widget {
    background: #fff;
    border: 1px solid #ebebeb;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 24px;
}

.sidebar-widget:last-child { margin-bottom: 0; }

.sidebar-widget-title {
    font-size: 13px;
    font-weight: 800;
    color: #024442;
    text-transform: uppercase;
    letter-spacing: .12em;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e8c97a;
    display: inline-block;
}

/* Table of contents */
.toc-list {
    list-style: none;
    padding: 0;
    margin: 0;
    counter-reset: toc;
}

.toc-list li {
    counter-increment: toc;
    padding: 8px 0;
    border-bottom: 1px solid #f5f5f5;
    font-size: 13.5px;
}

.toc-list li:last-child { border-bottom: none; }

.toc-list a {
    color: #444;
    text-decoration: none;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    transition: color .2s;
    line-height: 1.4;
}

.toc-list a::before {
    content: counter(toc) ".";
    color: #024442;
    font-weight: 700;
    font-size: 12px;
    flex-shrink: 0;
    margin-top: 1px;
}

.toc-list a:hover { color: #024442; }

/* Sidebar post list */
.sidebar-post {
    display: flex;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
    text-decoration: none;
}

.sidebar-post:last-child { border-bottom: none; }

.sidebar-post-img {
    width: 56px;
    height: 56px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
}

.sidebar-post-info { display: flex; flex-direction: column; gap: 4px; }

.sidebar-post-title {
    font-size: 13px;
    font-weight: 600;
    color: #111;
    line-height: 1.4;
    transition: color .2s;
}

.sidebar-post:hover .sidebar-post-title { color: #024442; }
.sidebar-post-date { font-size: 11px; color: #aaa; }

/* CTA widget */
.sidebar-cta-widget {
    background: #024442;
    border-color: #024442;
    text-align: center;
}

.sidebar-cta-widget .sidebar-widget-title { color: #e8c97a; border-bottom-color: rgba(232,201,122,.4); }

.sidebar-cta-widget p {
    font-size: 13.5px;
    color: rgba(255,255,255,.7);
    line-height: 1.6;
    margin-bottom: 16px;
}

.sidebar-cta-btn {
    display: inline-block;
    width: 100%;
    padding: 11px;
    background: #e8c97a;
    color: #024442;
    font-size: 14px;
    font-weight: 700;
    border-radius: 7px;
    text-decoration: none;
    text-align: center;
    transition: background .25s;
}

.sidebar-cta-btn:hover { background: #f0d98a; color: #024442; }

/* Tags */
.sidebar-tags { display: flex; flex-wrap: wrap; gap: 8px; }

.sidebar-tag {
    padding: 5px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 100px;
    font-size: 12px;
    color: #555;
    text-decoration: none;
    transition: all .2s;
}

.sidebar-tag:hover { background: #024442; color: #fff; border-color: #024442; }

/* ── Progress bar ──────────────────────────────────────────── */
.read-progress {
    position: fixed;
    top: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(90deg, #024442, #e8c97a);
    z-index: 9999;
    width: 0%;
    transition: width .1s linear;
}
</style>

<!-- Reading progress bar -->
<div class="read-progress" id="readProgress"></div>

<!-- ── Post Hero ──────────────────────────────────────────────── -->
<section class="post-hero">
    <div class="container position-relative">
        <div class="post-breadcrumb">
            <a href="index.php">Home</a>
            <span>/</span>
            <a href="blog.php">Blog</a>
            <span>/</span>
            <span style="color:rgba(255,255,255,.7);"><?= htmlspecialchars($post['category']) ?></span>
        </div>

        <span class="post-category-badge"><?= htmlspecialchars($post['category']) ?></span>

        <h1 class="post-hero-title"><?= htmlspecialchars($post['title']) ?></h1>

        <div class="post-hero-meta">
            <span>✍️ <?= htmlspecialchars($blog['author_name'] ? $blog['author_name'] : 'Admin') ?></span>
            <div class="divider"></div>
            <span>📅 <?= date('M d, Y', strtotime($blog['created_at'])) ?></span>
            <div class="divider"></div>
            <span>👁 <?= $blog['views'] ?> views</span>
        </div>

        <div class="post-thumbnail-wrap">
            <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($blog['title']) ?>">
        </div>
    </div>
</section>

<!-- ── Body ──────────────────────────────────────────────────── -->
<section class="post-body-section">
    <div class="container">
        <div class="row g-4">

            <!-- Main content -->
            <div class="col-12 col-lg-8">

                <!-- Article -->
                <div class="post-content-card" id="postContent">
                    <?= $blog['content'] ?>
                </div>

                <!-- Tags & Share -->
                <div class="post-footer-card">
                    <div class="post-tags">
                        <span class="post-tag-label">Category:</span>
                        <a href="blogs.php?category=<?= urlencode(strtolower(str_replace(' ', '-', $blog['category']))) ?>" class="post-tag"><?= htmlspecialchars($blog['category']) ?></a>
                    </div>
                    <div class="post-share">
                        <span class="post-share-label">Share:</span>
                        <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://graphicafix.com/blog_post.php?slug=' . $blog['slug']) ?>" target="_blank" title="Share on Facebook">𝑓</a>
                        <a class="share-btn" href="https://twitter.com/intent/tweet?url=<?= urlencode('https://graphicafix.com/blog_post.php?slug=' . $blog['slug']) ?>&text=<?= urlencode($blog['title']) ?>" target="_blank" title="Share on Twitter">𝕏</a>
                        <a class="share-btn" href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode('https://graphicafix.com/blog_post.php?slug=' . $blog['slug']) ?>" target="_blank" title="Share on LinkedIn">in</a>
                        <button class="share-btn" onclick="copyLink()" title="Copy link">🔗</button>
                    </div>
                </div>

                <!-- Author -->
                <div class="post-author-card">
                    <div class="post-author-avatar"><?= strtoupper(substr($blog['author_name'] ? $blog['author_name'] : 'A', 0, 1)) ?></div>
                    <div class="post-author-info">
                        <h4><?= htmlspecialchars($blog['author_name'] ? $blog['author_name'] : 'Graphicafix Admin') ?></h4>
                        <div class="post-author-role">Creative Design Agency</div>
                        <p class="post-author-bio">Graphicafix is a full-service creative agency specializing in branding, graphic design, web development, and digital marketing. We help businesses build meaningful identities and stand out in a crowded market.</p>
                    </div>
                </div>

                <!-- Prev / Next -->
                <?php
                // Fetch previous post
                $prev_stmt = $conn->prepare("SELECT title, slug FROM blogs WHERE created_at < ? AND status = 'published' ORDER BY created_at DESC LIMIT 1");
                $prev_stmt->bind_param("s", $blog['created_at']);
                $prev_stmt->execute();
                $prev_post = $prev_stmt->get_result()->fetch_assoc();

                // Fetch next post
                $next_stmt = $conn->prepare("SELECT title, slug FROM blogs WHERE created_at > ? AND status = 'published' ORDER BY created_at ASC LIMIT 1");
                $next_stmt->bind_param("s", $blog['created_at']);
                $next_stmt->execute();
                $next_post = $next_stmt->get_result()->fetch_assoc();
                ?>
                <div class="post-nav">
                    <?php if($prev_post): ?>
                    <a href="blog_post.php?slug=<?= $prev_post['slug'] ?>" class="post-nav-item prev">
                        <span class="post-nav-direction">← Previous Post</span>
                        <span class="post-nav-title"><?= htmlspecialchars($prev_post['title']) ?></span>
                    </a>
                    <?php else: ?>
                    <div class="post-nav-item prev" style="opacity:0.5; pointer-events:none;">
                        <span class="post-nav-direction">← Previous Post</span>
                        <span class="post-nav-title">None</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($next_post): ?>
                    <a href="blog_post.php?slug=<?= $next_post['slug'] ?>" class="post-nav-item next">
                        <span class="post-nav-direction">Next Post →</span>
                        <span class="post-nav-title"><?= htmlspecialchars($next_post['title']) ?></span>
                    </a>
                    <?php else: ?>
                    <div class="post-nav-item next" style="opacity:0.5; pointer-events:none;">
                        <span class="post-nav-direction">Next Post →</span>
                        <span class="post-nav-title">None</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Related posts -->
                <div class="post-related">
                    <div class="post-section-label">Related Articles</div>
                    <?php if(count($related) > 0): ?>
                    <div class="related-grid">
                        <?php foreach ($related as $rel): ?>
                        <a href="blog_post.php?slug=<?= $rel['slug'] ?>" class="related-card">
                            <div class="related-card-img">
                                <img src="https://placehold.co/600x340/024442/ffffff?text=<?= urlencode($rel['category']) ?>"
                                     alt="<?= htmlspecialchars($rel['title']) ?>" loading="lazy">
                            </div>
                            <div class="related-card-body">
                                <div class="related-card-cat"><?= htmlspecialchars($rel['category']) ?></div>
                                <div class="related-card-title"><?= htmlspecialchars($rel['title']) ?></div>
                                <div class="related-card-meta">📅 <?= date('M d, Y', strtotime($rel['created_at'])) ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <p style="color:#888; font-size:14px;">No related articles found.</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Sidebar -->
            <div class="col-12 col-lg-4">

                <!-- Table of Contents -->
                <div class="sidebar-widget">
                    <div class="sidebar-widget-title">Table of Contents</div>
                    <ul class="toc-list" id="dynamicToc">
                        <!-- Filled by JS -->
                    </ul>
                </div>

                <!-- CTA -->
                <div class="sidebar-widget sidebar-cta-widget">
                    <div class="sidebar-widget-title">Work With Us</div>
                    <p>Ready to build a brand identity that truly stands out? Let's create something extraordinary together.</p>
                    <a href="index.php#request" class="sidebar-cta-btn">✨ Request a Project</a>
                </div>

                <!-- Popular posts -->
                <div class="sidebar-widget">
                    <div class="sidebar-widget-title">Popular Posts</div>
                    <?php
                    $popular_stmt = $conn->query("SELECT id, title, slug, image, created_at, category FROM blogs WHERE status = 'published' ORDER BY views DESC LIMIT 3");
                    if ($popular_stmt->num_rows > 0):
                        while ($pop = $popular_stmt->fetch_assoc()):
                            $pop_img = !empty($pop['image']) ? htmlspecialchars($pop['image']) : "https://placehold.co/56x56/024442/ffffff?text=" . urlencode(substr($pop['category'], 0, 1));
                    ?>
                    <a href="blog_post.php?slug=<?= $pop['slug'] ?>" class="sidebar-post">
                        <img src="<?= $pop_img ?>" class="sidebar-post-img" alt="Post">
                        <div class="sidebar-post-info">
                            <div class="sidebar-post-title"><?= htmlspecialchars($pop['title']) ?></div>
                            <div class="sidebar-post-date">📅 <?= date('M d, Y', strtotime($pop['created_at'])) ?></div>
                        </div>
                    </a>
                    <?php 
                        endwhile; 
                    else:
                        echo "<p style='color:#888; font-size:14px;'>No popular posts yet.</p>";
                    endif; 
                    ?>
                </div>

                <!-- Tags -->
                <div class="sidebar-widget">
                    <div class="sidebar-widget-title">Tags</div>
                    <div class="sidebar-tags">
                        <a href="#" class="sidebar-tag">Logo Design</a>
                        <a href="#" class="sidebar-tag">Branding</a>
                        <a href="#" class="sidebar-tag">Typography</a>
                        <a href="#" class="sidebar-tag">Color Theory</a>
                        <a href="#" class="sidebar-tag">Web Design</a>
                        <a href="#" class="sidebar-tag">UI/UX</a>
                        <a href="#" class="sidebar-tag">Packaging</a>
                        <a href="#" class="sidebar-tag">Marketing</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<script>
// ── Reading progress bar ──────────────────────────────────────
window.addEventListener('scroll', function () {
    const content  = document.getElementById('postContent');
    const progress = document.getElementById('readProgress');
    if (!content || !progress) return;

    const contentTop    = content.getBoundingClientRect().top + window.scrollY;
    const contentHeight = content.offsetHeight;
    const scrolled      = window.scrollY - contentTop;
    const pct           = Math.min(100, Math.max(0, (scrolled / contentHeight) * 100));
    progress.style.width = pct + '%';
});

// ── Dynamic Table of Contents ─────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const headings = document.querySelectorAll('#postContent h2');
    const tocList = document.getElementById('dynamicToc');
    
    if (headings.length > 0 && tocList) {
        headings.forEach((heading, index) => {
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#';
            a.textContent = heading.textContent;
            a.onclick = function(e) {
                e.preventDefault();
                heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };
            li.appendChild(a);
            tocList.appendChild(li);
        });
    } else if (tocList) {
        tocList.innerHTML = '<li style="color:#888;">No headings found.</li>';
    }
});

// ── Table of contents scroll ──────────────────────────────────
function scrollToHeading(index) {
    const headings = document.querySelectorAll('#postContent h2');
    if (headings[index]) {
        headings[index].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    return false;
}

// ── Copy link ─────────────────────────────────────────────────
function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert('Link copied to clipboard!');
    });
}
</script>

<?php include 'footer.php'; ?>