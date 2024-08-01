<?php
namespace local_adapted\output;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../locallib.php');

class renderer extends \plugin_renderer_base {
    public function render_recommendations($userid) {
        global $OUTPUT, $COURSE;
        
        // Get the current course ID
        $courseid = $COURSE->id;
        
        $recommendations = get_recommendations($userid, $courseid);
        $templatecontext = [
            'heading' => get_string('recommendations', 'local_adapted'),
            'recommendations' => array_slice($recommendations, 0, 3)
        ];
        return $OUTPUT->render_from_template('local_adapted/recommender', $templatecontext);
    }
}