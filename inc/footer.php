<link href="https://fonts.googleapis.com/css2?family=Special+Elite&family=PT+Serif:wght@400;700&family=Playfair+Display:wght@400;700&family=Cinzel:wght@400;700&display=swap" rel="stylesheet">

<style>
    .vintage-footer {
        background: linear-gradient(135deg, #90caf9, #64b5f6); 
        color: #453c34; 
        font-family: 'Cinzel', serif;
        padding-top: 40px;
        position: relative;
        z-index: 10; 
        margin-top: 50px;
    }
    .footer-content {
        display: flex;
        justify-content: space-around;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px 30px;
    }
    .vintage-footer h4 {
        font-family: 'Cinzel', serif;
        font-size: 1.3em;
        color: #fdfae0; 
        margin-bottom: 20px;
        text-transform: uppercase;
        border-bottom: 2px solid rgba(253, 250, 224, 0.3);
        display: inline-block;
        padding-bottom: 5px;
    }
    .footer-column ul {
        list-style: none;
        padding: 0;
    }
    .footer-column ul li {
        margin-bottom: 10px;
    }
    .footer-column a {
        color: #fdfae0;
        text-decoration: none;
        transition: all 0.2s;
        font-family: 'PT Serif', serif;
    }
    .footer-column a:hover {
        color: #003566; 
        padding-left: 5px;
    }
    .contact-info i {
        margin-right: 8px;
        color: #fdfae0;
    }
    .social-links {
        margin-top: 15px;
    }
    .social-links a {
        font-size: 1.5em;
        margin-right: 15px;
        color: #fdfae0;
    }
    .social-links a:hover {
        color: #0a7100; 
    }
    .footer-column-contact-info p {
        color:#fdfae0;
        font-family: 'PT Serif', serif;
        line-height: 1.6;
    }
    .footer-bottom {
        border-top: 1px solid rgba(69, 60, 52, 0.2);
        padding: 15px 20px;
        text-align: center;
        background: linear-gradient(135deg, #90caf9, #64b5f6); 
        font-size: 0.9em;
    }
    .footer-bottom p {
        color:#fdfae0;
        margin: 5px 0;
    }
    .legal-links a {
        color: #fdfae0;
        text-decoration: none;
        margin: 0 10px;
    }
    .legal-links span {
        color: #fdfae0;
    }
    .btn-logout-footer {
        color: #8b0000 !important;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .footer-content {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .footer-column {
            margin-bottom: 30px;
        }
    }
</style>

<footer class="vintage-footer">
    <div class="footer-content">
        
        <div class="footer-column">
            <h4>Marketplace</h4>
            <ul>
                <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="../browse.php">Browse Listings</a></li>
                <li><a href="../Models/Join_Auction.php">Active Auctions</a></li>
                
                <?php if(isset($_SESSION['farmer_id'])): ?>
                    <li><a href="../farmer/manage_listings.php">My Farm Products</a></li>
                <?php elseif(isset($_SESSION['customer_id'])): ?>
                    <li><a href="../Models/my_orders.php">Track My Orders</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="footer-column">
            <h4>Account & Support</h4>
            <ul>
                <?php if(isset($_SESSION['farmer_id'])): ?>
                    <li><a href="../farmer/farmer_dashboard.php">Farmer Dashboard</a></li>
                    <li><a href="../farmer/farmer_profile.php">Farm Settings</a></li>
                    <li><a href="../logout.php" class="btn-logout-footer">Sign Out</a></li>
                <?php elseif(isset($_SESSION['customer_id'])): ?>
                    <li><a href="../Models/customer_dashboard.php">My Dashboard</a></li>
                    <li><a href="../Models/customer_profile.php">Profile Settings</a></li>
                    <li><a href="../Models/logout.php" class="btn-logout-footer">Sign Out</a></li>
                <?php else: ?>
                    <li><a href="Models/customer_login.php">Customer Login</a></li>
                    <li><a href="Models/farmer_login.php">Farmer Login</a></li>
                    <li><a href="Models/Farmer.php">Join as a Farmer</a></li>
                <?php endif; ?>
                
                <li><a href="../faq.php">Help Center / FAQ</a></li>
            </ul>
        </div>

        <div class="footer-column-contact-info">
            <h4>
                <?php 
                    if(isset($_SESSION['farmer_id'])) echo "Seller Support";
                    elseif(isset($_SESSION['customer_id'])) echo "Buyer Support";
                    else echo "Get In Touch";
                ?>
            </h4>
            <p>
                <i class="fas fa-map-marker-alt"></i> RanchLink HQ, <br>Serendah, Selangor
            </p>
            <p>
                <i class="fas fa-envelope"></i> support@ranchlink.com
            </p>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
        
    </div>

    <div class="footer-bottom">
        <div class="legal-links">
            <a href="../privacy.php">Privacy Policy</a>
            <span>|</span>
            <a href="../terms.php">Terms of Service</a>
        </div>
        <p>&copy; <?php echo date("Y"); ?> RanchLink. All Rights Reserved.</p>
        <?php if(isset($_SESSION['admin_id'])): ?>
            <p><small><a href="../admin/admin_dashboard.php" style="color:rgba(255,255,255,0.5); text-decoration:none;">Admin Access</a></small></p>
        <?php endif; ?>
    </div>
</footer>