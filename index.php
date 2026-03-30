<?php
session_start();

// 1. SERVER-SIDE DATA LOADING
$dataFile = 'posts.json';
$data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
$posts = $data['posts'] ?? [];
$settings = $data['settings'] ?? [];
$categories = $data['categories'] ?? [];

// Settings Defaults
$siteTitle = $settings['headerText'] ?? 'My Blog';
$heroTitle = $settings['heroTitle'] ?? 'Welcome';
$heroSub = $settings['heroSub'] ?? '';
$logo = $settings['logo'] ?? '';
$defaultCover = $settings['defaultCover'] ?? 'https://via.placeholder.com/800x400';

// 2. ROUTING & FILTERING
$view = 'home'; 
$currentId = $_GET['id'] ?? null; // CHANGED FROM 'post' TO 'id'
$currentCat = $_GET['category'] ?? null;
$searchQuery = $_GET['q'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;

$filteredPosts = $posts;
$singlePost = null;

// ROUTING LOGIC UPDATED TO USE ID
if ($currentId) {
    $view = 'single';
    foreach($posts as $p) {
        // We compare IDs now, which are always unique numbers
        if ($p['id'] == $currentId) {
            $singlePost = $p;
            break;
        }
    }
} elseif ($currentCat) {
    $view = 'category';
    $heroTitle = $currentCat;
    $heroSub = "Browsing category: " . $currentCat;
    $filteredPosts = array_filter($posts, function($p) use ($currentCat) {
        return $p['category'] === $currentCat;
    });
} elseif ($searchQuery) {
    $view = 'search';
    $heroTitle = "Search: " . htmlspecialchars($searchQuery);
    $filteredPosts = array_filter($posts, function($p) use ($searchQuery) {
        return stripos($p['title'], $searchQuery) !== false || stripos($p['content'], $searchQuery) !== false;
    });
}

// Pagination
$totalPosts = count($filteredPosts);
$totalPages = ceil($totalPosts / $perPage);
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;
$displayPosts = ($totalPosts > 0) ? array_slice($filteredPosts, ($page - 1) * $perPage, $perPage) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $singlePost ? $singlePost['title'] : $siteTitle; ?></title>
    <style>
        :root { --primary: #2563eb; --dark: #1e293b; --light: #f8fafc; --white: #ffffff; --gray: #64748b; --border: #e2e8f0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
        body { background: var(--light); color: var(--dark); padding-bottom: 50px; }
        a { text-decoration: none; color: inherit; }

        /* HEADER */
        header { background: var(--white); border-bottom: 1px solid var(--border); padding: 0 2rem; height: 70px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .brand { font-size: 1.5rem; font-weight: bold; color: var(--primary); display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .brand img { height: 40px; width: auto; object-fit: contain; }
        
        .header-right { display: flex; align-items: center; gap: 20px; flex-direction: row; }
        
        .header-search { display: flex; margin: 0; }
        .header-search input { padding: 8px 15px; border: 1px solid var(--border); border-radius: 20px; background: var(--light); width: 200px; transition: 0.3s; outline: none; }
        .header-search input:focus { width: 300px; border-color: var(--primary); }
        
        .menu-links { display: flex; gap: 20px; align-items: center; white-space: nowrap; }
        .nav-link { font-weight: 500; cursor: pointer; color: var(--gray); transition: 0.2s; }
        .nav-link:hover { color: var(--primary); }
        .btn-dashboard { background: var(--primary); color: white; padding: 6px 15px; border-radius: 20px; font-size: 0.9rem; }

        /* HERO */
        .hero { background: var(--white); padding: 3rem 2rem; text-align: center; border-bottom: 1px solid var(--border); margin-bottom: 2rem; }
        .hero h1 { font-size: 2.5rem; margin-bottom: 0.5rem; text-transform: capitalize; }

        /* MOBILE SEARCH */
        .mobile-search { display: none; padding: 0 1rem 2rem 1rem; text-align: center; max-width: 600px; margin: 0 auto; }
        .mobile-search input { width: 100%; padding: 12px 20px; border: 1px solid #ccc; border-radius: 30px; font-size: 1rem; }

        @media (max-width: 768px) {
            header { position: static; padding: 0 1rem; }
            .header-search { display: none; }
            .mobile-search { display: block; }
            .hero { padding: 2rem 1rem; }
        }

        /* LAYOUT */
        .container { max-width: 1200px; margin: 10px auto; padding: 0 1rem; display: flex; gap: 1rem; }
        .main-content { flex: 0 0 calc(75% - 1rem); max-width: calc(75% - 1rem); }
        .sidebar { flex: 0 0 calc(25% - 1rem); max-width: calc(25% - 1rem); }
        @media (max-width: 900px) { .container { flex-direction: column; } .main-content, .sidebar { flex: 100%; max-width: 100%; } }

        /* GRID */
        .grid-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        @media (max-width: 1100px) { .grid-container { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .grid-container { grid-template-columns: 1fr; } }

        /* CARD */
        .post-card { background: var(--white); border-radius: 8px; overflow: hidden; border: 1px solid var(--border); transition: transform 0.2s; display: flex; flex-direction: column; height: 100%; text-decoration: none; color: inherit; }
        .post-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--primary); }
        .post-img { width: 100%; height: 200px; object-fit: cover; background: #eee; }
        .post-body { padding: 1.2rem; display: flex; flex-direction: column; flex-grow: 1; }
        .post-title { font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--dark); line-height: 1.3; }
        .post-excerpt { color: var(--gray); font-size: 0.95rem; line-height: 1.5; flex-grow: 1; }

        /* SINGLE POST */
        .single-post-view { background: var(--white); padding: 1rem; border-radius: 8px; border: 1px solid var(--border); }
        .single-post-img { width: auto; max-width: 100%; height: auto; max-height: 650px; display: block; margin: 0 auto 2rem auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .single-post-content { color: #333; line-height: 1.8; }
        .single-post-content img { max-width: 100%; height: auto; margin: 10px 0; border-radius: 4px; }
        .single-post-content h2 { margin-top: 20px; margin-bottom: 10px; }
        
        /* SIDEBAR WIDGETS */
        .widget { background: var(--white); padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1.5rem; }
        .cat-list { list-style: none; }
        .cat-list li { margin-bottom: 0.5rem; }
        .cat-list a { display: flex; justify-content: space-between; }

        /* RELATED POSTS */
        .related-post-link { display: block !important; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; text-decoration: none; }
        .related-post-link:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .related-title { font-weight: 500; color: var(--dark); line-height: 1.4; display: block; margin-bottom: 5px; }
        .related-date { font-size: 0.85rem; color: var(--gray); display: block; }
        .related-post-link:hover .related-title { color: var(--primary); }

        /* PAGINATION */
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border); grid-column: 1 / -1; }
        .page-btn { padding: 8px 14px; border: 1px solid var(--border); background: var(--white); border-radius: 4px; font-weight: 500; text-decoration: none; color: inherit; }
        .page-btn:hover { background: #f1f5f9; }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-btn.dots { border: none; background: transparent; cursor: default; }
        
        /* GO TOP BTN */
        #scrollTopBtn { display: none; position: fixed; bottom: 30px; right: 30px; z-index: 99; width: 50px; height: 50px; border-radius: 50%; background: var(--primary); color: white; border: none; font-size: 24px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2); align-items: center; justify-content: center; transition: background-color 0.3s, transform 0.3s; }
        #scrollTopBtn:hover { background-color: #1d4ed8; transform: translateY(-5px); }
    </style>
</head>
<body>

    <button onclick="scrollToTop()" id="scrollTopBtn" title="Go to top">&uarr;</button>

    <header>
        <a href="index.php" class="brand">
            <?php if($logo): ?> <img src="<?php echo $logo; ?>" alt="Logo"> <?php endif; ?>
            <?php echo $siteTitle; ?>
        </a>
        <div class="header-right">
            <form action="index.php" method="GET" class="header-search">
                <input type="text" name="q" placeholder="Search..." value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
            </form>
            <div class="menu-links">
                <a href="index.php" class="nav-link">Home</a>
                <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                    <a href="admin.php" class="nav-link btn-dashboard">Dashboard</a>
                <?php else: ?>
                    <a href="admin.php" class="nav-link btn-dashboard">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="hero">
        <h1><?php echo $singlePost ? $singlePost['title'] : $heroTitle; ?></h1>
        <p><?php echo $singlePost ? '' : $heroSub; ?></p>
    </section>

    <form action="index.php" method="GET" class="mobile-search">
        <input type="text" name="q" placeholder="Search posts..." value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
    </form>

    <div class="container">
        
        <main class="main-content">
            <?php if ($view === 'single' && $singlePost): ?>
                
                <div class="single-post-view">
                    <a href="index.php" style="display:inline-block; margin-bottom:1rem; color:#64748b;">&larr; Back to Home</a>
                    <div style="color:#64748b; font-size:0.9rem; margin-bottom:1.5rem;">
                        <span><?php echo $singlePost['category']; ?></span> | <span><?php echo $singlePost['date']; ?></span>
                    </div>
                    <?php if($singlePost['image']): ?>
                        <img src="<?php echo $singlePost['image']; ?>" class="single-post-img">
                    <?php endif; ?>
                    <div class="single-post-content">
                        <?php echo $singlePost['content']; ?>
                    </div>
                </div>

            <?php else: ?>
                
                <div class="grid-container">
                    <?php if (count($displayPosts) > 0): ?>
                        <?php foreach($displayPosts as $post): 
                            $img = $post['image'] ?: $defaultCover;
                            $excerpt = substr(strip_tags($post['content']), 0, 100) . '...';
                        ?>
                        <a href="index.php?id=<?php echo $post['id']; ?>" class="post-card">
                            <img src="<?php echo $img; ?>" loading="lazy" class="post-img">
                            <div class="post-body">
                                <h2 class="post-title"><?php echo $post['title']; ?></h2>
                                <p class="post-excerpt"><?php echo $excerpt; ?></p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No posts found.</p>
                    <?php endif; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php 
                    $range = 2;
                    if ($page > 1) echo '<a href="?page='.($page-1).($currentCat ? '&category='.$currentCat : '').($searchQuery ? '&q='.$searchQuery : '').'" class="page-btn">&laquo; Prev</a>';
                    
                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                            $active = ($i == $page) ? 'active' : '';
                            echo '<a href="?page='.$i.($currentCat ? '&category='.$currentCat : '').($searchQuery ? '&q='.$searchQuery : '').'" class="page-btn '.$active.'">'.$i.'</a>';
                        } elseif ($i == $page - $range - 1 || $i == $page + $range + 1) {
                            echo '<span class="page-btn dots">...</span>';
                        }
                    }

                    if ($page < $totalPages) echo '<a href="?page='.($page+1).($currentCat ? '&category='.$currentCat : '').($searchQuery ? '&q='.$searchQuery : '').'" class="page-btn">Next &raquo;</a>';
                    ?>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </main>

        <aside class="sidebar">
            
            <div class="widget">
                <h3>Categories</h3>
                <ul class="cat-list">
                    <?php 
                    $catCounts = array_count_values(array_column($posts, 'category'));
                    foreach($categories as $cat): 
                        $count = $catCounts[$cat] ?? 0;
                    ?>
                        <li><a href="index.php?category=<?php echo urlencode($cat); ?>">
                            <?php echo $cat; ?> <span>(<?php echo $count; ?>)</span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($view === 'single' && $singlePost): 
                $related = array_filter($posts, function($p) use ($singlePost) {
                    return $p['category'] === $singlePost['category'] && $p['id'] !== $singlePost['id'];
                });
                $related = array_slice($related, 0, 5); 
            ?>
                <?php if (!empty($related)): ?>
                <div class="widget">
                    <h3>Related Posts</h3>
                    <?php foreach($related as $r): ?>
                        <a href="index.php?id=<?php echo $r['id']; ?>" class="related-post-link">
                            <span class="related-title"><?php echo $r['title']; ?></span>
                            <span class="related-date"><?php echo $r['date']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        </aside>
    </div>

    <script>
        window.onscroll = function() { 
            const btn = document.getElementById("scrollTopBtn");
            if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) btn.style.display = "flex";
            else btn.style.display = "none";
        };
        function scrollToTop() { window.scrollTo({top: 0, behavior: 'smooth'}); }
    </script>
</body>
</html>