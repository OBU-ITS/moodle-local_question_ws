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
require_once($CFG->libdir . "/externallib.php");

class local_question_ws_external extends external_api {
	public static function get_courses_parameters() {
		return new external_function_parameters(array());
	}


	public static function get_courses_returns() {
		return new external_multiple_structure(
			new external_single_structure(
				array(
					'course_id' => new external_value(PARAM_INT, 'Course ID'),
					'course_fullname' => new external_value(PARAM_TEXT, 'Full name of course'),
					'is_module_leader' => new external_value(PARAM_BOOL, 'True if user is module leader of course')
				)
			)
		);
	}


	public static function get_courses() {
		global $CFG, $DB, $USER;

		// Context validation
		$context = context_system::instance();
		self::validate_context($context);

		// Get module leader role
		$module_leader_role = $DB->get_record('role', array('shortname'=>'course_leader'), 'id', MUST_EXIST);

		// Build and execute course query
		$sql	= 'SELECT '
			. ' c.id,'
			. ' c.fullname,'
			. ' substr(c.shortname,8,3) as startmonth,'
			. ' substr(c.shortname,11,2) as startyear,'
			. ' substr(c.shortname,14,3) as endmonth,'
			. ' substr(c.shortname,17,2) as endyear '
			. 'FROM {course} c '
			. 'JOIN {enrol} e ON e.courseid = c.id '
			. 'JOIN {user_enrolments} ue ON ue.enrolid = e.id '
			. 'WHERE'
			. ' ue.userid = ? AND'
			. ' c.visible = 1 AND'
			. " substr(c.shortname, 7, 1) = ' ' AND"
			. " substr(c.shortname, 13, 1) = '-' AND"
			. ' length(c.shortname) >= 18';
		$db_ret = $DB->get_records_sql($sql, array($USER->id));

		$courses = array();
		foreach ($db_ret as $row) {
			// Check whether course is currently running
			$course_start_timestamp = strtotime(
				'01 ' .
				$row->startmonth . ' ' .
				$row->startyear
			);

			$course_end_timestamp = strtotime(
				'31 ' .
				$row->endmonth . ' ' .
				$row->endyear
			);

			$currtime = time();

			if ($currtime < $course_start_timestamp || $currtime > $course_end_timestamp) {
				continue;
			}

			$context = context_course::instance($row->id);
			if (!is_enrolled($context)) {
				// If we've got here something's wrong with the enrolments on this course, skip it
				continue;
			}

			// Check whether current user is a module leader
			$module_leaders = get_role_users($module_leader_role->id, $context, false, 'u.id');
			$is_module_leader = false;
			foreach ($module_leaders as $module_leader) {
				if ($module_leader->id == $USER->id) {
					$is_module_leader = true;
					break;
				}
			}

			// Remove run dates from course title
			$run_dates_search = '(' . $row->startmonth;
			$run_dates_pos = strpos($row->fullname, $run_dates_search);
			if ($run_dates_pos !== false) {
				$row->fullname = substr($row->fullname, 0, $run_dates_pos - 1);
			}

			$courses[] = array(
				'course_id' => $row->id,
				'course_fullname' => $row->fullname,
				'is_module_leader' => $is_module_leader
			);
		}

		return $courses;
	}


	public static function create_question_forum_parameters() {
		return new external_function_parameters(
			array(
				'course_id' => new external_value(PARAM_INT, 'ID of course in which to create forum'),
				'week_topic' => new external_value(PARAM_TEXT, 'Week number or topic of forum')
			)
		);
	}


	public static function create_question_forum_returns() {
		return new external_single_structure(
			array(
				'forum_id' => new external_value(PARAM_INT, 'ID of new forum')
			)
		);
	}


