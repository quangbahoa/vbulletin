<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 92875 $');
@ini_set('display_errors', 'On');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('album', 'attachment_image');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_album.php');
require_once(DIR . '/includes/adminfunctions_attachment.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################
if (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0)
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

print_cp_header($vbphrase['album_picture_manager']);

// ########################################################################
if ($_REQUEST['do'] == 'storage')
{
	switch ($vbulletin->options['album_dataloc'])
	{
		case 'fs_directthumb':
		{
			$options = array(
				'fs_move' => $vbphrase['move_pictures_within_file_system'],
				'fs_directthumb_to_fs' => $vbphrase['keep_pictures_file_system_disable_thumb'],
				'fs_to_db' => $vbphrase['move_pictures_into_database'],
			);

			$description = $vbphrase['album_pictures_currently_file_system_with_thumb'];
			break;
		}

		case 'fs':
		{
			$options = array(
				'fs_move' => $vbphrase['move_pictures_within_file_system'],
				'fs_to_fs_directthumb' => $vbphrase['keep_pictures_file_system_allow_thumb'],
				'fs_to_db' => $vbphrase['move_pictures_into_database'],
			);

			$description = $vbphrase['album_pictures_currently_file_system_without_thumb'];
			break;
		}

		case 'db':
		default:
		{
			$options = array(
				'db_to_fs' => $vbphrase['move_pictures_into_file_system_without_thumb'],
				'db_to_fs_directthumb' => $vbphrase['move_pictures_into_file_system_with_thumb'],
			);

			$description = $vbphrase['album_pictures_currently_in_database'];
		}
	}

	$i = 0;
	$dowhat = '';
	foreach($options AS $value => $text)
	{
		$dowhat .= "<label for=\"dw$value\"><input type=\"radio\" name=\"dowhat\" id=\"dw$value\" value=\"$value\"" . iif($i++ == 0, ' checked="checked"') . " />$text</label><br />";
	}

	print_form_header('album', 'switchtype');
	print_table_header("$vbphrase[storage_type]: <span class=\"normal\">$vbphrase[album_pictures]</span>");
	print_description_row($description);
	print_label_row($vbphrase['action'], $dowhat);
	print_submit_row($vbphrase['go'], 0);

}

// ########################################################################
if ($_REQUEST['do'] == 'switchtype')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'dowhat' 	=> TYPE_STR
	));

	// check safe mode stuff first
	switch ($vbulletin->GPC['dowhat'])
	{
		case 'db_to_fs':
		case 'db_to_fs_directthumb':
		{
			if (SAFEMODE)
			{
				// pics as files is not compatible with safe_mode since it creates directories
				// Safe_mode does not allow you to write to directories created by PHP
				print_stop_message('your_server_has_safe_mode_enabled');
			}

			$direct_thumb = ($vbulletin->GPC['dowhat'] == 'db_to_fs_directthumb' ? 1 : 0);

			print_form_header('album', 'do_dbfs');
			construct_hidden_code('type', $direct_thumb ? 'fs_directthumb' : 'fs');
			print_table_header($direct_thumb ? $vbphrase['album_pictures_currently_file_system_with_thumb'] : $vbphrase['album_pictures_currently_file_system_without_thumb']);
			print_description_row($vbphrase['album_pictures_currently_in_database']);

			if ($direct_thumb)
			{
				print_input_row($vbphrase['full_picture_file_path'], 'album_picpath', $vbulletin->options['album_picpath']);
				print_input_row($vbphrase['thumbnail_file_path'], 'album_thumbpath', $vbulletin->options['album_thumbpath']);
				print_input_row($vbphrase['url_to_thumbnails'], 'album_thumburl', $vbulletin->options['album_thumburl']);
			}
			else
			{
				print_input_row($vbphrase['picture_thumbnail_file_path'], 'album_picpath', $vbulletin->options['album_picpath']);
			}

			print_submit_row($vbphrase['go']);
			break;
		}

		case 'fs_to_db':
		{
			print_form_header('album', 'domovepictures');
			construct_hidden_code('type_old', $vbulletin->options['album_dataloc']);
			construct_hidden_code('type_new', 'db');

			print_table_header($vbphrase['edit_storage_type']);
			print_description_row($vbphrase['ready_move_pictures_into_database']);
			print_input_row($vbphrase['number_pictures_per_cycle'], 'perpage', 300, 1, 5);
			if ($vbulletin->debug)
			{
				print_input_row($vbphrase['starting_picture_id'], 'startid', 0, 1, 5);
			}
			print_submit_row($vbphrase['go']);
			break;
		}

		case 'fs_directthumb_to_fs':
		{
			print_form_header('album', 'domovepictures');
			construct_hidden_code('type_old', $vbulletin->options['album_dataloc']);
			construct_hidden_code('type_new', 'fs');

			print_table_header($vbphrase['edit_storage_type']);
			print_description_row("<p>$vbphrase[read_disable_direct_access_thumbnails]</p>" . $vbphrase['possible_not_enough_disk_space_finalize']);
			print_input_row($vbphrase['number_pictures_per_cycle'], 'perpage', 300, 1, 5);
			if ($vbulletin->debug)
			{
				print_input_row($vbphrase['starting_picture_id'], 'startid', 0, 1, 5);
			}
			print_submit_row($vbphrase['go']);
			break;
		}

		case 'fs_to_fs_directthumb':
		{
			print_form_header('album', 'do_fsdirectthumb');
			print_table_header($vbphrase['album_pictures_currently_file_system_with_thumb']);

			print_label_row($vbphrase['full_picture_file_path'], htmlspecialchars_uni($vbulletin->options['album_picpath']));
			print_input_row($vbphrase['thumbnail_file_path'], 'album_thumbpath', $vbulletin->options['album_thumbpath']);
			print_input_row($vbphrase['url_to_thumbnails'], 'album_thumburl', $vbulletin->options['album_thumburl']);

			print_submit_row($vbphrase['go']);
			break;
		}

		case 'fs_move':
		{
			print_form_header('album', 'do_fsmove');
			print_table_header($vbphrase['move_pictures_within_file_system']);

			print_input_row($vbphrase['full_picture_file_path'], 'album_picpath', $vbulletin->options['album_picpath']);
			if ($vbulletin->options['album_dataloc'] == 'fs_directthumb')
			{
				print_input_row($vbphrase['thumbnail_file_path'], 'album_thumbpath', $vbulletin->options['album_thumbpath']);
				print_input_row($vbphrase['url_to_thumbnails'], 'album_thumburl', $vbulletin->options['album_thumburl']);
			}

			print_submit_row($vbphrase['go']);
			break;
		}
	}
}

