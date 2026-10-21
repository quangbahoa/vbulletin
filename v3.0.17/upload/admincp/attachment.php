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
define('CVS_REVISION', '$RCSfile$ - $Revision: 11026 $');
define('NO_REGISTER_GLOBALS', 1);
ini_set('display_errors', 'On');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('attachment_image');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_file.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminthreads'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['attachmentid'] != 0, 'attachment id = ' . $_REQUEST['attachmentid'], iif(!empty($_REQUEST['extension']), "extension = $_REQUEST[extension]", '')));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['attachment_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'intro';
}

// ###################### Start checkpath #######################
function verify_upload_folder($attachpath)
{
	global $vbphrase;
	if ($attachpath == '')
	{
		print_stop_message('please_complete_required_fields');
	}

	if (!is_dir($attachpath . '/test'))
	{
		@umask(0);
		if (!@mkdir($attachpath . '/test', 0777))
		{
			print_stop_message('test_file_write_failed', $attachpath);
		}
	}
	@chmod($vboptions['attachpath'] . '/test', 0777);
	if ($fp = @fopen($attachpath . '/test/test.attach', 'wb'))
	{
		fclose($fp);
		if (!@unlink($attachpath . '/test/test.attach'))
		{
			print_stop_message('test_file_write_failed', $attachpath);
		}
		@rmdir($attachpath . '/test');
	}
	else
	{
		print_stop_message('test_file_write_failed', $attachpath);
	}
}

