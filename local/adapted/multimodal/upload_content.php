<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/moodlelib.php');

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

$response = array('success' => false, 'message' => '', 'file_urls' => array());
$TEMP_DIR = '/var/www/moodledata/temp/multimodal_files';

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
// $current_dir = getcwd();
// error_log("Current working directory: " . $current_dir);
// error_log("Files from database: " . print_r($files, true));

$fs = get_file_storage();

// Process the files
foreach ($files as $filename) {
    $full_path = $TEMP_DIR . '/' . basename($filename);
    
    if (file_exists($full_path)) {
        // Create the initial file in Moodle's file system
        $file_record = array(
            'contextid' => $context->id,
            'component' => 'local_adapted',
            'filearea'  => 'multimodal_content',
            'itemid'    => $job_id,
            'filepath'  => '/',
            'filename'  => basename($filename),
            'userid'    => $USER->id  // Add this line to associate the file with the current user
        );
        $file_obj = $fs->create_file_from_pathname($file_record, $full_path);
        
        if ($file_obj) {
            // Add file to the course
            $section = 0; // Assuming you want to add to the first section
            $cm = add_file_to_course($courseid, $file_obj, $section);
            
            if ($cm) {
                $response['success'] = true;
                $response['message'] .= "File " . basename($filename) . " added to course. ";
                
                // Generate URL for the file
                $url = new moodle_url('/mod/resource/view.php', array('id' => $cm->id));
                $response['file_urls'][] = array(
                    'name' => $file_obj->get_filename(),
                    'url' => $url->out()
                );
            } else {
                $response['message'] .= "Failed to add " . basename($filename) . " to course. ";
            }
        } else {
            $response['message'] .= "Failed to create file object for " . basename($filename) . ". ";
        }
    } else {
        $response['message'] .= "File " . basename($filename) . " not found at $full_path. ";
        error_log("File not found: $full_path");
    }
}
// // Clean up temporary files
// foreach ($files as $filename) {
//     $full_path = $TEMP_DIR . '/' . $filename;
//     if (file_exists($full_path)) {
//         unlink($full_path);
//     }

error_log('Response: ' . json_encode($response));

header('Content-Type: application/json');
echo json_encode($response);
die();

function add_file_to_course($courseid, $file, $section) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/lib/resourcelib.php');

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($courseid);

    $fs = get_file_storage();

    // Create resource instance
    $resource = new stdClass();
    $resource->course = $course->id;
    $resource->name = $file->get_filename();
    $resource->intro = '';
    $resource->introformat = FORMAT_HTML;
    $resource->tobemigrated = 0;
    $resource->legacyfiles = 0;
    $resource->legacyfileslast = null;
    $resource->display = RESOURCELIB_DISPLAY_AUTO;
    $resource->displayoptions = serialize(array('printintro' => 1));
    $resource->filterfiles = 0;
    $resource->revision = 1;
    $resource->timemodified = time();

    $resource->id = $DB->insert_record('resource', $resource);

    // Add to course
    $module = $DB->get_record('modules', array('name' => 'resource'), '*', MUST_EXIST);
    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $module->id;
    $cm->instance = $resource->id;
    $cm->section = $section;
    $cm->modname = 'resource';
    $cm->name = $resource->name;

    $cm->id = add_course_module($cm);
    course_add_cm_to_section($course, $cm->id, $section);

    // Prepare file record object
    $fileinfo = array(
        'contextid' => $context->id,
        'component' => 'mod_resource',
        'filearea' => 'content',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $file->get_filename(),
        'userid' => $file->get_userid()
    );

    // Copy file to mod_resource area
    $fs->create_file_from_storedfile($fileinfo, $file);

    // Trigger event for course module created
    $event = \core\event\course_module_created::create_from_cm($cm);
    $event->trigger();

    return $cm;
}