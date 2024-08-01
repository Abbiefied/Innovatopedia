<?php
function get_recommendations($userid, $courseid) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    debugging('Fetching recommendations for user ' . $userid . ' in course ' . $courseid);

    $url = 'http://recommender:5000/recommendations/' . $userid . '/' . $courseid;
    $curl = new curl();
    $curl->setopt(array(
        'CURLOPT_TIMEOUT' => 10,
        'CURLOPT_CONNECTTIMEOUT' => 10
    ));
    $response = $curl->get($url);
    
    if ($curl->get_errno()) {
        debugging('Curl error: ' . $curl->error);
        return [];
    }

    $recommendations = json_decode($response, true);
    if (!$recommendations || !is_array($recommendations)) {
        debugging('Invalid response from recommender service: ' . $response);
        return [];
    }

    if (empty($recommendations)) {
        debugging('Recommender service returned an empty list');
    } else {
        debugging('Received ' . count($recommendations) . ' recommendations');
    }

    return $recommendations;
}