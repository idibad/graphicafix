<?php
include('dashboard_header.php');

$is_admin = $role === 'admin';
if (!$is_admin) {
    echo "Access Denied.";
    exit;
}

// ── Handle Delete ────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM blogs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_blogs.php");
    exit;
}

// ── Fetch Blogs ──────────────────────────────────────────────────────────────
$blogs_result = $conn->query("
    SELECT b.*, u.name as author_name 
    FROM blogs b
    LEFT JOIN users u ON u.user_id = b.author_id
    ORDER BY b.created_at DESC
");

$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS drafts,
        SUM(views) AS total_views
    FROM blogs
")->fetch_assoc();

$total = $stats['total'] ?? 0;
$published = $stats['published'] ?? 0;
$drafts = $stats['drafts'] ?? 0;
$total_views = $stats['total_views'] ?? 0;
?>

<div class="height-100">
    <div class="projects-container">
        
        <!-- Page Header -->
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div class="page-title-section">
                <h1>Manage Blogs</h1>
                <p>Create and manage your articles</p>
            </div>
            <div class="header-actions">
                <a href="create_blog.php" class="btn btn-primary" style="background: var(--primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none;"><i class="fas fa-plus"></i> New Blog</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <div class="stat-icon" style="color: var(--primary); font-size: 24px; margin-bottom: 10px;"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number" style="font-size: 28px; font-weight: 700;"><?= $total ?></div>
                <div class="stat-label" style="color: #666; font-size: 14px;">Total Blogs</div>
            </div>
            <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <div class="stat-icon" style="color: var(--success); font-size: 24px; margin-bottom: 10px;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number" style="font-size: 28px; font-weight: 700;"><?= $published ?></div>
                <div class="stat-label" style="color: #666; font-size: 14px;">Published</div>
            </div>
            <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <div class="stat-icon" style="color: var(--warning); font-size: 24px; margin-bottom: 10px;"><i class="fas fa-edit"></i></div>
                <div class="stat-number" style="font-size: 28px; font-weight: 700;"><?= $drafts ?></div>
                <div class="stat-label" style="color: #666; font-size: 14px;">Drafts</div>
            </div>
            <div class="stat-card" style="background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <div class="stat-icon" style="color: var(--accent); font-size: 24px; margin-bottom: 10px;"><i class="fas fa-eye"></i></div>
                <div class="stat-number" style="font-size: 28px; font-weight: 700;"><?= $total_views ?></div>
                <div class="stat-label" style="color: #666; font-size: 14px;">Total Views</div>
            </div>
        </div>

        <!-- Blogs List -->
        <div class="main-card">
            <div class="main-header" style="flex-wrap: wrap; gap: 14px;">
                <h3 style="margin: 0; display:flex; align-items:center;">
                    <i class="fas fa-file-alt" style="color: var(--primary); margin-right: 10px;"></i> All Blogs
                </h3>
            </div>

            <div style="padding: 10px 24px 24px;">
                <div class="main-table" style="border: 1px solid #f0f0f0; border-radius: 10px;">
                    <div class="table-head" style="grid-template-columns: 2fr 1fr 1fr 1fr 1fr 0.6fr; background: #f9fbfc; border-radius: 10px 10px 0 0; padding: 12px 20px;">
                        <span>Title</span>
                        <span>Category</span>
                        <span>Status</span>
                        <span>Views</span>
                        <span>Date</span>
                        <span>Actions</span>
                    </div>

                    <?php if ($blogs_result->num_rows > 0): ?>
                        <?php while($blog = $blogs_result->fetch_assoc()): ?>
                        <div class="table-row" style="grid-template-columns: 2fr 1fr 1fr 1fr 1fr 0.6fr; padding: 12px 20px; border-bottom: 1px solid #f5f5f5; align-items: center;">
                            <div style="font-weight: 600; color: var(--dark);">
                                <?= htmlspecialchars($blog['title']) ?>
                            </div>
                            <span data-label="Category" style="color: #555;">
                                <?= htmlspecialchars($blog['category']) ?>
                            </span>
                            <span data-label="Status">
                                <?php if($blog['status'] == 'published'): ?>
                                    <span class="status active" style="background: #e6f8f0; color: #00a65a; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;">Published</span>
                                <?php else: ?>
                                    <span class="status inactive" style="background: #fff4e5; color: #e17055; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;">Draft</span>
                                <?php endif; ?>
                            </span>
                            <span data-label="Views" style="color: #555;">
                                <i class="fas fa-eye" style="color: #aaa; margin-right: 5px;"></i> <?= $blog['views'] ?>
                            </span>
                            <span data-label="Date" style="color: #777; font-size: 14px;">
                                <?= date('M d, Y', strtotime($blog['created_at'])) ?>
                            </span>
                            <div style="display:flex; gap:6px;">
                                <a href="../blog_post.php?slug=<?= $blog['slug'] ?>" target="_blank" class="dots" title="View">
                                    <i class="fas fa-external-link-alt" style="color: var(--primary);"></i>
                                </a>
                                <a href="edit_blog.php?id=<?= $blog['id'] ?>" class="dots" title="Edit">
                                    <i class="fas fa-pen" style="color: var(--primary);"></i>
                                </a>
                                <a href="manage_blogs.php?delete=<?= $blog['id'] ?>" onclick="return confirm('Are you sure you want to delete this blog?');" class="dots" title="Delete">
                                    <i class="fas fa-trash" style="color:#ef4444;"></i>
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: #888;">No blogs found. Start writing!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('dashboard_footer.php'); ?>
