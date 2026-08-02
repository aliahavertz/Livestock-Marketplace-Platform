<?php
$projectFolder = "/LivestockMarketplace";
$db_path = $_SERVER['DOCUMENT_ROOT'] . $projectFolder . "/db_connect.php";

if (file_exists($db_path)) { 
    include_once($db_path); 
} else {
    include_once(__DIR__ . "/../db_connect.php");
}

$userName = "User";
$customer = null;
$farmer = null; 
$imagePath = ""; 
$unreadCount = 0; 

if (isset($_SESSION['customer_id'])) {
    $stmt = $pdo->prepare("SELECT name, profile_image FROM customer WHERE customer_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        $userName = $customer['name'];
        $fileName = !empty($customer['profile_image']) ? $customer['profile_image'] : "default.png";
        $imagePath = $projectFolder . "/Models/uploads/" . $fileName;
        
        $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_type = 'customer' AND is_read = FALSE");
        $notifStmt->execute([$_SESSION['customer_id']]);
        $unreadCount = $notifStmt->fetchColumn();
    }
} 

$cartCount = 0;

if (isset($_SESSION['customer_id'])) {
    $cartStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM cart c
        JOIN livestock l ON c.livestock_id = l.livestock_id
        WHERE c.customer_id = ? AND l.availability_status = 'Available'
        ");
    $cartStmt->execute([$_SESSION['customer_id']]);
    $cartCount = (int)$cartStmt->fetchColumn();
} else {
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        $cartIds = array_filter(array_map('intval', $_SESSION['cart']));
        
        if (!empty($cartIds)) {
            $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM livestock WHERE livestock_id IN ($placeholders) AND availability_status = 'Available'");
            $checkStmt->execute($cartIds);
            $cartCount = (int)$checkStmt->fetchColumn();
        } else {
            $cartCount = 0;
        }
    } 
    elseif (isset($_COOKIE['persistent_cart'])) {
        $cookieData = json_decode($_COOKIE['persistent_cart'], true);
        if (is_array($cookieData) && !empty($cookieData)) {
            $cookieIds = array_filter(array_map('intval', $cookieData));
            if (!empty($cookieIds)) {
                $placeholders = implode(',', array_fill(0, count($cookieIds), '?'));
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM livestock WHERE livestock_id IN ($placeholders) AND availability_status = 'Available'");
                $checkStmt->execute($cookieIds);
                $cartCount = (int)$checkStmt->fetchColumn();
            }
        }
    }
}

$menu_categories = [];
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT INITCAP(category) as category, INITCAP(breed) as breed 
                         FROM livestock 
                         WHERE availability_status != 'Pending' 
                         AND breed IS NOT NULL 
                         GROUP BY INITCAP(category), INITCAP(breed) 
                         ORDER BY category, breed");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $menu_categories[$row['category']][] = $row['breed'];
    }
}

// 2. Fetch counts of strictly 'Available' items grouped by Category and Breed
$availability_counts = [];
if (isset($pdo)) {
    $countStmt = $pdo->query("SELECT INITCAP(category) as category, INITCAP(breed) as breed, COUNT(*) as total 
                              FROM livestock 
                              WHERE availability_status = 'Available' 
                              GROUP BY INITCAP(category), INITCAP(breed)");
    while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
        $availability_counts[$row['category']]['total_category_items'] = ($availability_counts[$row['category']]['total_category_items'] ?? 0) + $row['total'];
        $availability_counts[$row['category']]['breeds'][$row['breed']] = $row['total'];
    }
}

$new_arrivals = [];
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT livestock_id, name, breed FROM livestock 
       WHERE availability_status = 'Available' 
       AND date_listed >= NOW() - INTERVAL '7 days' 
       ORDER BY date_listed DESC LIMIT 5");
    $new_arrivals = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$dashboardPage = $projectFolder . "/Models/customer_dashboard.php";
