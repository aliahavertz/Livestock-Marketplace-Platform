<?php
class Order {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function createOrder($customer_id, $livestock_id, $bid_id, $total_price) {
        $stmt = $this->conn->prepare("INSERT INTO orders (customer_id, livestock_id, bid_id, total_price, status)
                                      VALUES (:customer_id, :livestock_id, :bid_id, :total_price, 'Pending')");
        return $stmt->execute(compact('customer_id','livestock_id','bid_id','total_price'));
    }
}
?>
