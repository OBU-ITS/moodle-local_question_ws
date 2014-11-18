moodle-question-ws
==================

A Moodle plugin that provides a web service to create questions forums in courses and to create posts within these forums.

The web service provides functions to:
  - Get a user's list of courses for the current semester
  - Create a new question forum on a course if the user has editing permissions on that course
  - Get a list of question forums on a course
  - Add a new question (post) to a forum, optionally anonymously.
  - Delete a question if the user has editing permissions on that course
  - Answer a question
  - Get a list of answers to a question

Users must authenticate by sending a GET or POST request to moodle_base_url/login/token.php, passing the parameters username, password and service which should be set to 'question_service'. A token will be returned if successfully authenticated. This token must be passed with each request to the web service.

Web service function calls should be POSTed to moodle_base_url/rest/server.php, passing the parameters moodlewsrestformat set to 'json', wstoken set to the value of the previously obtained token, wsfunction set to the name of the function to call, and any other parameters required by the function.

<h2>INSTALLATION</h2>
This plugin should be installed in the local directory of the Moodle instance.
