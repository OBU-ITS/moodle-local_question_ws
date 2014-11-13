<?php

function xmldb_local_question_ws_install() {
	global $DB;

	$user_rows = $DB->get_records('user', array('username'=>'anonquestion'));

	if (count($user_rows) < 1) {
		require_once('../user/lib.php');

		$user = new StdClass();
		$user->auth = 'manual';
		$user->confirmed = 1;
		$user->mnethostid = 1;
		$user->email = 'anonquestion@example.com';
		$user->username = 'anonquestion';
		$user->password = md5('De_T635smQpN');
		$user->lastname = 'user';
		$user->firstname = 'anonymous';

		user_create_user($user);
	}

	return true;
}

function xmldb_local_question_ws_uninstall() {
	return true;
}
