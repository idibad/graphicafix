<?php include 'header.php'; ?>

<style>
/* ── Hero ──────────────────────────────────────────────────── */
.blog-hero {
    background: #024442;
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
    color: #e8c97a;
    border: 1px solid rgba(232,201,122,.35);
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

.blog-hero h1 span { color: #e8c97a; }

.blog-hero p {
    font-size: clamp(.95rem, 2vw, 1.1rem);
    color: rgba(255,255,255,.65);
    max-width: 520px;
    margin: 0 auto;
    line-height: 1.65;
}

/* ── Search & Filter bar ───────────────────────────────────── */
.blog-toolbar {
    background: #fff;
    border-bottom: 1px solid #ebebeb;
    padding: 1.1rem 0;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
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
    transition: border-color .2s;
    background: #f9f9f9;
}

.blog-search input:focus { border-color: #024442; background: #fff; }

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
    flex-wrap: wrap;
}

.blog-filter-btn {
    padding: 8px 18px;
    border-radius: 100px;
    border: 1.5px solid #e0e0e0;
    background: transparent;
    font-size: 13px;
    font-weight: 500;
    color: #555;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}

.blog-filter-btn:hover,
.blog-filter-btn.active {
    background: #024442;
    border-color: #024442;
    color: #fff;
}

/* ── Main layout ───────────────────────────────────────────── */
.blog-main {
    padding: clamp(2.5rem, 6vw, 4rem) 0;
    background: #f9f9f9;
}

/* ── Featured post ─────────────────────────────────────────── */
.blog-featured {
    margin-bottom: clamp(2rem, 5vw, 3rem);
}

.blog-featured-card {
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #ebebeb;
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 340px;
    transition: box-shadow .25s, transform .25s;
}

.blog-featured-card:hover {
    box-shadow: 0 10px 40px rgba(2,68,66,.12);
    transform: translateY(-2px);
}

.blog-featured-img {
    position: relative;
    overflow: hidden;
}

.blog-featured-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .4s ease;
}

.blog-featured-card:hover .blog-featured-img img { transform: scale(1.04); }

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
                    <div class="blog-featured-card" data-category="branding">
                        <div class="blog-featured-img">
                            <img src="https://placehold.co/700x420/024442/ffffff?text=Featured+Post" alt="Featured Post">
                        </div>
                        <div class="blog-featured-body">
                            <div class="blog-featured-label">Branding</div>
                            <h2><a href="blog_post.php?id=1">How to Build a Strong Brand Identity That Stands Out in 2025</a></h2>
                            <p class="blog-featured-excerpt">A powerful brand identity goes far beyond a logo. It encompasses your colors, typography, tone of voice, and the emotions you evoke in your audience. In this guide, we break down the exact process we use for our clients.</p>
                            <div class="blog-featured-meta">
                                <span>📅 March 01, 2025</span>
                                <span>⏱ 6 min read</span>
                                <span>👁 1.2k views</span>
                            </div>
                            <a href="blog_post.php?id=1" class="blog-read-more">Read Article →</a>
                        </div>
                    </div>
                </div>

                <!-- All posts grid -->
                <div class="blog-section-label">Latest Articles</div>
                <div class="blog-grid" id="blogGrid">

                    <?php
                    // Static placeholder posts — replace with DB query when blog table is ready
                    $posts = [
                        ['id'=>2, 'title'=>'10 Logo Design Trends Dominating 2025', 'excerpt'=>'From minimalist wordmarks to bold retro revivals, logo design is evolving fast. We break down the top 10 trends shaping modern branding this year.', 'category'=>'Graphic Design', 'cat_slug'=>'graphic design', 'date'=>'Feb 24, 2025', 'read'=>'4 min', 'author'=>'Graphicafix'],
                        ['id'=>3, 'title'=>'Why Your Website Needs a Design Refresh in 2025', 'excerpt'=>'User expectations are higher than ever. If your website was built more than 2 years ago, here\'s why a redesign might be your best investment.', 'category'=>'Web Design', 'cat_slug'=>'web design', 'date'=>'Feb 18, 2025', 'read'=>'5 min', 'author'=>'Graphicafix'],
                        ['id'=>4, 'title'=>'The Power of Consistent Social Media Branding', 'excerpt'=>'Inconsistent visuals are silently killing your brand. Learn how to build a cohesive social media presence that builds trust and recognition.', 'category'=>'Marketing', 'cat_slug'=>'marketing', 'date'=>'Feb 10, 2025', 'read'=>'3 min', 'author'=>'Graphicafix'],
                        ['id'=>5, 'title'=>'Color Psychology in Brand Design: What Your Palette Says', 'excerpt'=>'Colors speak louder than words. Understanding color psychology helps you pick a palette that emotionally connects with your target audience.', 'category'=>'Branding', 'cat_slug'=>'branding', 'date'=>'Jan 28, 2025', 'read'=>'5 min', 'author'=>'Graphicafix'],
                        ['id'=>6, 'title'=>'Typography Tips Every Designer Should Know', 'excerpt'=>'Good typography is invisible — bad typography screams. Master these fundamentals to make your designs cleaner, more professional, and more impactful.', 'category'=>'Tips & Tricks', 'cat_slug'=>'tips', 'date'=>'Jan 20, 2025', 'read'=>'4 min', 'author'=>'Graphicafix'],
                        ['id'=>7, 'title'=>'How We Approach Every Branding Project at Graphicafix', 'excerpt'=>'From discovery call to final delivery, here\'s an inside look at our proven branding process and how we ensure every client gets a result they love.', 'category'=>'Branding', 'cat_slug'=>'branding', 'date'=>'Jan 12, 2025', 'read'=>'6 min', 'author'=>'Graphicafix'],
                        ['id'=>8, 'title'=>'5 Common Web Design Mistakes to Avoid', 'excerpt'=>'Even experienced designers fall into these traps. Avoid these 5 common web design mistakes to keep your site clean, fast, and conversion-friendly.', 'category'=>'Web Design', 'cat_slug'=>'web design', 'date'=>'Jan 05, 2025', 'read'=>'3 min', 'author'=>'Graphicafix'],
                        ['id'=>9, 'title'=>'What Makes a Great Packaging Design?', 'excerpt'=>'Packaging is your first physical touchpoint with a customer. Here\'s what separates forgettable packaging from designs that fly off shelves.', 'category'=>'Graphic Design', 'cat_slug'=>'graphic design', 'date'=>'Dec 28, 2024', 'read'=>'4 min', 'author'=>'Graphicafix'],
                        ['id'=>10, 'title'=>'SEO for Designers: Why Visual Content Matters for Rankings', 'excerpt'=>'Design and SEO aren\'t as separate as you think. Learn how great visual content, alt text, and page speed can dramatically boost your search rankings.', 'category'=>'Marketing', 'cat_slug'=>'marketing', 'date'=>'Dec 20, 2024', 'read'=>'5 min', 'author'=>'Graphicafix'],
                    ];
                    ?>

                    <?php foreach ($posts as $post):
                        $initial = strtoupper(substr($post['author'], 0, 1));
                    ?>
                    <div class="blog-card" data-category="<?= htmlspecialchars($post['cat_slug']) ?>" data-title="<?= strtolower(htmlspecialchars($post['title'])) ?>">
                        <div class="blog-card-img">
                            <img src="https://placehold.co/600x340/024442/ffffff?text=<?= urlencode($post['category']) ?>"
                                 alt="<?= htmlspecialchars($post['title']) ?>"
                                 loading="lazy">
                            <span class="blog-card-category"><?= htmlspecialchars($post['category']) ?></span>
                        </div>
                        <div class="blog-card-body">
                            <h3><a href="blog_post.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                            <p class="blog-card-excerpt"><?= htmlspecialchars($post['excerpt']) ?></p>
                        </div>
                        <div class="blog-card-footer">
                            <div class="blog-card-author">
                                <div class="blog-card-avatar"><?= $initial ?></div>
                                <span><?= htmlspecialchars($post['author']) ?> · <?= $post['date'] ?></span>
                            </div>
                            <a href="blog_post.php?id=<?= $post['id'] ?>" class="blog-card-read">
                                Read <span>→</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>

                <!-- No results message -->
                <div id="blogNoResults" style="display:none;text-align:center;padding:3rem 0;color:#999;font-size:15px;">
                    😕 No articles found. Try a different search or category.
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
                                🎨 Branding <span class="sidebar-cat-count">12</span>
                            </li>
                            <li onclick="filterCategory(null, 'web design')">
                                💻 Web Design <span class="sidebar-cat-count">8</span>
                            </li>
                            <li onclick="filterCategory(null, 'graphic design')">
                                ✏️ Graphic Design <span class="sidebar-cat-count">10</span>
                            </li>
                            <li onclick="filterCategory(null, 'marketing')">
                                📢 Marketing <span class="sidebar-cat-count">6</span>
                            </li>
                            <li onclick="filterCategory(null, 'tips')">
                                💡 Tips & Tricks <span class="sidebar-cat-count">9</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Popular posts -->
                    <div class="sidebar-widget">
                        <div class="sidebar-widget-title">Popular Posts</div>
                        <a href="blog_post.php?id=1" class="sidebar-post">
                            <img src="https://placehold.co/60x60/024442/ffffff?text=P" class="sidebar-post-img" alt="Post">
                            <div class="sidebar-post-info">
                                <div class="sidebar-post-title">How to Build a Strong Brand Identity in 2025</div>
                                <div class="sidebar-post-date">📅 March 01, 2025</div>
                            </div>
                        </a>
                        <a href="blog_post.php?id=2" class="sidebar-post">
                            <img src="https://placehold.co/60x60/01796f/ffffff?text=P" class="sidebar-post-img" alt="Post">
                            <div class="sidebar-post-info">
                                <div class="sidebar-post-title">10 Logo Design Trends Dominating 2025</div>
                                <div class="sidebar-post-date">📅 Feb 24, 2025</div>
                            </div>
                        </a>
                        <a href="blog_post.php?id=4" class="sidebar-post">
                            <img src="https://placehold.co/60x60/e8c97a/024442?text=P" class="sidebar-post-img" alt="Post">
                            <div class="sidebar-post-info">
                                <div class="sidebar-post-title">The Power of Consistent Social Media Branding</div>
                                <div class="sidebar-post-date">📅 Feb 10, 2025</div>
                            </div>
                        </a>
                    </div>

                    <!-- Newsletter -->
                    <div class="sidebar-widget sidebar-newsletter">
                        <div class="sidebar-widget-title">Newsletter</div>
                        <p>Get the latest design tips and creative insights delivered to your inbox every week.</p>
                        <input type="text" placeholder="Your name">
                        <input type="email" placeholder="Your email address">
                        <button class="sidebar-newsletter-btn">✉️ Subscribe Now</button>
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
        <a href="index.php#request" class="blog-cta-btn">✨ Request a Project</a>
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

<?php include 'footer.php'; ?>