<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

error_log('upload_content.php was called');
error_log('POST data: ' . print_r($_POST, true));

$courseid = required_param('courseid', PARAM_INT);
$job_id = required_param('job_id', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

error_log('Received courseid: ' . $courseid);
error_log('Received job_id: ' . $job_id);

// Verify sesskey
if (!confirm_sesskey($sesskey)) {
    error_log('Invalid sesskey');
    $response = array('success' => false, 'message' => 'Invalid session key');
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}

if ($courseid) {
    require_login($courseid);
    $context = context_course::instance($courseid);
    require_capability('block/multimodal_generator:use', $context);
} else {
    require_capability('moodle/site:config', context_system::instance());
}

if (!$courseid) {
    error_log('Course ID is missing or invalid');
    $response['success'] = false;
    $response['message'] = 'Course ID is missing or invalid';
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
if (!$course) {
    error_log('Course not found for ID: ' . $courseid);
    $response['success'] = false;
    $response['message'] = 'Course not found';
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}

$response = array('success' => false, 'message' => '');

// Retrieve file paths from the database
$job_details = $DB->get_record('local_adapted_jobs', array('id' => $job_id));
if (!$job_details) {
    error_log('Job not found in database for ID: ' . $job_id);
    $response['message'] = 'Job not found';
    header('Content-Type: application/json');
    echo json_encode($response);
    die();
}
error_log('Files from database: ' . $job_details->files);

$files = json_decode($job_details->files, true);

// Get the current working directory
$current_dir = getcwd();
error_log("Current working directory: " . $current_dir);
error_log("Files from database: " . print_r($files, true));

// Process the files
foreach ($files as $file) {
    $filename = basename($file);
    $full_path = $current_dir . '/' . $filename;
    error_log("Checking file: " . $full_path);

    if (file_exists($full_path)) {
        error_log("File exists: " . $full_path);
    
        $file_record = array(
            'contextid' => $context->id,
            'component' => 'local_adapted',
            'filearea'  => 'multimodal_content',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
            'timecreated' => time(),
            'timemodified' => time(),
            'userid'    => $USER->id
        );

        $fs = get_file_storage();
        $file_obj = $fs->create_file_from_pathname($file_record, $full_path);

        // Add file to the course page
        $section = 0;
        $module = add_file_to_course_page($courseid, $file_obj, $section);

        if ($module) {
            $response['success'] = true;
            $response['message'] .= get_string('content_uploaded_success', 'local_adapted') . ' ';
        } else {
            $response['message'] .= get_string('content_upload_error', 'local_adapted') . ' ';
        }
    } else {
        error_log("File does not exist: " . $full_path);
        error_log("PHP process user: " . exec('whoami'));
        $response['message'] .= get_string('filenotfound', 'error') . ' ';
    }
}

error_log('Response: ' . json_encode($response));

header('Content-Type: application/json');
echo json_encode($response);
die();

function add_file_to_course_page($courseid, $file, $section) {
    global $DB;

    error_log("Adding file to course page. Course ID: " . $courseid);

    if (!$courseid) {
        error_log('Course ID is missing or invalid in add_file_to_course_page');
        return false;
    }

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    if (!$course) {
        error_log('Course not found for ID: ' . $courseid . ' in add_file_to_course_page');
        return false;
    }
    $resource = new stdClass();
    $resource->course = $course->id;
    $resource->name = $file->get_filename();
    $resource->intro = '';
    $resource->introformat = FORMAT_HTML;
    $resource->tobemigrated = 0;
    $resource->legacyfiles = 0;
    $resource->legacyfileslast = NULL;
    $resource->display = 0;
    $resource->displayoptions = serialize(array('printintro' => 1));
    $resource->filterfiles = 0;
    $resource->revision = 1;
    $resource->timemodified = time();

    $module = $DB->get_record('modules', array('name' => 'resource'), '*', MUST_EXIST);
    $resource->module = $module->id;

    $resource->id = $DB->insert_record('resource', $resource);

    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $module->id;
    $cm->instance = $resource->id;
    $cm->section = $section;
    $cm->added = time();
    $cm->score = 0;
    $cm->indent = 0;
    $cm->visible = 1;
    $cm->visibleold = 1;
    $cm->groupmode = 0;
    $cm->groupingid = 0;
    $cm->completion = 0;
    $cm->completiongradeitemnumber = NULL;
    $cm->completionview = 0;
    $cm->completionexpected = 0;
    $cm->showdescription = 0;
    $cm->availability = NULL;

    $cm->id = add_course_module($cm);

    $section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $section));
    if (!$section) {
        print_error('sectionnotexist');
    }

    $content = $section->sequence;
    if (!empty($content)) {
        $content .= ',';
    }
    $content .= $cm->id;
    $DB->set_field('course_sections', 'sequence', $content, array('id' => $section->id));

    rebuild_course_cache($course->id, true);

    return $cm;
}