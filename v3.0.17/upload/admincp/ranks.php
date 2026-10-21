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
define('CVS_REVISION', '$RCSfile$ - $Revision: 8893 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('user', 'cpuser', 'cprank');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_ranks.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['rankid'] != 0, "rank id = $_REQUEST[rankid]"));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_rank_manager']);


if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start add #######################
if ($_REQUEST['do'] == 'add')
{
	// Add multiple
	print_form_header('ranks', 'insert');
	print_table_header($vbphrase['add_new_user_rank']);
	print_input_row($vbphrase['times_to_repeat_rank'], 'ranklevel', 1);
	print_input_row($vbphrase['minimum_posts'], 'minposts', 10);
	print_chooser_row($vbphrase['usergroup'], 'usergroupid', 'usergroup', -1, $vbphrase['all_usergroups']);
	print_table_header($vbphrase['rank_type']);
	print_input_row($vbphrase['user_rank_file_path'], 'rankpath', 'images/');
	print_input_row($vbphrase['or_you_may_enter_text'], 'rankhtml', '');
	print_submit_row($vbphrase['save']);
}

// ###################### Start insert #######################
if ($_POST['do'] == 'insert')
{
	globalize($_POST, array(
		'ranklevel' => INT,
		'minposts' => INT,
		'rankpath' => STR,
		'usergroupid' => INT,
		'doinsert' => STR,
		'rankhtml' => STR
	));

	if (!$ranklevel OR (!$rankpath AND !$rankhtml))
	{
		if ($doinsert)
		{
			echo '<p><b>' . $vbphrase['invalid_file_path_specified'] . '</b></p>';
			$rankpath = $doinsert;
		}
		else
		{
			print_stop_message('please_complete_required_fields');
		}

	}

	if ($usergroupid == -1)
	{
		$usergroupid = 0;
	}

	if (!$rankhtml)
	{
		$rankpath = preg_replace('/\/$/s', '', $rankpath);
		if($dirhandle = @opendir('./' . $rankpath))
		{ // Valid directory!
			chdir('./' . $rankpath);
			readdir($dirhandle);
			readdir($dirhandle);
			while ($filename = readdir($dirhandle))
			{
				$rankname = $rankpath . '/' . $filename;
				if (@is_file($filename) AND (($filelen = strlen($filename)) >= 5))
				{
					$fileext = strtolower(substr($filename, $filelen - 4, $filelen - 1));
					if ($fileext == '.gif' OR $fileext == '.bmp' OR $fileext == '.jpg' OR $fileext == 'jpeg' OR $fileext == 'png')
					{
						$FileArray[] = addslashes($filename);
					}
				}
			}
			if (!is_array($FileArray))
			{
			  	print_stop_message('no_matches_found');
			}

			print_form_header('ranks', 'insert', 0, 1, 'name', '');
			print_table_header($vbphrase['images']);
			construct_hidden_code('usergroupid', $usergroupid);
			construct_hidden_code('ranklevel', $ranklevel);
			construct_hidden_code('minposts', $minposts);
			construct_hidden_code('doinsert', $rankpath);
			foreach ($FileArray AS $key => $val)
			{
				print_yes_row("<img src='../$rankpath/$val' border='0' alt='' align='center' />", 'rankpath', '', '', "$rankpath/$val");
			}
			print_submit_row($vbphrase['save']);
			closedir($dirhandle);
			exit;
		}
		else
		{ // Not a valid dir so assume it is a filename
			if (!(@is_file('./' . $rankpath)))
			{
				print_stop_message('invalid_file_path_specified');
			}
		}
		$type = 0;
	}
	else
	{
		$rankpath = $rankhtml;
		$type = 1;
	}

	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "ranks
		(ranklevel, minposts, rankimg, usergroupid, type)
		VALUES
		($ranklevel, $minposts, '" . addslashes($rankpath) . "', $usergroupid, $type)
	");
	build_ranks();
	define('CP_REDIRECT', 'ranks.php?do=modify');
	print_stop_message('saved_user_rank_successfully');
}

