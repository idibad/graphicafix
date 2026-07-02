<?php include 'templates/header.php'; ?>

<style>
:root {
    --primary: #024442;
    --accent: #e8c97a;
}

/* ── Hero ──────────────────────────────────────────────────── */
.blog-hero {
    background: var(--primary);
    padding: clamp(3.5rem, 8vw, 6rem) 0 clamp(2.5rem, 6vw, 4rem);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.blog-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
    background-size: 48px 48px;
    pointer-events: none;
}

.blog-hero-label {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .2em;
    text-transform: uppercase;
    color: var(--accent);
    border: 1px solid rgba(182, 247, 99, 0.35); /* Using accent with opacity */
    border-radius: 100px;
    padding: .28rem .9rem;
    margin-bottom: 1.1rem;
}

.blog-hero h1 {
    font-size: clamp(2rem, 5vw, 3.2rem);
    font-weight: 800;
    color: #fff;
    margin-bottom: .75rem;
    line-height: 1.15;
}

.blog-hero h1 span { color: var(--accent); }

.blog-hero p {
    font-size: clamp(.95rem, 2vw, 1.1rem);
    color: rgba(255,255,255,.65);
    max-width: 520px;
    margin: 0 auto;
    line-height: 1.65;
}

/* ── Search & Filter bar ───────────────────────────────────── */
.blog-toolbar {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid #ebebeb;
    padding: 1.1rem 0;
    position: sticky;
    top: 0;
    z-index: 100;
}

.blog-toolbar-inner {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.blog-search {
    flex: 1;
    min-width: 200px;
    position: relative;
}

.blog-search input {
    width: 100%;
    padding: 10px 16px 10px 40px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    transition: all .3s;
    background: #f9f9f9;
}

.blog-search input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.1); }

.blog-search-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
    font-size: 15px;
    pointer-events: none;
}

.blog-filter-btns {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
    -ms-overflow-style: none;  
    scrollbar-width: none;  
}

.blog-filter-btns::-webkit-scrollbar { display: none; }

.blog-filter-btn {
    background: transparent;
    border: 1px solid #e0e0e0;
    padding: 8px 16px;
    border-radius: 100px;
    font-size: 13px;
    font-weight: 600;
    color: #555;
    white-space: nowrap;
    cursor: pointer;
    transition: all .3s;
}

.blog-filter-btn:hover {
    background: #f5f5f5;
    border-color: #d0d0d0;
}

.blog-filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    box-shadow: 0 4px 10px rgba(2, 68, 66, 0.2);
}

/* ── Layout & Cards ────────────────────────────────────────── */
.blog-main {
    padding: 3rem 0 5rem;
    background: #fafafa;
}

.blog-section-label {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1.5rem;
    padding-bottom: .5rem;
    border-bottom: 2px solid #eaeaea;
    position: relative;
}

.blog-section-label::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 60px;
    height: 2px;
    background: var(--accent);
}

/* Featured Card */
.blog-featured {
    margin-bottom: 3rem;
}

.blog-featured-card {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,.04);
    transition: transform .3s, box-shadow .3s;
    border: 1px solid #f0f0f0;
}

.blog-featured-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,.08);
}

.blog-featured-img {
    position: relative;
    overflow: hidden;
}

.blog-featured-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .6s ease;
}

.blog-featured-card:hover .blog-featured-img img {
    transform: scale(1.05);
}

.blog-featured-body {
    padding: clamp(1.5rem, 4vw, 2.5rem);
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 14px;
}

.blog-featured-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: #024442;
}

.blog-featured-label::before {
    content: '';
    display: inline-block;
    width: 18px;
    height: 2px;
    background: #e8c97a;
    border-radius: 2px;
}

.blog-featured-card h2 {
    font-size: clamp(1.2rem, 2.5vw, 1.65rem);
    font-weight: 800;
    color: #111;
    line-height: 1.3;
    margin: 0;
}

.blog-featured-card h2 a {
    color: inherit;
    text-decoration: none;
    transition: color .2s;
}

.blog-featured-card h2 a:hover { color: #024442; }

.blog-featured-excerpt {
    font-size: 15px;
    color: #666;
    line-height: 1.7;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.blog-featured-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 13px;
    color: #999;
    flex-wrap: wrap;
}

.blog-featured-meta span { display: flex; align-items: center; gap: 5px; }

.blog-read-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 22px;
    background: #024442;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    border-radius: 7px;
    text-decoration: none;
    width: fit-content;
    transition: background .25s, transform .2s;
}

