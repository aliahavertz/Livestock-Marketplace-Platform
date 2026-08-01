<?php
include '../db_connect.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $phone = $_POST['phone_number'];
    $position = $_POST['position'];
    $permissions = $_POST['permissions_level'];
    $status = 'active';

    $sql = "INSERT INTO admin (name, email, password, phone_number, position, permissions_level, status)
            VALUES ('$name', '$email', '$password', '$phone', '$position', '$permissions', '$status')";

    if (pg_query($conn, $sql)) {
        echo "Admin registered successfully!";
    } else {
        echo "Error: " . pg_last_error($conn);
    }

    pg_close($conn);
}
?>

<form action="register_admin.php" method="POST">
    <label>Full Name:</label><input type="text" name="name" required><br>
    <label>Email:</label><input type="email" name="email" required><br>
    <label>Password:</label><input type="password" name="password" required><br>
    <label>Phone Number:</label><input type="text" name="phone_number"><br>
    <label>Position:</label><input type="text" name="position"><br>
    <label>Permission Level:</label>
    <select name="permissions_level" required>
        <option value="Full Access">Full Access</option>
        <option value="Limited Access">Limited Access</option>
        <option value="Read Only">Read Only</option>
    </select><br>
    <button type="submit">Register Admin</button>
</form>
<p>Already registered? <a href="admin_login.php">Login here</a></p>