// ########################################################################
if ($_POST['do'] == 'do_dbfs')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'album_picpath' => TYPE_STR,
		'album_thumbpath' => TYPE_STR,
		'album_thumburl' => TYPE_STR,
		'type' => TYPE_STR,
	));

	$vbulletin->GPC['album_picpath'] = preg_replace('#[/\\\]+$#', '', $vbulletin->GPC['album_picpath']);
	verify_upload_folder($vbulletin->GPC['album_picpath']);

	if ($vbulletin->GPC['type'] == 'fs_directthumb')
	{
		$vbulletin->GPC['album_thumbpath'] = preg_replace('#[/\\\]+$#', '', $vbulletin->GPC['album_thumbpath']);
		verify_upload_folder($vbulletin->GPC['album_thumbpath']);
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "setting SET value =
		CASE varname
			WHEN 'album_picpath' THEN '" . $db->escape_string($vbulletin->GPC['album_picpath']) . "'
			WHEN 'album_thumbpath' THEN '" . $db->escape_string($vbulletin->GPC['album_thumbpath']) . "'
			WHEN 'album_thumburl' THEN '" . $db->escape_string($vbulletin->GPC['album_thumburl']) . "'
		ELSE value END
		WHERE varname IN('album_picpath', 'album_thumbpath', 'album_thumburl')
	");
	build_options();

	// ########################

	print_form_header('album', 'domovepictures');
	construct_hidden_code('type_old', 'db');
	construct_hidden_code('type_new', $vbulletin->GPC['type']);

	print_table_header($vbphrase['edit_storage_type']);
	print_description_row("<p>$vbphrase[ready_move_pictures_file_system_options]</p>" . $vbphrase['possible_not_enough_disk_space_finalize']);
	print_input_row($vbphrase['number_pictures_per_cycle'], 'perpage', 300, 1, 5);
	if ($vbulletin->debug)
	{
		print_input_row($vbphrase['starting_picture_id'], 'startid', 0, 1, 5);
	}
	print_submit_row($vbphrase['go']);
}

