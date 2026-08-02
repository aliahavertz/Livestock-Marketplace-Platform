<?php
class Bidding {
    private $conn;
    public function __construct($db) { $this->conn = $db; }

    public function createBidding($livestock_id, $start_date, $end_date, $start_price) {
        $stmt = $this->conn->prepare("INSERT INTO bidding (livestock_id, start_date, end_date, start_price, current_bid, status)
                                      VALUES (:livestock_id, :start_date, :end_date, :start_price, :start_price, 'active')");
        return $stmt->execute(compact('livestock_id', 'start_date', 'end_date', 'start_price'));
    }

    public function placeBid($bid_id, $amount) {
        $stmt = $this->conn->prepare("UPDATE bidding SET current_bid=:amount WHERE bid_id=:bid_id AND status='active'");
        return $stmt->execute(compact('amount', 'bid_id'));
    }
}
?>