.blog-read-more:hover { background: #01796f; transform: translateX(2px); color: #fff; }

/* ── Section label ─────────────────────────────────────────── */
.blog-section-label {
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

.blog-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}

/* ── Post grid ─────────────────────────────────────────────── */
.blog-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

@media (max-width: 991px) { .blog-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575px) { .blog-grid { grid-template-columns: 1fr; } }

/* ── Post card ─────────────────────────────────────────────── */
.blog-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #ebebeb;
    display: flex;
    flex-direction: column;
    transition: box-shadow .25s, transform .25s;
}

.blog-card:hover {
    box-shadow: 0 8px 32px rgba(2,68,66,.1);
    transform: translateY(-3px);
}

.blog-card-img {
    position: relative;
    overflow: hidden;
    aspect-ratio: 16/9;
}

.blog-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .4s ease;
}

.blog-card:hover .blog-card-img img { transform: scale(1.05); }

.blog-card-category {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #024442;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: 100px;
}

.blog-card-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
}

.blog-card-body h3 {
    font-size: 16px;
    font-weight: 700;
    color: #111;
    line-height: 1.4;
    margin: 0;
}

.blog-card-body h3 a {
    color: inherit;
    text-decoration: none;
    transition: color .2s;
}

.blog-card-body h3 a:hover { color: #024442; }

.blog-card-excerpt {
    font-size: 13.5px;
    color: #777;
    line-height: 1.65;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}

.blog-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-top: 1px solid #f0f0f0;
    font-size: 12px;
    color: #aaa;
    gap: 8px;
    flex-wrap: wrap;
}

.blog-card-author {
    display: flex;
    align-items: center;
    gap: 7px;
}

.blog-card-avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #024442;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.blog-card-read {
    font-size: 12px;
    color: #024442;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: gap .2s;
}

