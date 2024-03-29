<?php
// $Id: course_user_import.php 8216 2006-03-15 16:33:13Z turboke $
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2005 Bart Mollet <bart.mollet@hogent.be>

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact: Dokeos, 181 rue Royale, B-1000 Brussels, Belgium, info@dokeos.com
==============================================================================
*/
/**
==============================================================================
* This tool allows platform admins to update course-user relations by uploading
* a CSVfile
* @package dokeos.admin
==============================================================================
*/
/**
 * validate the imported data
 */
function validate_data($users_courses)
{
	$errors = array ();
	$coursecodes = array ();
	//print_r($users_courses);
	foreach ($users_courses as $index => $user_course)
	{
		$user_course['line'] = $index +1;
		//1. check if mandatory fields are set
		$mandatory_fields = array ('UserName', 'CourseCode', 'Status');
		foreach ($mandatory_fields as $key => $field)
		{
			if (!isset ($user_course[$field]) || strlen($user_course[$field]) == 0)
			{
				$user_course['error'] = get_lang($field.'Mandatory');
				$errors[] = $user_course;
			}
		}
		//2. check if coursecode exists
		if (isset ($user_course['CourseCode']) && strlen($user_course['CourseCode']) != 0)
		{
			//2.1 check if code allready used in this CVS-file
			if (!isset ($coursecodes[$user_course['CourseCode']]))
			{
				//2.1.1 check if code exists in DB
				$course_table = Database :: get_main_table(TABLE_MAIN_COURSE);
				$sql = "SELECT * FROM $course_table WHERE code = '".mysql_real_escape_string($user_course['CourseCode'])."'";
				$res = api_sql_query($sql, __FILE__, __LINE__);
				if (mysql_num_rows($res) == 0)
				{
					$user_course['error'] = get_lang('CodeDoesNotExists');
					$errors[] = $user_course;
				}
				else
				{
					$coursecodes[$user_course['CourseCode']] = 1;
				}
			}
		}
		//3. check if username exists
		if (isset ($user_course['UserName']) && strlen($user_course['UserName']) != 0)
		{
			if (UserManager :: is_username_available($user_course['UserName']))
			{
				$user_course['error'] = get_lang('UnknownUser');
				$errors[] = $user_course;
			}
		}
		//4. check if status is valid
		if (isset ($user_course['Status']) && strlen($user_course['Status']) != 0)
		{
			if ($user_course['Status'] != COURSEMANAGER && $user_course['Status'] != STUDENT)
			{
				$user_course['error'] = get_lang('UnknownStatus');
				$errors[] = $user_course;
			}
		}
	}
	return $errors;
}

/**
 * Save the imported data
 */
function save_data($users_courses)
{
	$user_table= Database::get_main_table(TABLE_MAIN_USER);
	$course_user_table= Database::get_main_table(TABLE_MAIN_COURSE_USER);
	$csv_data = array();
	foreach ($users_courses as $index => $user_course)
	{
		$csv_data[$user_course['UserName']][$user_course['CourseCode']] = $user_course['Status'];
	}
	foreach($csv_data as $username => $csv_subscriptions)
	{
		$user_id = 0;
		$sql = "SELECT * FROM $user_table u WHERE u.username = '".mysql_real_escape_string($username)."'";
		$res = api_sql_query($sql,__FILE__,__LINE__);
		$obj = mysql_fetch_object($res);
		$user_id = $obj->user_id;
		$sql = "SELECT * FROM $course_user_table cu WHERE cu.user_id = $user_id";
		$res = api_sql_query($sql,__FILE__,__LINE__);
		$db_subscriptions = array();
		while($obj = mysql_fetch_object($res))
		{
			$db_subscriptions[$obj->course_code] = $obj->status;
		}
		//echo '<b>User '.$username.'</b><br />';
		$to_subscribe = array_diff(array_keys($csv_subscriptions),array_keys($db_subscriptions));
		$to_unsubscribe = array_diff(array_keys($db_subscriptions),array_keys($csv_subscriptions));
//		echo '<pre>';
//		echo "CSV SUBSCRIPTION\n";
//		print_r($csv_subscriptions);
//		echo "DB SUBSCRIPTION\n";
//		print_r($db_subscriptions);
//		echo "TO SUBSCRIBE\n";
//	    print_r($to_subscribe);
//		echo "TO UNSUBSCRIBE\n";
//		print_r($to_unsubscribe);
//		echo '$_POST[]<br/>';
//		print_r($_POST);
//		echo '</pre>';
		
		if($_POST['subscribe'])
		{
			foreach($to_subscribe as $index => $course_code)
			{
				CourseManager::add_user_to_course($user_id,$course_code,$csv_subscriptions[$course_code]);
				//echo get_lang('Subscription').' : '.$course_code.'<br />';
			}
		}
		if($_POST['unsubscribe'])
		{
			foreach($to_unsubscribe as $index => $course_code)
			{
				CourseManager::unsubscribe_user($user_id,$course_code);
				//echo get_lang('Unsubscription').' : '.$course_code.'<br />';
			}
		}
	}
}
/**
 * Read the CSV-file
 * @param string $file Path to the CSV-file
 * @return array All course-information read from the file
 */
