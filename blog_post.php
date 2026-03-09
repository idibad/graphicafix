<?php include 'header.php'; ?>

<?php
// Placeholder post data — replace with DB query when blog table is ready
// e.g. $post = $conn->query("SELECT * FROM blogs WHERE id = ? AND status = 'published'")->fetch_assoc();
$post = [
    'id'         => 1,
    'title'      => 'How to Build a Strong Brand Identity That Stands Out in 2025',
    'category'   => 'Branding',
    'cat_slug'   => 'branding',
    'author'     => 'Graphicafix',
    'date'       => 'March 01, 2025',
    'read_time'  => '6 min read',
    'views'      => '1,200',
    'thumbnail'  => 'https://placehold.co/1200x600/024442/ffffff?text=Blog+Post+Thumbnail',
    'excerpt'    => 'A powerful brand identity goes far beyond a logo. It encompasses your colors, typography, tone of voice, and the emotions you evoke in your audience.',
    'content'    => '
        <p>A strong brand identity is one of the most valuable assets a business can own. It is the visual and emotional representation of who you are, what you stand for, and why your customers should choose you over the competition. In 2025, with more brands competing for attention than ever before, a cohesive identity is no longer optional — it is essential.</p>

        <h2>What Is Brand Identity?</h2>
        <p>Brand identity is the collection of all visual and verbal elements that represent your company. This includes your logo, color palette, typography, imagery style, tone of voice, and even the way your team answers the phone. When all of these elements work together harmoniously, they create a recognizable and trustworthy brand experience.</p>

        <blockquote>
            "Your brand is what other people say about you when you are not in the room." — Jeff Bezos
        </blockquote>

        <h2>Step 1: Define Your Brand Foundation</h2>
        <p>Before you pick a single color or sketch a logo concept, you need to get crystal clear on the core pillars of your brand:</p>
        <ul>
            <li><strong>Mission:</strong> Why does your business exist beyond making money?</li>
            <li><strong>Vision:</strong> Where do you want to be in 5 or 10 years?</li>
            <li><strong>Values:</strong> What principles guide every decision you make?</li>
            <li><strong>Target Audience:</strong> Who are you speaking to, and what do they care about?</li>
            <li><strong>Unique Selling Proposition:</strong> What makes you genuinely different?</li>
        </ul>

        <p>Answering these questions with honesty and specificity will inform every creative decision that follows.</p>

        <h2>Step 2: Research Your Market and Competitors</h2>
        <p>Great branding does not exist in a vacuum. Study your top competitors — not to copy them, but to understand the visual language of your industry and find the white space where your brand can stand out. If every competitor uses blue, maybe your brand should explore a bold green or a warm terracotta.</p>

        <img src="https://placehold.co/900x400/f0f5f4/024442?text=Brand+Research+Process" alt="Brand Research Process">

        <h2>Step 3: Design Your Visual Identity</h2>
        <p>With your foundation and research in hand, it is time to build your visual system. This typically includes:</p>
        <ol>
            <li><strong>Logo:</strong> Your primary mark, variations, and clear space rules</li>
            <li><strong>Color Palette:</strong> Primary, secondary, and neutral colors with defined hex codes</li>
            <li><strong>Typography:</strong> A heading font and a body font that complement each other</li>
            <li><strong>Imagery Style:</strong> The type of photography or illustration that feels on-brand</li>
            <li><strong>Iconography:</strong> A consistent set of icons or graphic elements</li>
        </ol>

        <h2>Step 4: Create Brand Guidelines</h2>
        <p>Your brand guidelines document is the rulebook that ensures consistency across every touchpoint — from your website and social media to business cards and packaging. Without it, your visual identity will slowly drift and lose its impact. A good brand guidelines document covers logo usage, color codes, typography specs, imagery dos and donts, and tone of voice.</p>

        <blockquote>
            Consistency is the foundation of trust. A brand that looks different every week is a brand that feels unreliable.
        </blockquote>

        <h2>Step 5: Apply and Evolve</h2>
        <p>Once your identity is defined, apply it consistently everywhere. But remember — brands are living things. As your business grows, your audience shifts, or the market changes, your brand should evolve too. The best brands revisit and refine their identity every few years to stay relevant without losing recognition.</p>

        <p>At Graphicafix, we have helped dozens of businesses build brand identities that are not just beautiful — they are strategic, memorable, and built to last. If you are ready to invest in your brand, we would love to help.</p>
    ',
];

// Related posts placeholder
$related = [
    ['id'=>5, 'title'=>'Color Psychology in Brand Design: What Your Palette Says', 'category'=>'Branding', 'date'=>'Jan 28, 2025', 'read'=>'5 min'],
    ['id'=>6, 'title'=>'Typography Tips Every Designer Should Know',               'category'=>'Tips & Tricks','date'=>'Jan 20, 2025', 'read'=>'4 min'],
    ['id'=>7, 'title'=>'How We Approach Every Branding Project at Graphicafix',    'category'=>'Branding', 'date'=>'Jan 12, 2025', 'read'=>'6 min'],
];
?>

