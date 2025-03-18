<?php
header('Content-Type: application/json');
error_reporting(0);
include $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';
include $_SERVER['DOCUMENT_ROOT'] . '/com/bbcode.php';
include $_SERVER['DOCUMENT_ROOT'] . '/ajax/time.php';
$bbcode = new BBCode;

if(isset($_GET['follow'])) {
    if(isset($_COOKIE['token']) && $tokendata->num_rows != 0) {
        $profile_id = $_GET['follow'];

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
                                'username' => htmlspecialchars($r3['username']),
                                'userid' => $userid, 
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

if (isset($_GET['user'])) {
    $profile_id = $_GET['user'];

    function defaultError()
    {
        header('HTTP/1.0 500 Internal Server Error');
        http_response_code(500);
        echo json_encode(['error' => 'Could not fetch profile data']);
        exit;
    }

    $sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (empty($_GET['user']) || $_GET['user'] === null) {
        defaultError();
    } elseif (!$result) {
        defaultError();
    } elseif (isset($_GET['sqlfail']) && ($_GET['sqlfail'] === '1' || $_GET['sqlfail'] === 'true')) {
        defaultError();
    }

    if ($result->num_rows === 0) {
        header('HTTP/1.0 404 Not Found');
        http_response_code(404);
        echo json_encode(['error' => "User does not exist."]);
        exit;
    }

    if (!empty($row['deactive'])) {
        header('HTTP/1.0 410 Gone');
        echo json_encode(['error' => $row['username'] . ' has deactivated their account.']);
        exit;
    }

    $sql = "SELECT * FROM bans WHERE user = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $result2 = $stmt->get_result();
    $stmt->close();

    while ($row2 = $result2->fetch_assoc()) {
        if ($result2->num_rows > 0 && $row2['end_date'] >= time()) {
            header("HTTP/1.0 403 Forbidden");
            echo json_encode(['error' => $row['username'] . " has been banned until " . gmdate("M d, Y H:i", $row2['end_date']) . ". " . $row2['reason']]);
            exit;
        }
    }

    $conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
    if ($conn2->connect_error) {
        exit($conn2->connect_error);
    }

    if (file_exists('../acc/users/banners/' . $profile_id . '..jpg')) {
        $hasBanner = 1;
    } else {
        $hasBanner = 0;
    }

    $count_sql = "SELECT COUNT(*) as all_models FROM model WHERE user = ?";
    $stmt = $conn2->prepare($count_sql);
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $model_count = $count_row['all_models'];
    $stmt->close();

    $count_sql = "SELECT COUNT(*) as following FROM follow WHERE profileid = ?";
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $followers = $count_row['following'];
    $stmt->close();

    $count_sql = "SELECT COUNT(*) as following FROM follow WHERE userid = ?";
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param("s", $profile_id);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $following = $count_row['following'];
    $stmt->close();

    if (isset($_COOKIE['token']) && $tokendata->num_rows != 0) {
        $login = true;
        $message = "";
    } else {
        $login = false;
        $message = "Sign in to view " . htmlspecialchars($row['username']) . "'s creations, posts, and comments.";
    }

    if ($login === true) {
        $sql2 = "SELECT * FROM follow WHERE userid = ? AND profileid = ?";
        $stmt = $conn->prepare($sql2);
        $stmt->bind_param("ss", $id, $profile_id);
        $stmt->execute();
        $result2 = $stmt->get_result();
        $row2 = $result2->fetch_assoc();
        $stmt->close();

        if ($result2->num_rows > 0) {
            $isFollowing = true;
        } elseif ($id != $profile_id) {
            $isFollowing = false;
        } else {
            $isFollowing = false;
        }

        if ($id != $profile_id && $admin === '1' && $row['admin'] != '1') {
            $canBan = true;
        } else {
            $canBan = false;
        }
    } else {
        $isFollowing = false;
        $canBan = false;
    }

    header("HTTP/1.0 200 OK");
    echo json_encode([
        'username' => htmlspecialchars($row['username']),
        'admin' => (string)$row['admin'],
        'verified' => (string)$row['verified'],
        'description' => $bbcode->toHTML($row['description']), 
        'twitter' => htmlspecialchars($row['twitter']),
        'age' => htmlspecialchars($row['age']),
        'model_count' => htmlspecialchars($model_count),
        'followers' => htmlspecialchars($followers),
        'following' => htmlspecialchars($following),
        'hasBanner' => $hasBanner,
        'isFollowing' => $isFollowing,
        'canBan' => $canBan,
        'isLoggedin' => $login,
        'tokenuser' => $id,
        'message' => $message
    ]);
    exit;
}
?>
