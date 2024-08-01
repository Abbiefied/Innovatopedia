<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_adapted', get_string('pluginname', 'local_adapted')));

    $settings = new admin_settingpage('local_adapted_settings', get_string('settings', 'local_adapted'));
    
    $settings->add(new admin_setting_configtext('local_adapted/api_key',
        get_string('apikey', 'local_adapted'), 
        get_string('apikey_desc', 'local_adapted'), 
        '', 
        PARAM_TEXT
    ));

    $ADMIN->add('local_adapted', $settings);

    // Register the multimodal generation page
    $ADMIN->add('local_adapted', new admin_externalpage('multimodal_generation', 
        get_string('multimodal_generation', 'local_adapted'),
        new moodle_url('/local/adapted/multimodal/multimodal_generation.php')
    ));

}

