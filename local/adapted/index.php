<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/local/adapted/lib/recommender.php');

require_login();
admin_externalpage_setup('local_adapted');

$PAGE->set_url(new moodle_url('/local/adapted/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_adapted'));
$PAGE->set_heading(get_string('pluginname', 'local_adapted'));

$iconurl = new moodle_url('/local/adapted/pix/chat_icon.jpg');
$PAGE->requires->css(new moodle_url('/local/adapted/styles/styles.css'));
$PAGE->requires->js(new moodle_url('/local/adapted/scripts/chatbot.js'));

echo $OUTPUT->header();
$templatecontext = (object)[
    'iconurl' => $iconurl
];
echo $OUTPUT->render_from_template('local_adapted/chatbot', $templatecontext);
echo $OUTPUT->footer();
