<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 31700 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('prefix', 'prefixadmin');

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_prefix.php');
require_once(DIR . '/includes/functions_prefix.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminforums'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$log_vars = array();
if (!empty($_REQUEST['prefixsetid']))
{
	$log_vars[] = 'prefixsetid = ' . htmlspecialchars_uni($_REQUEST['prefixsetid']);
}
if (!empty($_REQUEST['prefixid']))
{
	$log_vars[] = 'prefixid = ' . htmlspecialchars_uni($_REQUEST['prefixid']);
}
log_admin_action(implode(', ', $log_vars));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['thread_prefix_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

// notes on phrases:
// prefixset_ID_title (prefixes), prefix_ID_title_plain (global), prefix_ID_title_rich (global)

// ########################################################################

if ($_POST['do'] == 'killprefix')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixid' => TYPE_NOHTML,
	));

	$prefixdm =& datamanager_init('Prefix', $vbulletin, ERRTYPE_CP);

	$prefix = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "prefix
		WHERE prefixid = '" . $db->escape_string($vbulletin->GPC['prefixid']) . "'
	");
	if (!$prefix)
	{
		print_stop_message('invalid_action_specified');
	}

	$prefixdm->set_existing($prefix);
	$prefixdm->delete();

	define('CP_REDIRECT', 'prefix.php?do=list');
	print_stop_message('prefix_deleted');
}

// ########################################################################

if ($_REQUEST['do'] == 'deleteprefix')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefixid' => TYPE_NOHTML,
	));

	print_delete_confirmation('prefix', $vbulletin->GPC['prefixid'], 'prefix', 'killprefix');
}

// ########################################################################

if ($_POST['do'] == 'insertprefix')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixid' => TYPE_NOHTML,
		'origprefixid' => TYPE_NOHTML,
		'prefixsetid' => TYPE_NOHTML,
		'title_plain' => TYPE_STR,
		'title_rich' => TYPE_STR,
		'displayorder' => TYPE_UINT
	));

	$prefixdm =& datamanager_init('Prefix', $vbulletin, ERRTYPE_CP);

	if ($vbulletin->GPC['origprefixid'])
	{
		$prefix = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "prefix
			WHERE prefixid = '" . $db->escape_string($vbulletin->GPC['origprefixid']) . "'
		");
		if (!$prefix)
		{
			print_stop_message('invalid_action_specified');
		}

		$prefixdm->set_existing($prefix);
	}
	else
	{
		$prefixdm->set('prefixid', $vbulletin->GPC['prefixid']);
	}

	$prefixdm->set('prefixsetid', $vbulletin->GPC['prefixsetid']);
	$prefixdm->set('displayorder', $vbulletin->GPC['displayorder']);
	$prefixdm->set_info('title_plain', $vbulletin->GPC['title_plain']);
	$prefixdm->set_info('title_rich', $vbulletin->GPC['title_rich']);

	$prefixdm->save();

	define('CP_REDIRECT', 'prefix.php?do=list');
	print_stop_message('prefix_saved');
}

// ########################################################################