	public static function create_question_forum($course_id, $week_topic) {
		global $CFG, $DB, $USER;

		// Parameter validation
		$params = self::validate_parameters(
				self::create_question_forum_parameters(), array(
					'course_id' => $course_id,
					'week_topic' => $week_topic
				)
		);

		if ($params['course_id'] < 1) {
			throw new invalid_parameter_exception('course_id must be a positive integer');
		}
		if (is_numeric($params['week_topic'])) {
			$params['week_topic'] = (int) $params['week_topic'];
			if ($params['week_topic'] < 1) {
				throw new invalid_parameter_exception('week_topic must be a positive integer');
			}
		} else {
			if (strlen($params['week_topic']) < 1) {
				throw new invalid_parameter_exception('week_topic must be a non-empty string');
			}
		}

        	// Context validation
		$context = context_course::instance($params['course_id']);
		self::validate_context($context);

	        // Capability checking
		require_capability('moodle/course:manageactivities', $context);
		require_capability('moodle/course:activityvisibility', $context);
		require_capability('mod/forum:addinstance', $context);

		// Check course is correct format
		$course = $DB->get_record('course', array('id'=>$params['course_id']), '*', MUST_EXIST);
		if (in_array($course->format, array('site', 'social', 'scorm'))) {
			throw new moodle_exception('invalidcourseformat', 'error', '', null, 'course must be in weekly or topic format');
		}

		$course_format = course_get_format($course);

		$format_options = $course_format->get_format_options();
		$num_sections = $format_options['numsections'];

		if ($course->format == 'topcoll') {
			$layout_structure = $format_options['layoutstructure'];
			switch ($layout_structure) {
				case 1:
					$structure = 'topics';
					break;
				case 2:
					$structure = 'weeks';
					break;
				default:
					throw new moodle_exception('invalidcourseformat', 'error', '', null, 'course must be in weekly or topic format');
					break;
			}
		} else {
			$structure = $course->format;
		}

		// Set section in which to create forum
		if ($structure == 'topics' || !is_int($params['week_topic'])) {
			$use_section = 0;
		} else {
			if ($params['week_topic'] <= $num_sections) {
				$use_section = $params['week_topic'];
			} else {
				$use_section = $num_sections;
			}
		}

		// Check question forum does not already exist in course
		require_once($CFG->dirroot . '/mod/forum/lib.php');

		if (is_int($params['week_topic'])) {
			$forum_title = 'Question forum - week ' . $params['week_topic'];
		} else {
			$forum_title = 'Question forum - ' . $params['week_topic'];
		}
		$readable_forums = forum_get_readable_forums($USER->id, $params['course_id']);
		if ($readable_forums) {
			foreach ($readable_forums as $forum) {
				if ($forum->name == $forum_title) {
					throw new moodle_exception('forumalreadyexists', 'error', '', null, 'question forum for week already exists in course');
				}
			}
		}

		require_once($CFG->dirroot . '/course/lib.php');

		// Set course module initial data
		$newcm = new stdClass();
		$newcm->course = $params['course_id'];
		$newcm->module = 7;
		$newcm->modulename = 'forum';
		$newcm->section = $use_section;
		$newcm->instance = 0;
		$newcm->visible = 1;
		$newcm->groupmode = 0; // No groups
		$newcm->groupingid = 0;
		$newcm->groupmembersonly = 0;
		$newcm->showdescription = 0;
		$newcm->cmidnumber = '';
		$newcm->name = $forum_title;
		$newcm->intro = '';
		$newcm->introformat = 1;

		// Create course module
		if (!$newcm->coursemodule = add_course_module($newcm)) {
			throw new moodle_exception('coursemodulecreateerror', 'error', '', null, 'error creating new course module');
		}

		// Create module instance
		$newcm->type = 'general';
		$newcm->forcesubscribe = FORUM_CHOOSESUBSCRIBE;

		$ret = forum_add_instance($newcm);
		if (!$ret || !is_int($ret)) {
			// Error adding forum instance, remove course module and context
			$module_context = context_module::instance($newcm->coursemodule);
			$module_context->delete();
			$DB->delete_records('course_modules', array('id'=>$newcm->coursemodule));
			throw new moodle_exception('forumcreateerror', 'error', '', null, 'error creating new forum instance');
		}
		$newcm->instance = $ret;

		// Update course_modules DB row to reference new module instance
		$DB->set_field('course_modules', 'instance', $newcm->instance, array('id'=>$newcm->coursemodule));

		// Add module to section
		$section_id = course_add_cm_to_section($newcm->course, $newcm->coursemodule, $newcm->section);
		
		// Trigger mod_created event with information about this module
		$eventname = 'mod_created';
		$eventdata = new stdClass();
		$eventdata->modulename = $module->name;
		$eventdata->name       = $newcm->name;
		$eventdata->cmid       = $newcm->coursemodule;
		$eventdata->courseid   = $course->id;
		$eventdata->userid     = 0;
		events_trigger($eventname, $eventdata);

		return array('forum_id' => $ret);
	}


	public static function get_question_forums_parameters() {
		return new external_function_parameters(
			array(
				'course_id' => new external_value(PARAM_INT, 'ID of course from which to fetch forums')
			)
		);
	}


	public static function get_question_forums_returns() {
		return new external_multiple_structure(
			new external_single_structure(
				array(
					'forum_id' => new external_value(PARAM_INT, 'ID of forum'),
					'forum_title' => new external_value(PARAM_TEXT, 'Title of question forum')
				)
			)
		);
	}


