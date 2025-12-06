<?php
error_reporting(0);
include $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';
include $_SERVER['DOCUMENT_ROOT'] . '/com/bbcode.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ajax/time.php';
$bbcode = new BBCode;

if(isset($_GET['followed_by'])) {
    header('Content-Type: application/json');
    if(isset($_COOKIE['token']) && $tokendata->num_rows != 0) {
        $profile_id = $_GET['followed_by'];

        $stmt1 = $conn->prepare("SELECT userid FROM follow WHERE profileid = ?");
        $stmt1->bind_param("s", $profile_id);
        $stmt1->execute();
        $result1 = $stmt1->get_result();

        $followed_users = array();
        while ($r1 = $result1->fetch_assoc()) {
            $followed_users[] = $r1['userid'];
        }
        $stmt1->close();

        if (count($followed_users) > 0) {
            $followed_user_ids = implode(',', $followed_users);

            $stmt4 = $conn->prepare("SELECT profileid FROM follow WHERE userid = ?");
            $stmt4->bind_param("s", $id);
            $stmt4->execute();
            $result4 = $stmt4->get_result();

            $current_user_follows = array();
            while ($r4 = $result4->fetch_assoc()) {
                $current_user_follows[] = $r4['profileid'];
            }
            $stmt4->close();
            
            if (count($current_user_follows) > 0) {
                $current_user_follows_string = implode(',', $current_user_follows);
                
                $stmt2 = $conn->prepare("SELECT DISTINCT userid FROM follow WHERE userid IN ($followed_user_ids) AND userid IN ($current_user_follows_string) ORDER BY userid DESC");
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                
                if ($result2->num_rows > 0) {
                    $i = 0;
                    $followed_by = array();
                    while ($r2 = $result2->fetch_assoc()) {
                        $userid = $r2['userid'];
                        
                        $stmt3 = $conn->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt3->bind_param("s", $userid);
                        $stmt3->execute();
                        $result3 = $stmt3->get_result();
                        $r3 = $result3->fetch_assoc();
                        $stmt3->close();
                        
                        $stmt5 = $conn->prepare("SELECT * FROM bans WHERE user = ?");
                        $stmt5->bind_param("s", $userid);
                        $stmt5->execute();
                        $result5 = $stmt5->get_result();

                        $skip = false;
                        while ($r5 = $result5->fetch_assoc()) {
                            if ($result5->num_rows > 0 && $r5['end_date'] >= time()) {
                                $skip = true;
                                break;
                            }
                        }
                        $stmt5->close();

                        if($skip){
                            continue;
                        }

                        if(empty($result3->num_rows < 0 || $r3['username']) || !empty($r3['deactive'])) {
                            continue;
                        }

                        if ($result3->num_rows > 0) {
                            $followed_by[] = array(
                                'url' => '/user/' . $userid,
                                'url_legacy' => '/user/' . htmlspecialchars($r3['username']),
                                'userid' => $userid, 
                                'pfp_legacy' => '/acc/users/pfps/' . $userid . '.jpg',
                                'pfp' => $r3['picture'],
                                'username' => htmlspecialchars($r3['username']),
                                'random' => uniqid()
                            );
                        }
                    }
                    $stmt2->close();
                } else {
                    $followed_by = "";
                }
            } else {
                $followed_by = "";
            }

            header("HTTP/1.0 200 OK");
            echo json_encode($followed_by);
            exit;
        }else {
            header("HTTP/1.0 200 OK");
            echo json_encode("");
            exit;
        }
    } else {
        header("HTTP/1.0 200 OK");
        echo json_encode("");
        exit;
    }
}

$conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
if ($conn2->connect_error) {
    exit($conn2->connect_error);
}

