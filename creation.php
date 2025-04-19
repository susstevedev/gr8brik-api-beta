<?php
require_once 'constants.php';
$creation_id = $_GET['creation_id'];
$method = $_GET['method'];
if($method === "creation") {
	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
	if ($conn->connect_error) {
		exit($conn->connect_error);
	}
			
    $sql = "SELECT * FROM model WHERE id = $creation_id";
    $result = $conn->query($sql);

    $build = [];
    while ($build = $result->fetch_assoc()) {
		$build = $build;
		break;
	}

    if(empty($build['name'])) {
        header('HTTP/1.0 500 Internal Server Error');
        exit;
    }

	return $build;
}
/*
$userId = $_GET['id'];
$row = fetchUser($userId);
$user = $row['username'];
$pwd = $row['password'];
$email = $row['email'];
$about = $row['description'];
$x = $row['twitter'];
$admin = $row['admin'];
$alert = $row['alert'];
$age = $row['age'];
$pic = $row['image'];
*/
?>