// ########################################################################
if ($_POST['do'] == 'do_fsdirectthumb')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'album_thumbpath' => TYPE_STR,
		'album_thumburl' => TYPE_STR,
		'type' => TYPE_STR,
	));

	$vbulletin->GPC['album_thumbpath'] = preg_replace('#[/\\\]+$#', '', $vbulletin->GPC['album_thumbpath']);
	verify_upload_folder($vbulletin->GPC['album_thumbpath']);

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "setting SET value =
		CASE varname
			WHEN 'album_thumbpath' THEN '" . $db->escape_string($vbulletin->GPC['album_thumbpath']) . "'
			WHEN 'album_thumburl' THEN '" . $db->escape_string($vbulletin->GPC['album_thumburl']) . "'
		ELSE value END
		WHERE varname IN('album_thumbpath', 'album_thumburl')
	");
	build_options();

	// ########################

	print_form_header('album', 'domovepictures');
	construct_hidden_code('type_old', 'fs');
	construct_hidden_code('type_new', 'fs_directthumb');

	print_table_header($vbphrase['edit_storage_type']);
	print_description_row("<p>$vbphrase[ready_move_pictures_file_system_options]</p>" . $vbphrase['possible_not_enough_disk_space_finalize']);
	print_input_row($vbphrase['number_pictures_per_cycle'], 'perpage', 300, 1, 5);
	if ($vbulletin->debug)
	{
		print_input_row($vbphrase['starting_picture_id'], 'startid', 0, 1, 5);
	}
	print_submit_row($vbphrase['go']);
}

// ########################################################################
if ($_POST['do'] == 'do_fsmove')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'album_picpath' => TYPE_STR,
		'album_thumbpath' => TYPE_STR,
		'album_thumburl' => TYPE_STR,
		'type' => TYPE_STR,
	));

	$changed_values = array();

	$paths = array();
	$commands = array();
	$have_change = false;

	if ($vbulletin->GPC['album_picpath'] != $vbulletin->options['album_picpath'])
	{
		$vbulletin->GPC['album_picpath'] = preg_replace('#[/\\\]+$#', '', $vbulletin->GPC['album_picpath']);
		verify_upload_folder($vbulletin->GPC['album_picpath']);

		$old = htmlspecialchars_uni($vbulletin->options['album_picpath']);
		$new = htmlspecialchars_uni($vbulletin->GPC['album_picpath']);

		$paths[] = "<em>$vbphrase[full_pictures]</em>: $old &gt;&gt; $new";
		$commands[] = "mv $old/* $new/";
		$have_change = true;

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "setting SET
				value = '" . $db->escape_string($vbulletin->GPC['album_picpath']) . "'
			WHERE varname = 'album_picpath'
		");
	}

	if ($vbulletin->options['album_dataloc'] == 'fs_directthumb')
	{
		if ($vbulletin->GPC['album_thumbpath'] != $vbulletin->options['album_thumbpath'])
		{
			$vbulletin->GPC['album_thumbpath'] = preg_replace('#[/\\\]+$#', '', $vbulletin->GPC['album_thumbpath']);
			verify_upload_folder($vbulletin->GPC['album_thumbpath']);

			$old = htmlspecialchars_uni($vbulletin->options['album_thumbpath']);
			$new = htmlspecialchars_uni($vbulletin->GPC['album_thumbpath']);

			$paths[] = "<em>$vbphrase[thumbnails]</em>: $old &gt;&gt; $new";
			$commands[] = "mv $old/* $new/";
			$have_change = true;

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "setting SET
					value = '" . $db->escape_string($vbulletin->GPC['album_thumbpath']) . "'
				WHERE varname = 'album_thumbpath'
			");
		}

		if ($vbulletin->GPC['album_thumburl'] != $vbulletin->options['album_thumburl'])
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "setting SET
					value = '" . $db->escape_string($vbulletin->GPC['album_thumburl']) . "'
				WHERE varname = 'album_thumburl'
			");
			$have_change = true;
		}
	}

	if (!$have_change)
	{
		print_stop_message('no_file_paths_changed');
	}

	build_options();

	define('CP_CONTINUE', 'album.php?do=storage');
	if ($paths)
	{
		print_stop_message(
			'pictures_moved_within_file_system_update_x_y',
			'<li>' . implode('</li><li>', $paths) . '</li>',
			'<li>' . implode('</li><li>', $commands) . '</li>'
		);
	}
	else
	{
		// changed thumb url only
		print_stop_message('picture_thumb_url_updated');
	}
}

