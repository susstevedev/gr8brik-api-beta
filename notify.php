<?php
exit;

if(isset($_GET['notify'])) {
    if($_GET['user'] != $id) {
        $user = $_GET['user'];
        $time = time();
        $content = $_GET['id'];
        $category = $_GET['category'];

        $sql = "INSERT INTO notifications (user, profile, timestamp, content, category) VALUES ('$user', '$id', '$time', '$content', '$category')";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();

        $sql2 = "SELECT alert FROM users WHERE id = '$user'";
        $result2 = $conn->query($sql2);
        $row2 = $result2->fetch_assoc();

        $alertnum = $row2['alert'] + 1;
        $sql3 = "UPDATE users SET alert = '$alertnum' WHERE id = '$user'";
        $result2 = $conn->query($sql3);
        $row3 = $result3->fetch_assoc();

        if ($conn->query($sql) === TRUE) {
            header("HTTP/1.0 200 OK");
            echo json_encode(['success' => "Notification complete."]);
            exit;
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            echo json_encode(['error' => "Script didn't execute."]);
            exit;
        }
    }
    $conn->close();
}
?>