if(isset($_GET['who_you_follow'])) {
    header('Content-Type: application/json');
    $followed_by = array();
    $sql = "SELECT DISTINCT profileid FROM follow WHERE userid = '$id' ORDER BY id DESC";
    $result = $conn2->query($sql);

    while ($row = $result->fetch_assoc()) {
        $profileid = $row['profileid'];
        $sql2 = "SELECT * FROM users WHERE id = '$profileid'";
        $result2 = $conn2->query($sql2);
        if($result2->num_rows > 0) {
            $row2 = $result2->fetch_assoc();
            $followed_by[] = array(
                'username' => htmlspecialchars($row2['username']),
                'profileid' => $profileid, 
                'random' => uniqid()
            );
            $result2->free();
        }
    }
    $result->free();
    header("HTTP/1.0 200 OK");
    echo json_encode($followed_by);
    exit;
}

if(isset($_GET['who_follows_you'])) {
    header('Content-Type: application/json');
    $followed_by = array();
    $sql = "SELECT DISTINCT userid FROM follow WHERE profileid = '$id' ORDER BY id DESC";
    $result3 = $conn2->query($sql);

    while ($row3 = $result3->fetch_assoc()) {
        $userid = $row3['userid'];
        $sql2 = "SELECT * FROM users WHERE id = '$userid'";
        $result4 = $conn2->query($sql2);
        if($result4->num_rows > 0) {
            $row4 = $result4->fetch_assoc();
            $followed_by[] = array(
                'username' => htmlspecialchars($row4['username']),
                'userid' => $userid, 
                'random' => uniqid()
            );
            $result4->free();
        }
    }
    $result3->free();
    header("HTTP/1.0 200 OK");
    echo json_encode($followed_by);
    $conn2->close();
    exit;
}

function user_blocks($profileid, $userid, $username) {
    $you = false;
    $them = false;
    $message = "OK";

    $block_result = $conn2->query("SELECT * FROM user_blocks WHERE (userid = '$userid' AND profileid = '$profileid') OR (userid = '$profileid' AND profileid = '$userid')");

    if ($block_result && $block_result->num_rows > 0) {
        while ($row = $block_result->fetch_assoc()) {
            if ($row['userid'] == $id && $row['profileid'] == $c_user) {
                $you = true;
            } elseif ($row['userid'] == $c_user && $row['profileid'] == $id) {
                $them = true;
            }
        }

        if ($youBlocked && $theyBlocked) {
            $message = "You blocked @" . $username . ", and they blocked you.";
        } elseif ($you) {
            $message = "You blocked @" . $username;
        } elseif ($them) {
            $message = "You're blocked from @" . $username;
        }

        return $message;
    }
}