// ###################### Start edit #######################
if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'rankid' => INT
	));

	$ranks = $DB_site->query_first("
		SELECT ranks.*
		FROM " . TABLE_PREFIX . "ranks AS ranks
		WHERE rankid = $rankid
	");

	if ($ranks['type'])
	{
		$ranktext = $ranks['rankimg'];
	}
	else
	{
		$rankimg = $ranks['rankimg'];
	}

	print_form_header('ranks', 'doupdate');
	construct_hidden_code('rankid', $rankid);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user_rank'], '', $rankid));
	print_input_row($vbphrase['times_to_repeat_rank'], 'ranklevel', $ranks['ranklevel']);
	print_chooser_row($vbphrase['usergroup'], 'usergroupid', 'usergroup', $ranks['usergroupid'], $vbphrase['all_usergroups']);
	print_input_row($vbphrase['minimum_posts'], 'minposts', $ranks['minposts']);
	print_table_header($vbphrase['rank_type']);
	print_input_row($vbphrase['user_rank_file_path'], 'rankimg', $rankimg);
	print_input_row($vbphrase['or_you_may_enter_text'], 'rankhtml', $ranktext);

 	print_submit_row();
}

// ###################### Start do update #######################
if ($_POST['do'] == 'doupdate')
{
	globalize($_POST, array(
		'ranklevel' => INT,
		'minposts' => INT,
		'rankimg' => STR,
		'rankid' => INT,
		'usergroupid' => INT,
		'rankhtml' => STR
	));

	if (!$ranklevel OR (!$rankimg AND !$rankhtml))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($rankhtml)
	{
		$type = 1;
		$rankimg = $rankhtml;
	}
	else
	{
		$type = 0;
		if (!(@is_file('./' . $rankimg)))
		{
			print_stop_message('invalid_file_path_specified');
		}
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "ranks
  		SET ranklevel = $ranklevel,
			minposts = $minposts,
			rankimg = '" . addslashes($rankimg) . "',
			usergroupid = $usergroupid,
			type = $type
  		WHERE rankid = $rankid
	");
  	build_ranks();

	define('CP_REDIRECT', 'ranks.php?do=modify');
	print_stop_message('saved_user_rank_successfully');
}
// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	globalize($_REQUEST, array('rankid' => INT));

	print_form_header('ranks', 'kill');
	construct_hidden_code('rankid', $rankid);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_user_rank']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	globalize($_POST, array('rankid' => INT));

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "ranks WHERE rankid = $rankid");
	build_ranks();

	define('CP_REDIRECT', 'ranks.php?do=modify');
	print_stop_message('deleted_user_rank_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{
	$ranks = $DB_site->query("
		SELECT rankid, ranklevel, minposts, rankimg, ranks. usergroupid,title, type
		FROM " . TABLE_PREFIX . "ranks AS ranks
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup USING(usergroupid)
		ORDER BY ranks.usergroupid, minposts
	");

	print_form_header('', '');
	print_table_header($vbphrase['user_rank_manager']);
	print_description_row($vbphrase['user_ranks_desc'],'',0);
	print_table_footer();

	if ($DB_site->num_rows($ranks) == 0)
	{
		print_stop_message('no_user_ranks_defined');
	}

	print_form_header('', '');
	while ($rank = $DB_site->fetch_array($ranks))
	{
		if ($tempgroup != $rank['usergroupid'])
		{
			$tempgroup = $rank['usergroupid'];
			if (!empty($tempgroup))
			{
				print_table_break();
			}
			print_table_header(iif($rank['usergroupid'] == 0, $vbphrase['all_usergroups'], $rank['title']), 3, 1);
			print_cells_row(array($vbphrase['user_rank'], $vbphrase['minimum_posts'], $vbphrase['controls']), 1, '', -1);
		}

		$count = 0;
		$rankhtml = '';
		while ($count++ < $rank['ranklevel'])
		{
			if (!$rank['type'])
			{
				$rankhtml .= "<img src=\"../$rank[rankimg]\" border=\"0\" alt=\"\" />";
			}
			else
			{
				$rankhtml .= $rank['rankimg'];
			}
		}

		$cell = array(
			$rankhtml,
			vb_number_format($rank['minposts']),
			construct_link_code($vbphrase['edit'], "ranks.php?$session[sessionurl]do=edit&rankid=$rank[rankid]") . construct_link_code($vbphrase['delete'], "ranks.php?$session[sessionurl]do=remove&rankid=$rank[rankid]")
		);
		print_cells_row($cell, 0, '', -1);

	}
	print_table_footer();

}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 8893 $
|| ####################################################################
\*======================================================================*/
?>