// ########################################################################
if ($_REQUEST['do'] == 'domovepictures')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'      => TYPE_UINT,
		'startid'      => TYPE_UINT,
		'errorcount'   => TYPE_UINT,
		'count'        => TYPE_UINT,

		'type_old'     => TYPE_STR,
		'type_new'     => TYPE_STR,

		'paths'        => TYPE_ARRAY_STR
	));

	if (is_demo_mode())
	{
		print_cp_message('This function is disabled within demo mode');
	}

	if (!$vbulletin->GPC['type_old'] OR !$vbulletin->GPC['type_new'])
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($vbulletin->GPC['perpage'] < 1)
	{
		$vbulletin->GPC['perpage'] = 10;
	}

	$next_startid = $vbulletin->GPC['startid'];

	$pictures = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "picture
		WHERE pictureid >= " . $vbulletin->GPC['startid'] . "
		ORDER BY pictureid ASC
		LIMIT " . $vbulletin->GPC['perpage']
	);

	if ($vbulletin->debug)
	{
		echo '<table width="100%" border="1" cellspacing="0" cellpadding="1">
				<tr>
				<td><b>Picture ID</b></td><td><b>Caption</b></td><td><b>Size in Database</b></td><td><b>Move OK</b></td>
				</tr>
			';
	}
	while ($picture = $db->fetch_array($pictures))
	{
		$next_startid = $picture['pictureid'];
		$vbulletin->GPC['count']++;

		$picerror = false;

		if ($vbulletin->GPC['type_old'] == 'db')
		{
			// Converting FROM db TO fs - handles both methods
			$vbulletin->options['album_dataloc'] = $vbulletin->GPC['type_new'];

			$pic_path = verify_picture_fs_path($picture, false);
			$thumb_path = verify_picture_fs_path($picture, true);

			if ($pic_path AND $thumb_path)
			{
				// write pic
				$filename = fetch_picture_fs_path($picture, false);
				if ($fp = fopen($filename, 'wb'))
				{
					if (!fwrite($fp, $picture['filedata']))
					{
						$picerror = true;
					}
					fclose($fp);

					if (!$picerror AND (!is_readable($filename) OR @filesize($filename) == 0))
					{
						$picerror = true;
					}
				}
				else
				{
					$picerror = true;
				}

				// write thumb if one and no error
				if (!$picerror AND $picture['thumbnail'])
				{
					$filename = fetch_picture_fs_path($picture, true);
					if ($fp = fopen($filename, 'wb'))
					{
						if (!fwrite($fp, $picture['thumbnail']))
						{
							$picerror = true;
						}
						fclose($fp);
					}
					else
					{
						$picerror = true;
					}

					if (!$picerror AND (!is_readable($filename) OR @filesize($filename) == 0))
					{
						$picerror = true;
					}
				}
			}
			else
			{
				$picerror = true;
			}

			$vbulletin->options['album_dataloc'] = $vbulletin->GPC['type_old'];
		}
		else if ($vbulletin->GPC['type_new'] == 'db')
		{
			// Converting FROM fs TO mysql
			$pic_path = fetch_picture_fs_path($picture, false);
			$thumb_path = fetch_picture_fs_path($picture, true);

			if ($picdata = @file_get_contents($pic_path))
			{
				$db->query_write("
					UPDATE " . TABLE_PREFIX . "picture SET
						filedata = '" . $db->escape_string($picdata) . "',
						thumbnail = '" . $db->escape_string(file_get_contents($thumb_path)) . "'
					WHERE pictureid = $picture[pictureid]
				");
			}
			else
			{
				$picerror = true;
			}
		}
		else if ($vbulletin->GPC['type_old'] == 'fs_directthumb' AND $vbulletin->GPC['type_new'] == 'fs')
		{
			$pic_dir = fetch_picture_fs_path($picture, false, false);
			$thumb_path = fetch_picture_fs_path($picture, true);

			if (file_exists($thumb_path))
			{
				if (!@copy($thumb_path, $pic_dir . '/' . basename($thumb_path)))
				{
					$picerror = true;
				}
			}
		}
		else if ($vbulletin->GPC['type_old'] == 'fs' AND $vbulletin->GPC['type_new'] == 'fs_directthumb')
		{
			$old_thumb_path = fetch_picture_fs_path($picture, true);
			if (file_exists($old_thumb_path))
			{
				$vbulletin->options['album_dataloc'] = $vbulletin->GPC['type_new'];
				verify_picture_fs_path($picture, true);

				if (!@copy($old_thumb_path, fetch_picture_fs_path($picture, true)))
				{
					$picerror = true;
				}

				$vbulletin->options['album_dataloc'] = $vbulletin->GPC['type_old'];
			}

		}
		else
		{
			print_stop_message('please_complete_required_fields');
		}

		if ($vbulletin->debug)
		{
			$print_cap = substr($picture['caption'], 0, 35) . (strlen($picture['caption']) > 35 ? '...' : '');
			if ($print_cap === '')
			{
				$print_cap = '&nbsp;';
			}

			echo "	<tr>
					<td>$picture[pictureid]</td>
					<td>$print_cap</td>
					<td>$picture[filesize]</td>
					<td>" . ($picerror ? '<b>ERROR!</b>' : 'ok') . "</td>
					</tr>
			";
		}
		else
		{
			if ($picerror)
			{
				echo "$vbphrase[picture] : <b>$picture[pictureid] - $vbphrase[error]</b><br />";
			}
			else
			{
				echo "$vbphrase[picture] : <b>$picture[pictureid]</b> - $vbphrase[okay]<br />";
			}
			vbflush();
		}

		if ($picerror)
		{
			$vbulletin->GPC['errorcount']++;
		}
	}

	if ($vbulletin->debug)
	{
		echo '</table>';
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT pictureid FROM " . TABLE_PREFIX . "picture WHERE pictureid > $next_startid LIMIT 1"))
	{
		print_cp_redirect(
			"album.php?" . $vbulletin->session->vars['sessionurl'] . "do=domovepictures&startid=$checkmore[pictureid]" .
			"&pp=" . $vbulletin->GPC['perpage'] .
			"&count=" . $vbulletin->GPC['count'] .
			"&errorcount=" . $vbulletin->GPC['errorcount'] .
			"&type_old=" . urlencode($vbulletin->GPC['type_old']) .
			"&type_new=" . urlencode($vbulletin->GPC['type_new'])
		);

		echo "<p><a href=\"album.php?" . $vbulletin->session->vars['sessionurl'] . "do=domovepictures&amp;startid=$checkmore[pictureid]" .
			"&amp;pp=" . $vbulletin->GPC['perpage'] .
			"&amp;count=" . $vbulletin->GPC['count'] .
			"&amp;errorcount=" . $vbulletin->GPC['errorcount'] .
			"&amp;type_old=" . urlencode($vbulletin->GPC['type_old']) .
			"&amp;type_new=" . urlencode($vbulletin->GPC['type_new']) . "\">" .
			$vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		print_cp_redirect(
			"album.php?" . $vbulletin->session->vars['sessionurl'] . "do=finalize" .
			"&count=" . $vbulletin->GPC['count'] .
			"&errorcount=" . $vbulletin->GPC['errorcount'] .
			"&type_old=" . urlencode($vbulletin->GPC['type_old']) .
			"&type_new=" . urlencode($vbulletin->GPC['type_new'])
		);
	}
}

// ########################################################################
if ($_REQUEST['do'] == 'finalize')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'count'        => TYPE_UINT,
		'errorcount'   => TYPE_UINT,
		'type_old'     => TYPE_STR,
		'type_new'     => TYPE_STR,
	));

	if (!$vbulletin->GPC['type_old'] OR !$vbulletin->GPC['type_new'])
	{
		print_stop_message('please_complete_required_fields');
	}

	$totalpictures = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "picture");

	if ($totalpictures['count'] != $vbulletin->GPC['count'] OR !$vbulletin->GPC['count'] OR ($vbulletin->GPC['errorcount'] / $vbulletin->GPC['count']) * 10 > 1)
	{
		$finalizeoption = false;
	}
	else
	{
		$finalizeoption = true;
	}


	if ($vbulletin->GPC['type_old'] == 'db')
	{
		print_form_header('album', 'confirmfiledataremove');
		construct_hidden_code('type', $vbulletin->GPC['type_new']);
		print_table_header($vbphrase['confirm_picture_data_removal']);
		print_description_row(
			construct_phrase($vbphrase['pictures_moved_file_system_count_xyz'], $totalpictures['count'], $vbulletin->GPC['count'], $vbulletin->GPC['errorcount'])
		);

		print_yes_no_row($vbphrase['finalize'], 'removefiledata', $finalizeoption);
		print_submit_row($vbphrase['go']);
	}
	else if ($vbulletin->GPC['type_new'] == 'db')
	{
		print_form_header('album', 'confirmfsremove');
		construct_hidden_code('type_old', $vbulletin->GPC['type_old']);
		construct_hidden_code('type_new', $vbulletin->GPC['type_new']);
		print_table_header($vbphrase['confirm_picture_data_removal']);
		print_description_row(
			construct_phrase($vbphrase['pictures_moved_database_count_xyz'], $totalpictures['count'], $vbulletin->GPC['count'], $vbulletin->GPC['errorcount'])
		);

		print_yes_no_row($vbphrase['finalize'], 'removefiles', $finalizeoption);
		print_submit_row($vbphrase['go']);
	}
	else if ($vbulletin->GPC['type_old'] == 'fs_directthumb' AND $vbulletin->GPC['type_new'] == 'fs')
	{
		print_form_header('album', 'confirmfsremove');
		construct_hidden_code('type_old', $vbulletin->GPC['type_old']);
		construct_hidden_code('type_new', $vbulletin->GPC['type_new']);
		print_table_header($vbphrase['confirm_picture_data_removal']);
		print_description_row(
			construct_phrase($vbphrase['pictures_moved_thumbnails_disabled_count_xyz'], $totalpictures['count'], $vbulletin->GPC['count'], $vbulletin->GPC['errorcount'])
		);

		print_yes_no_row($vbphrase['finalize'], 'removefiles', $finalizeoption);
		print_submit_row($vbphrase['go']);
	}
	else if ($vbulletin->GPC['type_old'] == 'fs' AND $vbulletin->GPC['type_new'] == 'fs_directthumb')
	{
		print_form_header('album', 'confirmfsremove');
		construct_hidden_code('type_old', $vbulletin->GPC['type_old']);
		construct_hidden_code('type_new', $vbulletin->GPC['type_new']);
		print_table_header($vbphrase['confirm_picture_data_removal']);
		print_description_row(
			construct_phrase($vbphrase['pictures_moved_thumbnails_enabled_count_xyz'], $totalpictures['count'], $vbulletin->GPC['count'], $vbulletin->GPC['errorcount'])
		);

		print_yes_no_row($vbphrase['finalize'], 'removefiles', $finalizeoption);
		print_submit_row($vbphrase['go']);
	}
}

