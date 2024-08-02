<?php
use PHPUnit\Framework\TestCase;

class ChatbotTest extends TestCase
{
    protected $chatbot;

    protected function setUp(): void
    {
        parent::setUp();

        // Define MOODLE_INTERNAL if not already defined
        if (!defined('MOODLE_INTERNAL')) {
            define('MOODLE_INTERNAL', true);
        }

        // Mock global $CFG object
        global $CFG;
        $CFG = new stdClass();
        $CFG->dirroot = __DIR__ . '/..'; // Adjust this path as needed
        $CFG->wwwroot = 'http://example.com/moodle';
        $CFG->dataroot = __DIR__ . '/moodledata';
        $CFG->prefix = 'mdl_';
        $CFG->dboptions = array();

        // Mock global $DB object
        global $DB;
        $DB = $this->createMock(\moodle_database::class);

        // Mock common Moodle functions
        $this->mockMoodleFunctions();

        // Include your chatbot file
        require_once($CFG->dirroot . '/local/adapted/chatbot.php');

        // Instantiate your chatbot class
        $this->chatbot = new local_adapted_chatbot();
    }

    protected function mockMoodleFunctions()
    {
        // Mock get_string function
        if (!function_exists('get_string')) {
            function get_string($identifier, $component = '', $a = null) {
                return $identifier;
            }
        }

        // Mock required_param function
        if (!function_exists('required_param')) {
            function required_param($parname, $type) {
                return 'mocked_param';
            }
        }

        // Mock optional_param function
        if (!function_exists('optional_param')) {
            function optional_param($parname, $default, $type) {
                return $default;
            }
        }

        // Mock has_capability function
        if (!function_exists('has_capability')) {
            function has_capability($capability, $context, $userid = null, $doanything = true) {
                return true;
            }
        }

        // Mock enrol_get_my_courses function
        if (!function_exists('enrol_get_my_courses')) {
            function enrol_get_my_courses($fields = NULL, $sort = 'visible DESC,sortorder ASC', $limit = 0) {
                return array(
                    (object)array('id' => 1, 'fullname' => 'Test Course 1'),
                    (object)array('id' => 2, 'fullname' => 'Test Course 2')
                );
            }
        }
    }

    public function testGetCourseInfo()
    {
        global $DB;

        $DB->expects($this->once())
           ->method('get_record')
           ->with('course', ['fullname' => 'Introduction to Computer Science'], '*', MUST_EXIST)
           ->willReturn((object)[
               'id' => 1,
               'fullname' => 'Introduction to Computer Science',
               'shortname' => 'CS101',
               'summary' => 'An introductory course to CS'
           ]);

        $result = $this->chatbot->call_moodle_function('get_course_info', ['course_name' => 'Introduction to Computer Science']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('fullname', $result);
        $this->assertEquals('Introduction to Computer Science', $result['fullname']);
    }

    public function testGetUserAssignments()
    {
        global $DB;

        $DB->expects($this->once())
           ->method('get_record')
           ->with('user', ['username' => 'testuser'], '*', MUST_EXIST)
           ->willReturn((object)['id' => 1, 'username' => 'testuser']);

        // Mock the get_fast_modinfo function
        global $CFG;
        $CFG->modinfo = $this->createMock(\course_modinfo::class);
        $CFG->modinfo->expects($this->any())
            ->method('get_instances_of')
            ->with('assign')
            ->willReturn([
                (object)['name' => 'Assignment 1', 'instance' => (object)['duedate' => time() + 86400], 'url' => 'http://example.com/mod/assign/view.php?id=1'],
                (object)['name' => 'Assignment 2', 'instance' => (object)['duedate' => time() + 172800], 'url' => 'http://example.com/mod/assign/view.php?id=2']
            ]);

        $result = $this->chatbot->call_moodle_function('get_user_assignments', ['username' => 'testuser']);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Assignment 1', $result[0]['name']);
        $this->assertEquals('Assignment 2', $result[1]['name']);
    }

    // Add more test methods for other chatbot functions
}