<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/adapted/lib/chatbot.php');

require_login();
if (!isloggedin() || isguestuser()) {
    throw new moodle_exception('noguest');
}

$context = context_system::instance();
require_capability('moodle/site:config', $context);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Debugging: Check the received input
    error_log('Received input: ' . print_r($input, true));

    $message = $input['message'] ?? '';
    $username = $input['username'] ?? '';
    $context = $input['context'] ?? [];
    

    // Validate input
    if (empty($message)) {
        echo json_encode(['response' => 'Message is required']);
        exit;
    }

    try {
        $response = local_adapted_chatbot::get_response($message, $username, $context);
        // Debugging: Check the generated response
        error_log('Generated response: ' . $response);
        echo json_encode(['response' => $response]);
    } catch (Exception $e) {
        // Debugging: Log any exceptions
        error_log('Error: ' . $e->getMessage());
        echo json_encode(['response' => 'Error fetching response: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['response' => 'Invalid request method']);
}