// ###################### Start updateattachmenttypes #######################
function build_attachment_types()
{
	global $DB_site;

	$data = array();

	$types = $DB_site->query("
		SELECT extension, size, height, width, enabled, display
		FROM " . TABLE_PREFIX . "attachmenttype
		ORDER BY extension
	");
	while ($type = $DB_site->fetch_array($types))
	{
		if (!empty($type['enabled']))
		{
			$data['extensions'] .= iif($data['extensions'], " $type[extension]", $type['extension']);
			$data["$type[extension]"] = $type;
			unset($type['extension']); // save some space and don't store the extension as both a value and the index
		}
	}
	$DB_site->free_result($types);

	build_datastore('attachmentcache', serialize($data));
}

// ###################### Swap from database to file system and vice versa ##########
if ($_REQUEST['do'] == 'storage')
{
	if ($vboptions['attachfile'])
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

	print_form_header('attachment', 'switchtype');
	print_table_header("$vbphrase[storage_type]: <span class=\"normal\">$vbphrase[attachments]</span>");
	if ($vboptions['attachfile'])
	{
		print_description_row(construct_phrase($vbphrase['attachments_are_currently_being_stored_in_the_filesystem_at_x'], '<b>' . $vboptions['attachpath'] . '</b>'));
	}
	else
	{
		print_description_row($vbphrase['attachments_are_currently_being_stored_in_the_database']);
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
		// redirect straight through to attachment mover
		$_POST['attachpath'] = $vboptions['attachpath'];
		$_POST['do'] = 'doswitchtype';
		$_POST['dowhat'] = 'FS_to_DB';
	}
	else
	{
		if ($dowhat == 'FS_to_FS')
		{
			// show a form to allow user to specify file path
			print_form_header('attachment', 'doswitchtype');
			construct_hidden_code('dowhat', $dowhat);
			print_table_header($vbphrase['move_attachments_to_a_different_directory']);
			print_description_row(construct_phrase($vbphrase['attachments_are_currently_being_stored_in_the_filesystem_at_x'], '<b>' . $vboptions['attachpath'] . '</b>'));
		}
		else
		{
			if(ini_get('safe_mode') == 1 OR strtolower(ini_get('safe_mode')) == 'on')
			{
				// Attachments as files is not compatible with safe_mode since it creates directories
				// Safe_mode does not allow you to write to directories created by PHP
				print_stop_message('your_server_has_safe_mode_enabled');
			}
			// show a form to allow user to specify file path
			print_form_header('attachment', 'doswitchtype');
			construct_hidden_code('dowhat', $dowhat);
			print_table_header($vbphrase['move_items_from_database_into_filesystem']);
			print_description_row($vbphrase['attachments_are_currently_being_stored_in_the_database']);
		}

		print_input_row($vbphrase['attachment_file_path_dfn'], 'attachpath', $vboptions['attachpath']);
		print_submit_row($vbphrase['go']);
	}
}

// ############### Move files from database to file system and vice versa ###########
if ($_POST['do'] == 'doswitchtype')
{
	globalize($_POST, array('attachpath' => STR, 'dowhat' => STR));

	$attachpath = preg_replace('/(\/|\\\)$/s', '', $attachpath);

	switch($dowhat)
	{
		// #############################################################################
		// update attachment file path
		case 'FS_to_FS':

			if ($attachpath === $vboptions['attachpath'])
			{
				// new and old path are the same - show error
				print_stop_message('invalid_file_path_specified');
			}
			else
			{
				// new and old paths are different - check the directory is valid
				verify_upload_folder($attachpath);
				$oldpath = $vboptions['attachpath'];

				// update $vboptions
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "setting
					SET value = '" . addslashes($attachpath) . "'
					WHERE varname = 'attachpath'
				");
				build_options();

				// show message
				print_stop_message('your_vb_settings_have_been_updated_to_store_attachments_in_x', $attachpath, $oldpath);

			}

			break;

		// #############################################################################
		// move attachments from database to filesystem
		case 'DB_to_FS':

			// check path is valid
			verify_upload_folder($attachpath);

			// update $vboptions
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "setting
				SET value = '" . addslashes($attachpath) . "'
				WHERE varname = 'attachpath'
			");
			build_options();

			break;
	}

	// #############################################################################

	print_form_header('attachment', 'domoveattachment');
	print_table_header($vbphrase['edit_storage_type']);
	construct_hidden_code('dowhat', $dowhat);

	if ($dowhat == 'DB_to_FS')
	{
		print_description_row($vbphrase['we_are_ready_to_attempt_to_move_your_attachments_from_database_to_filesystem']);
	}
	else
	{
		print_description_row($vbphrase['we_are_ready_to_attempt_to_move_your_attachments_from_filesystem_to_database']);
	}

	print_input_row($vbphrase['number_of_attachments_to_process_per_cycle'], 'perpage', 300, 1, 5);
	print_input_row($vbphrase['attachmentid_start_at'], 'startat', 0, 1, 5);

	print_submit_row($vbphrase['go']);

}

// ################### Move attachments ######################################
if ($_REQUEST['do'] == 'domoveattachment')
{
	globalize($_REQUEST, array(
		'perpage' => INT,
		'startat' => INT,
		'attacherrorcount1' => INT,
		'attacherrorcount2' => INT,
		'count' => INT
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if ($perpage < 1)
	{
		$perpage = 10;
	}
	if (!$startat) // Grab the first attachmentid so that we don't process a bunch of nonexistent ids to begin with.
	{
		$start = $DB_site->query_first("SELECT MIN(attachmentid) AS min FROM " . TABLE_PREFIX . "attachment");
		$startat = intval($start['min']);
	}
	$finishat = $startat + $perpage;

	// echo '<p>' . $vbphrase['attachments'] . '</p>';

	$attachments = $DB_site->query("
		SELECT attachmentid, filename, filedata, filesize, userid, thumbnail
		FROM " . TABLE_PREFIX . "attachment
		WHERE attachmentid >= $startat AND attachmentid < $finishat
		ORDER BY attachmentid ASC
	");

	if ($debug)
	{
		echo '<table width="100%" border="1" cellspacing="0" cellpadding="1">
				<tr>
				<td><b>Attachment ID</b></td><td><b>Filename</b></td><td><b>Size in Database</b></td><td><b>Size in Filesystem</b></td>
				</tr>
			';
	}
	while ($attachment = $DB_site->fetch_array($attachments))
	{
		$count++;
		$attacherror = false;
		if ($vboptions['attachfile'] == 0)
		{ // Converting FROM mysql TO fs
			$vboptions['attachfile'] = 2;
			if (!($path = verify_attachment_path($attachment['userid'], $attachment['attachmentid'])))
			{
				print_stop_message('error_writing_x', $path);
			}

			if ($fp = fopen($path, 'wb'))
			{
				fwrite($fp, $attachment['filedata']);
				fclose($fp);
				$filesize = filesize($path);
				if (!$filesize AND $attachment['filesize']) // if written size is Zero and Database filesize > 0
				{
					$attacherror = $vbphrase['written_filesize_is_zero'];
					$attacherrorcount1++;
				}
				// try thumbnail now
				if ($attachment['thumbnail'])
				{
					$filename = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], true);
					$fp = fopen($filename, 'wb');
					fwrite($fp, $attachment['thumbnail']);
					fclose($fp);
					$thumbnail_filesize = filesize($filename);
				}
				else
				{
					$thumbnail_filesize = 0;
				}
			}
			else
			{
				$attacherror = $vbphrase['unable_to_create_file'];
				$attacherrorcount2++;
			}
			$vboptions['attachfile'] = 0;
		}
		else
		{ // Converting FROM fs TO mysql
			$path = fetch_attachment_path($attachment['userid'], $attachment['attachmentid']);
			$thumbnail_path = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], true);
			if ($filedata = @file_get_contents($path))
			{
				$thumbnail_filedata = @file_get_contents($thumbnail_path);
				$filesize = @filesize($path);
				$thumbnail_filesize = @filesize($thumbnail_path);
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "attachment
					SET
						### Attachmentid = $attachment[attachmentid] ###
						### Attachment Size = " . vb_number_format($filesize, 1, true) . " ###
 						### Thumbnail Size = " . vb_number_format($thumbnail_filesize, 1, true) . " ###
						filedata = '" . $DB_site->escape_string($filedata) . "',
						thumbnail = '" . $DB_site->escape_string($thumbnail_filedata) . "'
					WHERE attachmentid = $attachment[attachmentid]
				");
			}
		}
		if ($debug)
		{
			echo "	<tr>
					<td>$attachment[attachmentid]</td>
					<td>" . htmlspecialchars_uni($attachment['filename']) . iif($attacherror, "<br />$attacherror") . "</td>
					<td>$attachment[filesize]</td>
					<td>$filesize / $thumbnail_filesize</td>
					</tr>
					";
		}
		else
		{
			echo "$vbphrase[attachment] : <b>$attachment[attachmentid]</b> $vbphrase[filename] : <b>$attachment[filename]</b><br />";
			if ($attacherror)
			{
				echo "$vbphrase[attachment] : <b>$attachment[attachmentid] $vbphrase[error]</b> $attacherror<br />";
			}
			flush();
		}
	}

	if ($debug)
	{
		echo '</table>';
	}
	if ($checkmore = $DB_site->query_first("SELECT attachmentid FROM " . TABLE_PREFIX . "attachment WHERE attachmentid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("attachment.php?$session[sessionurl]do=domoveattachment&startat=$finishat&perpage=$perpage&count=$count&attacherrorcount1=$attacherrorcount1&attacherrorcount2=$attacherrorcount2");
		echo "<p><a href=\"attachment.php?$session[sessionurl]do=domoveattachment&amp;startat=$finishat&amp;perpage=$perpage&amp;count=$count&amp;attacherrorcount1=$attacherrorcount1&amp;attacherrorcount2=$attacherrorcount2\">" . $vbphrase['click_here_to_continue_processing_attachments'] . "</a></p>";
	}
	else
	{
		if ($DB_site->num_rows($attachments) > 0)
		{
			// Bump this to a new page
			print_cp_redirect("attachment.php?$session[sessionurl]do=domoveattachment&startat=$finishat&perpage=$perpage&count=$count&attacherrorcount1=$attacherrorcount1&attacherrorcount2=$attacherrorcount2");
			echo "<p><a href=\"attachment.php?$session[sessionurl]do=domoveattachment&amp;startat=$finishat&amp;perpage=$perpage&amp;count=$count&amp;attacherrorcount1=$attacherrorcount1&amp;attacherrorcount2=$attacherrorcount2\">" . $vbphrase['click_here_to_continue_processing_attachments'] . "</a></p>";
		}

		if ($vboptions['attachfile'] == 0)
		{

			$totalattach = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "attachment");
			// Here we get a form that the user must continue on to delete the filedata column so that they are really sure to complete this step!
			print_form_header('attachment', 'confirmattachmentremove');
			print_table_header($vbphrase['confirm_attachment_removal']);

			print_description_row(construct_phrase($vbphrase['attachment_removal'], $totalattach['count'], $count, $attacherrorcount1, $attacherrorcount2));

			$totalerrors = $attacherrorcount1 + $attacherrorcount2;

			if ($totalattach['count'] != $count OR !$count OR ($totalerrors / $count) * 10 > 1)
			{
				$finalizeoption = false;
			}
			else
			{
				$finalizeoption = true;
			}

			print_yes_no_row($vbphrase['finalize'], 'removeattachments', $finalizeoption);
			print_submit_row($vbphrase['go']);

		}
		else
		{
			// update $vboptions // attachments are now being read from and saved to the database
			$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value = '0' WHERE varname = 'attachfile'");
			build_options();

			print_form_header('attachment', 'confirmfileremove');
			print_table_header($vbphrase['confirm_attachment_removal']);

			print_description_row(construct_phrase($vbphrase['file_removal']));
			print_submit_row($vbphrase['go']);

		}
	}
}