// ########################################################################
if ($_REQUEST['do'] == 'confirmfsremove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'removefiles'    => TYPE_BOOL,
		'type_old'       => TYPE_STR,
		'type_new'       => TYPE_STR,
		'startid'        => TYPE_UINT,
		'perpage'        => TYPE_UINT,
	));

	if ($vbulletin->GPC['removefiles'])
	{
		if (empty($vbulletin->GPC['perpage']))
		{
			$vbulletin->GPC['perpage'] = 500;
		}

		if (!$vbulletin->GPC['type_old'] AND !$vbulletin->GPC['type_new'])
		{
			echo "bug";
			exit;
		}

		if ($vbulletin->GPC['startid'] == 0)
		{
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . $db->escape_string($vbulletin->GPC['type_new']) . "' WHERE varname = 'album_dataloc'");
			build_options();
		}

		// the below code needs to work as if the old value is still being used
		$vbulletin->options['album_dataloc'] = $vbulletin->GPC['type_old'];

		$pictures = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "picture
			WHERE pictureid >= " . $vbulletin->GPC['startid'] . "
			ORDER BY pictureid
			LIMIT " . $vbulletin->GPC['perpage']
		);
		if ($records = $db->num_rows($pictures))
		{
			echo '<p>' . construct_phrase($vbphrase['removing_x_records'], $records) . '</p>';
			vbflush();

			while ($picture = $db->fetch_array($pictures))
			{
				$maxid = $picture['pictureid'];

				if ($vbulletin->GPC['type_new'] == 'db')
				{
					$pic_path = fetch_picture_fs_path($picture, false);
					$thumb_path = fetch_picture_fs_path($picture, true);

					@unlink($pic_path);
					@unlink($thumb_path);

					$pic_dir = dirname($pic_path);
					$thumb_dir = dirname($thumb_path);

					@rmdir($pic_dir);
					if ($pic_dir != $thumb_dir)
					{
						@rmdir($thumb_dir);
					}
				}
				else if ($vbulletin->GPC['type_new'] == 'fs' OR $vbulletin->GPC['type_new'] == 'fs_directthumb')
				{
					// remove duplicate thumb data
					$thumb_path = fetch_picture_fs_path($picture, true);
					@unlink($thumb_path);

					$thumb_dir = dirname($thumb_path);
					@rmdir($thumb_dir);
				}
			}

			print_cp_redirect(
				"album.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmfsremove&startid=" . ($maxid + 1) . "&removefiles=1" .
				"&type_new=" . urlencode($vbulletin->GPC['type_new']) . "&pp=" . $vbulletin->GPC['perpage']
			);

			echo "<p><a href=\"album.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmfsremove&amp;startid=" . ($maxid + 1) . "&amp;removefiles=1" .
				"&amp;type_new=" . urlencode($vbulletin->GPC['type_new']) . "&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" .
				$vbphrase['click_here_to_continue_processing'] . "</a></p>";

		}
		else
		{
			// Again, make sure we are on the new setting.
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . $db->escape_string($vbulletin->GPC['type_new']) . "' WHERE varname = 'album_dataloc'");
			build_options();

			define('CP_CONTINUE', 'album.php?do=storage');
			if ($vbulletin->GPC['type_new'] == 'db')
			{
				print_stop_message('pictures_moved_to_the_database');
			}
			else
			{
				print_stop_message('pictures_moved_within_the_file_system');
			}
		}
	}
	else
	{
		define('CP_CONTINUE', 'album.php?do=storage');
		if ($vbulletin->GPC['type_new'] == 'db')
		{
			print_stop_message('pictures_not_moved_to_the_database');
		}
		else
		{
			print_stop_message('pictures_not_moved_within_the_file_system');
		}
	}
}

