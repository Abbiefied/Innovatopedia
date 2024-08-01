<?php
require_once(__DIR__ . '/../../../config.php');

$file = required_param('file', PARAM_PATH);
$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    require_login($courseid);
    $context = context_course::instance($courseid);
    require_capability('block/multimodal_generator:use', $context);
} else {
    require_login();
    require_capability('moodle/site:config', context_system::instance());
}

if (file_exists($file)) {
    $filename = basename($file);
    header('Content-Type: ' . mime_content_type($file));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($file);
    exit;
} else {
    print_error('filenotfound', 'error', '');
}