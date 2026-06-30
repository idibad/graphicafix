<?php
include('dashboard_header.php');

$is_admin = $role === 'admin';
if (!$is_admin) {
    echo "Access Denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: manage_blogs.php");
    exit;
}

$error = '';
$success = '';

// Fetch existing blog
$stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$blog = $stmt->get_result()->fetch_assoc();

if (!$blog) {
    header("Location: manage_blogs.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    
    // Check slug
    $slug = trim($_POST['slug'] ?? $blog['slug']);
    if ($slug !== $blog['slug']) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug)));
        $check = $conn->prepare("SELECT id FROM blogs WHERE slug = ? AND id != ?");
        $check->bind_param("si", $slug, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $slug = $slug . '-' . time();
        }
    }
    
    $category = trim($_POST['category'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');

    $image_path = $blog['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/uploads/blogs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES['image']['name']));
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = 'images/uploads/blogs/' . $filename;
            // Optionally delete old image here if needed
        }
    }

    if (empty($title) || empty($content)) {
        $error = "Title and Content are required.";
    } else {
        $stmt = $conn->prepare("UPDATE blogs SET title=?, slug=?, category=?, excerpt=?, content=?, image=?, status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("sssssssi", $title, $slug, $category, $excerpt, $content, $image_path, $status, $id);
        
        if ($stmt->execute()) {
            $success = "Blog post updated successfully!";
            // refresh data
            $blog['title'] = $title;
            $blog['slug'] = $slug;
            $blog['category'] = $category;
            $blog['excerpt'] = $excerpt;
            $blog['content'] = $content;
            $blog['status'] = $status;
            $blog['image'] = $image_path;
        } else {
            $error = "Database Error: " . $stmt->error;
        }
    }
}

$categories = ['Graphic Design', 'Web Design', 'Branding', 'Marketing', 'Tips & Tricks', 'UI/UX Design'];
?>

<div class="height-100">
    <div class="projects-container">
        
        <div class="page-header" style="margin-bottom: 24px;">
            <a href="manage_blogs.php" style="color: #666; text-decoration: none; margin-bottom: 10px; display: inline-block;">&larr; Back to Blogs</a>
            <h1>Edit Blog</h1>
        </div>

        <?php if($error): ?>
            <div style="background: #fee2e2; color: #ef4444; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div style="background: #d1fae5; color: #10b981; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>

        <div class="main-card" style="padding: 30px;">
            <form method="POST" enctype="multipart/form-data" id="blogForm">
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <div class="form-group" style="margin-bottom: 10px;">
                            <label>Blog Title <span class="required">*</span></label>
                            <input type="text" name="title" value="<?= htmlspecialchars($blog['title']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>URL Slug</label>
                            <input type="text" name="slug" value="<?= htmlspecialchars($blog['slug']) ?>">
                        </div>
                    </div>
                    <div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label>Category</label>
                            <select name="category">
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($blog['category'] == $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="published" <?= ($blog['status'] == 'published') ? 'selected' : '' ?>>Published</option>
                                <option value="draft" <?= ($blog['status'] == 'draft') ? 'selected' : '' ?>>Draft</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Short Excerpt (Summary)</label>
                    <textarea name="excerpt" rows="2"><?= htmlspecialchars($blog['excerpt']) ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Featured Image</label>
                    <?php if($blog['image']): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="../<?= htmlspecialchars($blog['image']) ?>" alt="Current Image" style="max-width: 200px; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                    <small style="color: #666; margin-top: 5px; display: block;">Leave blank to keep current image</small>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Content <span class="required">*</span></label>
                    <div id="editor" style="height: 400px;">
                        <?= $blog['content'] ?>
                    </div>
                    <input type="hidden" name="content" id="hiddenContent">
                </div>

                <div style="text-align: right; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right: 6px;"></i> Update Blog Post</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [2, 3, 4, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image', 'video', 'blockquote', 'code-block'],
                [{ 'color': [] }, { 'background': [] }],
                ['clean']
            ]
        }
    });

    document.getElementById('blogForm').onsubmit = function() {
        document.getElementById('hiddenContent').value = quill.root.innerHTML;
    };
</script>

<?php include('dashboard_footer.php'); ?>
