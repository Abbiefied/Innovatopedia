<?php
namespace local_adapted\task;

class update_recommender_model extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('update_recommender_model', 'local_adapted');
    }

    public function execute() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
    
        mtrace('Starting recommender model update task');
    
        $url = 'http://recommender:5000/update_model';
        $curl = new curl();
        $response = $curl->get($url);
        
        if ($curl->get_errno()) {
            mtrace('Curl error: ' . $curl->error);
        } else {
            mtrace('Model update response: ' . $response);
        }
    
        mtrace('Recommender model update task completed');
    }
}