<?php
session_start();
$dataFile = 'posts.json';

// --- 1. HANDLE LOGOUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// --- 2. HANDLE LOGIN ---
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === 'admin' && $_POST['password'] === 'admin') {
        $_SESSION['loggedin'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "Invalid Credentials";
    }
}

// REQUIRE LOGIN FOR EVERYTHING ELSE
if (!isset($_SESSION['loggedin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Admin Login</title>
        <style>
            body { font-family: sans-serif; display: flex; height: 100vh; justify-content: center; align-items: center; background: #f0f2f5; }
            form { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
            input { width: 100%; padding: 10px; margin-bottom: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
            button { width: 100%; padding: 10px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; }
            .error { color: red; font-size: 0.9rem; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <form method="POST">
            <h2 style="margin-top:0">Login</h2>
            <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
            <p style="text-align:center; margin-bottom:0;"><a href="index.php">Back to Home</a></p>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// --- 3. LOAD DATA ---
$data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
$posts = $data['posts'] ?? [];
$settings = $data['settings'] ?? [];
$categories = $data['categories'] ?? ['News', 'General'];

// --- 4. HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. SAVE POST (NEW or EDIT)
    if (isset($_POST['save_post'])) {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $category = $_POST['category'];
        $id = $_POST['post_id'] ? (int)$_POST['post_id'] : time();
        $date = $_POST['date'] ?: date('Y-m-d');
        
        // Handle Image Upload
        $imagePath = $_POST['existing_image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            if (!is_dir('images')) mkdir('images');
            $filename = time() . '_' . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], 'images/' . $filename);
            $imagePath = 'images/' . $filename;
        }

        $newPost = [
            'id' => $id,
            'title' => $title,
            'slug' => strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))),
            'category' => $category,
            'content' => $content,
            'date' => $date,
            'image' => $imagePath
        ];

        // If Edit, remove old version first
        if ($_POST['post_id']) {
            foreach ($posts as $key => $p) {
                if ($p['id'] == $id) { unset($posts[$key]); break; }
            }
        }
        
        // Add new/updated post to top
        array_unshift($posts, $newPost);
        $data['posts'] = array_values($posts); // Reindex
        file_put_contents($dataFile, json_encode($data));
        header("Location: admin.php?msg=Post Saved");
        exit;
    }

    // B. SAVE SETTINGS
    if (isset($_POST['save_settings'])) {
        $data['settings']['headerText'] = $_POST['headerText'];
        $data['settings']['heroTitle'] = $_POST['heroTitle'];
        $data['settings']['heroSub'] = $_POST['heroSub'];

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            if (!is_dir('images')) mkdir('images');
            $f = time().'_logo_'.basename($_FILES['logo']['name']);
            move_uploaded_file($_FILES['logo']['tmp_name'], 'images/'.$f);
            $data['settings']['logo'] = 'images/'.$f;
        }

        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === 0) {
            if (!is_dir('images')) mkdir('images');
            $f = time().'_cover_'.basename($_FILES['cover']['name']);
            move_uploaded_file($_FILES['cover']['tmp_name'], 'images/'.$f);
            $data['settings']['defaultCover'] = 'images/'.$f;
        }

        file_put_contents($dataFile, json_encode($data));
        header("Location: admin.php?tab=settings&msg=Settings Saved");
        exit;
    }

    // C. ADD CATEGORY
    if (isset($_POST['add_category'])) {
        $newCat = trim($_POST['cat_name']);
        if ($newCat && !in_array($newCat, $categories)) {
            $categories[] = $newCat;
            $data['categories'] = $categories;
            file_put_contents($dataFile, json_encode($data));
        }
        header("Location: admin.php?tab=categories");
        exit;
    }
}

// --- 5. HANDLE ACTIONS (GET) ---
// Delete Post
if (isset($_GET['delete_post'])) {
    $idToDelete = $_GET['delete_post'];
    $posts = array_filter($posts, function($p) use ($idToDelete) { return $p['id'] != $idToDelete; });
    $data['posts'] = array_values($posts);
    file_put_contents($dataFile, json_encode($data));
    header("Location: admin.php?msg=Post Deleted");
    exit;
}
// Delete Category
if (isset($_GET['delete_cat'])) {
    $catToDelete = $_GET['delete_cat'];
    $categories = array_diff($categories, [$catToDelete]);
    $data['categories'] = array_values($categories);
    file_put_contents($dataFile, json_encode($data));
    header("Location: admin.php?tab=categories");
    exit;
}

