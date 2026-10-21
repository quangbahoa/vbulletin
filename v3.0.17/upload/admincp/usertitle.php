<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 13174 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('user', 'cpuser');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['usertitleid'] != 0, 'usertitle id = ' . $_REQUEST['usertitleid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_title_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add #######################
if ($_REQUEST['do'] == 'add')
{

	print_form_header('usertitle', 'insert');

	print_table_header($vbphrase['add_new_user_title']);
	print_input_row($vbphrase['title'], 'title');
	print_input_row($vbphrase['minimum_posts'], 'minposts');

	print_submit_row($vbphrase['save']);
}

// ###################### Start insert #######################
if ($_POST['do'] == 'insert')
{

	globalize($_POST, array('title' => STR, 'minposts' => INT));

	if (empty($title))
	{
		print_stop_message('invalid_user_title_specified');
	}

	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "usertitle
			(title, minposts)
		VALUES
			('" . addslashes($title) . "', $minposts)
	");

	define('CP_REDIRECT', 'usertitle.php?do=modify');
	print_stop_message('saved_user_title_x_successfully', $title);
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{

	globalize($_REQUEST, array('usertitleid' => INT));

	$usertitle = $DB_site->query_first("SELECT title, minposts FROM " . TABLE_PREFIX . "usertitle WHERE usertitleid = $usertitleid");

	print_form_header('usertitle', 'doupdate');
	construct_hidden_code('usertitleid', $usertitleid);

	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user_title'], $usertitle['title'], $usertitleid), 2, 0);
	print_input_row($vbphrase['title'], 'title', $usertitle['title']);
	print_input_row($vbphrase['minimum_posts'], 'minposts', $usertitle['minposts']);

	print_submit_row($vbphrase['save']);

}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{

	globalize($_POST, array('title' => STR, 'usertitleid' => INT, 'minposts' => INT));

	if (empty($title))
	{
		print_stop_message('invalid_user_title_specified');
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "usertitle
		SET title = '" . addslashes($title) . "',
		minposts = $minposts
		WHERE usertitleid = $usertitleid
	");

	define('CP_REDIRECT', 'usertitle.php?do=modify');
	print_stop_message('saved_user_title_x_successfully', $title);

}
// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{

	print_form_header('usertitle', 'kill');
	construct_hidden_code('usertitleid', $_REQUEST['usertitleid']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_user_title']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	globalize($_POST, array('usertitleid' => INT));

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "usertitle WHERE usertitleid = $usertitleid");

	define('CP_REDIRECT', 'usertitle.php?do=modify');
	print_stop_message('deleted_user_title_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	$usertitles = $DB_site->query("
		SELECT usertitleid, title, minposts
		FROM " . TABLE_PREFIX . "usertitle
		ORDER BY minposts
	");

	?>
	<script type="text/javascript">
	function js_usergroup_jump(usertitleid, obj)
	{
		task = obj.options[obj.selectedIndex].value;
		switch (task)
		{
			case 'edit': window.location = "usertitle.php?s=<?php echo $session['sessionhash']; ?>&do=edit&usertitleid=" + usertitleid; break;
			case 'kill': window.location = "usertitle.php?s=<?php echo $session['sessionhash']; ?>&do=remove&usertitleid=" + usertitleid; break;
			default: return false; break;
		}
	}
	</script>
	<?php

	$options = array('edit' => $vbphrase['edit'], 'kill' => $vbphrase['delete'], 'no_value' => '_________________');

	print_form_header('usertitle', 'add');
	print_table_header($vbphrase['user_title_manager'], 3);

	print_description_row('<p>' . construct_phrase($vbphrase['it_is_recommended_that_you_update_user_titles'], $session['sessionurl']) . '</p>', 0, 3);
	print_cells_row(array($vbphrase['user_title'], $vbphrase['minimum_posts'], $vbphrase['controls']), 1);

	while ($usertitle = $DB_site->fetch_array($usertitles))
	{
		print_cells_row(array(
			'<b>' . $usertitle['title'] . '</b>',
			$usertitle['minposts'],
			"\n\t<select name=\"u$usertitle[usertitleid]\" onchange=\"js_usergroup_jump($usertitle[usertitleid], this);\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>\n\t<input type=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_usergroup_jump($usertitle[usertitleid], this.form.u$usertitle[usertitleid]);\" />\n\t"
		));
	}

	print_submit_row($vbphrase['add_new_user_title'], 0, 3);

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13174 $
|| ####################################################################
\*======================================================================*/
?>