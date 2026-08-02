<?php
class User {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function register($name, $email, $password, $role) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
        return $stmt->execute([':name'=>$name, ':email'=>$email, ':password'=>$hash, ':role'=>$role]);
    }

    public function login($email, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email=:email");
        $stmt->execute([':email'=>$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if($user && password_verify($password, $user['password'])) return $user;
        return false;
    }
}
?>