$is_home = (basename($_SERVER['PHP_SELF']) == 'index.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    body { margin: 0; padding: 0; padding-top: <?= $is_home ? '0' : '80px' ?>; }

    .vintage-header {
        position: fixed;
        top: 0; 
        left: 0; 
        width: 100%;
        z-index: 10000;
        background: transparent; 
        transition: all 0.35s ease;
    }

    .vintage-header.inner-page,
    .vintage-header.scrolled {
        background: linear-gradient(135deg, #90caf9, #64b5f6) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .vintage-header-inner {
        position: relative;
        max-width: 1400px;
        margin: auto;
        padding: 0 40px; 
        display: flex;
        align-items: center; 
        justify-content: space-between;
        z-index: 10002;
        height: 80px; 
    }

    .nav-left-section { 
        display: flex; 
        align-items: center; 
        gap: 30px; 
        height: 100%;
    }

    .vintage-logo img { 
        height: 65px; 
        display: block; 
        transform: translateY(-5px);
        transition: 0.3s; 
        width: auto;
    }

    .vintage-header.scrolled .vintage-logo img,
    .vintage-header.inner-page .vintage-logo img { filter: brightness(0.2); }

    .category-nav { display: flex; gap: 15px; }
    .cat-item { position: relative; }
    .cat-link {
        font-family: 'Cinzel', serif;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        color: #fffaf0;
        text-decoration: none;
        padding: 20px 10px;
        display: block;
        transition: color 0.3s;
    }

    .vintage-header.scrolled .cat-link,
    .vintage-header.inner-page .cat-link { color: #0d1b2a; }
    .cat-link:hover { color: #1976d2 !important; }

    .breed-menu {
        display: none; position: absolute; top: 100%; left: 0;
        background: #fdf6ec; min-width: 200px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        padding: 0; z-index: 10005;
    }

    .cat-item:hover .breed-menu { display: block; }
    .breed-menu a {
        display: block; padding: 12px 20px; font-family: 'PT Serif', serif;
        font-size: 14px; color: #453c34; text-decoration: none;
        transition: 0.2s; 
    }
    .breed-menu a:hover { background: #90caf9; color: #fff; }

    .nav-right-section { display: flex; align-items: center; gap: 20px; height: 100%;}
    .nav-right-section a, .dropbtn {
        font-family: 'Cinzel', serif; font-size: 13px; font-weight: 700;
        color: #fffaf0; text-decoration: none; transition: 0.3s;
    }

    .vintage-header .nav-right-section a.notif-link:hover,
    .vintage-header.inner-page .nav-right-section a.notif-link:hover,
    .vintage-header.scrolled .nav-right-section a.notif-link:hover {
        background-color: #000000 !important;
        color: #ffffff !important;
        border-radius: 50px;
        padding: 10px;
    }

    .vintage-header .nav-right-section a.notif-link:hover i,
    .vintage-header.inner-page .nav-right-section a.notif-link:hover i,
    .vintage-header.scrolled .nav-right-section a.notif-link:hover i {
        color: #ffffff !important;
    }

    .nav-profile-img { width: 34px; height: 34px; border-radius: 50%; border: 2px solid #fff; object-fit: cover; }
    .notif-badge {
        background: #d32f2f; color: white; font-size: 10px;
        padding: 2px 6px; border-radius: 50%; position: relative; top: -10px; left: -8px;
    }
    .cart-badge { background: #f4efe6; color: black;} 

    .dropdown {
        position: relative;
        padding-bottom: 15px; 
        margin-bottom: -15px;
    }

    .btn-pill {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background-color: #000000; 
        color: #ffffff !important;
        padding: 8px 25px;
        border-radius: 50px; 
        font-family: 'Cinzel', serif;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        border: 1px solid transparent;
    }

    .btn-pill i { font-size: 10px; transition: transform 0.3s ease; }
    .btn-pill:hover {
        background-color: #333333;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }

    .dropdown:hover .btn-pill i { transform: rotate(180deg); }

    .dropdown-cont {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background: #ffffff; 
        min-width: 220px;
        border-radius: 4px; 
        box-shadow: 0 15px 35px rgba(0,0,0,0.15), 0 5px 15px rgba(0,0,0,0.05);
        border: 1px solid #e0dcd0; 
        overflow: hidden; 
        z-index: 10005;
    }

    .dropdown-cont a {
        color: #453c34 !important; 
        display: block; 
        padding: 12px; 
        font-family: 'PT Serif', serif; 
        border-bottom: 1px solid #eee;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .dropdown-cont a i { width: 18px; color: #64b5f6; font-size: 15px; }
    .dropdown-cont a:hover { background-color: #f9fbfd; padding-left: 25px; color: #1976d2 !important; }
    .dropdown:hover .dropdown-cont { display: block; animation: vintageSlideIn 0.3s ease-out; }

    .mobile-menu-toggle {
        display: none;
        background: transparent;
        border: none;
        font-size: 24px;
        color: #fffaf0;
        cursor: pointer;
        z-index: 10010;
        transition: color 0.3s;
    }
    .vintage-header.scrolled .mobile-menu-toggle,
    .vintage-header.inner-page .mobile-menu-toggle {
        color: #0d1b2a;
    }

    .search-dropdown-bar {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background: #ffffff;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        z-index: 10001; 
    }
    .search-dropdown-bar.open { max-height: 85px; }
    .search-bar-inner { max-width: 1200px; margin: 0 auto; padding: 18px 40px; }
    .search-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
        max-width: 700px; 
        margin: 0 auto;
        background: #f7f7f7;
        border-radius: 4px;
        padding: 2px 15px;
        border: 1px solid #ddd;
    }
    .search-input-wrapper input {
        width: 100%;
        border: none;
        background: transparent;
        outline: none;
        padding: 10px 80px 10px 0;
        font-family: 'PT Serif', serif;
        font-size: 15px;
        color: #2c2c2c;
    }
    .search-submit-btn { position: absolute; right: 50px; border: none; background: transparent; color: #555; cursor: pointer; font-size: 16px; padding: 8px; }
    .search-close-btn { position: absolute; right: 15px; border: none; background: transparent; color: #777; cursor: pointer; font-size: 18px; padding: 8px; }

    @keyframes vintageSlideIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 1024px) {
        body { padding-top: 70px; } 

        .vintage-header-inner { padding: 10px 20px; }

        .mobile-menu-toggle { display: block; }

        .nav-left-section {
            position: fixed;
            top: 0;
            left: -100%; 
            width: 280px;
            height: 100vh;
            background: #fdf6ec;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            padding: 80px 25px 30px 25px;
            gap: 20px;
            box-shadow: 4px 0 15px rgba(0,0,0,0.15);
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            z-index: 10008;
        }

        .nav-left-section.active { left: 0; }

        .category-nav {
            flex-direction: column;
            width: 100%;
            gap: 5px;
        }

        .cat-link {
            color: #453c34 !important;
            padding: 12px 5px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            width: 100%;
        }

        .breed-menu {
            position: relative;
            top: 0;
            left: 0;
            width: 100%;
            box-shadow: none;
            background: rgba(144, 202, 249, 0.1);
            border-radius: 6px;
            margin-top: 5px;
            display: none; 
        }
        
        .cat-item.open-menu .breed-menu { display: block; }

        .user-display-name { display: none; } 
        .nav-right-section { gap: 12px; }
        .btn-pill { padding: 6px 16px; font-size: 12px; }
    }

    @media (max-width: 480px) {
        .nav-right-section a:not(.search-toggle-btn):not(.cart-link) {
            display: none; 
        }
        .nav-right-section { gap: 15px; }
        .search-bar-inner { padding: 15px 15px; }
    }
</style>

<header class="vintage-header <?= (!$is_home) ? 'inner-page' : '' ?>" id="vHeader">
    <div class="vintage-header-inner">
        <div class="nav-left-section" id="navLeftSection">
            <div class="vintage-logo">
                <a href="<?= $projectFolder ?>/index.php">
                    <img src="<?= $projectFolder ?>/assets/LOGO FYP baru.png" alt="RanchLink">
                </a>
            </div>

            <nav class="category-nav">
                <div class="cat-item">
                    <a href="<?= $dashboardPage ?>?filter=new_arrivals#listings" class="cat-link" style="color: #ffca28;">
                        <i class="fas fa-sparkles"></i> New Arrivals
                    </a>
                    
                    <?php if (!empty($new_arrivals)): ?>
                        <div class="breed-menu">
                            <?php foreach ($new_arrivals as $new): ?>
                                <a href="<?= $projectFolder ?>/Models/livestock_view.php?livestock_id=<?= $new['livestock_id'] ?>">
                                    <small style="color: #1976d2; font-size: 10px; display: block;">NEW</small>
                                    <?= htmlspecialchars($new['name']) ?> (<?= htmlspecialchars($new['breed']) ?>)
                                </a>
                            <?php endforeach; ?>
                            <a href="<?= $dashboardPage ?>?filter=new_arrivals#listings" style="text-align: center; font-weight: bold; border-top: 1px solid #ddd;">
                                View All New
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="breed-menu">
                            <a href="#">No new arrivals this week</a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php foreach ($menu_categories as $catName => $breeds): ?>
                    <?php 
                    $catHasAvailableItems = isset($availability_counts[$catName]['total_category_items']) && $availability_counts[$catName]['total_category_items'] > 0;
                    ?>
                    <div class="cat-item">
                        <a href="<?= $dashboardPage ?>?category=<?= urlencode($catName) ?>#listings" class="cat-link">
                            <?= htmlspecialchars($catName) ?>
                        </a>

                        <div class="breed-menu">
                            <?php if (!$catHasAvailableItems): ?>
                                <span class="no-items-msg"><i class="fas fa-exclamation-circle"></i> No available item for this category.</span>
                            <?php endif; ?>

                            <?php foreach ($breeds as $breed): ?>
                                <?php 
                                $breedAvailableCount = $availability_counts[$catName]['breeds'][$breed] ?? 0;
                                
                                if ($breedAvailableCount === 0) {
                                    continue; 
                                }
                                ?>
                                <a href="<?= $dashboardPage ?>?category=<?= urlencode($catName) ?>&breed=<?= urlencode($breed) ?>#listings" style="<?= $breedAvailableCount === 0 ? 'color: #b0bec5; position: relative;' : '' ?>">
                                    <?= htmlspecialchars($breed) ?>
                                    <?php if ($breedAvailableCount === 0): ?>
                                        <small style="font-size: 9px; display: block; color: #cfd8dc; font-style: italic;">(Out of Stock)</small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </nav>
        </div>

        <div style="display: flex; align-items: center; gap: 15px;">
            <button class="mobile-menu-toggle" id="mobileMenuBtn" onclick="toggleMobileNav(event)" title="Toggle Menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div class="nav-right-section">
            <a href="javascript:void(0);" class="notif-link search-toggle-btn" title="Search" onclick="toggleSearchBar(event)">
                <i class="fas fa-search search-toggle-btn"></i>
            </a>
            <a href="<?= $projectFolder ?>/Models/auction_market2.php" class="notif-link"><i class="fas fa-gavel"></i> Auctions</a>

            <?php if (!$customer): ?>
                <div class="dropdown">
                    <a href="#" class="dropbtn btn-pill">
                        Sign In <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 5px;"></i>
                    </a>
                    <div class="dropdown-cont">
                        <a href="<?= $projectFolder ?>/Models/customer_login.php"><i class="fas fa-user"></i> Customer</a>
                        <a href="<?= $projectFolder ?>/Models/farmer_login.php"><i class="fas fa-user-tie"></i> Farmer</a>
                        <hr style="border: 0; border-top: 1px solid #eee; margin: 0;">
                        <a href="<?= $projectFolder ?>/Models/choose_account.php" style="font-weight: bold; color: #1976d2 !important;"><i class="fas fa-user-plus"></i> Sign Up</a>
                    </div>
                </div>
            <?php else: ?>
                 <a href="<?= $projectFolder ?>/Models/my_orders.php" class="notif-link"><i class="fas fa-box"></i> My Orders</a>
                 <a href="<?= $projectFolder ?>/Models/view_feedback.php" class="notif-link"><i class="fas fa-comment"></i> My Feedback</a>
                 <a href="<?= $projectFolder ?>/Models/cart.php" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cartCount > 0): ?>
                        <span class="notif-badge cart-badge"><?= (int)$cartCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= $projectFolder ?>/Models/notifications.php" class="notif-link">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?><span class="notif-badge"><?= $unreadCount ?></span><?php endif; ?>
                </a>
                <div class="dropdown">
                    <div class="profile-trigger" style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <span class="user-display-name" style="font-family:'Cinzel'; font-size:12px; font-weight:700;"><?= htmlspecialchars($userName) ?></span>
                        <img src="<?= $imagePath ?>" class="nav-profile-img">
                    </div>
                    <div class="dropdown-cont">
                        <a href="<?= $projectFolder ?>/Models/customer_dashboard.php"><i class="fas fa-home"></i> Marketplace</a>
                        <a href="<?= $projectFolder ?>/Models/customer_profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="<?= $projectFolder ?>/Models/logout.php" style="color:#d32f2f !important;"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="searchDropdownBar" class="search-dropdown-bar <?= !empty($_GET['search']) ? 'open' : '' ?>">
        <div class="search-bar-inner">
            <form method="GET" action="<?= $dashboardPage ?>#listings" id="headerSearchForm">
                
                <?php if (!empty($_GET['category'])): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($_GET['category']) ?>">
                <?php endif; ?>
                <?php if (!empty($_GET['breed'])): ?>
                    <input type="hidden" name="breed" value="<?= htmlspecialchars($_GET['breed']) ?>">
                <?php endif; ?>
                <?php if (!empty($_GET['filter'])): ?>
                    <input type="hidden" name="filter" value="<?= htmlspecialchars($_GET['filter']) ?>">
                <?php endif; ?>

                <div class="search-input-wrapper">
                    <input type="text" name="search" id="headerSearchInput" placeholder="Search by animal name, breed (e.g., Sahiwal, Boer) or category..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    
                    <button type="submit" class="search-submit-btn" title="Submit Search">
                        <i class="fas fa-search"></i>
                    </button>
                    <button type="button" class="search-close-btn" title="Close Search" onclick="toggleSearchBar(event)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</header>

<script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
<script>
    function toggleMobileNav(e) {
        e.stopPropagation();
        const sideNav = document.getElementById("navLeftSection");
        const toggleIcon = document.querySelector("#mobileMenuBtn i");
        
        sideNav.classList.toggle("active");
        
        if (sideNav.classList.contains("active")) {
            toggleIcon.className = "fas fa-times"; 
        } else {
            toggleIcon.className = "fas fa-bars";
        }
    }

    document.addEventListener("DOMContentLoaded", () => {
        const dropToggles = document.querySelectorAll(".mobile-dropdown-toggle");
        
        dropToggles.forEach(toggle => {
            const chevron = toggle.querySelector(".mobile-only-chevron");
            if (window.innerWidth <= 1024 && chevron) {
                chevron.style.display = "inline-block";
            }

            toggle.addEventListener("click", function(e) {
                if (window.innerWidth <= 1024) {
                    e.preventDefault(); 
                    const parentItem = this.closest(".cat-item");
                    parentItem.classList.toggle("open-menu");
                }
            });
        });

        document.addEventListener("click", (e) => {
            const sideNav = document.getElementById("navLeftSection");
            const mobileBtn = document.getElementById("mobileMenuBtn");
            const toggleIcon = document.querySelector("#mobileMenuBtn i");

            if(sideNav && sideNav.classList.contains("active") && !sideNav.contains(e.target) && !mobileBtn.contains(e.target)) {
                sideNav.classList.remove("active");
                if(toggleIcon) toggleIcon.className = "fas fa-bars";
            }
        });
    });
    document.addEventListener("DOMContentLoaded", () => {
        const header = document.getElementById("vHeader");
        const searchInput = document.getElementById("headerSearchInput");
        const searchBar = document.getElementById("searchDropdownBar");

        const onScroll = () => {
            header.classList.toggle("scrolled", window.scrollY > 40);
        };
        window.addEventListener("scroll", onScroll);
        onScroll();

        if (searchBar.classList.contains("open") && searchInput) {
            setTimeout(() => { searchInput.focus(); }, 300);
        }
    });

    function toggleSearchBar(event) {
        if (event) event.preventDefault();
        const searchBar = document.getElementById("searchDropdownBar");
        const searchInput = document.getElementById("headerSearchInput");
        
        searchBar.classList.toggle("open");
        
        if (searchBar.classList.contains("open")) {
            setTimeout(() => { searchInput.focus(); }, 200);
        } else {
            searchInput.value = ""; 
        }
    }

    window.addEventListener("click", function(event) {
        const searchBar = document.getElementById("searchDropdownBar");
        if (!searchBar || !searchBar.classList.contains("open")) return;

        if (event.target.classList.contains("search-toggle-btn")) {
            return;
        }

        const insideHeader = event.target.closest("#vHeader");
        if (!insideHeader) {
            searchBar.classList.remove("open");
        }
    });

    const pusher = new Pusher('c86d192b04d14e240a9f', { cluster: 'ap1' });

    <?php if (isset($_SESSION['customer_id'])): ?>
        const customerId = "<?php echo $_SESSION['customer_id']; ?>";
        const channel = pusher.subscribe('customer-channel-' + customerId);
        channel.bind('order-updated', function(data) {
            alert(data.message);
            if (window.location.href.indexOf("notifications.php") > -1) {
                window.location.reload();
            }
        });
    <?php endif; ?>
</script>