// ###################### Confirm emptying of filedata ##########
if ($_POST['do'] == 'confirmfileremove')
{
	function rmpath($dir)
	{
		global $vboptions, $vbphrase;

		if (!file_exists($dir))
		{
			return false;
		}

		if (is_file($dir))
		{
			if (@unlink($dir))
			{
				return true;
			}
			else
			{
				echo construct_phrase($vbphrase['removing_file_x_failed'], htmlspecialchars_uni($dir)) . '<br />';
				flush();
				return false;
			}
		}


		if ($handle = opendir($dir))
		{
			while (false !== ($file = readdir($handle)))
			{
				if ((is_file("$dir/$file") AND preg_match('#[0-9]+(\.attach|\.thumb)$#i', $file)) OR (is_dir("$dir/$file") AND preg_match('#^[0-9]+$#i', $file)))
				{
					rmpath("$dir/$file");
				}
			}

			if (preg_match('#^[0-9]+$#i', basename($dir)) AND $dir != $vboptions['attachpath'])
			{
				closedir($handle);
				if (@rmdir($dir))
				{
					flush();
					return true;
				}
				else
				{
					echo construct_phrase($vbphrase['removing_dir_x_failed'], htmlspecialchars_uni($dir)) . '<br />';
					flush();
					return false;
				}
			}
		}
		else
		{
			return false;
		}
	}

	rmpath($vboptions['attachpath']);

	// redirect
	//define('CP_REDIRECT', 'attachment.php?do=stats');
	print_stop_message('attachments_moved_to_the_database');
}

// ###################### Confirm emptying of filedata ##########
if ($_POST['do'] == 'confirmattachmentremove')
{
	globalize($_POST, array('removeattachments'));

	if ($removeattachments)
	{
		// update $vboptions
		// attachfile is only set to 1 to indicate the PRE RC1 attachment FS behaviour
		$DB_site->query("UPDATE " . TABLE_PREFIX . "setting SET value = '2' WHERE varname = 'attachfile'");
		build_options();

		$DB_site->query("UPDATE " . TABLE_PREFIX . "attachment SET filedata = '', thumbnail = ''");

		// redirect
		// define('CP_REDIRECT', 'attachment.php?do=stats');
		print_stop_message('attachments_moved_to_the_filesystem');
	}
	else
	{
		// redirect
		// define('CP_REDIRECT', 'attachment.php?do=stats');
		print_stop_message('attachments_not_moved_to_the_filesystem');
	}
}

