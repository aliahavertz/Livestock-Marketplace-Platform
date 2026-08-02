<?php
function notify($pdo, $user_id, $user_type, $title, $message) {
    $sql = "INSERT INTO notifications (user_id, user_type, title, message) 
            VALUES (:uid, :utype, :title, :msg)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'uid' => $user_id,
        'utype' => $user_type,
        'title' => $title,
        'msg' => $message
    ]);
}

?>