if ($_REQUEST['do'] == 'addprefix' OR $_REQUEST['do'] == 'editprefix')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefixid' => TYPE_NOHTML,
		'prefixsetid' => TYPE_NOHTML
	));

	// fetch existing prefix if we want to edit
	if ($vbulletin->GPC['prefixid'])
	{
		$prefix = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "prefix
			WHERE prefixid = '" . $db->escape_string($vbulletin->GPC['prefixid']) . "'
		");
		if ($prefix)
		{
			$phrase_sql = $db->query_read("
				SELECT varname, text
				FROM " . TABLE_PREFIX . "phrase
				WHERE varname IN (
						'" . $db->escape_string("prefix_$prefix[prefixid]_title_plain") . "',
						'" . $db->escape_string("prefix_$prefix[prefixid]_title_rich") . "'
					)
					AND fieldname = 'global'
					AND languageid = 0
			");
			while ($phrase = $db->fetch_array($phrase_sql))
			{
				$title = str_replace("prefix_$prefix[prefixid]_", '', $phrase['varname']);
				$prefix["$title"] = $phrase['text'];
			}
		}
	}

	// if not editing a set, setup the default for a new set
	if (empty($prefix))
	{
		$prefix = array(
			'prefixid' => '',
			'prefixsetid' => $vbulletin->GPC['prefixsetid'],
			'title_plain' => '',
			'title_rich' => '',
			'displayorder' => 10
		);
	}

	$trans_link = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=global&t=1&varname="; // has varname appended

	print_form_header('prefix', 'insertprefix');

	if ($prefix['prefixid'])
	{
		print_table_header($vbphrase['editing_prefix']);
		print_label_row($vbphrase['prefix_id_alphanumeric_note'], $prefix['prefixid'], '', 'top', 'prefixid');
		construct_hidden_code('origprefixid', $prefix['prefixid']);
	}
	else
	{
		print_table_header($vbphrase['adding_prefix']);
		print_input_row($vbphrase['prefix_id_alphanumeric_note'], 'prefixid', '', true, 35, 25);
	}

	$prefixsets_sql = $db->query_read("
		SELECT prefixsetid
		FROM " . TABLE_PREFIX . "prefixset
		ORDER BY displayorder
	");

	$prefixsets = array();
	while ($prefixset = $db->fetch_array($prefixsets_sql))
	{
		$prefixsets["$prefixset[prefixsetid]"] = htmlspecialchars_uni($vbphrase["prefixset_$prefixset[prefixsetid]_title"]);
	}

	print_select_row($vbphrase['prefix_set'], 'prefixsetid', $prefixsets, $prefix['prefixsetid']);
	print_input_row(
		$vbphrase['title_plain_text'] . ($prefix['prefixid'] ?  '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "prefix_$prefix[prefixid]_title_plain", 1)  . '</dfn>' : ''),
		'title_plain', $prefix['title_plain']
	);
	print_input_row(
		$vbphrase['title_rich_text'] . ($prefix['prefixid'] ?  '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "prefix_$prefix[prefixid]_title_rich", 1)  . '</dfn>' : ''),
		'title_rich', $prefix['title_rich']
	);
	print_input_row($vbphrase['display_order'], 'displayorder', $prefix['displayorder']);
	print_submit_row();
}

// ########################################################################

if ($_POST['do'] == 'killset')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixsetid' => TYPE_NOHTML,
	));

	$prefixsetdm =& datamanager_init('PrefixSet', $vbulletin, ERRTYPE_CP);

	$prefixset = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "prefixset
		WHERE prefixsetid = '" . $db->escape_string($vbulletin->GPC['prefixsetid']) . "'
	");
	if (!$prefixset)
	{
		print_stop_message('invalid_action_specified');
	}

	$prefixsetdm->set_existing($prefixset);
	$prefixsetdm->delete();

	define('CP_REDIRECT', 'prefix.php?do=list');
	print_stop_message('prefix_set_deleted');
}

// ########################################################################

if ($_REQUEST['do'] == 'deleteset')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefixsetid' => TYPE_NOHTML,
	));

	print_delete_confirmation('prefixset', $vbulletin->GPC['prefixsetid'], 'prefix', 'killset');
}

// ########################################################################

