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
define('CVS_REVISION', '$RCSfile$ - $Revision: 9169 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('attachment_image');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminimages'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['avatars_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'storage';
}

// ###################### Start checkpath #######################
function verify_upload_folder($avatarpath)
{
	global $vbphrase;
	if ($avatarpath == '')
	{
		print_stop_message('please_complete_required_fields');
	}
	if ($fp = @fopen($avatarpath . '/test.avatar', 'wb'))
	{
		fclose($fp);
		if (!@unlink($avatarpath . '/test.avatar'))
		{
			print_stop_message('test_file_write_failed', $avatarpath);
		}
		return true;
	}
	else
	{
		print_stop_message('test_file_write_failed', $avatarpath);
	}
}

// ###################### Swap from database to file system and vice versa ##########
if ($_REQUEST['do'] == 'storage')
{
	if ($vboptions['usefileavatar'])
	{
		$options = array(
			'FS_to_DB' => $vbphrase['move_items_from_filesystem_into_database'],
			'FS_to_FS' => $vbphrase['move_items_to_a_different_directory']
		);
	}
	else
	{
		$options = array(
			'DB_to_FS' => $vbphrase['move_items_from_database_into_filesystem']
		);
	}

	$i = 0;
	$dowhat = '';
	foreach($options AS $value => $text)
	{
		$dowhat .= "<label for=\"dw$value\"><input type=\"radio\" name=\"dowhat\" id=\"dw$value\" value=\"$value\"" . iif($i++ == 0, ' checked="checked"') . " />$text</label><br />";
	}

	print_form_header('avatar', 'switchtype');
	print_table_header("$vbphrase[storage_type]: <span class=\"normal\">$vbphrase[avatars]</span>");
	if ($vboptions['usefileavatar'])
	{
		print_description_row(construct_phrase($vbphrase['avatars_are_currently_being_served_from_the_filesystem_at_x'], '<b>' . $vboptions['avatarpath'] . '</b>'));
	}
	else
	{
		print_description_row($vbphrase['avatars_are_currently_being_served_from_the_database']);
	}
	print_label_row($vbphrase['action'], $dowhat);
	print_submit_row($vbphrase['go'], 0);

}

// ###################### Swap from database to file system and vice versa ##########
if ($_REQUEST['do'] == 'switchtype')
{
	globalize($_POST, array('dowhat' => STR));

	if ($dowhat == 'FS_to_DB')
	{
		// redirect straight through to avatar mover
		$_POST['avatarpath'] = $vboptions['avatarpath'];
		$_POST['avatarurl'] = $vbpoptions['avatarurl'];
		$_POST['do'] = 'doswitchtype';
		$_POST['dowhat'] = 'FS_to_DB';
	}
	else
	{
		// show a form to allow user to specify file path
		print_form_header('avatar', 'doswitchtype');
		construct_hidden_code('dowhat', $dowhat);

		if ($dowhat == 'FS_to_FS')
		{
			print_table_header($vbphrase['move_items_to_a_different_directory']);
			print_description_row(construct_phrase($vbphrase['avatars_are_currently_being_served_from_the_filesystem_at_x'], '<b>' . $vboptions['avatarpath'] . '</b>'));
		}
		else
		{
			print_table_header($vbphrase['move_items_from_database_into_filesystem']);
			print_description_row($vbphrase['avatars_are_currently_being_served_from_the_database']);
		}

		print_input_row($vbphrase['avatar_file_path_dfn'], 'avatarpath', $vboptions['avatarpath']);
		print_input_row($vbphrase['url_to_avatars_relative_to_your_forums_home_page'], 'avatarurl', $vboptions['avatarurl']);
		print_submit_row($vbphrase['go']);
	}
}

// ############### Move files from database to file system and vice versa ###########
if ($_POST['do'] == 'doswitchtype')
{
	globalize($_POST, array('avatarpath' => STR, 'avatarurl' => STR, 'dowhat' => STR));

	$avatarpath = preg_replace('/(\/|\\\)$/s', '', $avatarpath);
	$avatarurl = preg_replace('/(\/|\\\)$/s', '', $avatarurl);

	switch($dowhat)
	{
		// #############################################################################
		// update attachment file path
		case 'FS_to_FS':

			if ($avatarpath === $vboptions['avatarpath'] AND $avatarurl === $vboptions['avatarurl'])
			{
				// new and old path are the same - show error
				print_stop_message('invalid_file_path_specified');
			}
			else
			{
				// new and old paths are different - check the directory is valid
				verify_upload_folder($avatarpath);
				$oldpath = $vboptions['avatarpath'];

				// update $vboptions
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "setting SET value =
					CASE varname
						WHEN 'avatarpath' THEN '" . addslashes($avatarpath) . "'
						WHEN 'avatarurl' THEN '" . addslashes($avatarurl) . "'
					ELSE value END
					WHERE varname IN('avatarpath', 'avatarurl')
				");
				build_options();

				// show message
				print_stop_message('your_vb_settings_have_been_updated_to_store_avatars_in_x', $avatarpath, $oldpath);

			}

			break;

		// #############################################################################
		// move attachments from database to filesystem
		case 'DB_to_FS':

			// check path is valid
			verify_upload_folder($avatarpath);

			// update $vboptions
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "setting SET value =
				CASE varname
					WHEN 'avatarpath' THEN '" . addslashes($avatarpath) . "'
					WHEN 'avatarurl' THEN '" . addslashes($avatarurl) . "'
				ELSE value END
				WHERE varname IN('avatarpath', 'avatarurl')
			");
			build_options();

			break;
	}

	// #############################################################################

	print_form_header('avatar', 'domoveavatar');
	print_table_header($vbphrase['edit_storage_type']);
	construct_hidden_code('dowhat', $dowhat);

	if ($dowhat == 'DB_to_FS')
	{
		print_description_row($vbphrase['we_are_ready_to_attempt_to_move_your_avatars_from_database_to_filesystem']);
	}
	else
	{
		print_description_row($vbphrase['we_are_ready_to_attempt_to_move_your_avatars_from_filesystem_to_database']);
	}

	print_input_row($vbphrase['number_of_avatars_to_process_per_cycle'], 'perpage', 300, 1, 5);
	print_submit_row($vbphrase['go']);

}

// ################### Move avatars ######################################
if ($_REQUEST['do'] == 'domoveavatar')
{
	globalize($_REQUEST, array(
		'perpage' => INT,
		'startat' => INT,
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if ($perpage < 1)
	{
		$perpage = 10;
	}

	if ($startat < 0)
	{
		$startat = 0;
	}

	$finishat = $startat + $perpage;

	if ($debug)
	{
		echo '<p>' . $vbphrase['avatars'] . '</p>';
	}

	$avatars = $DB_site->query("
		SELECT user.userid, filename, avatardata AS filedata, avatarrevision
		FROM " . TABLE_PREFIX . "customavatar AS customavatar
		INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE user.userid >= $startat AND
			user.userid < $finishat
		ORDER BY user.userid ASC
	");

	while ($avatar = $DB_site->fetch_array($avatars))
	{
		if ($debug)
		{
			echo "$avatar[postid] $avatar[filename]<br />";
		}
		if ($vboptions['usefileavatar'] == 0)
		{
			// Converting FROM mysql TO fs
			if ($fp = fopen("$vboptions[avatarpath]/avatar$avatar[userid]_$avatar[avatarrevision].gif", 'wb'))
			{
				fwrite($fp, $avatar['filedata']);
				fclose($fp);
			}
			else
			{
				print_stop_message('error_writing_x', $avatar['filename']);
			}
		}
		else
		{
			// Converting FROM fs TO mysql
			$path = "$vboptions[avatarpath]/avatar$avatar[userid]_$avatar[avatarrevision].gif";
			if ($filedata = @file_get_contents($path))
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "customavatar
					SET avatardata = '" . $DB_site->escape_string($filedata) . "',
						filesize = " . strlen($filedata) . "
					WHERE userid = $avatar[userid]
				");
			}
			@unlink("$vboptions[avatarpath]/avatar$avatar[userid]_$avatar[avatarrevision].gif");
		}
	}
	if ($checkmore = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "customavatar WHERE userid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("avatar.php?$session[sessionurl]do=domoveavatar&startat=$finishat&perpage=$perpage");
		echo "<p><a href=\"avatar.php?$session[sessionurl]do=domoveavatar&amp;startat=$finishat&amp;perpage=$perpage\">" . $vbphrase['click_here_to_continue_processing_avatars'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'avatar.php?do=storage');
		if ($vboptions['usefileavatar'] == 0)
		{

			$DB_site->query("UPDATE " . TABLE_PREFIX . "customavatar SET avatardata = ''");
			$DB_site->reporterror = 0;
			$DB_site->query("OPTIMIZE TABLE " . TABLE_PREFIX . "customavatar");
			$DB_site->reporterror = 1;

			// Update $vboptions[]
			$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value = 1 WHERE varname = 'usefileavatar'");

			build_options();
			print_stop_message('avatars_moved_to_the_filesystem');
		}
		else
		{
			$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value = 0 WHERE varname = 'usefileavatar'");
			build_options();
			print_stop_message('avatars_moved_to_the_database');
		}
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9169 $
|| ####################################################################
\*======================================================================*/
?>