<style>
/* ── Post Hero ─────────────────────────────────────────────── */
.post-hero {
    background: #024442;
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

.post-breadcrumb a:hover { color: #e8c97a; }
.post-breadcrumb span   { color: rgba(255,255,255,.25); }

.post-category-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: #024442;
    background: #e8c97a;
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
            <span>✍️ <?= htmlspecialchars($post['author']) ?></span>
            <div class="divider"></div>
            <span>📅 <?= htmlspecialchars($post['date']) ?></span>
            <div class="divider"></div>
            <span>⏱ <?= htmlspecialchars($post['read_time']) ?></span>
            <div class="divider"></div>
            <span>👁 <?= htmlspecialchars($post['views']) ?> views</span>
        </div>

        <div class="post-thumbnail-wrap">
            <img src="<?= htmlspecialchars($post['thumbnail']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
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
                    <?= $post['content'] ?>
                </div>

                <!-- Tags & Share -->
                <div class="post-footer-card">
                    <div class="post-tags">
                        <span class="post-tag-label">Tags:</span>
                        <a href="blog.php?tag=branding"    class="post-tag">Branding</a>
                        <a href="blog.php?tag=identity"    class="post-tag">Identity</a>
                        <a href="blog.php?tag=logo-design" class="post-tag">Logo Design</a>
                        <a href="blog.php?tag=strategy"    class="post-tag">Strategy</a>
                    </div>
                    <div class="post-share">
                        <span class="post-share-label">Share:</span>
                        <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://graphicafix.com/blog_post.php?id=' . $post['id']) ?>" target="_blank" title="Share on Facebook">𝑓</a>
                        <a class="share-btn" href="https://twitter.com/intent/tweet?url=<?= urlencode('https://graphicafix.com/blog_post.php?id=' . $post['id']) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" title="Share on Twitter">𝕏</a>
                        <a class="share-btn" href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode('https://graphicafix.com/blog_post.php?id=' . $post['id']) ?>" target="_blank" title="Share on LinkedIn">in</a>
                        <button class="share-btn" onclick="copyLink()" title="Copy link">🔗</button>
                    </div>
                </div>

                <!-- Author -->
                <div class="post-author-card">
                    <div class="post-author-avatar">G</div>
                    <div class="post-author-info">
                        <h4><?= htmlspecialchars($post['author']) ?></h4>
                        <div class="post-author-role">Creative Design Agency · Islamabad, Pakistan</div>
                        <p class="post-author-bio">Graphicafix is a full-service creative agency specializing in branding, graphic design, web development, and digital marketing. We help businesses build meaningful identities and stand out in a crowded market.</p>
                    </div>
                </div>

                <!-- Prev / Next -->
                <div class="post-nav">
                    <a href="blog_post.php?id=<?= $post['id'] - 1 ?>" class="post-nav-item prev">
                        <span class="post-nav-direction">← Previous Post</span>
                        <span class="post-nav-title">Typography Tips Every Designer Should Know</span>
                    </a>
                    <a href="blog_post.php?id=<?= $post['id'] + 1 ?>" class="post-nav-item next">
                        <span class="post-nav-direction">Next Post →</span>
                        <span class="post-nav-title">10 Logo Design Trends Dominating 2025</span>
                    </a>
                </div>

                <!-- Related posts -->
                <div class="post-related">
                    <div class="post-section-label">Related Articles</div>
                    <div class="related-grid">
                        <?php foreach ($related as $rel): ?>
                        <a href="blog_post.php?id=<?= $rel['id'] ?>" class="related-card">
                            <div class="related-card-img">
                                <img src="https://placehold.co/600x340/024442/ffffff?text=<?= urlencode($rel['category']) ?>"
                                     alt="<?= htmlspecialchars($rel['title']) ?>" loading="lazy">
                            </div>
                            <div class="related-card-body">
                                <div class="related-card-cat"><?= htmlspecialchars($rel['category']) ?></div>
                                <div class="related-card-title"><?= htmlspecialchars($rel['title']) ?></div>
                                <div class="related-card-meta">📅 <?= $rel['date'] ?> &nbsp;·&nbsp; ⏱ <?= $rel['read'] ?></div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- Sidebar -->
            <div class="col-12 col-lg-4">

                <!-- Table of Contents -->
                <div class="sidebar-widget">
                    <div class="sidebar-widget-title">Table of Contents</div>
                    <ul class="toc-list">
                        <li><a href="#" onclick="scrollToHeading(0)">What Is Brand Identity?</a></li>
                        <li><a href="#" onclick="scrollToHeading(1)">Step 1: Define Your Brand Foundation</a></li>
                        <li><a href="#" onclick="scrollToHeading(2)">Step 2: Research Your Market and Competitors</a></li>
                        <li><a href="#" onclick="scrollToHeading(3)">Step 3: Design Your Visual Identity</a></li>
                        <li><a href="#" onclick="scrollToHeading(4)">Step 4: Create Brand Guidelines</a></li>
                        <li><a href="#" onclick="scrollToHeading(5)">Step 5: Apply and Evolve</a></li>
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
                    <a href="blog_post.php?id=2" class="sidebar-post">
                        <img src="https://placehold.co/56x56/024442/ffffff?text=P" class="sidebar-post-img" alt="Post">
                        <div class="sidebar-post-info">
                            <div class="sidebar-post-title">10 Logo Design Trends Dominating 2025</div>
                            <div class="sidebar-post-date">📅 Feb 24, 2025</div>
                        </div>
                    </a>
                    <a href="blog_post.php?id=3" class="sidebar-post">
                        <img src="https://placehold.co/56x56/01796f/ffffff?text=P" class="sidebar-post-img" alt="Post">
                        <div class="sidebar-post-info">
                            <div class="sidebar-post-title">Why Your Website Needs a Design Refresh in 2025</div>
                            <div class="sidebar-post-date">📅 Feb 18, 2025</div>
                        </div>
                    </a>
                    <a href="blog_post.php?id=4" class="sidebar-post">
                        <img src="https://placehold.co/56x56/e8c97a/024442?text=P" class="sidebar-post-img" alt="Post">
                        <div class="sidebar-post-info">
                            <div class="sidebar-post-title">The Power of Consistent Social Media Branding</div>
                            <div class="sidebar-post-date">📅 Feb 10, 2025</div>
                        </div>
                    </a>
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