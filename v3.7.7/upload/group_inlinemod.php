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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// #################### DEFINE IMPORTANT CONSTANTS #######################
if ($_REQUEST['do'] == 'inlinemerge' OR $_POST['do'] == 'doinlinemerge')
{
	define('GET_EDIT_TEMPLATES', true);
}
define('THIS_SCRIPT', 'inlinemod');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('threadmanage', 'posting', 'inlinemod');

// get special data templates from the datastore
$specialtemplates = array();

$globaltemplates = array(
	'threadadmin_authenticate'
);

$actiontemplates = array(
	'inlinedelete' => array('socialgroups_deletemessages')
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_socialgroup.php');
require_once(DIR . '/includes/modfunctions.php');
require_once(DIR . '/includes/functions_log_error.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!$vbulletin->userinfo['userid'] OR !$vbulletin->options['socnet_groups_msg_enabled'])
{
	print_no_permission();
}

$itemlimit = 200;

// This is a list of ids that were checked on the page we submitted from
$vbulletin->input->clean_array_gpc('p', array(
	'gmessagelist' => TYPE_ARRAY_KEYS_INT,
	'userid'       => TYPE_UINT,
));

$vbulletin->input->clean_array_gpc('c', array(
	'vbulletin_inlinegmessage' => TYPE_STR,
));

if (!empty($vbulletin->GPC['vbulletin_inlinegmessage']))
{
	$gmessagelist = explode('-', $vbulletin->GPC['vbulletin_inlinegmessage']);
	$gmessagelist = $vbulletin->input->clean($gmessagelist, TYPE_ARRAY_UINT);

	$vbulletin->GPC['gmessagelist'] = array_unique(array_merge($gmessagelist, $vbulletin->GPC['gmessagelist']));
}

if (!$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

switch ($_POST['do'])
{
	case 'doinlinedelete':
	{
		$inline_mod_authenticate = true;
		break;
	}
	default:
	{
		$inline_mod_authenticate = false;
		($hook = vBulletinHook::fetch_hook('group_inlinemod_authenticate_switch')) ? eval($hook) : false;
	}
}

if ($inline_mod_authenticate AND !inlinemod_authenticated())
{
	show_inline_mod_login();
}

switch ($_POST['do'])
{
	case 'inlinedelete':
	case 'inlineapprove':
	case 'inlineunapprove':
	case 'inlineundelete':

		if (empty($vbulletin->GPC['gmessagelist']))
		{
			standard_error(fetch_error('you_did_not_select_any_valid_messages'));
		}

		if (count($vbulletin->GPC['gmessagelist']) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_messages', $itemlimit));
		}

		if ($vbulletin->GPC['userid'])
		{
			$userinfo = fetch_userinfo($vbulletin->GPC['userid'], 1);
		}

		$messageids = implode(', ', $vbulletin->GPC['gmessagelist']);
		break;

	case 'doinlinedelete':

		$vbulletin->input->clean_array_gpc('p', array(
			'messageids' => TYPE_STR,
		));
		$messageids = explode(',', $vbulletin->GPC['messageids']);
		$messageids = $vbulletin->input->clean($messageids, TYPE_ARRAY_UINT);

		if (count($messageids) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_messages', $itemlimit));
		}
		break;
}

// set forceredirect for IIS
$forceredirect = (strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false);

$messagelist = $messagearray = $userlist = array();

($hook = vBulletinHook::fetch_hook('group_inlinemod_start')) ? eval($hook) : false;

if ($_POST['do'] == 'clearmessage')
{
	setcookie('vbulletin_inlinegmessage', '', TIMENOW - 3600, '/');

	eval(print_standard_redirect('redirect_inline_messagelist_cleared', true, $forceredirect));
}

if ($_POST['do'] == 'inlineapprove' OR $_POST['do'] == 'inlineunapprove')
{
	$insertrecords = array();

	$approve = $_POST['do'] == 'inlineapprove' ? true : false;

	// Validate records
	$messages = $db->query_read_slave("
		SELECT gm.gmid, gm.state, gm.groupid, gm.dateline, gm.postuserid, gm.postusername
		FROM " . TABLE_PREFIX . "groupmessage AS gm
		WHERE gmid IN ($messageids)
		 AND gm.state IN (" . ($approve ? "'moderation'" : "'visible', 'deleted'") . ")
	");
	while ($message = $db->fetch_array($messages))
	{
		$group = fetch_socialgroupinfo($message['groupid']);
		$message['is_group_owner'] = ($group['creatoruserid'] == $vbulletin->userinfo['userid']);

		// Check permissions.....
		if ($message['state'] == 'deleted' AND !fetch_socialgroup_modperm('canundeletegroupmessages', $group))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_messages'));
		}
		else if (!fetch_socialgroup_modperm('canmoderategroupmessages', $group))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_moderate_messages'));
		}

		$message['group_name'] = $group['name'];

		$messagearray["$message[gmid]"] = $message;
		$grouplist["$message[groupid]"] = true;

		if (!$approve)
		{
			$insertrecords[] = "($message[gmid], 'groupmessage', " . TIMENOW . ")";
		}
	}

	if (empty($messagearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_messages'));
	}

	// Set message state
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "groupmessage
		SET state = '" . ($approve ? 'visible' : 'moderation') . "'
		WHERE gmid IN (" . implode(',', array_keys($messagearray)) . ")
	");

	if ($approve)
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "moderation
			WHERE primaryid IN(" . implode(',', array_keys($messagearray)) . ")
				AND type = 'groupmessage'
		");
	}
	else	// Unapprove
	{
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "moderation
				(primaryid, type, dateline)
			VALUES
				" . implode(',', $insertrecords) . "
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "deletionlog
			WHERE type = 'groupmessage' AND
				primaryid IN(" . implode(',', array_keys($messagearray)) . ")
		");
	}

	foreach($grouplist AS $groupid => $foo)
	{
		build_group_counters($groupid);
	}

	foreach ($messagearray AS $message)
	{
		if (!$message['is_group_owner'])
		{
			log_moderator_action($message,
				($approve ? 'gm_by_x_for_y_approved' : 'gm_by_x_for_y_unapproved'),
				array($message['postusername'], $message['group_name'])
			);
		}
	}

	setcookie('vbulletin_inlinegmessage', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('group_inlinemod_approveunapprove')) ? eval($hook) : false;

	if ($approve)
	{
		eval(print_standard_redirect('redirect_inline_approvedmessages', true, $forceredirect));
	}
	else
	{
		eval(print_standard_redirect('redirect_inline_unapprovedmessages', true, $forceredirect));
	}
}

if ($_POST['do'] == 'inlinedelete')
{
	$show['removemessagets'] = false;
	$show['deletemessages'] = false;
	$show['deleteoption'] = false;
	$checked = array('delete' => 'checked="checked"');

	// Validate Messages
	$messages = $db->query_read_slave("
		SELECT gm.gmid, gm.state, gm.groupid, gm.dateline, gm.postuserid
		FROM " . TABLE_PREFIX . "groupmessage AS gm
		WHERE gmid IN ($messageids)
	");
	while ($message = $db->fetch_array($messages))
	{
		$group = fetch_socialgroupinfo($message['groupid']);
		$canmoderatemessages = fetch_socialgroup_modperm('canmoderategroupmessages', $group);
		$candeletemessages = (fetch_socialgroup_modperm('candeletegroupmessages', $group) OR ($message['state'] == 'visible' AND $message['postuserid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanagemessages']));
		$canremovemessages = fetch_socialgroup_modperm('canremovegroupmessages', $group);

		if ($message['state'] == 'moderation' AND !$canmoderatemessages)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_messages'));
		}
		else if ($message['state'] == 'deleted' AND !$candeletemessages)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_messages'));
		}
		else
		{
			$show['deletemessages'] = $candeletemessages;
			if ($canremovemessages)
			{
				$show['removemessages'] = true;
				if (!$candeletemessages)
				{
					$checked = array('remove' => 'checked="checked"');
				}
			}

			if (!$candeletemessages AND !$canremovemessages)
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_messages'));
			}
			else if ($candeletemessages AND $canremovemessages)
			{
				$show['deleteoption'] = true;
			}
		}

		$messagearray["$message[gmid]"] = $message;
		$grouplist["$message[groupid]"] = true;
	}

	if (empty($messagearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_messages'));
	}

	$messagecount = count($messagearray);
	$groupcount = count($grouplist);

	$url =& $vbulletin->url;

	$navbits = array('' => $vbphrase['delete_messages']);
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('group_inlinemod_delete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('socialgroups_deletemessages') . '");');

}

if ($_POST['do'] == 'doinlinedelete')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'   => TYPE_UINT, // 1 - Soft Deletion, 2 - Physically Remove
		'deletereason' => TYPE_NOHTMLCOND,
	));

	$physicaldel = ($vbulletin->GPC['deletetype'] == 2) ? true : false;

	// Validate Messages
	$messages = $db->query_read_slave("
		SELECT gm.gmid, gm.state, gm.groupid, gm.dateline, gm.postuserid, gm.postusername
		FROM " . TABLE_PREFIX . "groupmessage AS gm
		WHERE gmid IN (" . implode(',', $messageids) . ")
	");
	while ($message = $db->fetch_array($messages))
	{
		$group = fetch_socialgroupinfo($message['groupid']);
		$message['is_group_owner'] = ($group['creatoruserid'] == $vbulletin->userinfo['userid']);

		$canmoderatemessages = fetch_socialgroup_modperm('canmoderategroupmessages', $group);
		$candeletemessages = (fetch_socialgroup_modperm('candeletegroupmessages', $group) OR ($message['state'] == 'visible' AND $message['postuserid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanagemessages']));
		$canremovemessages = can_moderate(0, 'canremovegroupmessages');

		if ($message['state'] == 'moderation' AND !$canmoderatemessages)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_messages'));
		}
		else if ($message['state'] == 'deleted' AND !$candeletemessages)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_messages'));
		}
		else
		{
			if (($physicaldel AND !$canremovemessages) OR (!$physicaldel AND !$candeletemessages))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_messages'));
			}
		}

		$message['group_name'] = $group['name'];

		$messagearray["$message[gmid]"] = $message;
		$grouplist["$message[groupid]"] = true;
	}

	if (empty($messagearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_messages'));
	}

	foreach($messagearray AS $gmid => $message)
	{
		$dataman =& datamanager_init('GroupMessage', $vbulletin, ERRTYPE_SILENT);
		$dataman->set_existing($message);
		$dataman->set_info('hard_delete', $physicaldel);
		$dataman->set_info('reason', $vbulletin->GPC['deletereason']);
		$dataman->set_info('skip_build_counters', true);
		$dataman->delete();
		unset($dataman);
	}

	foreach($grouplist AS $groupid => $foo)
	{
		build_group_counters($groupid);
	}

	foreach ($messagearray AS $message)
	{
		if (!$message['is_group_owner'])
		{
			log_moderator_action($message,
				($physicaldel ? 'gm_by_x_for_y_removed' : 'gm_by_x_for_y_soft_deleted'),
				array($message['postusername'], $message['group_name'])
			);
		}
	}

	// empty cookie
	setcookie('vbulletin_inlinegmessage', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('group_inlinemod_dodelete')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_deletedmessages', true, $forceredirect));
}

if ($_POST['do'] == 'inlineundelete')
{
	if (!can_moderate(0, 'candeletegroupmessages'))
	{
		standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_messages'));
	}
	// Validate Messages
	$messages = $db->query_read_slave("
		SELECT gm.gmid, gm.state, gm.groupid, gm.dateline, gm.postuserid, gm.postusername,
			socialgroup.name AS group_name, socialgroup.creatoruserid
		FROM " . TABLE_PREFIX . "groupmessage AS gm
		LEFT JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (socialgroup.groupid = gm.groupid)
		WHERE gmid IN ($messageids)
			AND state = 'deleted'
	");
	while ($message = $db->fetch_array($messages))
	{
		$message['is_group_owner'] = ($message['creatoruserid'] == $vbulletin->userinfo['userid']);
		$messagearray["$message[gmid]"] = $message;
		$grouplist["$message[groupid]"] = true;
	}

	if (empty($messagearray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_messages'));
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "deletionlog
		WHERE type = 'groupmessage' AND
			primaryid IN(" . implode(',', array_keys($messagearray)) . ")
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "groupmessage
		SET state = 'visible'
		WHERE gmid IN(" . implode(',', array_keys($messagearray)) . ")
	");

	foreach($grouplist AS $groupid => $foo)
	{
		build_group_counters($groupid);
	}

	foreach ($messagearray AS $message)
	{
		if (!$message['is_group_owner'])
		{
			log_moderator_action($message, 'gm_by_x_for_y_undeleted',
				array($message['postusername'], $message['group_name'])
			);
		}
	}

	// empty cookie
	setcookie('vbulletin_inlinegmessage', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('group_inlinemod_undelete')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_undeletedmessages', true, $forceredirect));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # SVN: $Revision: 31700 $
|| ####################################################################
\*======================================================================*/