if ($_POST['do'] == 'insertset')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixsetid' => TYPE_NOHTML,
		'origprefixsetid' => TYPE_NOHTML,
		'title' => TYPE_STR,
		'displayorder' => TYPE_UINT,
		'forumids' => TYPE_ARRAY_INT
	));

	$prefixsetdm =& datamanager_init('PrefixSet', $vbulletin, ERRTYPE_CP);

	if ($vbulletin->GPC['origprefixsetid'])
	{
		$prefixset = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "prefixset
			WHERE prefixsetid = '" . $db->escape_string($vbulletin->GPC['origprefixsetid']) . "'
		");
		if (!$prefixset)
		{
			print_stop_message('invalid_action_specified');
		}

		$prefixsetdm->set_existing($prefixset);
	}
	else
	{
		$prefixsetdm->set('prefixsetid', $vbulletin->GPC['prefixsetid']);
	}

	$prefixsetdm->set('displayorder', $vbulletin->GPC['displayorder']);
	$prefixsetdm->set_info('title', $vbulletin->GPC['title']);

	$prefixsetdm->save();
	$vbulletin->GPC['prefixsetid'] = $prefixsetdm->fetch_field('prefixsetid');

	// setup this prefix set for selected forums
	$old_forums = array();
	if ($vbulletin->GPC['origprefixsetid'])
	{
		// find where the prefix used to be used
		$forum_list_sql = $db->query_read("
			SELECT forumid
			FROM " . TABLE_PREFIX . "forumprefixset
			WHERE prefixsetid = '" . $db->escape_string($vbulletin->GPC['prefixsetid']) . "'
		");
		while ($forum = $db->fetch_array($forum_list_sql))
		{
			$old_forums[] = $forum['forumid'];
		}
	}

	$new_forums = array_diff($vbulletin->GPC['forumids'], array(-1, 0));

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "forumprefixset
		WHERE prefixsetid = '" . $db->escape_string($vbulletin->GPC['prefixsetid']) . "'
	");

	$add_forums_query = array();
	$escaped_id = $db->escape_string($vbulletin->GPC['prefixsetid']);

	foreach ($new_forums AS $forumid)
	{
		$add_forums_query[] = "($forumid, '$escaped_id')";
	}

	if ($add_forums_query)
	{
		$db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "forumprefixset
				(forumid, prefixsetid)
			VALUES
				" . implode(',', $add_forums_query)
		);
	}

	// find the forums that were removed and remove these prefixes from threads
	$removed_forums = array_diff($old_forums, $new_forums);
	if ($removed_forums)
	{
		$prefixes = array();
		$prefix_sql = $db->query_read("
			SELECT prefixid
			FROM " . TABLE_PREFIX . "prefix
			WHERE prefixsetid = '" . $db->escape_string($vbulletin->GPC['prefixsetid']) . "'
		");
		while ($prefix = $db->fetch_array($prefix_sql))
		{
			$prefixes[] = $prefix['prefixid'];
		}

		remove_prefixes_forum($prefixes, $removed_forums);
	}

	build_prefix_datastore();

	define('CP_REDIRECT', 'prefix.php?do=list');
	print_stop_message('prefix_set_saved');
}

// ########################################################################

if ($_REQUEST['do'] == 'addset' OR $_REQUEST['do'] == 'editset')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'prefixsetid' => TYPE_NOHTML
	));

	// fetch existing prefix set if we want to edit
	if ($vbulletin->GPC['prefixsetid'])
	{
		$prefixset = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "prefixset
			WHERE prefixsetid = '" . $db->escape_string($vbulletin->GPC['prefixsetid']) . "'
		");
		if ($prefixset)
		{
			$phrase = $db->query_first("
				SELECT text
				FROM " . TABLE_PREFIX . "phrase
				WHERE varname = '" . $db->escape_string("prefixset_$prefixset[prefixsetid]_title") . "'
					AND fieldname = 'prefix'
					AND languageid = 0
			");
			$prefixset['title'] = $phrase['text'];
		}
	}

	// if not editing a set, setup the default for a new set
	if (empty($prefixset))
	{
		$prefixset = array(
			'prefixsetid' => '',
			'title' => '',
			'displayorder' => 10
		);
	}

	print_form_header('prefix', 'insertset');

	if ($prefixset['prefixsetid'])
	{
		print_table_header($vbphrase['editing_prefix_set']);
		print_label_row($vbphrase['prefix_set_id_alphanumeric_note'], $prefixset['prefixsetid'], '', 'top', 'prefixsetid');
		construct_hidden_code('origprefixsetid', $prefixset['prefixsetid']);
	}
	else
	{
		print_table_header($vbphrase['adding_prefix_set']);
		print_input_row($vbphrase['prefix_set_id_alphanumeric_note'], 'prefixsetid', '', true, 35, 25);
	}

	$trans_link = "phrase.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&fieldname=prefix&t=1&varname="; // has varname appended

	print_input_row(
		$vbphrase['title']. ($prefixset['prefixsetid'] ?  '<dfn>' . construct_link_code($vbphrase['translations'], $trans_link . "prefixset_$prefixset[prefixsetid]_title", 1)  . '</dfn>' : ''),
		'title', $prefixset['title']
	);
	print_input_row($vbphrase['display_order'], 'displayorder', $prefixset['displayorder']);

	$enabled_forums = array();
	if ($prefixset['prefixsetid'])
	{
		$forums_sql = $db->query_read("
			SELECT forumid
			FROM " . TABLE_PREFIX . "forumprefixset
			WHERE prefixsetid = '" . $db->escape_string($prefixset['prefixsetid']) . "'
		");
		while ($forum = $db->fetch_array($forums_sql))
		{
			$enabled_forums[] = $forum['forumid'];
		}
	}

	if (empty($enabled_forums))
	{
		// default to selecting "none"
		$enabled_forums = array(-1);
	}

	print_forum_chooser($vbphrase['use_prefix_set_in_these_forums'], 'forumids[]', $enabled_forums, $vbphrase['none'], false, true);

	print_submit_row();
}

// ########################################################################

if ($_POST['do'] == 'displayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'prefixset_order' => TYPE_ARRAY_UINT,
		'prefix_order' => TYPE_ARRAY_UINT
	));

	foreach ($vbulletin->GPC['prefixset_order'] AS $prefixsetid => $displayorder)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "prefixset SET
				displayorder =  " . intval($displayorder) . "
			WHERE prefixsetid = '" . $db->escape_string($prefixsetid) . "'
		");
	}

	foreach ($vbulletin->GPC['prefix_order'] AS $prefixid => $displayorder)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "prefix SET
				displayorder =  " . intval($displayorder) . "
			WHERE prefixid = '" . $db->escape_string($prefixid) . "'
		");
	}

	build_prefix_datastore();

	define('CP_REDIRECT', 'prefix.php?do=list');
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################

