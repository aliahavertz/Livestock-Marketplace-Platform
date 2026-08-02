<?php
class Payment {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function processPayment($order_id, $amount, $method) {
        $stmt = $this->conn->prepare("INSERT INTO payments (order_id, amount, payment_method) VALUES (:order_id, :amount, :method)");
        return $stmt->execute(compact('order_id','amount','method'));
    }
}
?>