	public static function get_question_forums($course_id) {
		global $CFG, $USER;

		// Parameter validation
		$params = self::validate_parameters(
				self::get_question_forums_parameters(), array(
					'course_id' => $course_id
				)
		);

		if ($params['course_id'] < 1) {
			throw new invalid_parameter_exception('course_id must be a positive integer');
		}

        	// Context validation
		$context = context_course::instance($params['course_id']);
		self::validate_context($context);

	        // Capability checking
		require_capability('mod/forum:viewdiscussion', $context);

		require_once($CFG->dirroot . '/mod/forum/lib.php');

		$readable_forums = forum_get_readable_forums($USER->id, $params['course_id']);
		if (!$readable_forums) {
			return array();
		}

		$question_forums = array();
		foreach ($readable_forums as $forum) {
			if (strpos($forum->name, 'Question forum') !== false) {
				// Return only what's after the 'Question forum - ' part
				$forum_title = substr($forum->name, strpos($forum->name, '-') + 2);
				$question_forums[] = array(
					'forum_id' => $forum->id,
					'forum_title' => $forum_title
				);
			}
		}

		return $question_forums;
	}


	public static function get_questions_parameters() {
		return new external_function_parameters(
			array(
				'forum_id' => new external_value(PARAM_INT, 'ID of forum from which to get questions')
			)
		);
	}


	public static function get_questions_returns() {
		return new external_multiple_structure(
			new external_single_structure(
				array(
					'question_id' => new external_value(PARAM_INT, 'Question ID'),
					'question_text' => new external_value(PARAM_TEXT, 'Question text'),
					'user_full_name' => new external_value(PARAM_TEXT, 'User full name')
				)
			)
		);
	}


	public static function get_questions($forum_id) {
		global $CFG;

		// Parameter validation
		$params = self::validate_parameters(
				self::get_questions_parameters(), array(
					'forum_id' => $forum_id
				)
		);

		if ($params['forum_id'] < 1) {
			throw new invalid_parameter_exception('forum_id must be a positive integer');
		}

		$course_module = get_coursemodule_from_instance('forum', $forum_id, 0, false, MUST_EXIST);

        	// Context validation
		$context = context_module::instance($course_module->id);
		self::validate_context($context);

	        // Capability checking
		require_capability('mod/forum:viewdiscussion', $context);

		require_once($CFG->dirroot . '/mod/forum/lib.php');

		$discussions = forum_get_discussions($course_module);

		$questions = array();
		foreach ($discussions as $discussion) {
			$questions[] = array(
				'question_id' => $discussion->discussion,
				'question_text' => strip_tags($discussion->message),
				'user_full_name' => $discussion->firstname . ' ' . $discussion->lastname
			);
		}

		return $questions;
	}


	public static function ask_question_parameters() {
		return new external_function_parameters(
			array(
				'forum_id' => new external_value(PARAM_INT, 'ID of forum in which to create question'),
				'question_text' => new external_value(PARAM_TEXT, 'Question text'),
				'anonymous' => new external_value(PARAM_BOOL, 'If true question is asked with anonymous user')
			)
		);
	}


	public static function ask_question_returns() {
		return new external_single_structure(
			array(
				'question_id' => new external_value(PARAM_INT, 'ID of new question')
			)
		);
	}


	public static function ask_question($forum_id, $question_text, $anonymous) {
		global $CFG, $DB, $USER;

		// Parameter validation
		$params = self::validate_parameters(
				self::ask_question_parameters(), array(
					'forum_id' => $forum_id,
					'question_text' => $question_text,
					'anonymous' => $anonymous
				)
		);

		if ($params['forum_id'] < 1) {
			throw new invalid_parameter_exception('forum_id must be a positive integer');
		}
		if (strlen($params['question_text']) < 1) {
			throw new invalid_parameter_exception('question_text must be a non-empty string');
		}

		$course_module = get_coursemodule_from_instance('forum', $params['forum_id'], 0, false, MUST_EXIST);

        	// Context validation
		$context = context_module::instance($course_module->id);
		self::validate_context($context);

	        // Capability checking
		require_capability('mod/forum:startdiscussion', $context);

		if ($anonymous) {
			$user = $DB->get_record('user', array('username' => 'anonquestion'), 'id', MUST_EXIST);
		} else {
			$user = $USER;
		}

		require_once($CFG->dirroot . '/mod/forum/lib.php');

		$discussion = new stdClass();
		$discussion->forum = $params['forum_id'];
		$discussion->course = $course_module->course;
		if (strlen($params['question_text'] > 100)) {
			$discussion->name = substr($params['question_text'], 0, 100) . '...';
		} else {
			$discussion->name = $params['question_text'];
		}
		$discussion->message = $params['question_text'];
		$discussion->messageformat = 1;
		$discussion->messagetrust = 1;
		$discussion->mailnow = true;

		$message = null;
		$discussion_id = forum_add_discussion($discussion, null, $message, $user->id);

		return array('question_id' => $discussion_id);
	}


	public static function delete_question_parameters() {
		return new external_function_parameters(
			array(
				'question_id' => new external_value(PARAM_INT, 'ID of question to delete')
			)
		);
	}


