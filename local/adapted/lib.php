<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/adapted/locallib.php');

function local_adapted_extend_navigation(global_navigation $navigation) {
    global $PAGE;
    $PAGE->requires->css(new moodle_url('/local/adapted/styles/styles.css'));
    $PAGE->requires->js(new moodle_url('/local/adapted/scripts/chatbot.js'));
    
    $iconurl = new moodle_url('/local/adapted/pix/chat_icon.png');
    $templatecontext = (object)[
        'iconurl' => $iconurl
    ];
    echo $PAGE->get_renderer('core')->render_from_template('local_adapted/chatbot', $templatecontext);
}

function local_adapted_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {

    // Check if the filearea is correct
    if ($filearea !== 'multimodal_content') {
        return false;
    }

    require_login($course);

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/'.implode('/', $args).'/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_adapted', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    // Send the file
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function local_adapted_extend_navigation_course($navigation, $course, $context) {
    global $PAGE, $USER;
    
    $PAGE->requires->css(new moodle_url('/local/adapted/styles/styles.css'));
    
    if (isloggedin() && has_capability('local/adapted:view_recommendations', $context)) {
        $renderer = $PAGE->get_renderer('local_adapted');
        $recommendations = $renderer->render_recommendations($USER->id, $course->id);
        
        // Encode the recommendations to be safely passed to JavaScript
        $encoded_recommendations = json_encode($recommendations);
        
        // Inline JavaScript to inject the recommendations
        $PAGE->requires->js_init_code("
            document.addEventListener('DOMContentLoaded', function() {
                var recommendationsHtml = " . $encoded_recommendations . ";
                var pageFooter = document.getElementById('page-footer');
                if (pageFooter) {
                    var div = document.createElement('div');
                    div.innerHTML = recommendationsHtml;
                    pageFooter.insertBefore(div, pageFooter.firstChild);
                }
            });
        ");
    }
}