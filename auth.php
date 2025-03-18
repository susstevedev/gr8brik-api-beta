<?php
header('Content-type: application/json');
error_reporting(0);
include $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';

if (isset($_GET['sessions'])) {
    if(!isset($id)) {
        header('HTTP/1.0 500 Internal Server Error');
        exit(json_encode(["error" => "userid isn't set"]));
    }

    $conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    if ($conn2->connect_error) {
        header('HTTP/1.0 500 Internal Server Error');
        exit(json_encode(["error" => "db connection failed"]));
    }

    $userid = $id; // userid from user.php

    if (!filter_var($userid, FILTER_VALIDATE_INT)) {
        header('HTTP/1.0 500 Internal Server Error');
        exit(json_encode(["error" => "invalid userid"]));
    }

    $sql = "SELECT * FROM sessions WHERE user = ? ORDER BY timestamp DESC";
    $stmt = $conn2->prepare($sql);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $result = $stmt->get_result();

    $sessions = [];

    while ($row = $result->fetch_assoc()) {
        $time = is_numeric($row['timestamp']) ? gmdate("F j, Y, g:i a", $row['timestamp']) : 'no timestamp';

        $sessions[] = [
            'fetched_at' => $_SERVER['REQUEST_TIME'],
            'id' => $row['id'],
            'user' => $row['user'],
            'name' => $row['username'],
            'hashed_pwd' => $row['password'],
            'from' => $row['login_from'],
            'date' => $time,
            'timestamp' => $row['timestamp'],
        ];
    }

    echo json_encode($sessions);

    $stmt->close();
    $conn2->close();
    exit;
}

if (isset($_GET['revoke']) && isset($_GET['tokenid'])) {
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['error' => "db connection failed"]);
        exit;
    }

    $session = $_GET['tokenid'];

    if (!filter_var($session, FILTER_VALIDATE_INT)) {
        header('HTTP/1.0 400 Bad Request');
        echo json_encode(['error' => "invalid tokenid"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM sessions WHERE id = ? LIMIT 1");
    $stmt->bind_param("s", $session);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => "session deleted"]);
    } else {
        header('HTTP/1.0 404 Not Found');
        echo json_encode(['error' => "session not found"]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

if(isset($_POST['login'])) {
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['error' => "Database connection failed"]);
        exit;
    }       
    $mail = $conn->real_escape_string($_POST['mail']);
    $pwd = $_POST['pwd'];

    if(empty($_POST['mail'])) {
		header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['error' => "Email cannot be blank"]);
        exit;
    }

    $sql = "SELECT * FROM users WHERE email = '$mail' LIMIT 1";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if($result->num_rows != 0) {
        if(!empty($row['salt'])) {
            $db_hashed_pwd = md5($pwd . $row['salt']);
        } else {
            $db_hashed_pwd = md5($pwd);
        }

        if($db_hashed_pwd === $row['password']) {
            $username = $row['username'];
            $userid = $row['id'];
            $login_from = md5($_SERVER['REMOTE_ADDR']);
            $tokenid = md5(time());
            $time = time();

            if(!empty($row['deactive'])) {
                if($_SERVER['REQUEST_TIME'] - 2592000 < strtotime($row['deactive'])) {
                    $sql = "INSERT INTO sessions (id, user, username, password, timestamp) VALUES ('$tokenid', '$userid', '$username', '$pwd', '$time') LIMIT 1";
                    if ($conn->query($sql) === TRUE) {
                        header('HTTP/1.0 500 Internal Server Error');
                        echo json_encode(['popup' => "Do you want to reactivate your account?", 'error' => "See modal for more info", 'goto' => "http://www.gr8brik.rf.gd/acc/index?reactive=1&token=" . $tokenid ]);
                        exit;
                    }
                } else {
                    header('HTTP/1.0 500 Internal Server Error');
                    echo json_encode(['error' => "This account has been deleted"]);
                    exit;
                }
            }

            $sql = "SELECT * FROM bans WHERE user = '$userid' LIMIT 1";
            $result2 = $conn->query($sql);

            while ($row2 = $result2->fetch_assoc()) {
                if ($result2->num_rows > 0 && $row2['end_date'] >= time()) {
                    header('HTTP/1.0 500 Internal Server Error');
                    echo json_encode(['error' => $row['username'] . " has been banned until " . gmdate("M d, Y H:i", $row2['end_date']) . ". " . $row2['reason']]);
                    exit;
                }
            }

            $sql = "INSERT INTO sessions (id, login_from, user, username, password, timestamp) VALUES ('$tokenid', '$login_from', '$userid', '$username', '$db_hashed_pwd', '$time') LIMIT 1";
            if ($conn->query($sql) === TRUE) {
                setcookie('token', $tokenid, $time + (10 * 365 * 24 * 60 * 60), "/", ".gr8brik.rf.gd");
                $_SESSION['username'] = $userid;
                $_SESSION['auth'] = true;
                echo json_encode(['success' => 'logged in, sending info to client...']);
                exit;
            }
        } else {
            header('HTTP/1.0 500 Internal Server Error');
            echo json_encode(['error' => "Invalid password"]);
            exit;
        }
    } else {
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['error' => "No account found. If you haven't made an account yet, please register one."]);
        exit;
    }
}

if(isset($_POST['register'])) {
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['error' => "Database connection failed"]);
        exit;
    }
    $username = $conn->real_escape_string(htmlspecialchars($_POST['name']));
    $salt = uniqid('', true);	
	$password = md5($_POST['pwd'] . $salt);
	$email = $conn->real_escape_string($_POST['mail']);
    $age = date("Y-m-d");

    if(empty($_POST['pwd']) || empty($_POST['mail']) || empty($_POST['name'])) {
		header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => "One of the field(s) are blank"]);
        exit;
    }
		
	$sql_check = "SELECT id FROM users WHERE username = '$username' UNION ALL SELECT id FROM users WHERE email = '$email'";
    $stmt = $conn->query($sql_check);

	if ($stmt->num_rows > 0) {
		header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => "Username or email already in use"]);
        exit;
	} else {
		$sql = "INSERT INTO users (username, password, salt, email, age) VALUES ('$username', '$password', '$salt', '$email', '$age') LIMIT 1";
		if ($conn->query($sql) === TRUE) {
            $sql = "SELECT id FROM users WHERE username = '$username'";
            $result = $conn->query($sql);
            $row2 = $result->fetch_assoc();

            $userid = $row2['id'];
            $tokenid = uniqid('', true);
            $time = time();
            $pwd = $password;

            $sql = "INSERT INTO sessions (id, user, username, password, timestamp) VALUES ('$tokenid', '$userid', '$username', '$pwd', '$time') LIMIT 1";
            if ($conn->query($sql) === TRUE) {
                setcookie('token', $tokenid, $time + (10 * 365 * 24 * 60 * 60), "/", ".gr8brik.rf.gd");
                $_SESSION['username'] = $userid;
                $_SESSION['auth'] = true;
                echo json_encode(['success' => 'account created, sending info to client...']);
                exit;
            }
		} else {
			header("HTTP/1.0 500 Internal Server Error");
            echo json_encode(['error' => " "]);
            exit;
		}
	}   
}

?>