<?php

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir.'/adminlib.php');

ob_start();

$courseid = optional_param('courseid', 0, PARAM_INT);

// Enable error logging
error_log("multimodal_generation.php called");

// Function to handle AJAX requests

function handle_ajax_request() {
    global $DB, $USER;

    $response = ['success' => false, 'message' => '', 'files' => [], 'job_id' => '', 'progress' => 0];

    if ($courseid) {
        require_login($courseid);
        $context = context_course::instance($courseid);
        require_capability('block/multimodal_generator:use', $context);
    } else {
        require_capability('moodle/site:config', context_system::instance());

    }

    // Check if it is a POST request with a file upload
    if (!empty($_FILES['content_file'])) {
        require_sesskey();
        error_log("File upload detected");
        $text_content = file_get_contents($_FILES['content_file']['tmp_name']);
        $generate_audio = isset($_POST['generate_audio']) ? true : false;
        $generate_slides = isset($_POST['generate_slides']) ? true : false;
        $generate_video = isset($_POST['generate_video']) ? true : false;
        $courseid = optional_param('courseid', 0, PARAM_INT);

        // Store job details in the database
        $job_details = new stdClass();
        $job_details->text_content = $text_content;
        $job_details->generate_audio = $generate_audio ? 1 : 0;
        $job_details->generate_slides = $generate_slides ? 1 : 0;
        $job_details->generate_video = $generate_video ? 1 : 0;
        $job_details->progress = 0;
        $job_details->files = json_encode([]);
        $job_details->courseid = $courseid;
        $job_details->userid = $USER->id;

        // Insert the record and get the generated job_id

        $job_id = $DB->insert_record('local_adapted_jobs', $job_details);

        error_log("Job created with ID: " . $job_id);
        $job_id = strval($job_id);

        // Start the conversion process
        $url = 'http://multimodal:5001/generate';

        $data = [

            'job_id' => $job_id,
            'text' => $text_content,
            'generate_audio' => $generate_audio,
            'generate_slides' => $generate_slides,
            'generate_video' => $generate_video
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
        error_log("Attempting to call Flask app at: " . $url);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        error_log("cURL result: " . $result);
        error_log("HTTP code: " . $http_code);

        if (curl_errno($ch)) {
            error_log("cURL error: " . curl_error($ch));
        }

        if ($http_code == 200) {
            $response['success'] = true;
            $response['message'] = get_string('conversion_started', 'local_adapted', null, true);
            $response['job_id'] = $job_id;
            error_log("Conversion started for job ID: " . $job_id);
            error_log("Current working directory: " . getcwd());
            error_log("PHP process user: " . exec('whoami'));
        } else {
            $response['message'] = 'Failed to start conversion process';
        }
        curl_close($ch);
    }

    // Check if it's a POST request for progress check
    elseif (isset($_POST['check_progress'])) {
        require_sesskey();

        $job_id = $_POST['job_id'];
        error_log("Checking progress for job ID: " . $job_id);
        $job_details = $DB->get_record('local_adapted_jobs', ['id' => $job_id]);

        if ($job_details) {
            $response = [
                'success' => true,
                'progress' => $job_details->progress,
                'files' => json_decode($job_details->files, true),
                'status' => $job_details->progress < 26 ? 'Generating audio' : ($job_details->progress == 26 ? 'Generating slides' : ($job_details->progress == 66 ? 'Generating video' : 'Complete'))
            ];
            error_log("Job progress: " . $job_details->progress);
        } else {
            $response['message'] = get_string('job_not_found', 'local_adapted', null, true);
            error_log("Job not found: $job_id");
        }
    }
    return $response;
}

// Check if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $response = handle_ajax_request();

    ob_clean();

    // Set the content type to JSON
    header('Content-Type: application/json');

    // Output the JSON response
    echo json_encode($response);
    error_log("Response sent: " . json_encode($response));
    die();
}

// If it's not an AJAX request, render the page as before
if ($courseid) {
    require_login($courseid);
    $context = context_course::instance($courseid);
    require_capability('block/multimodal_generator:use', $context);

    $PAGE->set_context($context);
    $PAGE->set_url('/local/adapted/multimodal/multimodal_generation.php', array('courseid' => $courseid));
    $PAGE->set_title(get_string('multimodal_generation', 'local_adapted'));
    $PAGE->set_heading(get_string('multimodal_generation', 'local_adapted'));
} else {
    admin_externalpage_setup('multimodal_generation');
}

$PAGE->requires->jquery();
$PAGE->requires->js_init_call('M.local_adapted.init', array($COURSE->id));
$PAGE->requires->js(new moodle_url('/local/adapted/scripts/multimodal_generation.js'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('multimodal_generation', 'local_adapted'));

$templatecontext = [
    'wwwroot' => $CFG->wwwroot,
    'sesskey' => sesskey(),
    'courseid' => $courseid,
    'job_id' => $job_id
];

echo $OUTPUT->render_from_template('local_adapted/multimodal_generation', $templatecontext);
echo $OUTPUT->footer();
ob_end_flush();