if ($_REQUEST['do'] == 'list')
{
	$prefixsets_sql = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "prefixset
		ORDER BY displayorder
	");

	$prefixsets = array();
	while ($prefixset = $db->fetch_array($prefixsets_sql))
	{
		$prefixsets["$prefixset[prefixsetid]"] = $prefixset;
		$prefixsets["$prefixset[prefixsetid]"]['prefixes'] = array();
	}

	$prefixes_sql = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "prefix
		ORDER BY displayorder
	");
	while ($prefix = $db->fetch_array($prefixes_sql))
	{
		if (isset($prefixsets["$prefix[prefixsetid]"]))
		{
			$prefixsets["$prefix[prefixsetid]"]['prefixes']["$prefix[prefixid]"] = $prefix;
		}
	}

	print_form_header('prefix', 'displayorder');
	print_table_header($vbphrase['thread_prefixes'], 3);

	if (!$prefixsets)
	{
		print_description_row($vbphrase['no_prefix_sets_defined_click_create'], false, 3, '', 'center');
	}
	else
	{
		// display existing sets
		foreach ($prefixsets AS $prefixset)
		{
			print_cells_row(array(
				htmlspecialchars_uni($vbphrase["prefixset_$prefixset[prefixsetid]_title"]),
				'<input type="text" size="3" class="bginput" name="prefixset_order[' . $prefixset['prefixsetid'] . ']" value="' . $prefixset['displayorder'] . '" />',
				'<div align="right" class="smallfont">'
					. construct_link_code($vbphrase['add_prefix'], "prefix.php?do=addprefix&amp;prefixsetid=$prefixset[prefixsetid]")
					. construct_link_code($vbphrase['edit'], "prefix.php?do=editset&amp;prefixsetid=$prefixset[prefixsetid]")
					. construct_link_code($vbphrase['delete'], "prefix.php?do=deleteset&amp;prefixsetid=$prefixset[prefixsetid]")
				. '</div>',
			), 1);

			if (!$prefixset['prefixes'])
			{
				print_description_row(construct_phrase($vbphrase['no_prefixes_defined_click_create'], $prefixset['prefixsetid']), false, 3, '', 'center');
			}
			else
			{
				foreach ($prefixset['prefixes'] AS $prefix)
				{
					print_cells_row(array(
						htmlspecialchars_uni($vbphrase["prefix_$prefix[prefixid]_title_plain"]),
						'<input type="text" size="3" class="bginput" name="prefix_order[' . $prefix['prefixid'] . ']" value="' . $prefix['displayorder'] . '" />',
						'<div align="right" class="smallfont">'
							. construct_link_code($vbphrase['edit'], "prefix.php?do=editprefix&amp;prefixid=$prefix[prefixid]")
							. construct_link_code($vbphrase['delete'], "prefix.php?do=deleteprefix&amp;prefixid=$prefix[prefixid]")
						. '</div>'
					));
				}
			}
		}
	}

	print_submit_row($vbphrase['save_display_order'], false, 3);

	echo '<p align="center">' . construct_link_code($vbphrase['add_prefix_set'], 'prefix.php?do=addset') . '</p>';
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>