// ###################### Search attachments ####################
if ($_REQUEST['do'] == 'search' AND $_REQUEST['massdelete'])
{
	globalize($_REQUEST, array('a_delete'));

	// they hit the mass delete submit button
	if (!is_array($a_delete))
	{
		// nothing in the array
		print_stop_message('invalid_attachments_specified');
	}
	else
	{
		$_REQUEST['do'] = 'massdelete';
	}
}

// ###################### Actually search attachments ####################
if ($_REQUEST['do'] == 'search')
{
	globalize($_REQUEST, array(
		'search',
		'prevsearch',
		'prunedate' => INT,
		'pagenum' => INT,
	));

	// for additional pages of results
	if ($prevsearch)
	{
		$search = unserialize($prevsearch);
	}
	else
	{
		$prevsearch = serialize($search);
	}

	// error prevention
	if (!isset($search['visible']) OR $search['visible'] < -1 OR $search['visible'] > 1)
	{
		$search['visible'] = -1;
	}

	if (!$search['orderby'])
	{
		$search['orderby'] = 'filename';
		$search['ordering'] = 'DESC';
	}
	if (!$search['results'])
	{
		$search['results'] = 10;
	}

	// special case
	if ($prunedate > 0)
	{
		$search['datelinebefore'] = date('Y-m-d', TIMENOW - 86400 * $prunedate);
	}

	if ($pagenum < 1)
	{
		$pagenum = 1;
	}

	if ($_POST['next_page'])
	{
		++$pagenum;
	}
	else if ($_POST['prev_page'])
	{
		--$pagenum;
	}

	$query = "
		SELECT attachment.attachmentid, attachment.postid, attachment.dateline, attachment.userid, attachment.visible, filename, counter,
		filesize, IF(user.userid<>0, user.username, post.username) AS username
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid=user.userid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid=post.postid)
		WHERE 1=1
	";

	if ($search['filename'])
	{
		$query .= "AND filename LIKE '%" . addslashes_like($search['filename']) . "%' ";
	}

	if ($search['attachedby'])
	{
		$user = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username='" . addslashes(htmlspecialchars_uni($search['attachedby'])) . "'");
		if (!$user)
		{
			$user = $DB_site->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username LIKE '%" . addslashes_like(htmlspecialchars_uni($search['attachedby'])) . "%'");
		}
		if (!$user)
		{
			print_stop_message('invalid_user_specified');
		}
		else
		{
			$query .= "AND attachment.userid=$user[userid] ";
		}
	}

	if ($search['datelinebefore'] AND $search['datelineafter'])
	{
		$query .= "AND (attachment.dateline BETWEEN UNIX_TIMESTAMP('$search[datelineafter]') AND UNIX_TIMESTAMP('$search[datelinebefore]')) ";
	}
	else if ($search['datelinebefore'])
	{
		$query .= "AND attachment.dateline < UNIX_TIMESTAMP('$search[datelinebefore]') ";
	}
	else if ($search['datelineafter'])
	{
		$query .= "AND attachment.dateline > UNIX_TIMESTAMP('$search[datelineafter]') ";
	}

	if ($search['downloadsmore'] AND $search['downloadsless'])
	{
		$query .= "AND (counter BETWEEN $search[downloadsmore] AND $search[downloadsless]) ";
	}
	else if ($search['downloadsless'])
	{
		$query .= "AND counter < $search[downloadsless] ";
	}
	else if ($search['downloadsmore'])
	{
		$query .= "AND counter > $search[downloadsmore] ";
	}

	if ($search['sizemore'] AND $search['sizeless'])
	{
		$query .= "AND (filesize BETWEEN $search[sizemore] AND $search[sizeless]) ";
	}
	else if ($search['sizeless'])
	{
		$query .= "AND filesize < $search[sizeless] ";
	}
	else if ($search['sizemore'])
	{
		$query .= "AND filesize > $search[sizemore] ";
	}

	if ($search['visible'] != -1)
	{
		$query .= "AND attachment.visible = $search[visible] ";
	}

	$query .= "\nORDER BY $search[orderby] $search[ordering]";

	$results = $DB_site->query($query);
	$count = $DB_site->num_rows($results);
	if (!$count)
	{
		print_stop_message('no_matches_found');
	}
	$pages = ceil($count / $search['results']);
	if (!$pages)
	{
		$pages = 1;
	}

	print_form_header('attachment', 'search', 0, 1);
	construct_hidden_code('prevsearch', $prevsearch);
	construct_hidden_code('prunedate', $prunedate);
	construct_hidden_code('pagenum', $pagenum);
	print_table_header(construct_phrase($vbphrase['showing_attachments_x_to_y_of_z'], ($pagenum - 1) * $search['results'] + 1,  iif($search['results'] * $pagenum > $count, $count, $search['results'] * $pagenum), $count), 7);

	print_cells_row(array(
		'<input type="checkbox" name="allbox" title="' . $vbphrase['check_all'] . '" onclick="js_check_all(this.form);" />',
		$vbphrase['filename'],
		$vbphrase['username'],
		$vbphrase['date'],
		$vbphrase['size'],
		$vbphrase['downloads'],
		$vbphrase['controls']
	), 1);

	$DB_site->data_seek(($pagenum - 1) * $search['results'], $results);
	$currentrow = 1;
	while ($row = $DB_site->fetch_array($results))
	{
		$cell = array();
		$cell[] = "<input type=\"checkbox\" name=\"a_delete[]\" value=\"$row[attachmentid]\" tabindex=\"1\" />";
		$cell[] = "<p align=\"$stylevar[left]\"><a href=\"../attachment.php?$session[sessionurl]attachmentid=$row[attachmentid]\">" . htmlspecialchars_uni($row['filename']) . '</a></p>';
		$cell[] = iif($row['userid'], "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$row[userid]\">$row[username]</a>", $row['username']);
		$cell[] = vbdate($vboptions['dateformat'], $row['dateline']) . construct_link_code($vbphrase['view_post'], "../showthread.php?$session[sessionurl]postid=$row[postid]#post$row[postid]", true);
		$cell[] = vb_number_format($row['filesize'], 1, true);
		$cell[] = $row['counter'];
		$cell[] = construct_link_code($vbphrase['delete'], "attachment.php?$session[sessionurl]do=delete&amp;attachmentid=$row[attachmentid]");
		print_cells_row($cell);
		$currentrow++;
		if ($currentrow > $search['results'])
		{
			break;
		}
	}
	print_description_row('<input type="submit" class="button" name="massdelete" value="' . $vbphrase['delete_selected_attachments'] . '" tabindex="1" />', 0, 7, '', 'center');


	$DB_site->free_result($results);

	if ($pages > 1 AND $pagenum < $pages)
	{
		print_table_footer(7, iif($pagenum > 1, "<input type=\"submit\" name=\"prev_page\" class=\"button\" tabindex=\"1\" value=\"$vbphrase[prev_page]\" accesskey=\"s\" />") . "\n<input type=\"submit\" name=\"next_page\" class=\"button\" tabindex=\"1\" value=\"$vbphrase[next_page]\" accesskey=\"s\" />");
	}
	else if ($pagenum == $pages AND $pages > 1)
	{
		print_table_footer(7, "<input type=\"submit\" name=\"prev_page\" class=\"button\" tabindex=\"1\" value=\"$vbphrase[prev_page]\" accesskey=\"s\" />");
	}
	else
	{
		print_table_footer(7);
	}
}

