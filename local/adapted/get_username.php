<?php
require_once('../../config.php');
require_login();

header('Content-Type: application/json');

if (isloggedin() && !isguestuser()) {
    echo json_encode(['username' => fullname($USER)]);
} else {
    echo json_encode(['username' => 'User']);
}