// Determine Current View
$tab = $_GET['tab'] ?? 'posts';
$editPost = null;
if ($tab === 'edit' && isset($_GET['id'])) {
    foreach ($posts as $p) {
        if ($p['id'] == $_GET['id']) { $editPost = $p; break; }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <style>
        body { margin: 0; display: flex; font-family: 'Segoe UI', sans-serif; background: #f8fafc; height: 100vh; }
        
        /* SIDEBAR */
        .sidebar { width: 200px; background: #1e293b; color: white; display: flex; flex-direction: column; flex-shrink: 0; }
        .brand { padding: 20px; font-size: 1.2rem; font-weight: bold; border-bottom: 1px solid #334155; }
        .menu { list-style: none; padding: 0; margin: 0; }
        .menu a { display: block; padding: 15px 20px; color: #cbd5e1; text-decoration: none; border-bottom: 1px solid #334155; }
        .menu a:hover, .menu a.active { background: #2563eb; color: white; }
        .menu a.logout { color: #f87171; margin-top: auto; border-top: 1px solid #334155; }

        /* CONTENT */
        .content { flex: 1; padding: 30px; overflow-y: auto; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 900px; }
        h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        /* FORMS */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; color: white; background: #2563eb; font-weight: 600; }
        .btn-danger { background: #dc2626; color: white; text-decoration: none; padding: 5px 10px; border-radius: 4px; font-size: 0.9rem; }
        
        /* TABLE */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        th { background: #f1f5f9; }
        .actions a { margin-right: 10px; text-decoration: none; font-weight: 600; }
        .edit-link { color: #2563eb; }
        .delete-link { color: #dc2626; }

        .msg { background: #d1fae5; color: #065f46; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand">Admin Panel</div>
        <div class="menu">
            <a href="admin.php?tab=posts" class="<?php echo $tab=='posts'?'active':''; ?>">All Posts</a>
            <a href="admin.php?tab=edit" class="<?php echo $tab=='edit'?'active':''; ?>">Add New</a>
            <a href="admin.php?tab=categories" class="<?php echo $tab=='categories'?'active':''; ?>">Categories</a>
            <a href="admin.php?tab=settings" class="<?php echo $tab=='settings'?'active':''; ?>">Settings</a>
            <a href="index.php" target="_blank">View Website</a>
            <a href="admin.php?logout=true" class="logout">Logout</a>
        </div>
    </div>

    <div class="content">
        <?php if(isset($_GET['msg'])): ?><div class="msg"><?php echo $_GET['msg']; ?></div><?php endif; ?>

        <?php if($tab === 'posts'): ?>
            <div class="card">
                <h2>All Posts</h2>
                <table>
                    <thead><tr><th>Title</th><th>Category</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($posts as $post): ?>
                        <tr>
                            <td><?php echo $post['title']; ?></td>
                            <td><?php echo $post['category']; ?></td>
                            <td><?php echo $post['date']; ?></td>
                            <td class="actions">
                                <a href="admin.php?tab=edit&id=<?php echo $post['id']; ?>" class="edit-link">Edit</a>
                                <a href="admin.php?delete_post=<?php echo $post['id']; ?>" class="delete-link" onclick="return confirm('Delete this post?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if($tab === 'edit'): ?>
            <div class="card">
                <h2><?php echo $editPost ? 'Edit Post' : 'Add New Post'; ?></h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="post_id" value="<?php echo $editPost['id'] ?? ''; ?>">
                    <input type="hidden" name="existing_image" value="<?php echo $editPost['image'] ?? ''; ?>">
                    <input type="hidden" name="date" value="<?php echo $editPost['date'] ?? ''; ?>">

                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" class="form-control" value="<?php echo $editPost['title'] ?? ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo ($editPost['category']??'') == $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cover Image</label>
                        <?php if(!empty($editPost['image'])): ?>
                            <div style="margin-bottom:5px;"><img src="<?php echo $editPost['image']; ?>" height="50"></div>
                        <?php endif; ?>
                        <input type="file" name="image" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Content</label>
                        <textarea name="content" id="summernote"><?php echo $editPost['content'] ?? ''; ?></textarea>
                    </div>

                    <button type="submit" name="save_post" class="btn">Save Post</button>
                </form>
            </div>
            <script>
                $('#summernote').summernote({
                    placeholder: 'Write your content here...',
                    tabsize: 2,
                    height: 300
                });
            </script>
        <?php endif; ?>

        <?php if($tab === 'categories'): ?>
            <div class="card">
                <h2>Categories</h2>
                <form method="POST" style="margin-bottom:20px; display:flex; gap:10px;">
                    <input type="text" name="cat_name" class="form-control" placeholder="New Category Name" required style="width:auto; flex:1;">
                    <button type="submit" name="add_category" class="btn">Add</button>
                </form>
                <table>
                    <tbody>
                        <?php foreach($categories as $cat): ?>
                        <tr>
                            <td><?php echo $cat; ?></td>
                            <td style="text-align:right;">
                                <a href="admin.php?delete_cat=<?php echo urlencode($cat); ?>" class="btn-danger" onclick="return confirm('Delete?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if($tab === 'settings'): ?>
            <div class="card">
                <h2>Site Settings</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Site Title</label>
                        <input type="text" name="headerText" class="form-control" value="<?php echo $settings['headerText'] ?? 'My Blog'; ?>">
                    </div>
                    <div class="form-group">
                        <label>Logo</label>
                        <input type="file" name="logo" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Hero Title</label>
                        <input type="text" name="heroTitle" class="form-control" value="<?php echo $settings['heroTitle'] ?? 'Welcome'; ?>">
                    </div>
                    <div class="form-group">
                        <label>Hero Subtext</label>
                        <input type="text" name="heroSub" class="form-control" value="<?php echo $settings['heroSub'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Default Cover Image</label>
                        <input type="file" name="cover" class="form-control">
                    </div>
                    <button type="submit" name="save_settings" class="btn">Save Settings</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>