<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Account Type | Ranch Outlet</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=PT+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --farm-blue: #1976d2;      
            --sky-light: #e3f2fd;     
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            margin: 0;
            background: url('https://cdn.pixabay.com/photo/2022/02/17/17/21/cows-7019167_1280.jpg') no-repeat center center;
            /*background-image: radial-gradient(#caf0f8 1px, transparent 1px);*/
            background-size: cover;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .main-container {
            background: radial-gradient(circle at top, rgba(253, 246, 236, 0.7), rgba(244, 239, 230, 0.7));
            padding: 60px 40px;
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 900px;
            width: 100%;
            position: relative; 
        }

        .home-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            text-decoration: none;
            color: var(--farm-blue);
            background: var(--sky-light);
            padding: 8px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .home-btn:hover {
            background: var(--farm-blue);
            color: white;
        }

        h1 { 
            font-family: 'Cinzel', serif; 
            color: #1976d2; 
            margin-bottom: 10px; 
            font-size: 2.2rem; 
            letter-spacing: 1px;
        }

        .subtitle {
            color: #888;
            margin-bottom: 50px;
            font-size: 1.1rem;
        }

        .selection-wrapper {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .role-card {
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            border: 2px solid #f0f0f0;
            border-radius: 20px;
            padding: 40px;
            width: 280px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            position: relative;
        }

        .role-card:hover {
            transform: translateY(-10px);
            border-color: #90caf9;
            background: radial-gradient(circle at top, #fdf6ec, #f4efe6);
            box-shadow: 0 15px 35px rgba(25, 118, 210, 0.1);
        }

        .icon-box {
            font-size: 4rem;
            color: #1976d2;
            margin-bottom: 20px;
        }

        .role-card h2 {
            font-family: 'Cinzel', serif;
            font-size: 1.4rem;
            margin-top: 15px;
        }

        .role-card p {
            font-size: 0.9rem;
            color: #666;
            margin-top: 10px;
            line-height: 1.4;
        }

        .role-card:hover::after {
            content: '\f058';
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            color: #1976d2;
            font-size: 30px;
            border-radius: 50%;
            line-height: 1;
        }
    </style>
</head>
<body>

<div class="main-container">
    <a href="../index.php" class="home-btn">
        <i class="fas fa-home"></i> Home
    </a>

    <h1>Choose Account Type</h1>
    <p class="subtitle">Please select your role to continue to login</p>

    <div class="selection-wrapper">
        <a href="Customer.php" class="role-card">
            <div class="icon-box">
                <i class="fas fa-shopping-basket"></i>
            </div>
            <h2>Customer</h2>
            <p>Browse livestock, place bids, and manage your purchases.</p>
        </a>

        <a href="Farmer.php" class="role-card">
            <div class="icon-box">
                <i class="fas fa-user-tie"></i>
            </div>
            <h2>Farmer</h2>
            <p>List your livestock, manage auctions, and track sales.</p>
        </a>
    </div>

    <p style="margin-top: 50px; font-size: 0.8rem; color: #bbb;">
        &copy; 2026 RanchLink Marketplace System
    </p>
</div>

</body>
</html>