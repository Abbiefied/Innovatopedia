<?php
class block_multimodal_generator extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_multimodal_generator');
    }

    public function get_content() {
        global $OUTPUT, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (has_capability('block/multimodal_generator:use', $this->context)) {
            $url = new moodle_url('/local/adapted/multimodal/multimodal_generation.php', array('courseid' => $COURSE->id));
            $button = $OUTPUT->single_button($url, get_string('generate_multimodal', 'block_multimodal_generator'), 'get');
            $this->content->text = $button;
        }

        return $this->content;
    }

    public function applicable_formats() {
        return array('course' => true);
    }
}