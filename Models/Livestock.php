<?php
session_start();

if (!isset($_SESSION['farmer_id']) && !isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php");
    exit();
}

include('../db_connect.php'); 


$sql = "SELECT * FROM livestock ORDER BY date_registered DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$livestock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Livestock Catalogue</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
        }

        .catalogue-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
        }

        .livestock-name {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }

        .price {
            color: #00a000;
            font-weight: bold;
            margin-top: 5px;
        }

        .btn-view {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 12px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .btn-view:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>

<h1>Livestock Catalogue</h1>

<div class="catalogue-container">

<?php foreach ($livestock as $item): ?>
    <div class="card">
        <img src="../uploads/<?php echo $item['image']; ?>" alt="Livestock Image">

        <div class="livestock-name"><?php echo $item['name']; ?></div>

        <div class="price">RM <?php echo number_format($item['price'], 2); ?></div>

        <a class="btn-view" href="livestock_view.php?id=<?php echo $item['id']; ?>">
            View Details
        </a>
    </div>
<?php endforeach; ?>

</div>

</body>
</html>