	public static function delete_question_returns() {
		return null;
	}


	public static function delete_question($question_id) {
		global $CFG, $DB, $USER;

		// Parameter validation
		$params = self::validate_parameters(
				self::delete_question_parameters(), array(
					'question_id' => $question_id
				)
		);

		if ($params['question_id'] < 1) {
			throw new invalid_parameter_exception('question_id must be a positive integer');
		}

		$discussion = $DB->get_record('forum_discussions', array('id' => $params['question_id']), 'id, forum', MUST_EXIST);
		$forum      = $DB->get_record('forum', array('id' => $discussion->forum), 'id', MUST_EXIST);
		$cm         = get_coursemodule_from_instance('forum', $forum->id);
		$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        	// Context validation
		$context    = context_module::instance($cm->id);
		self::validate_context($context);

	        // Capability checking
		require_capability('mod/forum:deleteanypost', $context);

		require_once($CFG->dirroot . '/mod/forum/lib.php');

		forum_delete_discussion($discussion, false, $course, $cm, $forum);
	}


	public static function answer_question_parameters() {
		return new external_function_parameters(
			array(
				'question_id' => new external_value(PARAM_INT, 'ID of question to answer'),
				'answer_text' => new external_value(PARAM_TEXT, 'Answer text')
			)
		);
	}


	public static function answer_question_returns() {
		return null;
	}


	public static function answer_question($question_id, $answer_text) {
		global $DB, $USER;

		// Parameter validation
		$params = self::validate_parameters(
				self::answer_question_parameters(), array(
					'question_id' => $question_id,
					'answer_text' => $answer_text
				)
		);

		if ($params['question_id'] < 1) {
			throw new invalid_parameter_exception('question_id must be a positive integer');
		}
		if (strlen($params['answer_text']) < 1) {
			throw new invalid_parameter_exception('answer_text must be a non-empty string');
		}

		$discussion = $DB->get_record('forum_discussions', array('id' => $params['question_id']), 'id, forum, firstpost', MUST_EXIST);
		$forum      = $DB->get_record('forum', array('id' => $discussion->forum), 'id', MUST_EXIST);
		$cm         = get_coursemodule_from_instance('forum', $forum->id);


        	// Context validation
		$context    = context_module::instance($cm->id);
		self::validate_context($context);

	        // Capability checking
		require_capability('mod/forum:replypost', $context);

		$post = new stdClass();
		$post->discussion = $discussion->id;
		$post->parent = $discussion->firstpost;
		$post->userid = $USER->id;		
		$post->created = $post->modified = time();
		$post->mailed     = "0";
		$post->attachment = "";
		$post->message = $params['answer_text'];
		$post->messageformat = 1;
		$post->messagetrues = 1;

		$post->id = $DB->insert_record("forum_posts", $post);
	}	
			

	public static function get_answers_parameters() {
		return new external_function_parameters(
			array(
				'question_id' => new external_value(PARAM_INT, 'ID of question')
			)
		);
	}


	public static function get_answers_returns() {
		return new external_multiple_structure(
			new external_single_structure(
				array(
					'answer_text' => new external_value(PARAM_TEXT, 'Answer text'),
					'user_full_name' => new external_value(PARAM_TEXT, 'User full name')
				)
			)
		);
	}


	public static function get_answers($question_id) {
		global $DB;

		// Parameter validation
		$params = self::validate_parameters(
				self::get_answers_parameters(), array(
					'question_id' => $question_id
				)
		);

		if ($params['question_id'] < 1) {
			throw new invalid_parameter_exception('question_id must be a positive integer');
		}

		$discussion = $DB->get_record('forum_discussions', array('id' => $params['question_id']), 'forum', MUST_EXIST);
		$forum      = $DB->get_record('forum', array('id' => $discussion->forum), 'id', MUST_EXIST);
		$cm         = get_coursemodule_from_instance('forum', $forum->id);

        	// Context validation
		$context    = context_module::instance($cm->id);
		self::validate_context($context);

	        // Capability checking
		require_capability('mod/forum:viewdiscussion', $context);

		$sql 	= 'SELECT p.message, u.firstname, u.lastname '
			. 'FROM {forum_posts} p '
			. 'JOIN {user} u ON u.id = p.userid '
			. 'WHERE p.discussion = ? AND p.parent <> 0 '
			. 'ORDER BY p.created';
		$posts = $DB->get_records_sql($sql, array($params['question_id']));

		if (!$posts) {
			$posts = array();
		}

		$answers = array();
		foreach ($posts as $post) {
			$answers[] = array(
				'answer_text' => strip_tags($post->message),
				'user_full_name' => $post->firstname . ' ' . $post->lastname
			);
		}

		return $answers;
	}
}
