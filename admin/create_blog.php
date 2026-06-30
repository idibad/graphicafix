<?php
include('dashboard_header.php');

$is_admin = $role === 'admin';
if (!$is_admin) {
    echo "Access Denied.";
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    // generate slug from title
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    
    // check slug uniqueness
    $check = $conn->prepare("SELECT id FROM blogs WHERE slug = ?");
    $check->bind_param("s", $slug);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $slug = $slug . '-' . time();
    }
    
    $category = trim($_POST['category'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');
    $author_id = $user_id;

    // Handle Image Upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/uploads/blogs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES['image']['name']));
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = 'images/uploads/blogs/' . $filename;
        }
    }

    if (empty($title) || empty($content)) {
        $error = "Title and Content are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO blogs (title, slug, category, excerpt, content, image, author_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("ssssssis", $title, $slug, $category, $excerpt, $content, $image_path, $author_id, $status);
        
        if ($stmt->execute()) {
            $success = "Blog post created successfully!";
            echo "<script>setTimeout(() => { window.location.href = 'manage_blogs.php'; }, 1500);</script>";
        } else {
            $error = "Database Error: " . $stmt->error;
        }
    }
}
?>

<div class="height-100">
    <div class="projects-container">
        
        <div class="page-header" style="margin-bottom: 24px;">
            <a href="manage_blogs.php" style="color: #666; text-decoration: none; margin-bottom: 10px; display: inline-block;">&larr; Back to Blogs</a>
            <h1>Create New Blog</h1>
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
                    <div class="form-group">
                        <label>Blog Title <span class="required">*</span></label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="Graphic Design">Graphic Design</option>
                            <option value="Web Design">Web Design</option>
                            <option value="Branding">Branding</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Tips & Tricks">Tips & Tricks</option>
                            <option value="UI/UX Design">UI/UX Design</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Short Excerpt (Summary)</label>
                    <textarea name="excerpt" rows="2"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Featured Image</label>
                        <input type="file" name="image" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="published">Published</option>
                            <option value="draft" selected>Draft</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Content <span class="required">*</span></label>
                    <!-- Quill container -->
                    <div id="editor" style="height: 400px;"></div>
                    <input type="hidden" name="content" id="hiddenContent">
                </div>

                <div style="text-align: right; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right: 6px;"></i> Save Blog Post</button>
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
