<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'db';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'moodle';
$CFG->dbpass    = 'moodlepass';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array(
    'dbpersist' => false,
    'dbsocket'  => false,
    'dbport'    => 5432,
);

$CFG->wwwroot   = 'https://localhost';
$CFG->dataroot  = '/var/www/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;
$CFG->sslproxy = true;
$CFG->purge_pluginlist_cache = true;

// Show debug messages
$CFG->debug = E_ALL;

// Show developer level messages
$CFG->debugdisplay = 1;

// Don't hide PHP notices
$CFG->debugdeveloper = 1;

// Log all errors
$CFG->debugdisplay = 1;

// Increase the verbosity of error logging
$CFG->debug = 32767;

// Show database errors
$CFG->dblogerror = true;

// Enable performance info
$CFG->perfdebug = 15;

// Enable backtrace in error messages
$CFG->debugdeveloper = 1;

require_once(__DIR__ . '/lib/setup.php');