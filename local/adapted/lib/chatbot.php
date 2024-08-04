<?php
defined('MOODLE_INTERNAL') || die();

class local_adapted_chatbot {
    public static function get_response($message) {
        global $CFG, $DB;

        $api_key = get_config('local_adapted', 'api_key');
        if (empty($api_key)) {
            return 'API key is not set';
        }

        $url = 'https://api.openai.com/v1/chat/completions';
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are a helpful assistant for a Moodle learning management system. 
                                You can provide information about courses, assignments, grades, and deadlines. 
                                When asked about specific courses, modules, content, assignments, grades, 
                                or deadlines, first extract the relevant information from the user's query, 
                                then use the provided functions to fetch the required data from Moodle before 
                                answering. Always consider the user's context and provide personalized responses.
                                For queries that are not related to the Moodle context, use OpenAI gpt"
                ],
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
            'functions' => [
                [
                    'name' => 'get_course_info',
                    'description' => 'Get information about a specific course',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'course_name' => [
                                'type' => 'string',
                                'description' => 'The name of the course'
                            ]
                        ],
                        'required' => ['course_name']
                    ]
                ],
                [
                    'name' => 'get_course_content',
                    'description' => 'Get content for a specific week of a course',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'course_name' => [
                                'type' => 'string',
                                'description' => 'The name of the course'
                            ],
                            'week_number' => [
                                'type' => 'integer',
                                'description' => 'The week number'
                            ]
                        ],
                        'required' => ['course_name', 'week_number']
                    ]
                ],
                [
                    'name' => 'get_user_assignments',
                    'description' => 'Get assignments for a specific user',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'username' => [
                                'type' => 'string',
                                'description' => 'The username of the student'
                            ]
                        ],
                        'required' => ['username']
                    ]
                ],
                [
                    'name' => 'get_user_grades',
                    'description' => 'Get grades for a specific user',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'username' => [
                                'type' => 'string',
                                'description' => 'The username of the student'
                            ],
                            'course_name' => [
                                'type' => 'string',
                                'description' => 'The name of the course (optional)'
                            ]
                        ],
                        'required' => ['username']
                    ]
                ],
                [
                    'name' => 'get_upcoming_deadlines',
                    'description' => 'Get upcoming deadlines for a specific user',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'username' => [
                                'type' => 'string',
                                'description' => 'The username of the student'
                            ],
                            'days' => [
                                'type' => 'integer',
                                'description' => 'Number of days to look ahead (default: 7)'
                            ]
                        ],
                        'required' => ['username']
                    ]
                ]   
            ],
            'function_call' => 'auto'
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n" .
                             "Authorization: Bearer $api_key\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
            ],
        ];

        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === FALSE) {
            $error = error_get_last();
            error_log('Error fetching from OpenAI: ' . $error['message']);
            return 'Error contacting OpenAI API: ' . $error['message'];
        }

        $response = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Error decoding JSON response: ' . json_last_error_msg());
            return 'Error decoding response';
        }
        
        $message = $response['choices'][0]['message'];
        
        if (isset($message['function_call'])) {
            $function_name = $message['function_call']['name'];
            $arguments = json_decode($message['function_call']['arguments'], true);
            
            $function_response = self::call_moodle_function($function_name, $arguments);
            
            // Call API again with the function response
            $data['messages'][] = [
                'role' => 'function',
                'name' => $function_name,
                'content' => json_encode($function_response)
            ];
            
            $options['http']['content'] = json_encode($data);
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $response = json_decode($result, true);
        }

        return $response['choices'][0]['message']['content'] ?? 'No response';
    }

    private static function call_moodle_function($function_name, $arguments) {
        global $DB;

        if ($function_name === 'get_user_assignments') {
            // Debugging statement
            error_log('call_moodle_function: get_user_assignments called with params: ' . print_r($params, true));
        }
        switch ($function_name) {
            case 'get_course_info':
                $course = $DB->get_record('course', ['fullname' => $arguments['course_name']], '*', MUST_EXIST);
                return [
                    'id' => $course->id,
                    'fullname' => $course->fullname,
                    'shortname' => $course->shortname,
                    'summary' => $course->summary
                ];

            case 'get_course_content':
                $course = $DB->get_record('course', ['fullname' => $arguments['course_name']], '*', MUST_EXIST);
                $modinfo = get_fast_modinfo($course);
                $section = $modinfo->get_section_info($arguments['week_number']);
                $modules = [];
                foreach ($modinfo->sections[$arguments['week_number']] as $modnumber) {
                    $mod = $modinfo->cms[$modnumber];
                    $modules[] = [
                        'name' => $mod->name,
                        'type' => $mod->modname,
                        'url' => $mod->url,
                    ];
                }
                return [
                    'week' => $arguments['week_number'],
                    'name' => $section->name,
                    'summary' => $section->summary,
                    'modules' => $modules
                ];

            case 'get_user_assignments':
                $user = $DB->get_record('user', ['username' => $arguments['username']], '*', MUST_EXIST);
                $courses = enrol_get_users_courses($user->id);
                $assignments = [];
                foreach ($courses as $course) {
                    $cms = get_fast_modinfo($course)->get_instances_of('assign');
                    foreach ($cms as $cm) {
                        $assignments[] = [
                            'course' => $course->fullname,
                            'name' => $cm->name,
                            'duedate' => $cm->instance->duedate,
                            'url' => $cm->url,
                        ];
                    }
                }
                return $assignments;

            case 'get_user_grades':
                $user = $DB->get_record('user', ['username' => $arguments['username']], '*', MUST_EXIST);
                $courses = isset($arguments['course_name']) 
                    ? [$DB->get_record('course', ['fullname' => $arguments['course_name']], '*', MUST_EXIST)]
                    : enrol_get_users_courses($user->id);
                $grades = [];
                foreach ($courses as $course) {
                    $course_grades = grade_get_grades($course->id, 'all', 'all', null, $user->id);
                    foreach ($course_grades->items as $item) {
                        $grades[] = [
                            'course' => $course->fullname,
                            'item' => $item->name,
                            'grade' => $item->grades[$user->id]->str_grade,
                        ];
                    }
                }
                return $grades;

            case 'get_upcoming_deadlines':
                $user = $DB->get_record('user', ['username' => $arguments['username']], '*', MUST_EXIST);
                $days = isset($arguments['days']) ? $arguments['days'] : 7;
                $courses = enrol_get_users_courses($user->id);
                $deadlines = [];
                $now = time();
                $future = $now + ($days * 24 * 60 * 60);
                foreach ($courses as $course) {
                    $modinfo = get_fast_modinfo($course);
                    foreach ($modinfo->get_instances_of('assign') as $cm) {
                        $assignment = $cm->instance;
                        if ($assignment->duedate > $now && $assignment->duedate <= $future) {
                            $deadlines[] = [
                                'course' => $course->fullname,
                                'name' => $cm->name,
                                'duedate' => date('Y-m-d H:i', $assignment->duedate),
                                'url' => $cm->url,
                            ];
                        }
                    }
                }
                return $deadlines;

                case 'get_user_assignments':
                    $user = $DB->get_record('user', ['username' => $arguments['username']], '*', MUST_EXIST);
                    $courses = enrol_get_users_courses($user->id);
                    $assignments = [];
                    foreach ($courses as $course) {
                        $cms = get_fast_modinfo($course)->get_instances_of('assign');
                        foreach ($cms as $cm) {
                            $assignments[] = [
                                'course' => $course->fullname,
                                'name' => $cm->name,
                                'duedate' => $cm->instance->duedate,
                                'url' => $cm->url,
                            ];
                        }
                    }
                    return $assignments;
        
                case 'get_user_grades':
                    $user = $DB->get_record('user', ['username' => $arguments['username']], '*', MUST_EXIST);
                    $courses = isset($arguments['course_name']) 
                        ? [$DB->get_record('course', ['fullname' => $arguments['course_name']], '*', MUST_EXIST)]
                        : enrol_get_users_courses($user->id);
                    $grades = [];
                    foreach ($courses as $course) {
                        $course_grades = grade_get_grades($course->id, 'all', 'all', null, $user->id);
                        foreach ($course_grades->items as $item) {
                            $grades[] = [
                                'course' => $course->fullname,
                                'item' => $item->name,
                                'grade' => $item->grades[$user->id]->str_grade,
                            ];
                        }
                    }
                    return $grades;
        
                case 'get_upcoming_deadlines':
                    $user = $DB->get_record('user', ['username' => $arguments['username']], '*', MUST_EXIST);
                    $days = isset($arguments['days']) ? $arguments['days'] : 7;
                    $courses = enrol_get_users_courses($user->id);
                    $deadlines = [];
                    $now = time();
                    $future = $now + ($days * 24 * 60 * 60);
                    foreach ($courses as $course) {
                        $modinfo = get_fast_modinfo($course);
                        foreach ($modinfo->get_instances_of('assign') as $cm) {
                            $assignment = $cm->instance;
                            if ($assignment->duedate > $now && $assignment->duedate <= $future) {
                                $deadlines[] = [
                                    'course' => $course->fullname,
                                    'name' => $cm->name,
                                    'duedate' => date('Y-m-d H:i', $assignment->duedate),
                                    'url' => $cm->url,
                                ];
                            }
                        }
                    }
                    return $deadlines;
        
                default:
                    return null;
            }
    }
}