<?php
$language_file='admin';

$cidReset=true;

include('../inc/global.inc.php');

api_protect_admin_script();

$tbl_user=Database::get_main_table(TABLE_MAIN_USER);
$tbl_course=Database::get_main_table(TABLE_MAIN_COURSE);
$tbl_session=Database::get_main_table(TABLE_MAIN_SESSION);
$tbl_session_rel_course=Database::get_main_table(TABLE_MAIN_SESSION_COURSE);
$tbl_session_rel_course_rel_user=Database::get_main_table(TABLE_MAIN_SESSION_COURSE_USER);

$id_session=intval($_GET['id_session']);
$course_code=trim(stripslashes($_GET['course_code']));
$page=intval($_GET['page']);
$action=$_REQUEST['action'];
$sort=in_array($_GET['sort'],array('lastname','firstname','username'))?$_GET['sort']:'lastname';
$idChecked = (is_array($_GET['idChecked']) ? $_GET['idChecked'] : (is_array($_POST['idChecked']) ? $_POST['idChecked'] : null));

$result=api_sql_query("SELECT name,title FROM $tbl_session,$tbl_course WHERE id='$id_session' AND code='".addslashes($course_code)."'",__FILE__,__LINE__);

if(!list($session_name,$course_title)=mysql_fetch_row($result))
{
	header('Location: session_course_list.php?id_session='.$id_session);
	exit();
}

if($action == 'delete')
{
	if(is_array($idChecked))
	{
		$idChecked=implode(',',$idChecked);

		api_sql_query("DELETE FROM $tbl_session_rel_course_rel_user WHERE id_session='$id_session' AND course_code='".addslashes($course_code)."' AND id_user IN($idChecked)",__FILE__,__LINE__);
		
		$nbr_affected_rows=mysql_affected_rows();

		api_sql_query("UPDATE $tbl_session_rel_course SET nbr_users=nbr_users-$nbr_affected_rows WHERE id_session='$id_session' AND course_code='".addslashes($course_code)."'",__FILE__,__LINE__);
	}

	header('Location: '.api_get_self().'?id_session='.$id_session.'&course_code='.urlencode($course_code).'&sort='.$sort);
	exit();
}

$limit=20;
$from=$page * $limit;

$result=api_sql_query("SELECT user_id,lastname,firstname,username FROM $tbl_session_rel_course_rel_user,$tbl_user WHERE user_id=id_user AND id_session='$id_session' AND course_code='".addslashes($course_code)."' ORDER BY $sort LIMIT $from,".($limit+1),__FILE__,__LINE__);

$Users=api_store_result($result);

$nbr_results=sizeof($Users);

$tool_name = get_lang('ListOfUsersSubscribedToCourse').' &quot;'.htmlspecialchars($course_title,ENT_QUOTES,$charset).'&quot; '.get_lang('ForTheSession').' &quot;'.htmlentities($session_name,ENT_QUOTES,$charset).'&quot;';

$interbredcrump[]=array("url" => "index.php","name" => get_lang('AdministrationTools'));
$interbredcrump[]=array("url" => "session_list.php","name" => get_lang('SessionList'));
$interbredcrump[]=array("url" => "session_course_list.php?id_session=$id_session","name" => get_lang('ListOfCoursesOfSession')." &quot;".htmlspecialchars($session_name,ENT_QUOTES,$charset)."&quot;");

Display::display_header($tool_name);

api_display_tool_title($tool_name);
?>

<div id="main">

<form method="post" action="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&sort=<?php echo $sort; ?>" onsubmit="javascript:if(!confirm('<?php echo get_lang('ConfirmYourChoice'); ?>')) return false;">

<div align="right">

<?php
if($page)
{
?>

<a href="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&page=<?php echo $page-1; ?>&sort=<?php echo $sort; ?>"><?php echo get_lang('Previous');?></a>

<?php
}
else
{
	echo get_lang('Previous');
}
?>

|

<?php
if($nbr_results > $limit)
{
?>

<a href="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&page=<?php echo $page+1; ?>&sort=<?php echo $sort; ?>"><?php echo get_lang('Next');?></a>

<?php
}
else
{
	echo get_lang('Next');
}
?>

</div>

<br>

<table class="data_table" width="100%">
<tr>
  <th>&nbsp;</th>
  <th><a href="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&sort=lastname"><?php echo get_lang('lastName'); ?></a></th>
  <th><a href="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&sort=firstname"><?php echo get_lang('firstName'); ?></a></th>
  <th><a href="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&sort=username"><?php echo get_lang('students'); ?></a></th>
  <th><?php echo get_lang('actions'); ?></th>
</tr>

<?php
$i=0;

foreach($Users as $key=>$enreg)
{
	if($key == $limit)
	{
		break;
	}
?>

<tr class="<?php echo $i?'row_odd':'row_even'; ?>">
  <td><input type="checkbox" name="idChecked[]" value="<?php echo $enreg['user_id']; ?>"></td>
  <td><?php echo htmlspecialchars($enreg['lastname'],ENT_QUOTES,$charset); ?></td>
  <td><?php echo htmlspecialchars($enreg['firstname'],ENT_QUOTES,$charset); ?></td>
  <td><?php echo htmlspecialchars($enreg['username'],ENT_QUOTES,$charset); ?></td>
  <td>
	<a href="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&sort=<?php echo $sort; ?>&action=delete&idChecked[]=<?php echo $enreg['user_id']; ?>" onclick="javascript:if(!confirm('<?php echo get_lang('ConfirmYourChoice'); ?>')) return false;"><img src="../img/delete.gif" border="0" align="absmiddle" title="Effacer"></a>
  </td>
</tr>

<?php
	$i=$i ? 0 : 1;
}

unset($Users);
?>

</table>

<br>

<div align="left">

<?php
if($page)
{
?>

<a href="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&page=<?php echo $page-1; ?>&sort=<?php echo $sort; ?>"><?php echo get_lang('Previous'); ?></a>

<?php
}
else
{
	echo get_lang('Previous');
}
?>

|

<?php
if($nbr_results > $limit)
{
?>

<a href="<?php echo api_get_self(); ?>?id_session=<?php echo $id_session; ?>&course_code=<?php echo urlencode($course_code); ?>&page=<?php echo $page+1; ?>&sort=<?php echo $sort; ?>"><?php echo get_lang('Next'); ?></a>

<?php
}
else
{
	echo get_lang('Next');
}
?>

</div>

<br>

<select name="action">
<option value="delete"><?php echo get_lang('UnsubscribeSelectedUsersFromSession');?></option>
</select>
<input type="submit" value="<?php echo get_lang('Ok'); ?>">

</table>

</div>

<?php
Display::display_footer();
?>