.blog-card-read:hover { gap: 7px; color: #024442; }

/* ── Sidebar ───────────────────────────────────────────────── */
.blog-sidebar {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.sidebar-widget {
    background: #fff;
    border: 1px solid #ebebeb;
    border-radius: 12px;
    padding: 22px;
}

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

/* Categories list */
.sidebar-categories { list-style: none; padding: 0; margin: 0; }
.sidebar-categories li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid #f5f5f5;
    font-size: 14px;
    color: #444;
    cursor: pointer;
    transition: color .2s;
}
.sidebar-categories li:last-child { border-bottom: none; }
.sidebar-categories li:hover { color: #024442; }
.sidebar-cat-count {
    background: #f0f5f4;
    color: #024442;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 100px;
}

/* Popular posts */
.sidebar-post {
    display: flex;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
    text-decoration: none;
}
.sidebar-post:last-child { border-bottom: none; }
.sidebar-post-img {
    width: 60px;
    height: 60px;
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

/* Tags */
.sidebar-tags { display: flex; flex-wrap: wrap; gap: 8px; }
.sidebar-tag {
    padding: 5px 13px;
    border: 1px solid #e0e0e0;
    border-radius: 100px;
    font-size: 12px;
    color: #555;
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
}
.sidebar-tag:hover { background: #024442; color: #fff; border-color: #024442; }

/* Newsletter widget */
.sidebar-newsletter p {
    font-size: 13.5px;
    color: #666;
    line-height: 1.6;
    margin-bottom: 14px;
}
.sidebar-newsletter input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 7px;
    font-size: 14px;
    outline: none;
    margin-bottom: 9px;
    transition: border-color .2s;
}
.sidebar-newsletter input:focus { border-color: #024442; }
.sidebar-newsletter-btn {
    width: 100%;
    padding: 10px;
    background: #024442;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    transition: background .25s;
}
.sidebar-newsletter-btn:hover { background: #01796f; }

/* ── Pagination ────────────────────────────────────────────── */
.blog-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: clamp(2rem, 5vw, 3rem);
    flex-wrap: wrap;
}

.page-btn {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    border: 1.5px solid #e0e0e0;
    background: #fff;
    font-size: 14px;
    font-weight: 600;
    color: #555;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s;
    text-decoration: none;
}

.page-btn:hover,
.page-btn.active {
    background: #024442;
    border-color: #024442;
    color: #fff;
}

.page-btn.disabled {
    opacity: .4;
    pointer-events: none;
}

/* ── CTA Banner ────────────────────────────────────────────── */
.blog-cta {
    background: #024442;
    padding: clamp(3rem, 7vw, 5rem) 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.blog-cta::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
    background-size: 48px 48px;
    pointer-events: none;
}

.blog-cta h2 {
    font-size: clamp(1.5rem, 4vw, 2.4rem);
    font-weight: 800;
    color: #fff;
    margin-bottom: .75rem;
}

.blog-cta h2 span { color: #e8c97a; }

.blog-cta p {
    color: rgba(255,255,255,.65);
    font-size: clamp(.95rem, 2vw, 1.05rem);
    margin-bottom: 1.75rem;
    max-width: 480px;
    margin-left: auto;
    margin-right: auto;
}

.blog-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 30px;
    background: #e8c97a;
    color: #024442;
    font-size: 15px;
    font-weight: 700;
    border-radius: 8px;
    text-decoration: none;
    transition: background .25s, transform .2s;
}

.blog-cta-btn:hover {
    background: #f0d98a;
    transform: translateY(-2px);
    color: #024442;
}

/* ── Responsive fixes ──────────────────────────────────────── */
@media (max-width: 991px) {
    .blog-featured-card {
        grid-template-columns: 1fr;
    }
    .blog-featured-img {
        aspect-ratio: 16/7;
    }
}

@media (max-width: 767px) {
    .blog-toolbar-inner {
        flex-direction: column;
        align-items: stretch;
    }
    .blog-filter-btns {
        justify-content: center;
    }
}
</style>

<!-- ── Hero ─────────────────────────────────────────────────── -->
<section class="blog-hero">
    <div class="container position-relative">
        <div class="blog-hero-label">Our Blog</div>
        <h1>Insights, Ideas & <span>Creative Tips</span></h1>
        <p>Stay updated with the latest trends in graphic design, branding, web development, and digital marketing.</p>
    </div>
</section>

<!-- ── Toolbar ───────────────────────────────────────────────── -->
<div class="blog-toolbar">
    <div class="container">
        <div class="blog-toolbar-inner">
            <div class="blog-search">
                <span class="blog-search-icon">🔍</span>
                <input type="text" id="blogSearch" placeholder="Search articles..." oninput="filterPosts()">
            </div>
            <div class="blog-filter-btns">
                <button class="blog-filter-btn active" onclick="filterCategory(this, 'all')">All</button>
                <button class="blog-filter-btn" onclick="filterCategory(this, 'branding')">Branding</button>
                <button class="blog-filter-btn" onclick="filterCategory(this, 'web design')">Web Design</button>
                <button class="blog-filter-btn" onclick="filterCategory(this, 'graphic design')">Graphic Design</button>
                <button class="blog-filter-btn" onclick="filterCategory(this, 'marketing')">Marketing</button>
                <button class="blog-filter-btn" onclick="filterCategory(this, 'tips')">Tips & Tricks</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Main ──────────────────────────────────────────────────── -->
<section class="blog-main">
    <div class="container">
        <div class="row g-4">

            <!-- Posts column -->
            <div class="col-12 col-lg-8">

                <!-- Featured post -->
                <div class="blog-featured">
                    <div class="blog-section-label">Featured Post</div>
                    <?php
                    $featured_stmt = $conn->query("SELECT * FROM blogs WHERE status = 'published' ORDER BY created_at DESC LIMIT 1");
                    if($featured_stmt && $featured_stmt->num_rows > 0):
                        $featured = $featured_stmt->fetch_assoc();
                        $featured_img = !empty($featured['image']) ? htmlspecialchars($featured['image']) : "https://placehold.co/700x420/024442/ffffff?text=" . urlencode($featured['category']);
                    ?>
                    <div class="blog-featured-card" data-category="<?= strtolower(str_replace(' ', '-', $featured['category'])) ?>">
                        <div class="blog-featured-img">
                            <img src="<?= $featured_img ?>" alt="<?= htmlspecialchars($featured['title']) ?>">
                        </div>
                        <div class="blog-featured-body">
                            <div class="blog-featured-label"><?= htmlspecialchars($featured['category']) ?></div>
                            <h2><a href="blog_post.php?slug=<?= $featured['slug'] ?>"><?= htmlspecialchars($featured['title']) ?></a></h2>
                            <p class="blog-featured-excerpt"><?= htmlspecialchars($featured['excerpt']) ?></p>
                            <div class="blog-featured-meta">
                                <span><i class="far fa-calendar-alt"></i> <?= date('F d, Y', strtotime($featured['created_at'])) ?></span>
                                <span><i class="far fa-eye"></i> <?= $featured['views'] ?> views</span>
                            </div>
                            <a href="blog_post.php?slug=<?= $featured['slug'] ?>" class="blog-read-more">Read Article →</a>
                        </div>
                    </div>
                    <?php else: ?>
                        <p style="color: #666;">No featured post available.</p>
                    <?php endif; ?>
                </div>

                <!-- All posts grid -->
                <div class="blog-section-label">Latest Articles</div>
                <div class="blog-grid" id="blogGrid">

                    <?php
                    $posts = [];
                    $stmt = $conn->query("SELECT b.*, u.name as author_name FROM blogs b LEFT JOIN users u ON u.user_id = b.author_id WHERE b.status = 'published' ORDER BY b.created_at DESC");
                    if ($stmt) {
                        while($row = $stmt->fetch_assoc()) {
                            $posts[] = [
                                'id' => $row['id'],
                                'slug' => $row['slug'],
                                'title' => $row['title'],
                                'excerpt' => $row['excerpt'],
                                'category' => $row['category'],
                                'cat_slug' => strtolower(str_replace(' ', '-', $row['category'])),
                                'date' => date('M d, Y', strtotime($row['created_at'])),
                                'author' => $row['author_name'] ? $row['author_name'] : 'Admin',
                                'image' => $row['image']
                            ];
                        }
                    } else {
                        // Error fallback if table doesn't exist yet
                        echo "<!-- DB Error: " . htmlspecialchars($conn->error) . " -->";
                    }
                    ?>

                    <?php foreach ($posts as $post):
                        $initial = strtoupper(substr($post['author'], 0, 1));
                        $img_src = !empty($post['image']) ? htmlspecialchars($post['image']) : "https://placehold.co/600x340/024442/ffffff?text=" . urlencode($post['category']);
                    ?>
                    <div class="blog-card" data-category="<?= htmlspecialchars($post['cat_slug']) ?>" data-title="<?= strtolower(htmlspecialchars($post['title'])) ?>">
                        <div class="blog-card-img">
                            <img src="<?= $img_src ?>"
                                 alt="<?= htmlspecialchars($post['title']) ?>"
                                 loading="lazy">
                            <span class="blog-card-category"><?= htmlspecialchars($post['category']) ?></span>
                        </div>
                        <div class="blog-card-body">
                            <h3><a href="blog_post.php?slug=<?= $post['slug'] ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                            <p class="blog-card-excerpt"><?= htmlspecialchars($post['excerpt']) ?></p>
                        </div>
                        <div class="blog-card-footer">
                            <div class="blog-card-author">
                                <div class="blog-card-avatar"><?= $initial ?></div>
                                <span><?= htmlspecialchars($post['author']) ?> · <?= $post['date'] ?></span>
                            </div>
                            <a href="blog_post.php?slug=<?= $post['slug'] ?>" class="blog-card-read">
                                Read <span>→</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>

                <!-- No results message -->
                <div id="blogNoResults" style="display:none;text-align:center;padding:3rem 0;color:#999;font-size:15px;">
                    <i class="far fa-frown"></i> No articles found. Try a different search or category.
                </div>

                <!-- Pagination -->
                <div class="blog-pagination">
                    <a href="#" class="page-btn disabled">←</a>
                    <a href="#" class="page-btn active">1</a>
                    <a href="#" class="page-btn">2</a>
                    <a href="#" class="page-btn">3</a>
                    <span style="color:#ccc;font-size:18px;">···</span>
                    <a href="#" class="page-btn">8</a>
                    <a href="#" class="page-btn">→</a>
                </div>

            </div>

            <!-- Sidebar -->
            <div class="col-12 col-lg-4">
                <div class="blog-sidebar">

                    <!-- Categories -->
                    <div class="sidebar-widget">
                        <div class="sidebar-widget-title">Categories</div>
                        <ul class="sidebar-categories">
                            <li onclick="filterCategory(null, 'branding')">
                                <i class="fas fa-paint-brush"></i> Branding <span class="sidebar-cat-count">12</span>
                            </li>
                            <li onclick="filterCategory(null, 'web design')">
                                <i class="fas fa-laptop-code"></i> Web Design <span class="sidebar-cat-count">8</span>
                            </li>
                            <li onclick="filterCategory(null, 'graphic design')">
                                <i class="fas fa-pencil-alt"></i> Graphic Design <span class="sidebar-cat-count">10</span>
                            </li>
                            <li onclick="filterCategory(null, 'marketing')">
                                <i class="fas fa-bullhorn"></i> Marketing <span class="sidebar-cat-count">6</span>
                            </li>
                            <li onclick="filterCategory(null, 'tips')">
                                <i class="fas fa-lightbulb"></i> Tips & Tricks <span class="sidebar-cat-count">9</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Popular posts -->
                    <div class="sidebar-widget">
                        <div class="sidebar-widget-title">Popular Posts</div>
                        <?php
                        $popular_stmt = $conn->query("SELECT id, title, slug, image, created_at, category FROM blogs WHERE status = 'published' ORDER BY views DESC LIMIT 3");
                        if ($popular_stmt && $popular_stmt->num_rows > 0):
                            while ($pop = $popular_stmt->fetch_assoc()):
                                $pop_img = !empty($pop['image']) ? htmlspecialchars($pop['image']) : "https://placehold.co/60x60/024442/ffffff?text=" . urlencode(substr($pop['category'], 0, 1));
                        ?>
                        <a href="blog_post.php?slug=<?= $pop['slug'] ?>" class="sidebar-post">
                            <img src="<?= $pop_img ?>" class="sidebar-post-img" alt="Post">
                            <div class="sidebar-post-info">
                                <div class="sidebar-post-title"><?= htmlspecialchars($pop['title']) ?></div>
                                <div class="sidebar-post-date"><i class="far fa-calendar-alt"></i> <?= date('M d, Y', strtotime($pop['created_at'])) ?></div>
                            </div>
                        </a>
                        <?php 
                            endwhile; 
                        else:
                            echo "<p style='color:#888; font-size:14px;'>No popular posts yet.</p>";
                        endif; 
                        ?>
                    </div>

                    <!-- Newsletter -->
                    <div class="sidebar-widget sidebar-newsletter">
                        <div class="sidebar-widget-title">Newsletter</div>
                        <p>Get the latest design tips and creative insights delivered to your inbox every week.</p>
                        <input type="text" placeholder="Your name">
                        <input type="email" placeholder="Your email address">
                        <button class="sidebar-newsletter-btn"><i class="far fa-envelope"></i> Subscribe Now</button>
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
                            <a href="#" class="sidebar-tag">Social Media</a>
                            <a href="#" class="sidebar-tag">SEO</a>
                            <a href="#" class="sidebar-tag">Marketing</a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── CTA Banner ─────────────────────────────────────────────── -->
<section class="blog-cta">
    <div class="container position-relative">
        <h2>Ready to Transform Your <span>Brand?</span></h2>
        <p>Let's work together to create something extraordinary. Get a free quote today.</p>
        <a href="index.php#request" class="blog-cta-btn"><i class="fas fa-magic"></i> Request a Project</a>
    </div>
</section>

<script>
let activeCategory = 'all';

function filterCategory(btn, category) {
    activeCategory = category;

    // Update active button state in toolbar
    document.querySelectorAll('.blog-filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    else {
        // Called from sidebar — activate matching toolbar button
        document.querySelectorAll('.blog-filter-btn').forEach(b => {
            if (b.getAttribute('onclick')?.includes(`'${category}'`)) b.classList.add('active');
        });
    }

    filterPosts();
    // Smooth scroll to grid
    document.getElementById('blogGrid').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function filterPosts() {
    const search  = document.getElementById('blogSearch').value.toLowerCase().trim();
    const cards   = document.querySelectorAll('#blogGrid .blog-card');
    let visible   = 0;

    cards.forEach(card => {
        const cat   = card.dataset.category || '';
        const title = card.dataset.title    || '';

        const catMatch   = activeCategory === 'all' || cat === activeCategory;
        const searchMatch = search === '' || title.includes(search);

        if (catMatch && searchMatch) {
            card.style.display = '';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('blogNoResults').style.display = visible === 0 ? 'block' : 'none';
}
</script>

<?php include 'templates/footer.php'; ?>