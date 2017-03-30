<?php

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Question web service plugin
 * @package   local_question_ws
 * @copyright 2014 Oxford Brookes University
 * @author    Peter Andrew
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define the web service functions to install.
$functions = array(
        'local_question_ws_get_courses' => array(
                'classname'   => 'local_question_ws_external',
                'methodname'  => 'get_courses',
                'classpath'   => 'local/question_ws/externallib.php',
                'description' => 'Returns array of courses (course_id, course_fullname, is_module_leader) running in current semester that user is enrolled on. Run dates are removed from course_fullname',
                'type'        => 'read'
        ),
        'local_question_ws_create_question_forum' => array(
                'classname'   => 'local_question_ws_external',
                'methodname'  => 'create_question_forum',
                'classpath'   => 'local/question_ws/externallib.php',
                'description' => 'Creates new question forum. course_id and week_topic are passed in as parameters. If week_topic is an integer, forum will have title like "Question forum - week ...". If week_topic is a string, forum will have title like "Question forum - ...". If course is in weekly format and week_topic is integer, question forum is added to appropriate week. If course is in topics format or week_topic is string, question forum is added to first section. Returns forum_id. If question forum already exists, returns error.',
                'type'        => 'write',
		'capabilities'=> 'moodle/course:manageactivities, moodle/course:activityvisibility, mod/forum:addinstance'
        ),
        'local_question_ws_get_question_forums' => array(
                'classname'   => 'local_question_ws_external',
                'methodname'  => 'get_question_forums',
                'classpath'   => 'local/question_ws/externallib.php',
                'description' => 'Returns array of question forums (forum_id, forum_title). course_id is passed in as parameter.',
                'type'        => 'read',
		'capabilities'=> 'mod/forum:viewdiscussion'
        ),
        'local_question_ws_get_questions' => array(
                'classname'   => 'local_question_ws_external',
                'methodname'  => 'get_questions',
                'classpath'   => 'local/question_ws/externallib.php',
                'description' => 'Returns array of questions (question_id, question text, user full name, modified time, number of answers). forum_id is passed in as parameter.',
                'type'        => 'read',
		'capabilities'=> 'mod/forum:viewdiscussion'
        ),
        'local_question_ws_ask_question' => array(
                'classname'   => 'local_question_ws_external',
                'methodname'  => 'ask_question',
                'classpath'   => 'local/question_ws/externallib.php',
                'description' => 'Adds new question to forum. forum_id, question_text and (optional) anonymous flag are passed in as parameters. Returns question_id. Anonymous user is used to ask question if anonymous flag is present and set.',
                'type'        => 'write',
		'capabilities'=> 'mod/forum:startdiscussion'
        ),
	'local_question_ws_delete_question' => array(
                'classname'   => 'local_question_ws_external',
                'methodname'  => 'delete_question',
                'classpath'   => 'local/question_ws/externallib.php',
                'description' => 'Delete question. Only the module leader is able to delete a question. question_id is passed in as a parameter.',
                'type'        => 'write',
		'capabilities'=> 'mod/forum:deleteanypost'
	),
        'local_question_ws_answer_question' => array(
                'classname'   => 'local_question_ws_external',
                'methodname'  => 'answer_question',
                'classpath'   => 'local/question_ws/externallib.php',
                'description' => 'Answer question in forum. question_id, answer_text and (optional) anonymous flag are passed in as parameters.  Anonymous user is used to answer question if anonymous flag is present and set.',
                'type'        => 'write',
		'capabilities'=> 'mod/forum:replypost'
        ),
        'local_question_ws_get_answers' => array(
                'classname'   => 'local_question_ws_external',
                'methodname'  => 'get_answers',
                'classpath'   => 'local/question_ws/externallib.php',
                'description' => 'Get answers to question. Returns array of answer_text, user full name, modified time. question_id is passed in as a parameter',
                'type'        => 'read',
		'capabilities'=> 'mod/forum:viewdiscussion'
        )
);

// Define the services to install as pre-build services.
$services = array(
        'Question web service' => array(
		'shortname' => 'question_service',
                'functions' => array(
			'local_question_ws_get_courses',
			'local_question_ws_create_question_forum',
			'local_question_ws_get_question_forums',
			'local_question_ws_get_questions',
			'local_question_ws_ask_question',
			'local_question_ws_delete_question',
			'local_question_ws_answer_question',
			'local_question_ws_get_answers'
		),
                'restrictedusers' => 0,
                'enabled'=>1
        )
);