// ###################### Delete an attachment ####################
if ($_REQUEST['do'] == 'delete')
{
	globalize($_REQUEST, array('attachmentid' => INT));

	$attachment = $DB_site->query_first("SELECT filename FROM " . TABLE_PREFIX . "attachment WHERE attachmentid=$attachmentid");

	print_form_header('attachment', 'dodelete');
	construct_hidden_code('attachmentid', $attachmentid);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_delete_the_attachment_x'], $attachment['filename'], $attachmentid));
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Do delete the attachment ####################
if ($_POST['do'] == 'dodelete')
{
	globalize($_POST, array('attachmentid' => INT));

	// get attachment postid and threadid
	$post = $DB_site->query_first("
		SELECT attachment.postid, threadid, attachment.userid
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid = post.postid)
		WHERE attachmentid = $attachmentid
	");

	// update thread and post tables to have -1 attachment count
	if ($post['postid'])
	{
		$DB_site->query("UPDATE " . TABLE_PREFIX . "post SET attach = attach - 1 WHERE postid = $post[postid]");
	}
	if ($post['threadid'])
	{
		$DB_site->query("UPDATE " . TABLE_PREFIX . "thread SET attach = attach - 1 WHERE threadid = $post[threadid]");
	}

	// delete the attachment if it is stored as a file
	$deletearray = array($attachmentid => $post['userid']);
	delete_attachment_files($deletearray);

	// delete the attachment if it is stored in the database
	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "attachment WHERE attachmentid = $attachmentid");

	define('CP_REDIRECT', 'attachment.php?do=intro');
	print_stop_message('deleted_attachment_successfully');

}

// ###################### Mass Delete attachments ####################
if ($_REQUEST['do'] == 'massdelete')
{
	globalize($_POST, array('a_delete'));

	print_form_header('attachment','domassdelete');
	construct_hidden_code('a_delete', serialize($a_delete));
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_these_attachments']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
}