// ########################################################################
if ($_REQUEST['do'] == 'confirmfiledataremove')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'removefiledata' => TYPE_BOOL,
		'type'           => TYPE_STR,
		'startid'        => TYPE_UINT,
		'perpage'        => TYPE_UINT,
	));

	if ($vbulletin->GPC['removefiledata'])
	{
		if (empty($vbulletin->GPC['perpage']))
		{
			$vbulletin->GPC['perpage'] = 500;
		}

		if ($vbulletin->GPC['startid'] == 0)
		{
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . $db->escape_string($vbulletin->GPC['type']) . "' WHERE varname = 'album_dataloc'");
			build_options();
		}

		$pictures = $db->query_read("
			SELECT pictureid
			FROM " . TABLE_PREFIX . "picture
			WHERE pictureid >= " . $vbulletin->GPC['startid'] . "
			ORDER BY pictureid
			LIMIT " . $vbulletin->GPC['perpage']
		);
		if ($records = $db->num_rows($pictures))
		{
			echo '<p>' . construct_phrase($vbphrase['removing_x_records'], $records) . '</p>';
			vbflush();

			$maxid = 0;

			$pictureids = '';
			while ($picture = $db->fetch_array($pictures))
			{
				$pictureids .= ",$picture[pictureid]";
				$maxid = $picture['pictureid'];
			}

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "picture SET
					filedata = '',
					thumbnail = ''
				WHERE pictureid IN (0$pictureids)
			");

			print_cp_redirect(
				"album.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmfiledataremove&startid=" . ($maxid + 1) . "&removefiledata=1" .
				"&type=" . urlencode($vbulletin->GPC['type']) . "&pp=" . $vbulletin->GPC['perpage']
			);

			echo "<p><a href=\"album.php?" . $vbulletin->session->vars['sessionurl'] . "do=confirmfiledataremove&amp;startid=" . ($maxid + 1) . "&amp;removefiledata=1" .
				"&amp;type=" . urlencode($vbulletin->GPC['type']) . "&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" .
				$vbphrase['click_here_to_continue_processing'] . "</a></p>";

		}
		else
		{
			// Again, make sure we are on the new setting.
			$db->query_write("UPDATE " . TABLE_PREFIX . "setting SET value = '" . $db->escape_string($vbulletin->GPC['type']) . "' WHERE varname = 'album_dataloc'");
			build_options();

			define('CP_CONTINUE', 'album.php?do=storage');
			print_stop_message('pictures_moved_to_the_file_system');
		}
	}
	else
	{
		define('CP_CONTINUE', 'album.php?do=storage');
		print_stop_message('pictures_not_moved_to_the_file_system');
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'rebuildthumbs')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'quality' => TYPE_UINT,
		'perpage' => TYPE_UINT,
		'startid' => TYPE_UINT
	));

	require_once(DIR . '/includes/class_image.php');
	$image = vB_Image::fetch_library($vbulletin);

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 25;
	}

	$maxid = $vbulletin->GPC['startid'];

	if ($vbulletin->options['album_dataloc'] == 'db')
	{
		if ($vbulletin->options['safeupload'])
		{
			$tempfilename = $vbulletin->options['tmppath'] . '/' . md5(uniqid(microtime()) . $vbulletin->userinfo['userid']);
		}
		else
		{
			$tempfilename = tempnam(ini_get('upload_tmp_dir'), 'vbthumb');
		}
	}

	$pictures = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "picture
		WHERE pictureid >= " . $vbulletin->GPC['startid'] . "
		ORDER BY pictureid
		LIMIT " . $vbulletin->GPC['perpage']
	);
	while ($picture = $db->fetch_array($pictures))
	{
		$maxid = $picture['pictureid'];

		echo construct_phrase($vbphrase['processing_x'], $picture['pictureid']);

		if ($vbulletin->options['album_dataloc'] == 'db')
		{
			$filename = $tempfilename;
			$filenum = fopen($filename, 'wb');
			fwrite($filenum, $picture['filedata']);
			fclose($filenum);
		}
		else
		{
			$filename = fetch_picture_fs_path($picture);
		}

		$thumbnail = $image->fetch_thumbnail(
			"picture.$picture[extension]",
			$filename,
			$vbulletin->options['album_thumbsize'],
			$vbulletin->options['album_thumbsize'],
			$vbulletin->GPC['quality']
		);

		if (!empty($thumbnail['filedata']))
		{
			$picturedata = datamanager_init(fetch_picture_dm_name(), $vbulletin, ERRTYPE_SILENT, 'picture');
			$picturedata->set_existing($picture);
			$picturedata->setr_info('thumbnail', $thumbnail['filedata']);
			$picturedata->set('thumbnail_dateline', TIMENOW);
			$picturedata->set('thumbnail_width', $thumbnail['width']);
			$picturedata->set('thumbnail_height', $thumbnail['height']);
			$picturedata->save();
			unset($picturedata);
		}

		echo '<br />';
		vbflush();
	}

	if ($vbulletin->options['album_dataloc'] == 'db')
	{
		@unlink($tempfilename);
	}

	$maxid++;

	if ($checkmore = $db->query_first("SELECT pictureid FROM " . TABLE_PREFIX . "picture WHERE pictureid >= $maxid LIMIT 1"))
	{
		print_cp_redirect("album.php?" . $vbulletin->session->vars['sessionurl'] .
			"do=rebuildthumbs&amp;startid=$maxid&amp;pp=" . $vbulletin->GPC['perpage'] .
			"&amp;quality=" . $vbulletin->GPC['quality']
		);
		echo "<p><a href=\"album.php?" . $vbulletin->session->vars['sessionurl'] .
			"do=rebuildthumbs&amp;startid=$maxid&amp;pp=" . $vbulletin->GPC['perpage'] .
			"&amp;quality=" . $vbulletin->GPC['quality'] . '">' . $vbphrase['click_here_to_continue_processing'] .
			"</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'album.php?do=thumb');
		print_stop_message('rebuilt_picture_thumbnails_successfully');
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'thumb')
{
	print_form_header('album', 'rebuildthumbs');
	print_table_header($vbphrase['rebuild_album_picture_thumbnails']);
	print_description_row($vbphrase['function_rebuilds_album_picture_thumbs']);
	print_input_row($vbphrase['number_pictures_per_cycle'], 'perpage', 25);
	$quality = intval($vbulletin->options['thumbquality']);
	if ($quality <= 0 OR $quality > 100)
	{
		$quality = 75;
	}
	print_input_row($vbphrase['thumbnail_quality'], 'quality', $quality);
	print_submit_row($vbphrase['rebuild_album_picture_thumbnails']);
}

// #######################################################################

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
