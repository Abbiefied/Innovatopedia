<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_adapted_upgrade($oldversion) {
    global $DB;

    $result = true;

    if ($oldversion < 2024070800) {
        // Set default value for API key setting
        set_config('api_key', '', 'local_adapted');

        // Moodle savepoint reached
        upgrade_plugin_savepoint(true, 2024070800, 'local', 'adapted');
    }

    return $result;
}
