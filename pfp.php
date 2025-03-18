<?php
/*ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

$name = base64_decode($_GET['id']) . '.jpg';

$imagePath = '../acc/users/pfps/' . $name;

if(!file_exists($imagePath)) {
    $imagePath = '../img/user.png';
}

$fp = fopen($imagePath, 'rb');

$contents = file_get_contents($imagePath);

$data = 'data:image/png;base64,'. base64_encode($contents);

if ($_GET['method'] === 'image') {
    header("Content-Type: image/png");
    header("Content-Length: " . filesize($imagePath));
    fpassthru($fp);
    echo $data;
    exit;
}

if($_GET['method'] === 'data') {
    echo $data;
    exit;
} 
if($_GET['method'] === 'url') {
    header('Location:' . $imagePath);
    exit;
}
echo "No loading method specified.";*/

// eol for the old pfp api, redirect to the folder that the image is located in
$path = '../acc/users/pfps/' . base64_decode($_GET['id']) . '.jpg';
header('Location:' . $path);
exit;
?>