function fetch_profile($profile_id, $csrf) {
    global $token, $users_row;
    $userid = $token['user'] ?? null;

    require_once $_SERVER['DOCUMENT_ROOT'] . '/com/bbcode.php';
    $bbcode = new BBCode();

    if (empty($csrf) || $csrf != $_SESSION['csrf']) {
        return json_encode([
            "message" => 'No CSRF token provided, or it is invalid!',
            "error" => 'INVALID_CSRF'
        ]);
    }

    if (empty($profile_id) || $profile_id === null) {
        header("HTTP/1.0 403 Forbidden");
        return json_encode([
            "message" => 'No user ID provided!',
            "error" => 'USR_ID_NUL'
        ]);
    }

    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        exit($conn->connect_error);
    }

    $sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $profile_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0 || !$result) {
        header("HTTP/1.0 403 Forbidden");
        return json_encode([
            "message" => 'User not found!',
            "error" => 'NO_USR'
        ]);
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!empty($row['deactive'])) {
        header("HTTP/1.0 403 Forbidden");
        return json_encode([
            "message" => htmlspecialchars($row['username']) . ' has deactivated their account.',
            "error" => 'DEL_ACC'
        ]);
    }

    $sql = "SELECT * FROM bans WHERE user = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $profile_id);
    $stmt->execute();
    $result2 = $stmt->get_result();
    $stmt->close();

    if ($result2->num_rows != 0) {
        $ban_data = $result2->fetch_assoc();
        if ($ban_data['end_date'] >= time()) {
            header("HTTP/1.0 403 Forbidden");
            return json_encode([
                "message" => htmlspecialchars($row['username']) . " has been banned until " . gmdate("M d, Y H:i", $ban_data['end_date']) . ". " . htmlspecialchars($ban_data['reason']) . ".",
                "error" => 'ACC_BAN'
            ]);
        }
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as blocking FROM user_blocks WHERE userid = ? AND profileid = ?");
    $stmt->bind_param("ii", $_SESSION['userid'], $profile_id);
    $stmt->execute();
    $is_blocking = $stmt->get_result()->fetch_assoc()['blocking'];
    $stmt->close();

    $sql = "SELECT * FROM user_blocks WHERE userid = ? AND profileid = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $profile_id, $id);
    $stmt->execute();
    $result3 = $stmt->get_result();
    $stmt->close();

    if ($result3->num_rows > 0) {
        header("HTTP/1.0 403 Forbidden");
        return json_encode([
            "message" => htmlspecialchars($row['username']) . " has blocked you.",
            "error" => 'ACC_BLOCKED_USR'
        ]);
    }
    
    /*if (!loggedin()) {
        header("HTTP/1.0 403 Forbidden");
        return json_encode([
            "message" => "Sign in to view " . htmlspecialchars($row['username']) . "'s creations, posts, and comments.",
            "error" => 'USR_SESS_NUL'
        ]);
    }*/

    $conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
    if ($conn2->connect_error) {
        exit($conn2->connect_error);
    }

    $stmt = $conn2->prepare("SELECT COUNT(*) as all_models FROM model WHERE user = ?");
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $model_count = $stmt->get_result()->fetch_assoc()['all_models'];
    $stmt->close();

    $stmt = $conn2->prepare("SELECT SUM(views) as total_views FROM model WHERE user = ?");
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $views = $stmt->get_result()->fetch_assoc()['total_views'] ?? 0;
    $stmt->close();

    $stmt = $conn2->prepare("SELECT SUM(likes) as total_likes FROM model WHERE user = ?");
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $likes = $stmt->get_result()->fetch_assoc()['total_likes'] ?? 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as following FROM follow WHERE profileid = ?");
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $followers = $stmt->get_result()->fetch_assoc()['following'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as following FROM follow WHERE userid = ?");
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $following = $stmt->get_result()->fetch_assoc()['following'];
    $stmt->close();
    
    if (loggedin()) {
    	$stmt = $conn->prepare("SELECT COUNT(*) as following FROM follow WHERE userid = ? AND profileid = ?");
    	$stmt->bind_param("ss", $_SESSION['userid'], $profile_id);
    	$stmt->execute();
    	$is_following = $stmt->get_result()->fetch_assoc()['following'];
    	$stmt->close();
    } else {
        $is_following = false;
    }

    if(isset($_GET['user'])) {
        header("HTTP/1.0 200 OK");
    }
    
    if($users_row['admin'] == '1') {
    	$dataemail = htmlspecialchars($row['email']);
    }

    $message = null;
    $data = [
        'username' => htmlspecialchars($row['username']),
        'admin' => (string)$row['admin'],
        'description' => isset($row['description']) ? $bbcode->toHTML($row['description']) : '', 
        'twitter' => htmlspecialchars($row['twitter']),
        'bsky' => htmlspecialchars($row['bsky']),
        'age' => htmlspecialchars($row['age']),
        'picture' => htmlspecialchars($row['picture']),
        'model_count' => htmlspecialchars($model_count),
        'followers' => htmlspecialchars($followers),
        'following' => htmlspecialchars($following),
        'views' => htmlspecialchars($views),
        'likes' => htmlspecialchars($likes),
        'blockedUser' => (bool)$is_blocking,
        'isFollowing' => (bool)$is_following,
        'message' => $message,
        'email' => $dataemail ?? ''
    ];

    return json_encode($data);
}

if(isset($_GET['user'])) {
    header('Content-Type: application/json');
    $profile_id = htmlspecialchars($_GET['user']);
    $data = fetch_profile($profile_id, $_SESSION['csrf']);
    echo $data;
}
?>