function parse_csv_data($file)
{
	$courses = Import :: csv_to_array($file);
	return $courses;
}
// name of the language file that needs to be included 
$language_file = array ('admin', 'registration');

$cidReset = true;

include ('../inc/global.inc.php');
api_protect_admin_script();
require_once (api_get_path(LIBRARY_PATH).'fileManage.lib.php');
require_once (api_get_path(LIBRARY_PATH).'import.lib.php');
require_once (api_get_path(LIBRARY_PATH).'usermanager.lib.php');
require_once (api_get_path(LIBRARY_PATH).'course.lib.php');
require_once (api_get_path(LIBRARY_PATH).'formvalidator/FormValidator.class.php');
$formSent = 0;
$errorMsg = '';

$tool_name = get_lang('AddUsersToACourse').' CSV';

$interbreadcrumb[] = array ("url" => 'index.php', "name" => get_lang('PlatformAdmin'));

set_time_limit(0);
$form = new FormValidator('course_user_import');
$form->addElement('file','import_file', get_lang('ImportFileLocation'));
$form->addElement('checkbox','subscribe',get_lang('Action'),get_lang('SubscribeUserIfNotAllreadySubscribed'));
$form->addElement('checkbox','unsubscribe','',get_lang('UnsubscribeUserIfSubscriptionIsNotInFile'));
$form->addElement('submit','submit',get_lang('Ok'));
if ($form->validate())
{
	$users_courses = parse_csv_data($_FILES['import_file']['tmp_name']);
	$errors = validate_data($users_courses);
	if (count($errors) == 0)
	{
		save_data($users_courses);
		header('Location: user_list.php?action=show_message&message='.urlencode(get_lang('FileImported')));
		exit ();
	}
}
Display :: display_header($tool_name);
api_display_tool_title($tool_name);
if (count($errors) != 0)
{
	$error_message = '<ul>';
	foreach ($errors as $index => $error_course)
	{
		$error_message .= '<li>'.get_lang('Line').' '.$error_course['line'].': <b>'.$error_course['error'].'</b>: ';
		$error_message .= $error_course['Code'].' '.$error_course['Title'];
		$error_message .= '</li>';
	}
	$error_message .= '</ul>';
	Display :: display_error_message($error_message,false);
}

$form->display();
?>
<p><?php echo get_lang('CSVMustLookLike').' ('.get_lang('MandatoryFields').')'; ?> :</p>
<blockquote>
<pre>
<b>UserName</b>;<b>CourseCode</b>;<b>Status</b>
jdoe;course01;<?php echo COURSEMANAGER; ?>

a.dam;course01;<?php echo STUDENT; ?>
</pre>
<?php
echo COURSEMANAGER.': '.get_lang('Teacher').'<br />';
echo STUDENT.': '.get_lang('Student').'<br />';
?>
</blockquote>
<?php
/*
==============================================================================
		FOOTER
==============================================================================
*/
Display :: display_footer();
?>