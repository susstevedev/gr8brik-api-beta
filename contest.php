<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/constants.php';

function fetch_contests($cid) {
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);

    $stmt = $conn->prepare("SELECT * FROM contests WHERE id = ?");
    $stmt->bind_param("i", $cid);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows != 0) {
        $row = $result->fetch_assoc();
        return $row;
    }
}

function insert_contest($cid) {
    return "Todo";
}

if(isset($_GET['fetch_contests'])) {
    $data = fetch_contests($_GET['contest_id']);
    echo json_encode($data);
}
?>