// ###################### Mass Delete attachments ####################
if ($_POST['do'] == 'domassdelete')
{
	globalize($_POST, array('a_delete'));

	$delete = unserialize($a_delete);
	if ($delete)
	{
		if (is_array($delete))
		{
			$ids = implode(',', $delete);
			$posts = $DB_site->query("
				SELECT attachment.attachmentid, attachment.postid, threadid, attachment.userid
				FROM " . TABLE_PREFIX . "attachment AS attachment
				LEFT JOIN " . TABLE_PREFIX . "post AS post ON (post.postid = attachment.postid)
				WHERE attachmentid IN (-1,$ids)
			");
			$postids = array();
			$threadids = array();
			$attachmentids = array();
			while ($post = $DB_site->fetch_array($posts))
			{
				$postids["$post[postid]"]++;
				$threadids["$post[threadid]"] = 1;
				$attachmentids["$post[attachmentid]"] = $post['userid'];
			}
			delete_attachment_files($attachmentids);
			if (is_array($postids))
			{
				foreach($postids AS $postid => $count)
				{
					$DB_site->query("
						UPDATE " . TABLE_PREFIX . "post
						SET attach = attach -  $count
						WHERE postid = $postid
					");
				}
			}
			if (is_array($threadids))
			{
				require_once('./includes/functions_databuild.php');
				foreach($threadids AS $threadid)
				{
					build_thread_counters($threadid);
				}
			}

			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "attachment WHERE attachmentid IN (-1,$ids)");
		}
	}

	define('CP_REDIRECT', 'attachment.php?do=intro');
	print_stop_message('deleted_attachments_successfully');
}

// ###################### Statistics ####################
if ($_REQUEST['do'] == 'stats')
{
	$stats = $DB_site->query_first("
		SELECT COUNT(*) AS count, SUM(filesize) AS totalsize, SUM(counter) AS downloads
		FROM " . TABLE_PREFIX . "attachment
	");

	if ($stats['count'])
	{
		$stats['average'] = vb_number_format(($stats['totalsize'] / $stats['count']), 1, true);
	}
	else
	{
		$stats['average'] = '0.00';
	}

	print_form_header('', '');
	print_table_header($vbphrase['statistics']);
	print_label_row($vbphrase['total_attachments'], vb_number_format($stats['count']));
	print_label_row($vbphrase['disk_space_used'], vb_number_format(iif(!$stats['totalsize'], 0, $stats['totalsize']), 1, true));

	if ($vboptions['attachfile'])
	{
		print_label_row($vbphrase['storage_type'], construct_phrase($vbphrase['attachments_are_currently_being_stored_in_the_filesystem_at_x'], '<b>' . $vboptions['attachpath'] . '</b>'));
	}
	else
	{
		print_label_row($vbphrase['storage_type'], $vbphrase['attachments_are_currently_being_stored_in_the_database']);
	}

	print_label_row($vbphrase['average_attachment_filesize'], $stats['average']);
	print_label_row($vbphrase['total_downloads'], vb_number_format($stats['downloads']));
	print_table_break();

	$popular = $DB_site->query("
		SELECT attachmentid,attachment.postid, filename, counter,
		user.userid, IF(user.userid<>0, user.username, post.username) AS username
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid=user.userid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid=post.postid)
		ORDER BY counter DESC
		LIMIT 5
	");
	$position = 0;

	print_table_header($vbphrase['five_most_popular_attachments'], 5);
	print_cells_row(array('', $vbphrase['filename'], $vbphrase['username'], $vbphrase['downloads'], '&nbsp;'), 1);
	while($thispop = $DB_site->fetch_array($popular))
	{
		$position++;
		$cell = array();
		$cell[] = $position . '.';
		$cell[] = "<a href=\"../attachment.php?$session[sessionurl]attachmentid=$thispop[attachmentid]\">$thispop[filename]</a>";
		$cell[] = iif($thispop['userid'], "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$thispop[userid]\">$thispop[username]</a>", $thispop['username']);
		$cell[] = vb_number_format($thispop['counter']);
		$cell[] = '<span class="smallfont">' . construct_link_code($vbphrase['view_post'], "../showthread.php?$session[sessionurl]postid=$thispop[postid]#post$thispop[postid]", true) .
			construct_link_code($vbphrase['delete'], "attachment.php?$session[sessionurl]do=delete&amp;attachmentid=$thispop[attachmentid]") .
			'</span>';
		print_cells_row($cell);
	}
	print_table_break();

	$largest=$DB_site->query("
		SELECT attachmentid,attachment.postid, filename, filesize,
		user.userid, IF(user.userid<>0, user.username, post.username) AS username
		FROM " . TABLE_PREFIX . "attachment AS attachment
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (attachment.userid=user.userid)
		LEFT JOIN " . TABLE_PREFIX . "post AS post ON (attachment.postid=post.postid)
		ORDER BY filesize DESC
		LIMIT 5
	");
	$position = 0;

	print_table_header($vbphrase['five_largest_attachments'], 5);
	print_cells_row(array($vbphrase['user_rank'], $vbphrase['filename'], $vbphrase['username'], $vbphrase['size'], '&nbsp;'), 1);
	while($thispop = $DB_site->fetch_array($largest))
	{
		$position++;
		$cell = array();
		$cell[] = $position . '.';
		$cell[] = "<a href=\"../attachment.php?$session[sessionurl]attachmentid=$thispop[attachmentid]\">$thispop[filename]</a>";
		$cell[] = iif($thispop['userid'], "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$thispop[userid]\">$thispop[username]</a>", $thispop['username']);
		$cell[] = vb_number_format($thispop['filesize'], 1, true);
		$cell[] = '<span class="smallfont">' . construct_link_code($vbphrase['view_post'], "../showthread.php?$session[sessionurl]postid=$thispop[postid]#post$thispop[postid]", true) .
			construct_link_code($vbphrase['delete'], "attachment.php?$session[sessionurl]do=delete&amp;attachmentid=$thispop[attachmentid]") .
			'</span>';
		print_cells_row($cell);
	}
	print_table_break();

	$largestuser=$DB_site->query("
		SELECT COUNT(postid) AS count, SUM(filesize) AS totalsize, user.userid, username
		FROM " . TABLE_PREFIX . "attachment AS attachment, " . TABLE_PREFIX . "user AS user
		WHERE user.userid= attachment.userid
		GROUP BY attachment.userid
		HAVING totalsize > 0
		ORDER BY totalsize DESC
		LIMIT 5
	");
	$position = 0;

	print_table_header($vbphrase['five_users_most_attachment_space'], 5);
	print_cells_row(array($vbphrase['user_rank'], $vbphrase['username'], $vbphrase['attachments'], $vbphrase['total_size'], '&nbsp;'), 1);
	while($thispop=$DB_site->fetch_array($largestuser))
	{
		$position++;
		$cell = array();
		$cell[] = $position . '.';
		$cell[] = "<a href=\"user.php?$session[sessionurl]do=edit&amp;userid=$thispop[userid]\">$thispop[username]</a>";
		$cell[] = vb_number_format($thispop['count']);
		$cell[] = vb_number_format($thispop['totalsize'], 1, true);
		$cell[] = '<span class="smallfont">' . construct_link_code($vbphrase['view_attachments'], "attachment.php?$session[sessionurl]do=search&amp;search[attachedby]=" . urlencode($thispop['username'])) . '</span>';
		print_cells_row($cell);
	}
	print_table_footer();
}

// ###################### Introduction ####################
if ($_REQUEST['do'] == 'intro')
{
	print_form_header('attachment', 'search');
	print_table_header($vbphrase['quick_search']);
	print_description_row("
	<br />
	<ul>
		<li><a href=\"attachment.php?$session[sessionurl]do=search&amp;search[orderby]=filesize&amp;search[ordering]=DESC\">" . $vbphrase['view_largest_attachments'] . "</a></li>
		<li><a href=\"attachment.php?$session[sessionurl]do=search&amp;search[orderby]=counter&amp;search[ordering]=DESC\">" . $vbphrase['view_most_popular_attachments'] . "</a></li>
		<li><a href=\"attachment.php?$session[sessionurl]do=search&amp;search[orderby]=post.dateline&amp;search[ordering]=DESC\">" . $vbphrase['view_newest_attachments'] . "</a></li>
		<li><a href=\"attachment.php?$session[sessionurl]do=search&amp;search[orderby]=post.dateline&amp;search[ordering]=ASC\">" . $vbphrase['view_oldest_attachments'] . "</a></li>
	</ul>
	");
	print_table_break();

	print_table_header($vbphrase['prune_attachments']);
	print_input_row($vbphrase['find_all_attachments_older_than_days'], 'prunedate', 30);
	print_submit_row($vbphrase['search'], 0);

	print_form_header('attachment', 'search');
	print_table_header($vbphrase['advanced_search']);
	print_input_row($vbphrase['filename'], 'search[filename]');
	print_input_row($vbphrase['attached_by'], 'search[attachedby]');
	print_input_row($vbphrase['attached_before'], 'search[datelinebefore]');
	print_input_row($vbphrase['attached_after'], 'search[datelineafter]');
	print_input_row($vbphrase['downloads_greater_than'], 'search[downloadsmore]');
	print_input_row($vbphrase['downloads_less_than'], 'search[downloadsless]');
	print_input_row($vbphrase['filesize_greater_than'], 'search[sizemore]');
	print_input_row($vbphrase['filesize_less_than'], 'search[sizeless]');
	print_yes_no_other_row($vbphrase['attachment_is_visible'], 'search[visible]', $vbphrase['either'], -1);

	print_label_row($vbphrase['order_by'],'
		<select name="search[orderby]" tabindex="1" class="bginput">
			<option value="user.username">' . $vbphrase['attached_by'] . '</option>
			<option value="counter">' . $vbphrase['downloads'] . '</option>
			<option value="filename" selected="selected">' . $vbphrase['filename'] . '</option>
			<option value="filesize">' . $vbphrase['filesize'] . '</option>
			<option value="post.dateline">' . $vbphrase['time'] . '</option>
			<option value="attachment.visible">' . $vbphrase['visible'] . '</option>
		</select>
		<select name="search[ordering]" tabindex="1" class="bginput">
			<option value="DESC">' . $vbphrase['descending'] . '</option>
			<option value="ASC">' . $vbphrase['ascending'] . '</option>
		</select>
	', '', 'top', 'orderby');
	print_input_row($vbphrase['attachments_to_show_per_page'], 'search[results]', 20);

	print_submit_row($vbphrase['search'], 0);
}

// ###################### File Types ####################
if ($_REQUEST['do'] == 'types')
{
	$types = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "attachmenttype ORDER BY extension");

	print_form_header('attachment', 'updatetype');
	print_table_header($vbphrase['edit_attachment_types'], 6);
	print_cells_row(array(
		$vbphrase['extension'],
		$vbphrase['maximum_filesize'],
		$vbphrase['maximum_width'],
		$vbphrase['maximum_height'],
		$vbphrase['enabled'],
		$vbphrase['controls']
	), 1, 'tcat');

	while ($type = $DB_site->fetch_array($types))
	{
		$type['size'] = iif($type['size'], $type['size'], $vbphrase['none']);
		switch($type['extension'])
		{
			case 'gif':
			case 'bmp':
			case 'jpg':
			case 'jpeg':
			case 'jpe':
			case 'png':
			case 'psd':
			case 'swf':
			case 'tiff':
			case 'tif':
				$type['width'] = iif($type['width'], $type['width'], $vbphrase['none']);
				$type['height'] = iif($type['height'], $type['height'], $vbphrase['none']);
				break;
			default:
				$type['width'] = '&nbsp;';
				$type['height'] = '&nbsp;';
		}
		$cell = array();
		$cell[] = "<b>$type[extension]</b>";
		$cell[] = $type['size'];
		$cell[] = $type['width'];
		$cell[] = $type['height'];
		$cell[] = iif($type['enabled'], $vbphrase['yes'], $vbphrase['no']);
		$cell[] = construct_link_code($vbphrase['edit'], "attachment.php?$session[sessionurl]do=updatetype&extension=$type[extension]") .
				  construct_link_code($vbphrase['delete'], "attachment.php?$session[sessionurl]do=removetype&extension=$type[extension]");
		print_cells_row($cell);
	}
	print_submit_row($vbphrase['add_new_attachment_type'], 0, 6);
}

// ###################### File Types ####################
if ($_REQUEST['do'] == 'updatetype')
{

	globalize($_REQUEST, array('extension'));

	print_form_header('attachment', 'doupdatetype');
	if ($extension)
	{ // This is an edit
		$type = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "attachmenttype
			WHERE extension = '$extension'
		");
		if ($type['mimetype'])
		{
			$type['mimetype'] = implode("\n", unserialize($type['mimetype']));
		}
		construct_hidden_code('extension', $extension);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['attachment_type'], $extension, $extension));
	}
	else
	{
		$type = array('enabled' => 1);
		print_table_header($vbphrase['add_new_extension']);
	}

	print_input_row($vbphrase['extension'], 'type[extension]', $type['extension']);
	print_input_row(construct_phrase($vbphrase['maximum_filesize_dfn']), 'type[size]', $type['size']);
	print_input_row($vbphrase['max_width_dfn'], 'type[width]', $type['width']);
	print_input_row($vbphrase['max_height_dfn'], 'type[height]', $type['height']);

	print_textarea_row($vbphrase['mime_type_dfn'], 'type[mimetype]', $type['mimetype']);
	print_yes_no_row($vbphrase['enabled'], 'type[enabled]', $type['enabled']);

	print_submit_row(iif($extension, $vbphrase['update'], $vbphrase['save']));

}

// ###################### Update File Type ####################
if ($_POST['do'] == 'doupdatetype')
{
	globalize($_POST, array('extension', 'type'));

	$type['extension'] = strtolower($type['extension']);

	if (empty($type['extension']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($extension != $type['extension'] AND $test = $DB_site->query_first("SELECT extension FROM " . TABLE_PREFIX . "attachmenttype WHERE extension = '" . addslashes($type['extension']) . "'"))
	{
		print_stop_message('name_exists', $vbphrase['filetype'], htmlspecialchars($type['extension']));
	}

	if ($type['mimetype'])
	{
		$mimetype = explode("\n", $type['mimetype']);
		foreach($mimetype AS $index => $value)
		{
			$mimetype["$index"] = trim($value);
		}
	}
	else

	{
		$mimetype = array('Content-type: unknown/unknown');
	}
	$type['mimetype'] = serialize($mimetype);

	define('CP_REDIRECT', 'attachment.php?do=types');
	if ($extension)
	{
		$DB_site->query(fetch_query_sql($type, 'attachmenttype', 'WHERE extension = \'' . addslashes($extension) . '\''));

		build_attachment_types();
	}
	else
	{
		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "attachmenttype
				(extension, size, height, width, mimetype, enabled)
			VALUES
				('" . addslashes($type['extension']) . "', " . intval($type['size']) . ", " . intval($type['height']) . ", " . intval($type['width']) . ", '" . addslashes($type['mimetype']) . "', " . intval($type['enabled']) . ")
		");

		build_attachment_types();
	}

	print_stop_message('saved_attachment_type_x_successfully', $type['extension']);

}

// ###################### Remove File Type ####################
if ($_REQUEST['do'] == 'removetype')
{
	globalize($_REQUEST, array('extension' => STR));

	print_form_header('attachment', 'killtype', 0, 1, '', '75%');
	construct_hidden_code('extension', $extension);
	print_table_header(construct_phrase($vbphrase['confirm_deletion_of_attachment_type_x'], $extension));
	print_description_row("
		<blockquote><br />".
		construct_phrase($vbphrase['are_you_sure_you_want_to_delete_the_attachment_type_x'], $extension)."
		<br /></blockquote>\n\t");
	print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
}

// ###################### Kill File Type ####################
if ($_POST['do'] == 'killtype')
{
	globalize($_REQUEST, array('extension' => STR));

	$DB_site->query("
		DELETE FROM " . TABLE_PREFIX . "attachmenttype
		WHERE extension = '" . addslashes($extension) . "'
	");

	build_attachment_types();

	define('CP_REDIRECT', 'attachment.php?do=types');
	print_stop_message('deleted_attachment_type_successfully');
}


print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11026 $
|| ####################################################################
\*======================================================================*/
?>