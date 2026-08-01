<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch Data
$customers = $pdo->query("SELECT * FROM customer ORDER BY customer_id ASC LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
$farmers = $pdo->query("SELECT * FROM farmer ORDER BY farmer_id ASC LIMIT $limit OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND user_type = 'admin' AND is_read = FALSE");
$stmt->execute(['uid' => $_SESSION['admin_id']]);
$unread_count = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users | Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Raleway:wght@300;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --gold: #b89b5e;
        --charcoal: #2c2c2c;
        --cream: #f9f7f2;
        --border: #453c34;
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 70px;
    }

    html, body { 
        height: 100vh; 
        overflow: hidden; 
        margin: 0; 
        font-family: 'Raleway', sans-serif; 
        background: var(--cream); 
    }

    body { display: flex; }

    .sidebar { 
        width: var(--sidebar-width); 
        background: var(--charcoal); 
        color: white; 
        height: 100vh; 
        position: fixed; 
        border-right: 3px solid var(--gold); 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        z-index: 1000; 
        overflow: hidden; 
    }
    .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
    .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid #444; font-family: 'Cinzel', serif; white-space: nowrap; }
    #sidebarCollapse { position: absolute; top: 15px; right: -15px; background: var(--gold); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid var(--charcoal); z-index: 1001; transition: 0.3s; }
    .sidebar.collapsed #sidebarCollapse { right: 20px; }
    .nav-links { list-style: none; padding: 0; margin-top: 20px; }
    .nav-links li a { display: flex; align-items: center; padding: 15px 25px; color: #ccc; text-decoration: none; transition: 0.3s; font-family: 'Cinzel', serif; font-size: 0.9rem; }
    .nav-links i { margin-right: 20px; width: 20px; font-size: 1.1rem; text-align: center; }
    .sidebar.collapsed .link-text, .sidebar.collapsed .sidebar-header h3 { display: none; }
    .nav-links li a:hover, .nav-links li a.active { background: var(--gold); color: white; }

    .main-content { 
        margin-left: var(--sidebar-width); 
        flex: 1; 
        display: flex;
        flex-direction: column; 
        height: 100vh;
        padding: 20px 30px; 
        box-sizing: border-box;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden; 
    }
    body.collapsed-active .main-content { margin-left: var(--sidebar-collapsed-width); }

    .top-bar, .breadcrumbs, .table-tools, .tabs { flex-shrink: 0; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 2px solid var(--border); padding-bottom: 10px; }
    .top-bar h1 { font-family: 'Cinzel', serif; margin: 0; color: var(--charcoal); font-size: 1.8rem; }
    .breadcrumbs { margin-bottom: 20px; font-size: 0.8rem; font-family: 'Cinzel', serif; color: #777; text-transform: uppercase; letter-spacing: 1px; padding-top: 10px; font-weight: bold;}
    .breadcrumbs a { color: var(--gold); text-decoration: none; }
    .breadcrumbs i { color: var(--gold);}

    .table-tools { 
        display: flex !important;
        flex-wrap: nowrap !important;
        align-items: flex-end !important; 
        gap: 12px !important;
        background: white !important;
        padding: 20px !important;
        border-radius: 12px !important;
        border: 1px solid #eaddca !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03) !important;
    }

    .tool-item { 
        flex: 1 !important; 
        display: flex;
        flex-direction: column;
        gap: 6px; 
    }

    .tool-item:first-child { 
        flex: 2 !important; 
    }

    .tool-item label { 
        font-family: 'Cinzel', serif; 
        font-size: 0.7rem; 
        color: var(--gold); 
        font-weight: bold; 
        text-transform: uppercase;
        margin: 0;
    }

    .search-box {
        position: relative;
        display: flex;
        align-items: center;
    }

    .search-box i {
        position: absolute;
        left: 12px;
        color: #aaa;
        margin: 0 !important; 
    }

    .search-box input, 
    .filter-input { 
        width: 100% !important; 
        height: 42px !important; 
        padding: 8px 12px; 
        border: 1px solid #ddd !important;
        border-radius: 6px !important; 
        background: #fafafa !important; 
        box-sizing: border-box;
    }

    .btn-tools {
        height: 42px !important; 
        padding: 0 15px !important;
        border-radius: 6px !important;
        font-family: 'Cinzel', serif;
        font-size: 0.75rem;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        transition: 0.2s;
        background: white;
        border: 1px solid #ddd;
        box-sizing: border-box;
    }
    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        background-color: white;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1002;
        border-radius: 8px;
        border: 1px solid var(--border);
        margin-top: 5px;
    }

    .dropdown-content a {
        color: var(--charcoal);
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        font-family: 'Raleway', sans-serif;
        font-size: 0.85rem;
        transition: 0.2s;
    }

    .dropdown-content a i {
        margin-right: 10px;
        color: var(--gold);
    }

    .dropdown-content a:hover {
        background-color: var(--cream);
        color: var(--gold);
    }

    .show { display: block; }

    .btn-add-user {
        background: var(--gold) !important;
        color: white !important;
        border: none !important;
    }

    .btn-add-user:hover {
        background: #a38950 !important;
    }

    .btn-bulk-delete {
        border-color: #c62828 !important;
        color: #c62828 !important;
    }

    .btn-bulk-delete:hover {
        background: #c62828 !important;
        color: white !important;
    }

    .btn-clear {
        background: white !important;
        border: 1px solid #ddd !important;
        color: #666 !important;
    }

    .btn-clear:hover {
        background: #f5f5f5 !important;
        border-color: #bbb !important;
    }

    .dashboard-section {
        flex: 1; 
        overflow-y: auto !important; 
        overflow-x: auto !important; 
        border-radius: 12px;
        margin-bottom: 5px;
        background: white;
    }

    .user-table { 
        width: 100% !important;
        min-width: 100% !important; 
        border-collapse: separate !important; 
        border-spacing: 0 5px !important;
        table-layout: auto;
    }

    .user-table thead th {
        position: sticky !important;
        top: 0;
        z-index: 10;
        background: white !important;
        padding: 12px 10px; 
        font-family: 'Cinzel', serif; font-size: 0.7rem; color: var(--gold); text-transform: uppercase; text-align: left;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
    }

    .user-table td { 
        background: white !important; 
        padding: 10px !important; 
        font-size: 0.8rem; 
        vertical-align: middle;
        border-top: 1px solid #eee !important; 
        border-bottom: 1px solid #eee !important;
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis;
        max-width: 150px; 
    }

    .pagination { 
        flex-shrink: 0;
        display: flex !important; 
        justify-content: center !important; 
        align-items: center !important; 
        gap: 5px !important; 
        padding: 10px 0;
    }

    .tabs { display: flex; gap: 10px; margin-bottom: 10px; flex-shrink: 0; margin-top:20px; justify-content:center; align-items:center; }
    .tab-btn { padding: 10px 20px; cursor: pointer; border: 2px solid var(--gold);; border-radius: 8px; background: none; font-family: 'Cinzel', serif; font-size: 0.85rem; font-weight: bold; color:var(--gold); position: relative; }
    .tab-btn:hover {
        background: var(--gold); 
        color:white;
    }

    .tab-btn.active { 
        background: var(--gold); 
        color: #ffffff; 
    }

    .tab-btn.active::after { 
        content: ''; 
        position: absolute; 
        bottom: 0; 
        left: 0; 
        width: 100%; 
        height: 3px; 
        background: var(--gold); 
        display: none; 
        color: white;
    }
    .status-pill { padding: 3px 8px; border-radius: 4px; font-size: 0.6rem; font-family: 'Cinzel', serif; font-weight: bold; text-transform: uppercase; }
    .status-active { background: #e8f5e9; color: #2e7d32; }
    .status-pending { background: #fff3e0; color: #ef6c00; }
    
    .btn-action { width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; cursor: pointer; font-size: 0.75rem; }
    .btn-edit { background: #f0f0f0; color: var(--charcoal); }
    .btn-delete { background: #ffebee; color: #c62828; }

    .dashboard-section::-webkit-scrollbar { width: 5px; height: 5px; }
    .dashboard-section::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 10px; }
</style>
</head>
<body id="bodyTag">

<div class="sidebar" id="sidebar">
    <div id="sidebarCollapse"><i class="fas fa-chevron-left" id="toggleIcon"></i></div>
    <div class="sidebar-header"><h3>Admin Portal</h3></div>
    <ul class="nav-links">
        <li><a href="admin_dashboard.php"><i class="fas fa-th-large"></i> <span class="link-text">Overview</span></a></li>
        <li><a href="manage_users.php" class="active"><i class="fas fa-users"></i> <span class="link-text">Manage Users</span></a></li>
        <li><a href="verify_listings.php"><i class="fas fa-check-circle"></i> <span class="link-text">Verify Listings</span></a></li>
        <li><a href="manage_auctions.php"><i class="fas fa-gavel"></i> <span class="link-text">Manage Auctions</span></a></li>
        <li><a href="send_notifications.php"><i class="fas fa-bullhorn"></i> <span class="link-text">Send Notifications</span></a></li>
        <li><a href="logout.php" style="margin-top: 50px; color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1>User Management</h1>
        <div class="date"><i class="fas fa-calendar"></i> <?= date('D, d M Y') ?></div>
    </div>

    <div class="breadcrumbs">
        <i class="fas fa-home"></i><a href="admin_dashboard.php"> Dashboard</a><span> > </span> Users
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div id="successMsg" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-family: 'Raleway', sans-serif; font-size: 0.9rem; display: flex; align-items: center; justify-content: space-between; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <span><i class="fas fa-check-circle" style="margin-right: 10px;"></i> User profile has been successfully updated!</span>
            <i class="fas fa-times" style="cursor: pointer; opacity: 0.7;" onclick="this.parentElement.style.display='none'"></i>
        </div>
    <?php endif; ?>

    <div class="table-tools">
        <div class="tool-item">
            <label for="userSearch">Search Users</label>
            <div class="search-box">
                <i class="fas fa-search" style="position: absolute; margin: 12px; color: #aaa;"></i>
                <input type="text" id="userSearch" style="padding-left: 35px;" placeholder="Name, Email, or ID..." onkeyup="filterTable()">
            </div>
        </div>
        <div class="tool-item">
            <label for="statusFilter">Filter Status</label>
            <select id="statusFilter" class="filter-input" onchange="filterTable()">
                <option value="">All Status</option>
                <option value="verified">Verified</option>
                <option value="unverified">Unverified</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
            </select>
        </div>
        <div class="tool-item">
            <label for="dateFilter">Joined On</label>
            <input type="date" id="dateFilter" class="filter-input" onchange="filterTable()">
        </div>
        <div style="display: flex; gap: 8px;">
            <!-- <div class="dropdown">
                <button onclick="toggleAddDropdown()" class="btn-tools btn-add-user">
                    <i class="fas fa-user-plus"></i> Add User <i class="fas fa-caret-down"></i>
                </button>
                <div id="addUserDropdown" class="dropdown-content">
                    <a href="add_customer.php"><i class="fas fa-shopping-cart"></i> New Customer</a>
                    <a href="add_farmer.php"><i class="fas fa-tractor"></i> New Farmer</a>
                </div>
            </div> -->
            <button onclick="clearFilters()" class="btn-tools btn-clear" style="border: 1px solid #ddd; background: #fff; padding: 0 12px; height: 38px; border-radius: 6px; cursor: pointer; font-family: 'Cinzel'; font-size: 0.7rem;">
                <i class="fas fa-undo"></i> Reset
            </button>
            <button class="btn-tools btn-bulk-delete" style="border: 1px solid #c62828; color: #c62828; background: #fff; padding: 0 12px; height: 38px; border-radius: 6px; cursor: pointer; font-family: 'Cinzel'; font-size: 0.7rem;">
                <i class="fas fa-trash-alt"></i> Delete
            </button>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('customers', event)">Customers</button>
        <button class="tab-btn" onclick="showTab('farmers', event)">Farmers</button>
    </div>

    <div class="dashboard-section">
        <div id="customers" class="tab-content">
            <table class="user-table" id="customerTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" onclick="toggleSelectAll('customer', this)"></th>
                        <th>No.</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Email Ver.</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $index => $c): 
                        $rowNumber = $index + 1 + $offset;
                        $isVerified = (strtolower($c['verify_status'] ?? '') == 'verified' || ($c['verify_status'] ?? '') == 1);
                        $emailStatus = $isVerified ? 'verified' : 'unverified';
                        $filterMeta = "approved " . $emailStatus;
                    ?>
                    <tr data-status="<?= $filterMeta ?>" data-date="<?= $c['date_registered'] ?>">
                        <td><input type="checkbox" class="customer-check"></td>
                        <td style="font-weight: bold; color: var(--gold);"><?= $rowNumber ?>.</td>
                        <td><span class="status-pill" style="background: #e3f2fd; color: #1976d2;">Customer</span></td>
                        <td title="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></td>
                        <td title="<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></td>
                        <td><?= htmlspecialchars($c['phone_number']) ?></td>
                        <td title="<?= htmlspecialchars($c['address']) ?>"><?= htmlspecialchars($c['address']) ?></td>
                        <td><span class="status-pill status-active">Active</span></td>
                        <td><span class="status-pill <?= $isVerified ? 'status-active' : 'status-pending' ?>"><?= $isVerified ? 'Verified' : 'Unverified' ?></span></td>
                        <td><?= date('Y-m-d', strtotime($c['date_registered'])) ?></td>
                        <td>
                            <a href="edit_user.php?type=customer&id=<?= $c['customer_id'] ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <a href="user_actions.php?type=customer&id=<?= $c['customer_id'] ?>&action=delete" 
                             class="btn-action btn-delete" 
                             onclick="return confirm('Confirm deletion?')">
                             <i class="fas fa-trash"></i>
                         </a>
                     </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="farmers" class="tab-content" style="display:none;">
            <table class="user-table" id="farmerTable">
                <thead>
                    <tr>
                        <th><input type="checkbox" onclick="toggleSelectAll('farmer', this)"></th>
                        <th>No.</th>
                        <th>Type</th>
                        <th>Farm Name</th>
                        <th>Farmer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Reg No.</th>
                        <th>Cert</th>
                        <th>Account</th>
                        <th>Email Ver.</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($farmers as $index => $f): 
                        $rowNumber = $index + 1 + $offset;
                        $accStatus = trim(strtolower($f['verified_status'] ?? 'pending'));
                        $isEmailVer = (trim(strtolower($f['verify_status'] ?? '')) == 'verified');
                        $emailStatus = $isEmailVer ? 'verified' : 'unverified';
                        $filterMeta = $accStatus . " " . $emailStatus;
                    ?>
                    <tr data-status="<?= $filterMeta ?>" data-date="<?= $f['date_registered'] ?? '' ?>">
                        <td><input type="checkbox" class="farmer-check"></td>
                        <td style="font-weight: bold; color: var(--gold);"><?= $rowNumber ?>.</td>
                        <td><span class="status-pill" style="background: #f1f8e9; color: #388e3c;">Farmer</span></td>
                        <td title="<?= htmlspecialchars($f['farm_name']) ?>"><strong><?= htmlspecialchars($f['farm_name']) ?></strong></td>
                        <td><?= htmlspecialchars($f['name']) ?></td>
                        <td><?= htmlspecialchars($f['email']) ?></td>
                        <td><?= htmlspecialchars($f['phone_number']) ?></td>
                        <td title="<?= htmlspecialchars($f['address']) ?>"><?= htmlspecialchars($f['address']) ?></td>
                        <td><?= htmlspecialchars($f['registration_number']) ?></td>
                        <td><a href="../uploads/certificates/<?= $f['business_cert'] ?>" target="_blank" style="color: var(--gold);">View</a></td>
                        <td><span class="status-pill <?= ($accStatus == 'approved') ? 'status-active' : 'status-pending' ?>"><?= $accStatus ?></span></td>
                        <td><span class="status-pill <?= $isEmailVer ? 'status-active' : 'status-pending' ?>"><?= $isEmailVer ? 'Verified' : 'Unverified' ?></span></td>
                        <td><?= !empty($f['date_registered']) ? date('Y-m-d', strtotime($f['date_registered'])) : 'N/A' ?></td>
                        <td>
                            <a href="edit_user.php?type=farmer&id=<?= $f['farmer_id'] ?>" class="btn-action btn-edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>

                            <?php if($accStatus == 'pending'): ?>
                                <a href="user_actions.php?type=farmer&id=<?= $f['farmer_id'] ?>&action=approve" 
                                 class="btn-action" 
                                 style="background: #e8f5e9; color: #2e7d32;" 
                                 title="Approve">
                                 <i class="fas fa-check"></i>
                             </a>

                             <a href="user_actions.php?type=farmer&id=<?= $f['farmer_id'] ?>&action=reject" 
                                 class="btn-action" 
                                 style="background: #fff3e0; color: #e65100; margin-left: 5px;" 
                                 onclick="return confirm('Reject this farmer application?')" 
                                 title="Reject">
                                 <i class="fas fa-times"></i>
                             </a>
                         <?php endif; ?>

                         <a href="user_actions.php?type=farmer&id=<?= $f['farmer_id'] ?>&action=delete" 
                             class="btn-action btn-delete" 
                             onclick="return confirm('Confirm deletion?')" 
                             title="Delete">
                             <i class="fas fa-trash"></i>
                         </a>
                     </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- <div class="pagination">
        <a href="#" class="page-link active">1</a>
        <a href="#" class="page-link">2</a>
        <a href="#" class="page-link">3</a>
    </div> -->
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const bodyTag = document.getElementById('bodyTag');
    const toggleBtn = document.getElementById('sidebarCollapse');
    const toggleIcon = document.getElementById('toggleIcon');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        bodyTag.classList.toggle('collapsed-active');
        toggleIcon.classList.toggle('fa-chevron-left');
        toggleIcon.classList.toggle('fa-chevron-right');
    });

    function showTab(tabId, event) {
        const customers = document.getElementById('customers');
        const farmers = document.getElementById('farmers');
        const allHeaders = document.querySelectorAll('.all-only-header');

        customers.style.display = 'none';
        farmers.style.display = 'none';
        allHeaders.forEach(h => h.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

        if (tabId === 'all') {
            customers.style.display = 'block';
            farmers.style.display = 'block';
            allHeaders.forEach(h => h.style.display = 'block'); 
        } else {
            document.getElementById(tabId).style.display = 'block';
        }

        event.currentTarget.classList.add('active');
        filterTable(); 
    }

    function filterTable() {
        const searchText = document.getElementById('userSearch').value.toLowerCase();
        const statusValue = document.getElementById('statusFilter').value.toLowerCase();
        const dateValue = document.getElementById('dateFilter').value;

        const visibleSections = document.querySelectorAll('.tab-content:not([style*="none"])');

        visibleSections.forEach(section => {
            const rows = section.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rowStatus = (row.getAttribute('data-status') || "").toLowerCase();
                const rowText = row.textContent.toLowerCase();
                const rowDate = row.getAttribute('data-date') || ""; 

                const matchesSearch = rowText.includes(searchText);
                const statusRegex = new RegExp('\\b' + statusValue + '\\b');
                const matchesStatus = statusValue === "" || statusRegex.test(rowStatus);
                const matchesDate = dateValue === "" || rowDate.startsWith(dateValue);

                row.style.display = (matchesSearch && matchesStatus && matchesDate) ? "" : "none";
            });
        });
    }

    function clearFilters() {
        document.getElementById('userSearch').value = "";
        document.getElementById('statusFilter').value = "";
        document.getElementById('dateFilter').value = "";
        filterTable();
    }

    function toggleSelectAll(type, master) {
        const checkboxes = document.getElementsByClassName(type + '-check');
        for (let cb of checkboxes) {
            cb.checked = master.checked;
        }
    }
    function toggleAddDropdown() {
        document.getElementById("addUserDropdown").classList.toggle("show");
    }

    window.onclick = function(event) {
        if (!event.target.matches('.btn-add-user') && !event.target.closest('.btn-add-user')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

    window.addEventListener('DOMContentLoaded', () => {
        const msg = document.getElementById('successMsg');
        if (msg) {
            setTimeout(() => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.style.display = 'none', 500);
            }, 4000); // Disappears after 4 seconds
        }
    });
</script>
</body>
</html>