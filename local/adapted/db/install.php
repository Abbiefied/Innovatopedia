<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_adapted_install() {
    // Set default value for API key setting
    set_config('api_key', '', 'local_adapted');
}
