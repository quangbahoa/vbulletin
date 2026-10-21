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
define('THIS_SCRIPT', 'group');
define('CSRF_PROTECTION', true);
define('GET_EDIT_TEMPLATES', 'message,picture');

if ($_POST['do'] == 'message')
{
	if (isset($_POST['ajax']))
	{
		define('NOPMPOPUP', 1);
		define('NOSHUTDOWNFUNC', 1);
	}
	if (isset($_POST['fromquickcomment']))
	{	// Don't update Who's Online for Quick Comments since it will get stuck on that until the user goes somewhere else
		define('LOCATION_BYPASS', 1);
	}
}

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array (
	'socialgroups',
	'search',
	'user',
	'posting',
	'album',
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'grouplist' => array(
		'SOCIALGROUPS',
		'socialgroups_grouplist',
		'socialgroups_mygroups',
		'socialgroups_mygroups_bit',
		'socialgroups_grouplist_bit',
		'forumdisplay_sortarrow'
	),
	'invitations' => array(
		'SOCIALGROUPS',
		'socialgroups_grouplist',
		'socialgroups_grouplist_bit',
		'forumdisplay_sortarrow'
	),
	'requests' => array(
		'SOCIALGROUPS',
		'socialgroups_grouplist',
		'socialgroups_grouplist_bit',
		'forumdisplay_sortarrow'
	),
	'moderatedgms' => array(
		'SOCIALGROUPS',
		'socialgroups_grouplist',
		'socialgroups_grouplist_bit',
		'forumdisplay_sortarrow'
	),
	'leave' => array(
		'socialgroups_confirm'
	),
	'delete' => array(
		'socialgroups_confirm'
	),
	'join' => array(
		'socialgroups_confirm'
	),
	'edit' => array(
		'socialgroups_form'
	),
	'create' => array(
		'socialgroups_form'
	),
	'view' => array(
		'memberinfo_tiny',
		'socialgroups_group',
		'editor_css',
		'editor_clientscript',
		'editor_jsoptions_font',
		'editor_jsoptions_size',
		'showthread_quickreply',
		'socialgroups_message',
		'socialgroups_message_deleted',
		'socialgroups_message_ignored',
		'socialgroups_picturebit',
		'socialgroups_css',
	),
	'viewmembers' => array(
		'im_aim',
		'im_icq',
		'im_msn',
		'im_skype',
		'im_yahoo',
		'memberinfo_small',
		'memberinfo_css',
		'postbit_onlinestatus',
		'socialgroups_memberlist'
	),
	'search' => array(
		'socialgroups_search'
	),
	'message' => array(
		'socialgroups_editor',
		'visitormessage_preview',
	),
	'report' => array(
		'newpost_usernamecode',
		'reportitem',
	),
	'reportpicture' => array(
		'newpost_usernamecode',
		'reportitem',
	),
	'grouppictures' => array(
		'socialgroups_pictures',
		'socialgroups_picturebit'
	),
	'picture' => array(
		'socialgroups_picture',
		'picturecomment_commentarea',
		'picturecomment_css',
		'picturecomment_form',
		'picturecomment_message',
		'picturecomment_message_deleted',
		'picturecomment_message_ignored',
		'picturecomment_message_global_ignored',
		'showthread_quickreply'
	),
	'addpictures' => array(
		'socialgroups_addpictures'
	),
	'insertpictures' => array(
		'socialgroups_addpictures',
		'socialgroups_addpicture_invalidurl',
		'socialgroups_addpicture_messagebit',
		'socialgroups_addpicture_messages',
		'socialgroups_addpictures',
		'socialgroups_picturebit'
	),
	'manage' => array(
		'socialgroups_manage',
		'socialgroups_managebit',
		'socialgroups_css',
	),
	'managemembers' => array(
		'socialgroups_css',
		'socialgroups_managebit',
		'socialgroups_managemembers',
	),
	'sendinvite' => array(
		'socialgroups_manage',
		'socialgroups_managebit',
		'socialgroups_css',
	),
	'removepicture' => array(
		'socialgroups_confirm'
	)
);


$action_needs_groupid = array(
	'addpictures',
	'cancelinvites',
	'delete',
	'deletemessage',
	'dodelete',
	'doedit',
	'dojoin',
	'doleave',
	'doremovepicture',
	'edit',
	'grouppictures',
	'insertpictures',
	'join',
	'kickmembers',
	'leave',
	'manage',
	'managemembers',
	'message',
	'pendingmembers',
	'picture',
	'removepicture',
	'report',
	'reportpicture',
	'sendemail',
	'sendinvite',
	'sendpictureemail',
	'view',
	'viewmembers'
);

if (!$_REQUEST['do'] AND ($_REQUEST['gmid'] OR $_REQUEST['groupid']))
{
	$actiontemplates['none'] =& $actiontemplates['view'];
}
else
{
	$actiontemplates['none'] =& $actiontemplates['grouplist'];
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_socialgroup.php');
require_once(DIR . '/includes/functions_album.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/class_postbit.php'); // for construct_im_icons

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'groupid'	=> TYPE_UINT,
	'gmid'    => TYPE_UINT,
));

($hook = vBulletinHook::fetch_hook('group_start_precheck')) ? eval($hook) : false;

if (empty($_REQUEST['do']))
{
	if ($vbulletin->GPC['groupid'] OR $vbulletin->GPC['gmid'])
	{
		$_REQUEST['do'] = 'view';
	}
	else
	{
		$_REQUEST['do'] = 'grouplist';
	}
}

if (
	!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups'])
	OR !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])
	OR !($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
	)
{
	print_no_permission();
}

// check if there is a valid groupid
if ($vbulletin->GPC['gmid'])
{
	$messageinfo = verify_groupmessage($vbulletin->GPC['gmid'], false, false);
	if (!empty($messageinfo['groupid']))
	{
		$vbulletin->GPC['groupid'] = $messageinfo['groupid'];
	}
}

// If a group id is specified, but the group doesn't exist, error out
if ($vbulletin->GPC['groupid'])
{
	$group = fetch_socialgroupinfo($vbulletin->GPC['groupid']);

	if (empty($group))
	{
		standard_error(fetch_error('invalidid', $vbphrase['social_group'], $vbulletin->options['contactuslink']));
	}
}

// Error out if no group specified, but a group is needed for the actions
if (in_array($_REQUEST['do'], $action_needs_groupid) AND empty($group))
{
	standard_error(fetch_error('invalidid', $vbphrase['social_group'], $vbulletin->options['contactuslink']));
}

if ($vbulletin->GPC['gmid'])
{
	if ($messageinfo['state'] == 'deleted' AND !fetch_socialgroup_modperm('canviewdeleted', $group))
	{
		standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink']));
	}
	else if ($messageinfo['state'] == 'moderation' AND !fetch_socialgroup_modperm('canmoderategroupmessages', $group) AND $messageinfo['postuserid'] != $vbulletin->userinfo['userid'])
	{
		standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink']));
	}
}

($hook = vBulletinHook::fetch_hook('group_start_postcheck')) ? eval($hook) : false;

// #######################################################################
if ($_REQUEST['do'] == 'grouplist' OR $_REQUEST['do'] == 'invitations' OR $_REQUEST['do'] == 'requests' OR $_REQUEST['do'] == 'moderatedgms')
{
	require_once(DIR . '/includes/class_socialgroup_search.php');
	$socialgroupsearch = new vB_SGSearch($vbulletin);

	switch ($_REQUEST['do'])
	{
		case 'invitations':
		{
			if (!$vbulletin->userinfo['userid'])
			{
				print_no_permission();
			}

			$socialgroupsearch->add('member', $vbulletin->userinfo['userid']);
			$socialgroupsearch->add('membertype', 'invited');
			$grouplisttitle = $navphrase = $vbphrase['your_invites'];
			$doaction = 'invitations';
		}
		break;

		case 'requests':
		{
			$socialgroupsearch->add('creator', $vbulletin->userinfo['userid']);
			$socialgroupsearch->add('pending', true);
			$grouplisttitle = $navphrase = $vbphrase['your_groups_in_need_of_attention'];
			$doaction = 'requests';
		}
		break;

		case 'moderatedgms':
		{
			if (!$vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups'])
			{
				print_no_permission();
			}

			$socialgroupsearch->add('creator', $vbulletin->userinfo['userid']);
			$socialgroupsearch->add('moderatedgms', true);
			$grouplisttitle = $navphrase = $vbphrase['your_groups_with_moderated_messages'];
			$doaction = 'moderatedgms';
		}
		break;
	}

	$dobreadcrumb = $navphrase ? true : false;
	$doaction = $doaction ? $doaction : 'grouplist';
	$navphrase = $navphrase ? $navphrase : $vbphrase['social_groups'];


	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'sortfield'  => TYPE_NOHTML,
		'sortorder'  => TYPE_NOHTML
	));

	$vbulletin->input->clean_array_gpc('r', array(
		'dofilter'   => TYPE_BOOL
	));

	if (empty($vbulletin->GPC['dofilter']) AND !($vbulletin->GPC['pagenumber'] > 1) AND $vbulletin->userinfo['userid'] AND $_REQUEST['do'] == 'grouplist')
	{
		// find list of groups that I'm in
		$mygroups = $vbulletin->db->query_read("
			SELECT socialgroup.*, socialgroup.dateline AS createdate,
				socialgroupmember.dateline AS joindate, socialgroupmember.type AS membertype
			FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
			INNER JOIN " . TABLE_PREFIX ."socialgroup AS socialgroup ON (socialgroup.groupid = socialgroupmember.groupid)
			WHERE socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . " AND socialgroupmember.type = 'member'
			ORDER BY socialgroupmember.dateline DESC
		");

		$mygroup_bits = '';
		$hasnormalgroups = false;

		while ($mygroup = $vbulletin->db->fetch_array($mygroups))
		{
			$mygroup = prepare_socialgroup($mygroup);
			$show['delete_group'] = can_delete_group($mygroup);
			$show['edit_group'] = can_edit_group($mygroup);
			$show['leave_group'] = can_leave_group($mygroup);
			$show['group_options'] = ($show['delete_group'] OR $show['edit_group'] OR $show['leave_group']);

			($hook = vBulletinHook::fetch_hook('group_list_mygroupsbit')) ? eval($hook) : false;

			eval('$mygroup_bits .= "' . fetch_template('socialgroups_mygroups_bit') . '";');
		}

		$vbulletin->db->free_result($mygroups);
	}

	if (empty($grouplisttitle))
	{
		if (empty($mygroup_bits))
		{
			$grouplisttitle = $vbphrase['social_groups'];
		}
		else
		{
			$grouplisttitle = $vbphrase['available_groups'];
		}
	}

	// start processing group list
	$sortfield  = $vbulletin->GPC['sortfield'];
	$perpage    = $vbulletin->GPC['perpage'];
	$pagenumber = $vbulletin->GPC['pagenumber'];

	if (empty($sortfield))
	{
		$sortfield = 'lastpost';
	}

	$perpage = $perpage ? $perpage : 20;

	$socialgroupsearch->set_sort($sortfield, $vbulletin->GPC['sortorder']);

	if ($vbulletin->GPC['dofilter'])
	{
		$vbulletin->input->clean_array_gpc('r', array(
			'filtertext'       => TYPE_NOHTML,
			'memberlimit'      => TYPE_UINT,
			'memberless'       => TYPE_UINT,
			'messagelimit'     => TYPE_UINT,
			'messageless'      => TYPE_UINT,
			'picturelimit'     => TYPE_UINT,
			'pictureless'      => TYPE_UINT,
			'filter_date_gteq' => TYPE_UNIXTIME,
			'filter_date_lteq' => TYPE_UNIXTIME,
		));

		$filters = array();

		if (!empty($vbulletin->GPC['filtertext']))
		{
			$filters['text'] = $vbulletin->GPC['filtertext'];
 		}

 		if (!empty($vbulletin->GPC['filter_date_lteq']))
		{
			$filters['date_lteq'] = $vbulletin->GPC['filter_date_lteq'];
 		}

 		if (!empty($vbulletin->GPC['filter_date_gteq']))
		{
			$filters['date_gteq'] = $vbulletin->GPC['filter_date_gteq'];
 		}

 		if (!empty($vbulletin->GPC['memberlimit']))
		{
			if ($vbulletin->GPC['memberless'] == 1)
			{
				$filters['members_lteq'] = $vbulletin->GPC['memberlimit'];
			}
			else
			{
				$filters['members_gteq'] = $vbulletin->GPC['memberlimit'];
			}
			$memberlessselected[$vbulletin->GPC['memberless']] = 'selected="selected"';
			$memberlimit = $vbulletin->GPC['memberlimit'];
 		}

 		if (!empty($vbulletin->GPC['messagelimit']))
		{
			if ($vbulletin->GPC['messageless'] == 1)
			{
				$filters['message_lteq'] = $vbulletin->GPC['messagelimit'];
			}
			else
			{
				$filters['message_gteq'] = $vbulletin->GPC['messagelimit'];
			}
			$messagelessselected[$vbulletin->GPC['messageless']] = 'selected="selected"';
			$messagelimit = $vbulletin->GPC['messagelimit'];
 		}

  		if (!empty($vbulletin->GPC['picturelimit']))
		{
			if ($vbulletin->GPC['pictureless'] == 1)
			{
				$filters['picture_lteq'] = $vbulletin->GPC['picturelimit'];
			}
			else
			{
				$filters['picture_gteq'] = $vbulletin->GPC['picturelimit'];
			}
			$picturelessselected[$vbulletin->GPC['pictureless']] = 'selected="selected"';
			$picturelimit = $vbulletin->GPC['picturelimit'];
 		}

 		foreach ($filters AS $key => $value)
 		{
 			$socialgroupsearch->add($key, $value);
 		}
 	}

 	($hook = vBulletinHook::fetch_hook('group_list_filter')) ? eval($hook) : false;

	$totalgroups = $socialgroupsearch->execute(true);
	$grouplist = '';
	if ($socialgroupsearch->has_errors())
	{
		$errorlist = '';
		foreach($socialgroupsearch->generator->errors AS $error)
		{
			$errorlist .= "<li>$error</li>";
		}
		$show['errors'] = $vbulletin->GPC['dofilter']; // don't show the error box if we didn't actually do a search
	}
	else
	{
		sanitize_pageresults($totalgroups, $pagenumber, $perpage);

		$socialgroupsearch->limit(($pagenumber - 1) * $perpage, $perpage);

		$groups = $socialgroupsearch->fetch_results();

		$show['gminfo'] = $vbulletin->options['socnet_groups_msg_enabled'];
		$show['pictureinfo'] = ($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums'] AND $vbulletin->options['socnet_groups_albums_enabled']) ? true : false;

		$picturealt = ($show['gminfo'] ? 'alt2' : 'alt1');
		$lastpostalt = ($show['pictureinfo'] ? 'alt1' : 'alt2');

		if (is_array($groups))
		{
			foreach ($groups AS $group)
			{
				$group = prepare_socialgroup($group);

				$show['pending_link'] = (fetch_socialgroup_modperm('caninvitemoderatemembers', $group) AND $group['moderatedmembers'] > 0);
				$show['lastpostinfo'] = ($group['lastpost']);

				($hook = vBulletinHook::fetch_hook('group_list_groupbit')) ? eval($hook) : false;

				eval('$grouplist .= "' . fetch_template("socialgroups_grouplist_bit") . '";');
			}
		}

		$sorturl = 'group.php?' . $vbulletin->session->vars['sessionurl'] . "do=$doaction" . ($perpage != 20 ? "&amp;pp=$perpage" : '');
		if ($vbulletin->GPC['dofilter'])
		{
			$sorturl .= '&amp;dofilter=1' .
			($vbulletin->GPC['filtertext'] ? '&amp;filtertext=' . $vbulletin->GPC['filtertext'] : '') .
			($vbulletin->GPC['memberlimit'] ? '&amp;memberlimit=' . $vbulletin->GPC['memberlimit'] : '') .
			($vbulletin->GPC['memberless'] ? '&amp;memberless=' . $vbulletin->GPC['memberless'] : '') .
			($vbulletin->GPC['messagelimit'] ? '&amp;messagelimit=' . $vbulletin->GPC['messagelimit'] : '') .
			($vbulletin->GPC['messageless'] ? '&amp;messageless=' . $vbulletin->GPC['messageless'] : '') .
			($vbulletin->GPC['picturelimit'] ? '&amp;picturelimit=' . $vbulletin->GPC['picturelimit'] : '') .
			($vbulletin->GPC['pictureless'] ? '&amp;pictureless=' . $vbulletin->GPC['pictureless'] : '') .
			($vbulletin->GPC['filter_date_gteq'] ? '&amp;filter_date_gteq=' . $vbulletin->GPC['filter_date_gteq'] : '') .
			($vbulletin->GPC['filter_date_lteq'] ? '&amp;filter_date_lteq=' . $vbulletin->GPC['filter_date_lteq'] : '');
		}

		$pagenav = construct_page_nav($pagenumber, $perpage, $totalgroups,
			$sorturl . "&amp;sort=$sortfield" .
				(!empty($vbulletin->GPC['sortorder']) ? "&amp;order=" . $vbulletin->GPC['sortorder'] : '')
		);

		$oppositesort = ($vbulletin->GPC['sortorder'] == 'asc' ? 'desc' : 'asc');

		eval('$sortarrow["$sortfield"] = "' . fetch_template('forumdisplay_sortarrow') . '";');
	}

	$show['creategroup'] = ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['cancreategroups']);

	$templatename = 'SOCIALGROUPS';

	$navbits = array();
	if ($dobreadcrumb)
	{
		$navbits['group.php' . $vbulletin->session->vars['sessionurl_q']] = $vbphrase['social_groups'];
	}
	$navbits[''] = $navphrase;
}

// #######################################################################
if ($_REQUEST['do'] == 'leave' OR $_REQUEST['do'] == 'delete' OR $_REQUEST['do'] == 'join')
{
	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'leave')
{
	if (!can_leave_group($group))
	{
		if ($group['is_owner'])
		{
			standard_error(fetch_error('cannot_leave_group_if_owner'));
		}
		else
		{
			standard_error(fetch_error('invalidid', $vbphrase['social_group'], $vbulletin->options['contactuslink']));
		}
	}

	$confirmdo = 'doleave';
	$confirmaction = 'group.php?do=doleave';

	if ($group['membertype'] == 'moderated')
	{
		$question_phrase = construct_phrase($vbphrase['confirm_cancel_join_group_x'], $group['name']);
		$title_phrase = $vbphrase['cancel_join_request_question'];
		$navphrase = $vbphrase['cancel_join_request'];
	}
	else if ($group['membertype'] == 'invited')
	{
		$question_phrase = construct_phrase($vbphrase['confirm_decline_join_group_x'], $group['name']);
		$title_phrase = $vbphrase['decline_join_invitation_question'];
		$navphrase = $vbphrase['decline_join_invitation'];
	}
	else
	{
		$question_phrase = construct_phrase($vbphrase['confirm_leave_group_x'], $group['name']);
		$title_phrase = $vbphrase['leave_group_question'];
		$navphrase = $vbphrase['leave_social_group'];
	}

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'] => $group['name'],
		'' => $navphrase
	);

	$url =& $vbulletin->url;
	$templatename = 'socialgroups_confirm';
}

// #######################################################################
if ($_REQUEST['do'] == 'delete')
{
	if (!can_delete_group($group))
	{
		print_no_permission();
	}

	$confirmdo = 'dodelete';
	$question_phrase = construct_phrase($vbphrase['confirm_delete_group_x'], $group['name']);
	$title_phrase = $vbphrase['delete_group_question'];
	$confirmaction = 'group.php?do=dodelete';

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'] => $group['name'],
		'' => $vbphrase['delete_group']
	);

	$url =& $vbulletin->url;
	$templatename = 'socialgroups_confirm';
}

// #######################################################################
if ($_REQUEST['do'] == 'join')
{
	if (!can_join_group($group))
	{
		print_no_permission();
	}

	$confirmdo = 'dojoin';
	$question_phrase = construct_phrase($vbphrase['confirm_join_group_x'], $group['name']);
	$title_phrase = $vbphrase['join_group_question'];
	$confirmaction = 'group.php?do=dojoin';

	$extratext = empty($vbphrase['join_' . $group['type'] . '_extratext']) ? '' : $vbphrase['join_' . $group['type'] . '_extratext'];

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'] => $group['name'],
		'' => $vbphrase['join_group']
	);

	$url =& $vbulletin->url;
	$templatename = 'socialgroups_confirm';
}

// #######################################################################
if ($_POST['do'] == 'doleave' OR $_POST['do'] == 'dodelete' OR $_POST['do'] == 'dojoin')
{
	$vbulletin->input->clean_array_gpc('p', array(
 		'deny'  => TYPE_NOHTML
	));

	// You either clicked no or you're a guest
	if ($vbulletin->GPC['deny'])
	{
		eval(print_standard_redirect('action_cancelled'));
	}

	if ($vbulletin->userinfo['userid'] == 0)
	{
		print_no_permission();
	}

	if ($vbulletin->url == $vbulletin->options['forumhome'] . '.php')
	{
		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . "groupid=$group[groupid]";
	}
}

// #######################################################################
if ($_POST['do'] == 'doleave')
{
	if (!can_leave_group($group))
	{
		if ($group['is_owner'])
		{
			standard_error(fetch_error('cannot_leave_group_if_owner'));
		}
		else
		{
			standard_error(fetch_error('invalidid', $vbphrase['social_group'], $vbulletin->options['contactuslink']));
		}
	}

	if (!empty($group['membertype']))
	{
		$currentmemberentry = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "socialgroupmember WHERE userid = " . $vbulletin->userinfo['userid'] . " AND groupid = " . $vbulletin->GPC['groupid']);

		// remove us from the group if we're still in it
		if ($currentmemberentry)
		{
			$socialgroupmemberdm = datamanager_init('SocialGroupMember', $vbulletin);
			$socialgroupmemberdm->set_existing($currentmemberentry);
			$socialgroupmemberdm->delete();
		}
	}

	eval(print_standard_redirect('successfully_left_group'));
}

// #######################################################################
if ($_POST['do'] == 'dodelete')
{
	if (!can_delete_group($group))
	{
		print_no_permission();
	}

	$socialgroupdm = datamanager_init('SocialGroup', $vbulletin);
	$socialgroupdm->set_existing($group);
 	$socialgroupdm->delete();

 	if (!$group['is_owner'] AND can_moderate(0, 'candeletesocialgroups'))
	{
		require_once(DIR . '/includes/functions_log_error.php');
		log_moderator_action($group, 'social_group_x_deleted',
			array($group['name'])
		);
	}

	$vbulletin->url = 'group.php' . $vbulletin->session->vars['sessionurl_q'];

	eval(print_standard_redirect('successfully_deleted_group'));
}

// #######################################################################
if ($_POST['do'] == 'dojoin')
{
	if (!can_join_group($group))
	{
		print_no_permission();
	}

	$jointype = array(
		'public'     => 'member',
		'moderated'  => 'moderated',
		'inviteonly' => 'member'
	);

	$socialgroupmemberdm = datamanager_init('SocialGroupMember', $vbulletin);

	if (!empty($group['membertype']))
	{
		$socialgroupmemberdm->set_existing($vbulletin->db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "socialgroupmember WHERE userid=" . $vbulletin->userinfo['userid'] . "  AND groupid = " . $group['groupid']
		));
	}

	$socialgroupmemberdm->set('userid', $vbulletin->userinfo['userid']);
	$socialgroupmemberdm->set('groupid', $vbulletin->GPC['groupid']);
	$socialgroupmemberdm->set('dateline', TIMENOW);
	$socialgroupmemberdm->set('type', $jointype["$group[type]"]);

	($hook = vBulletinHook::fetch_hook('group_dojoin')) ? eval($hook) : false;

	$socialgroupmemberdm->save();

	eval(print_standard_redirect('successfully_joined_group'));
}

// ############# Do we need group owner info? ############################
if ($_REQUEST['do'] == 'edit' OR $_POST['do'] == 'doedit' OR $_REQUEST['do'] == 'create' OR $_POST['do'] == 'docreate')
{
	if (!empty($group))
	{
		$groupowner = fetch_userinfo($group['creatoruserid']);
		cache_permissions($groupowner);
	}
	else
	{
		$groupowner = $vbulletin->userinfo;
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'create' OR $_REQUEST['do'] == 'edit')
{
	switch($_REQUEST['do'])
	{
		case 'create':
		{
			if (!($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['cancreategroups']))
			{
				print_no_permission();
			}
			$phrase =  $vbphrase['create_group'];
			$action = 'docreate';

			$checked['enable_group_messages'] = ' checked="checked"';
			$checked['enable_group_albums'] = ' checked="checked"';
		}
		break;
		case 'edit':
		{
			if (!can_edit_group($group))
			{
				print_no_permission();
			}

			$typeselected["$group[type]"] = ' selected="selected"';

			$phrase =  $vbphrase['edit_group'];
			$action = 'doedit';

			$checked['enable_group_messages'] = ($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_messages']) ? ' checked="checked"' : '';
			$checked['enable_group_albums'] = ($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_albums']) ? ' checked="checked"' : '';
			$checked['mod_queue'] = ($group['options'] & $vbulletin->bf_misc_socialgroupoptions['owner_mod_queue']) ? ' checked="checked"' : '';
			$checked['join_to_view'] = ($group['options'] & $vbulletin->bf_misc_socialgroupoptions['join_to_view']) ? ' checked="checked"' : '';
		}
		break;
	}

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups']
	);

	if ($_REQUEST['do'] == 'edit')
	{
		$navbits['group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid']] = $group['name'];
	}

 	$navbits[''] = $phrase;

	if ($_REQUEST['do'] == 'edit')
	{
		$show['title'] = (can_moderate(0, 'caneditsocialgroups') OR $group['members'] <= 1);
	}
	else
	{
		$show['title'] = true;
	}

	$show['mod_queue'] = (
		$vbulletin->options['sg_allow_owner_mod_queue']
		AND !$vbulletin->options['social_moderation']
		AND $groupowner['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
		AND $vbulletin->options['socnet_groups_msg_enabled']
	);

	$show['join_to_view'] = (
		$vbulletin->options['sg_allow_join_to_view']
		AND (
			$vbulletin->options['socnet_groups_msg_enabled']
			OR $vbulletin->options['socnet_groups_albums_enabled']
		)
	);

	$show['enable_group_messages'] = $vbulletin->options['socnet_groups_msg_enabled'];
	$show['enable_group_albums'] = $vbulletin->options['socnet_groups_albums_enabled'];

	$show['options'] = ($show['mod_queue'] OR $show['join_to_view'] OR $show['enable_group_albums'] OR $show['enable_group_messages']);

	$url =& $vbulletin->url;
	$templatename = 'socialgroups_form';
}

// #######################################################################
if ($_POST['do'] == 'docreate')
{
	if (!($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['cancreategroups']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
 		'groupname'        => TYPE_NOHTML,
 		'groupdescription' => TYPE_NOHTML,
 		'grouptype'        => TYPE_STR,
 		'options'          => TYPE_ARRAY_BOOL,
	));

	$groupdm = datamanager_init('SocialGroup', $vbulletin, ERRTYPE_STANDARD);

	$groupdm->set('name', $vbulletin->GPC['groupname']);
	$groupdm->set('description', $vbulletin->GPC['groupdescription']);
	$groupdm->set('creatoruserid', $vbulletin->userinfo['userid']);
	$groupdm->set('type', $vbulletin->GPC['grouptype']);

	foreach (array_keys($vbulletin->bf_misc_socialgroupoptions) AS $key)
	{
		switch ($key)
		{
			case 'owner_mod_queue':
			{
				$permcheck = (
					$vbulletin->options['sg_allow_owner_mod_queue']
					AND !$vbulletin->options['social_moderation']
					AND $groupowner['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
					AND $vbulletin->options['socnet_groups_msg_enabled']
				) ? true : false;
			}
			break;

			case 'join_to_view':
			{
				$permcheck = (
					$vbulletin->options['sg_allow_join_to_view']
					AND (
						$vbulletin->options['socnet_groups_msg_enabled']
						OR $vbulletin->options['socnet_groups_albums_enabled']
					)
				) ? true : false;
			}
			break;

			case 'enable_group_messages':
			{
				$permcheck = $vbulletin->options['socnet_groups_msg_enabled'] ? true : false;
			}
			break;

			case 'enable_group_albums':
			{
				$permcheck = $vbulletin->options['socnet_groups_albums_enabled'] ? true : false;
			}
			break;

			default:
			{
				$permcheck = false;
			}
		}

		$value = $permcheck ? isset($vbulletin->GPC['options']["$key"]) : false;

		$groupdm->set_bitfield('options', $key, $value);
	}

	($hook = vBulletinHook::fetch_hook('group_docreate')) ? eval($hook) : false;

	$groupid = $groupdm->save();

	$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $groupid;

	eval(print_standard_redirect('successfully_created_group'));
}

// #######################################################################
if ($_POST['do'] == 'doedit')
{
	$vbulletin->input->clean_array_gpc('p', array(
 		'groupname'        => TYPE_NOHTML,
 		'groupdescription' => TYPE_NOHTML,
 		'grouptype'        => TYPE_STR,
 		'options'          => TYPE_ARRAY_BOOL,
	));

	if (!can_edit_group($group))
	{
		print_no_permission();
	}

	$groupdm = datamanager_init('SocialGroup', $vbulletin, ERRTYPE_STANDARD);

	$groupdm->set_existing($group);
	if (can_moderate(0, 'caneditsocialgroups') OR $group['members'] == 1)
	{
		$groupdm->set('name', $vbulletin->GPC['groupname']);
	}
	$groupdm->set('description', $vbulletin->GPC['groupdescription']);
	$groupdm->set('type', $vbulletin->GPC['grouptype']);

	foreach (array_keys($vbulletin->bf_misc_socialgroupoptions) AS $key)
	{
		switch ($key)
		{
			case 'owner_mod_queue':
			{
				$permcheck = (
					$vbulletin->options['sg_allow_owner_mod_queue']
					AND !$vbulletin->options['social_moderation']
					AND $groupowner['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
					AND $vbulletin->options['socnet_groups_msg_enabled']
				) ? true : false;
			}
			break;

			case 'join_to_view':
			{
				$permcheck = (
					$vbulletin->options['sg_allow_join_to_view']
					AND (
						$vbulletin->options['socnet_groups_msg_enabled']
						OR $vbulletin->options['socnet_groups_albums_enabled']
					)
				) ? true : false;
			}
			break;

			case 'enable_group_messages':
			{
				$permcheck = $vbulletin->options['socnet_groups_msg_enabled'] ? true : false;
			}
			break;

			case 'enable_group_albums':
			{
				$permcheck = $vbulletin->options['socnet_groups_albums_enabled'] ? true : false;
			}
			break;

			default:
			{
				$permcheck = false;
			}
		}

		$value = $permcheck ? isset($vbulletin->GPC['options']["$key"]) : false;

		$groupdm->set_bitfield('options', $key, $value);
	}

	($hook = vBulletinHook::fetch_hook('group_doedit')) ? eval($hook) : false;

	$groupdm->save();

	if (!$group['is_owner'] AND can_moderate(0, 'caneditsocialgroups'))
	{
		require_once(DIR . '/includes/functions_log_error.php');
		log_moderator_action($group, 'social_group_x_edited',
			array($group['name'])
		);
	}

	eval(print_standard_redirect('successfully_edited_group'));
}

// #######################################################################
if ($_REQUEST['do'] == 'view')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'     => TYPE_UINT,
		'pagenumber'  => TYPE_UINT,
		'showignored' => TYPE_BOOL,
	));

	$show['quickcomment'] = $show['groupmessages'] = ($vbulletin->options['socnet_groups_msg_enabled'] AND $group['canviewcontent'] AND ($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_messages']));

	$groupmemberids = $vbulletin->db->query_read("
		SELECT userfield.*, usertextfield.*, user.*, UNIX_TIMESTAMP(passworddate) AS passworddate,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid
			" . ($vbulletin->options['avatarenabled'] ? ', avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width_thumb AS avwidth_thumb, customavatar.height_thumb AS avheight_thumb, customavatar.width as avwidth, customavatar.height as avheight, customavatar.filedata_thumb' : '') .
			', customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight' .
			", user.icq AS icq, user.aim AS aim, user.yahoo AS yahoo, user.msn AS msn, user.skype AS skype
		FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = socialgroupmember.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (user.userid = userfield.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) " : '') .
		"LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid) " .
		"WHERE socialgroupmember.groupid = " . $group['groupid'] . " AND socialgroupmember.type = 'member'
		ORDER BY user.lastactivity DESC
		LIMIT 10
	");

	$members_shown = $vbulletin->db->num_rows($groupmemberids);

	while ($groupmember = $vbulletin->db->fetch_array($groupmemberids))
	{
		$width = 0;
		$height = 0;

		fetch_avatar_from_userinfo($groupmember, true);
		fetch_musername($groupmember);
		$user =& $groupmember;

		($hook = vBulletinHook::fetch_hook('group_memberbit')) ? eval($hook) : false;

		eval('$short_member_list_bits .= "' . fetch_template('memberinfo_tiny') .'";');
	}

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'' => $group['name'],
	);

	$show['groupoptions'] = false;

	$grouptypephrase = $vbphrase['group_desc_' . $group['type']];

	foreach(array('join', 'leave', 'edit', 'delete', 'manage', 'managemembers') AS $groupoption)
	{
		switch ($groupoption)
		{
			case 'join':
			{
				$allowedtojoin = can_join_group($group);
				$groupoptions['join'] = $allowedtojoin ? 'alt1' : '';
				$show['groupoptions'] = $allowedtojoin ? true : $show['groupoptions'];
			}
			break;
			case 'leave':
			{
				$allowedtoleave = can_leave_group($group);

				if ($allowedtoleave)
				{
					switch ($group['membertype'])
					{
						case 'member':
						{
							$leavephrase = $vbphrase['leave_social_group'];
						}
						break;

						case 'invited':
						{
							$leavephrase = $vbphrase['decline_join_invitation'];
						}
						break;

						case 'moderated':
						{
							$leavephrase = $vbphrase['cancel_join_request'];
						}
						break;
					}
				}

				$groupoptions['leave'] = $allowedtoleave ? 'alt1' : '';
				$show['groupoptions'] = $allowedtoleave ? true : $show['groupoptions'];
			}
			break;

			case 'edit':
			{
				$allowedtoedit = can_edit_group($group);
				$groupoptions['edit'] = $allowedtoedit ? 'alt1' : '';
				$show['groupoptions'] = $allowedtoedit ? true : $show['groupoptions'];
			}
			break;

			case 'delete':
			{
				$allowedtodelete = can_delete_group($group);
				$groupoptions['delete'] = $allowedtodelete ? 'alt1' : '';
				$show['groupoptions'] = $allowedtodelete ? true : $show['groupoptions'];
			}
			break;

			case 'manage':
			{
				$allowedtomanage = fetch_socialgroup_modperm('caninvitemoderatemembers', $group);
				$groupoptions['manage'] = $allowedtomanage ? 'alt1' : '';
				$show['groupoptions'] = $allowedtomanage ? true : $show['groupoptions'];
			}
			break;

			case 'managemembers':
			{
				$allowedtomanagemembers = fetch_socialgroup_modperm('canmanagemembers', $group);
				$groupoptions['managemembers'] = $allowedtomanagemembers ? 'alt1' : '';
				$show['groupoptions'] = $allowedtomanagemembers ? true : $show['groupoptions'];
			}
			break;

		}
	}

	($hook = vBulletinHook::fetch_hook('group_view_message_start')) ? eval($hook) : false;

	if ($show['groupmessages'])
	{
		require_once(DIR . '/includes/class_bbcode.php');
		require_once(DIR . '/includes/class_groupmessage.php');

		$show['auto_moderation'] = (
			(
				$vbulletin->options['social_moderation']
				OR
				$group['is_automoderated']
				OR
				!($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['followforummoderation'])
			)
			AND !fetch_socialgroup_modperm('canmoderategroupmessages', $group)
		);

		$state = array('visible');
		$state_or = array();
		if (fetch_socialgroup_modperm('canmoderategroupmessages', $group))
		{
			$state[] = 'moderation';
		}
		else if ($vbulletin->userinfo['userid'])
		{
			$state_or[] = "(gm.postuserid = " . $vbulletin->userinfo['userid'] . " AND state = 'moderation')";
		}

		if (fetch_socialgroup_modperm('canviewdeleted', $group))
		{
			$state[] = 'deleted';
			$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (gm.gmid = deletionlog.primaryid AND deletionlog.type = 'groupmessage')";
		}
		else
		{
			$deljoinsql = '';
		}

		$state_or[] = "gm.state IN ('" . implode("','", $state) . "')";

		$perpage = (!$vbulletin->GPC['perpage'] OR $vbulletin->GPC['perpage'] > $vbulletin->options['vm_maxperpage']) ? $vbulletin->options['vm_perpage'] : $vbulletin->GPC['perpage'];

		if ($messageinfo['gmid'])
		{
			$getpagenum = $db->query_first("
				SELECT COUNT(*) AS comments
				FROM " . TABLE_PREFIX . "groupmessage AS gm
				WHERE groupid = $group[groupid]
					AND (" . implode(" OR ", $state_or) . ")
					AND dateline >= $messageinfo[dateline]
			");
			$vbulletin->GPC['pagenumber'] = ceil($getpagenum['comments'] / $perpage);
		}
		$pagenumber = $vbulletin->GPC['pagenumber'];

		do
		{
			if (!$pagenumber)
			{
				$pagenumber = 1;
			}
			$start = ($pagenumber - 1) * $perpage;

			$messagebits = '';

			$hook_query_fields = $hook_query_joins = $hook_query_where = '';
			($hook = vBulletinHook::fetch_hook('group_view_message_query')) ? eval($hook) : false;

			$messages = $db->query_read("
				SELECT SQL_CALC_FOUND_ROWS
					gm.*, user.*, gm.ipaddress AS messageipaddress
					" . ($deljoinsql ? ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason" : "") . "
					" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight, customavatar.width_thumb AS avwidth_thumb, customavatar.height_thumb AS avheight_thumb, filedata_thumb, NOT ISNULL(customavatar.userid) AS hascustom" : "") . "
					$hook_query_fields
				FROM " . TABLE_PREFIX . "groupmessage AS gm
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (gm.postuserid = user.userid)
				" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
				$deljoinsql
				$hook_query_joins
				WHERE gm.groupid = $group[groupid]
					AND (" . implode(" OR ", $state_or) . ")
					$hook_query_where
				ORDER BY gm.dateline DESC
				LIMIT $start, $perpage
			");

			list($messagetotal) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);
			if ($start >= $messagetotal)
			{
				$pagenumber = ceil($messagetotal / $perpage);
			}
		}
		while ($start >= $messagetotal AND $messagetotal);

		$messagestart = $start + 1;
		$messageend = min($start + $perpage, $messagetotal);

		$bbcode =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
		$factory =& new vB_Group_MessageFactory($vbulletin, $bbcode, $group);

		$messagebits = '';

		if ($vbulletin->userinfo['userid'] AND !$vbulletin->GPC['showignored'])
		{
			$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$ignorelist = array();
		}

		$firstrecord = array();
		while ($message = $db->fetch_array($messages))
		{
			if (!$firstrecord)
			{
				$firstrecord = $message;
			}

			if ($ignorelist AND in_array($message['postuserid'], $ignorelist))
			{
				$message['ignored'] = true;
			}

			$response_handler =& $factory->create($message);
			$response_handler->cachable = false;
			$messagebits .= $response_handler->construct();

			$lastcomment = !$lastcomment ? $message['dateline'] : $lastcomment;
		}

		$show['approve'] = fetch_socialgroup_modperm('canmoderategroupmessages', $group);
		$show['delete'] = (fetch_socialgroup_modperm('canremovegroupmessages') OR fetch_socialgroup_modperm('candeletegroupmessages', $group));
		$show['undelete'] = fetch_socialgroup_modperm('canundeletegroupmessages', $group);
		$show['inlinemod'] = ($show['approve'] OR $show['delete'] OR $show['undelete']);

		// Only allow AJAX QC on the first page
		$show['quickcomment']  = ($vbulletin->userinfo['userid'] AND ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canpostnonmembergroup'] OR $group['membertype'] == 'member'));
		$show['allow_ajax_qc'] = ($pagenumber == 1 AND $messagetotal) ? 1 : 0;

		if ($show['quickcomment'])
		{
			require_once(DIR . '/includes/functions_editor.php');

			$stylevar['messagewidth'] = $stylevar['messagewidth_usercp'];
			$editorid = construct_edit_toolbar(
				'',
				false,
				'groupmessage',
				$vbulletin->options['allowsmilies'],
				true,
				false,
				'qr_small'
			);
		}
	}

	// Only allow AJAX QC on the first page
	$show['quickcomment']  = ($vbulletin->userinfo['userid'] AND ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canpostnonmembergroup'] OR $group['membertype'] == 'member'));
	$show['allow_ajax_qc'] = ($pagenumber == 1) ? 1 : 0;

	$pagenavbits = array(
		"groupid=$group[groupid]"
	);
	if ($perpage != $vbulletin->GPC['vm_perpage'])
	{
		$pagenavbits[] = "pp=$perpage";
	}
	if ($vbulletin->GPC['showignored'])
	{
		$pagenavbits[] = 'showignored=1';
	}
	$pagenavurl = 'group.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavbits);
	$pagenav = construct_page_nav($pagenumber, $perpage, $messagetotal, $pagenavurl);

	// find recent pictures
	$show['pictures_block'] = (
		$group['canviewcontent']
		AND $group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_albums']
		AND $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum']
		AND (
			$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']
			AND $vbulletin->options['socnet_groups_albums_enabled']
		)
	);

	($hook = vBulletinHook::fetch_hook('group_view_pictures_start')) ? eval($hook) : false;

	if ($show['pictures_block'])
	{
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('group_pictures_query')) ? eval($hook) : false;

		$pictures_sql = $db->query_read("
			SELECT picture.pictureid, picture.userid, picture.caption, picture.extension, picture.filesize, picture.idhash,
				picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height,
				socialgrouppicture.dateline, user.username
				$hook_query_fields
			FROM " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture
			INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = socialgrouppicture.pictureid)
			INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
				(socialgroupmember.userid = picture.userid AND socialgroupmember.groupid = $group[groupid] AND socialgroupmember.type = 'member')
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = picture.userid)
			$hook_query_joins
			WHERE socialgrouppicture.groupid = $group[groupid]
				$hook_query_where
			ORDER BY socialgrouppicture.dateline DESC
			LIMIT 5
		");

		// work out the effective picturebit height/width including any borders and paddings; the +4 works around an IE float issue
		$picturebit_height = $vbulletin->options['album_thumbsize'] + (($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding']) * 2 + 4;
		$picturebit_width = $vbulletin->options['album_thumbsize'] + (($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding']) * 2;

		$pictures_shown = vb_number_format($db->num_rows($pictures_sql));
		$picturebits = '';
		while ($picture = $db->fetch_array($pictures_sql))
		{
			$picture = prepare_pictureinfo_thumb($picture, $group);

			($hook = vBulletinHook::fetch_hook('group_picturebit')) ? eval($hook) : false;

			eval('$picturebits .= "' . fetch_template('socialgroups_picturebit') . '";');
		}

		$show['add_pictures_link'] = ($group['membertype'] == 'member' AND $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']);
		$show['pictures_block'] = ($show['add_pictures_link'] OR $picturebits OR $group['picturecount']);
	}

	$vbphrase['delete_messages_js'] = addslashes_js($vbphrase['delete_messages']);
	$vbphrase['undelete_messages_js'] = addslashes_js($vbphrase['undelete_messages']);
	$vbphrase['approve_messages_js'] = addslashes_js($vbphrase['approve_messages']);
	$vbphrase['unapprove_messages_js'] = addslashes_js($vbphrase['unapprove_messages']);

	$ownerlink = 'member.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $group['creatoruserid'];

	eval('$socialgroups_css = "' . fetch_template('socialgroups_css') . '";');
	$templatename = 'socialgroups_group';
}

// #######################################################################
if ($_REQUEST['do'] == 'viewmembers')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
	));

	$perpage = $vbulletin->GPC['perpage'];
	$pagenumber = $vbulletin->GPC['pagenumber'];
	$totalmembers = $group['members'];

	sanitize_pageresults($totalmembers, $pagenumber, $perpage);

	$groupmembers = $vbulletin->db->query_read("
		SELECT userfield.*, usertextfield.*, user.*, UNIX_TIMESTAMP(passworddate) AS passworddate,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, (user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ") AS invisible,
			" . ($vbulletin->options['avatarenabled'] ? 'avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width AS avwidth, customavatar.height AS avheight,' : '') . "
			customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight,
			user.icq AS icq, user.aim AS aim, user.yahoo AS yahoo, user.msn AS msn, user.skype AS skype
		FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = socialgroupmember.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (user.userid = userfield.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) " : '') . "
		LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid)
		WHERE socialgroupmember.groupid = " . $vbulletin->GPC['groupid'] . " AND socialgroupmember.type = 'member'
		ORDER BY user.username
		LIMIT " . (($pagenumber - 1) * $perpage) . ", $perpage
	");

	require_once(DIR . '/includes/functions_bigthree.php');

	while ($groupmember = $vbulletin->db->fetch_array($groupmembers))
	{
		$width = 0;
		$height = 0;

		$alt = exec_switch_bg();

		fetch_avatar_from_userinfo($groupmember, true);
		fetch_musername($groupmember);
		$user =& $groupmember;

		fetch_online_status($user, true);
		construct_im_icons($user, true);

		($hook = vBulletinHook::fetch_hook('group_memberbit')) ? eval($hook) : false;

		eval('$member_list .= "' . fetch_template('memberinfo_small') .'";');
	}

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?'. $vbulletin->session->vars['sessionurl'] .'groupid=' . $group['groupid'] => $group['name'],
		'' => $vbphrase['member_list']
	);
	$custompagetitle =  $group['name'] . ' - ' . $vbphrase['member_list'];

	$pagenav = construct_page_nav($pagenumber, $perpage, $totalmembers,
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=viewmembers&amp;groupid=' . $group['groupid'] . ($perpage ? "&amp;pp=$perpage" : '')
	);

	eval('$memberinfo_css = "' . fetch_template('memberinfo_css') . '";');

	$templatename = 'socialgroups_memberlist';
}

// #######################################################################
if ($_REQUEST['do'] == 'search')
{
	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'' => $vbphrase['advanced_search']
	);
	$templatename = 'socialgroups_search';
}

// #######################################################################
if ($_REQUEST['do'] == 'message')
{

	if (!$vbulletin->options['socnet_groups_msg_enabled'])
	{
		print_no_permission();
	}

	if (empty($group))
	{
		standard_error(fetch_error('invalidid', $vbphrase['social_group'], $vbulletin->options['contactuslink']));
	}

	if ($messageinfo)
	{
		// Can we edit?
		if (!can_edit_group_message($messageinfo, $group))
		{
			print_no_permission();
		}
	}
	else
	{
		if (!($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canpostnonmembergroup']))
		{	// Are we a member of this group?
			if ($group['membertype'] != 'member')
			{
				print_no_permission();
			}
		}
	}

	if ($_POST['do'] == 'message')
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'message'          => TYPE_STR,
			'wysiwyg'          => TYPE_BOOL,
			'disablesmilies'   => TYPE_BOOL,
			'parseurl'         => TYPE_BOOL,
			'username'         => TYPE_STR,
			'ajax'             => TYPE_BOOL,
			'lastcomment'      => TYPE_UINT,
			'humanverify'      => TYPE_ARRAY,
			'loggedinuser'     => TYPE_UINT,
			'fromquickcomment' => TYPE_BOOL,
			'preview'          => TYPE_STR,
		));

		($hook = vBulletinHook::fetch_hook('group_message_post_start')) ? eval($hook) : false;

		// unwysiwygify the incoming data
		if ($vbulletin->GPC['wysiwyg'])
		{
			require_once(DIR . '/includes/functions_wysiwyg.php');
			$vbulletin->GPC['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'],  $vbulletin->options['allowhtml']);
		}

		// parse URLs in message text
		if ($vbulletin->options['allowbbcode'] AND $vbulletin->GPC['parseurl'])
		{
			require_once(DIR . '/includes/functions_newpost.php');
			$vbulletin->GPC['message'] = convert_url_to_bbcode($vbulletin->GPC['message']);
		}

		$message = array(
			'message'        =>& $vbulletin->GPC['message'],
			'groupid'        =>& $group['groupid'],
			'postuserid'     =>& $vbulletin->userinfo['userid'],
			'disablesmilies' =>& $vbulletin->GPC['disablesmilies'],
			'parseurl'       =>& $vbulletin->GPC['parseurl'],
		);

		if ($vbulletin->GPC['ajax'])
		{
			$message['message'] = convert_urlencoded_unicode($message['message']);
		}

		$dataman =& datamanager_init('GroupMessage', $vbulletin, ERRTYPE_ARRAY);
		$dataman->set_info('group', $group);

		if ($messageinfo)
		{	// existing message
			$show['edit'] = true;
			$dataman->set_existing($messageinfo);
		}
		else
		{
			// New message
			if (
				(
					$vbulletin->options['social_moderation']
					OR
					$group['is_automoderated']
					OR
					!($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['followforummoderation'])
				)
				AND !fetch_socialgroup_modperm('canmoderategroupmessages', $group)
			)
			{
				$dataman->set('state', 'moderation');
			}
			if ($vbulletin->userinfo['userid'] == 0)
			{
				$dataman->setr('username', $vbulletin->GPC['username']);
			}
			$dataman->setr('groupid', $message['groupid']);
			$dataman->setr('postuserid', $message['postuserid']);
		}

		$dataman->set_info('preview', $vbulletin->GPC['preview']);
		$dataman->setr('pagetext', $message['message']);
		$dataman->set('allowsmilie', !$message['disablesmilies']);

		//todo: Notification to group owner and members about new posts?

		$dataman->pre_save();

		if ($vbulletin->GPC['fromquickcomment'] AND $vbulletin->GPC['preview'])
		{
			$dataman->errors = array();
		}

		// Visitor Messages and Group Messages share the same restrictive bbcode set because of this...
		require_once(DIR . '/includes/class_socialmessageparser.php');
		$pmparser =& new vB_GroupMessageParser($vbulletin, fetch_tag_list());
		$pmparser->parse($message['message']);
		if ($error_num = count($pmparser->errors))
		{
			foreach ($pmparser->errors AS $tag => $error_phrase)
			{
				$dataman->errors[] = fetch_error($error_phrase, $tag);
			}
		}

		if (!empty($dataman->errors))
		{
			if ($vbulletin->GPC['ajax'])
			{
				require_once(DIR . '/includes/class_xml.php');
				$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
				$xml->add_group('errors');
				foreach ($dataman->errors AS $error)
				{
					$xml->add_tag('error', $error);
				}
				$xml->close_group();
				$xml->print_xml();
			}
			else
			{
				define('MESSAGEPREVIEW', true);
				require_once(DIR . '/includes/functions_newpost.php');
				$preview = construct_errors($dataman->errors);
				$_GET['do'] = 'message';
			}
		}
		else if ($vbulletin->GPC['preview'])
		{
			define('MESSAGEPREVIEW', true);
			$preview = process_group_message_preview($message);
			$_GET['do'] = 'message';
		}
		else
		{
			$gmid = $dataman->save();

			if ($messageinfo)
			{
				$gmid = $messageinfo['gmid'];
			}

			if ($messageinfo AND !$group['is_owner'] AND can_moderate(0, 'caneditgroupmessages'))
			{
				require_once(DIR . '/includes/functions_log_error.php');
				log_moderator_action($messageinfo, 'gm_by_x_for_y_edited',
					array($messageinfo['postusername'], $group['name'])
				);
			}

			if ($vbulletin->GPC['ajax'])
			{
				require_once(DIR . '/includes/class_xml.php');
				$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
				$xml->add_group('commentbits');

				$state = array('visible');
				if (fetch_socialgroup_modperm('canmoderategroupmessages', $group))
				{
					$state[] = 'moderation';
				}

				if (fetch_socialgroup_modperm('canviewdeleted', $group))
				{
					$state[] = 'deleted';
					$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (gm.gmid = deletionlog.primaryid AND deletionlog.type = 'gmid')";
				}
				else
				{
					$deljoinsql = '';
				}

				$state_or = array(
					"gm.state IN ('" . implode("','", $state) . "')"
				);
				// Get the viewing user's moderated posts
				if ($vbulletin->userinfo['userid'] AND !fetch_socialgroup_modperm('canmoderategroupmessages', $group))
				{
					$state_or[] = "(gm.postuserid = " . $vbulletin->userinfo['userid'] . " AND state = 'moderation')";
				}

				require_once(DIR . '/includes/class_bbcode.php');
				require_once(DIR . '/includes/class_groupmessage.php');

				$bbcode =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
				$factory =& new vB_Group_MessageFactory($vbulletin, $bbcode, $group);

				$hook_query_fields = $hook_query_joins = $hook_query_where = '';
				($hook = vBulletinHook::fetch_hook('group_message_post_ajax')) ? eval($hook) : false;

				$messages = $db->query_read_slave("
					SELECT
						gm.*, user.*, gm.ipaddress AS messageipaddress
						" . ($deljoinsql ? ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason" : "") . "
						" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight, customavatar.width_thumb AS avwidth_thumb, customavatar.height_thumb AS avheight_thumb, customavatar.filedata_thumb" : "") . "
						$hook_query_fields
					FROM " . TABLE_PREFIX . "groupmessage AS gm
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (gm.postuserid = user.userid)
					" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
					$deljoinsql
					$hook_query_joins
					WHERE gm.groupid = $group[groupid]
						AND (" . implode(" OR ", $state_or) . ")
						AND " . (($lastviewed = $vbulletin->GPC['lastcomment']) ?
							"(gm.dateline > $lastviewed OR gm.gmid = $gmid)" :
							"gm.gmid = $gmid"
							) . "
						$hook_query_where
					ORDER BY gm.dateline ASC
				");
				while ($message = $db->fetch_array($messages))
				{
					$response_handler =& $factory->create($message);
					// Shall we pre parse these?
					$response_handler->cachable = false;

					$xml->add_tag('message', process_replacement_vars($response_handler->construct()), array(
						'gmid'              => $message['gmid'],
						'visible'           => ($message['state'] == 'visible') ? 1 : 0,
						'bgclass'           => $bgclass,
					));
				}

				$xml->add_tag('time', TIMENOW);
				$xml->close_group();
				$xml->print_xml(true);
			}
			else
			{
				($hook = vBulletinHook::fetch_hook('group_message_post_complete')) ? eval($hook) : false;
				$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . "groupid=$group[groupid]&gmid=$gmid#gmessage$gmid";
				eval(print_standard_redirect('visitormessagethanks', true, true));
			}
		}
	}

	if ($_GET['do'] == 'message')
	{
		require_once(DIR . '/includes/functions_editor.php');

		($hook = vBulletinHook::fetch_hook('group_message_form_start')) ? eval($hook) : false;

		if (defined('MESSAGEPREVIEW'))
		{
			$postpreview =& $preview;
			$message['message'] = htmlspecialchars_uni($message['message']);

			require_once(DIR . '/includes/functions_newpost.php');
			construct_checkboxes($message);
		}
		else if ($messageinfo)
		{
			require_once(DIR . '/includes/functions_newpost.php');
			construct_checkboxes(
				array(
					'disablesmilies' => (!$messageinfo['allowsmilie']),
					'parseurl'       => 1,
				)
			);
			$message['message'] = htmlspecialchars_uni($messageinfo['pagetext']);
		}
		else
		{
			$message['message'] = '';
		}

		$navbits['group.php?' . $vbulletin->session->vars['sessionurl'] . "groupid=$group[groupid]"] = $group['name'];
		if ($messageinfo)
		{
			$show['edit'] = true;
			$show['delete'] = (
				fetch_socialgroup_modperm('candeletegroupmessages', $group)
				OR fetch_socialgroup_modperm('canremovegroupmessages')
				OR (
					$messageinfo['postuserid'] == $vbulletin->userinfo['userid']
					AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanagemessages']
				)
			);
			$navbits[] = $vbphrase['edit_group_message'];
		}
		else
		{
			$navbits[] = $vbphrase['post_new_group_message'];
		}

		$istyles_js = construct_editor_styles_js();
		$editorid = construct_edit_toolbar(
			$message['message'],
			false,
			'groupmessage',
			$vbulletin->options['allowsmilies'],
			true,
			false
		);

		eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

		// auto-parse URL
		if (!isset($checked['parseurl']))
		{
			$checked['parseurl'] = 'checked="checked"';
		}
		$show['parseurl'] = $vbulletin->options['allowbbcode'];
		$show['misc_options'] = ($show['parseurl'] OR !empty($disablesmiliesoption));
		$show['additional_options'] = ($show['misc_options'] OR !empty($attachmentoption));
		$show['physicaldeleteoption'] = can_moderate(0, 'canremovegroupmessages');

		$navbits = construct_navbits($navbits);
		eval('$navbar = "' . fetch_template('navbar') . '";');

		($hook = vBulletinHook::fetch_hook('group_message_form_complete')) ? eval($hook) : false;

		// complete
		eval('print_output("' . fetch_template('socialgroups_editor') . '");');
	}
}

// #######################################################################
if ($_POST['do'] == 'deletemessage')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'deletemessage' => TYPE_STR,
		'reason'        => TYPE_STR,
	));

	if (
		(
			!fetch_socialgroup_modperm('candeletegroupmessages', $group)
			AND !fetch_socialgroup_modperm('canremovegroupmessages')
			AND (
				$messageinfo['state'] != 'visible'
				OR $messageinfo['postuserid'] != $vbulletin->userinfo['userid']
				OR !($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanagemessages'])
			)
		)
		OR (
			$messageinfo['state'] == 'deleted'
			AND !fetch_socialgroup_modperm('candeletegroupmessages', $group)
		)
		OR (
			$messageinfo['state'] == 'moderation'
			AND !fetch_socialgroup_modperm('canmoderategroupmessages', $group)
		)
	)
	{
		print_no_permission();
	}

	if ($vbulletin->GPC['deletemessage'] != '')
	{
		if ($vbulletin->GPC['deletemessage'] == 'remove' AND can_moderate(0, 'canremovegroupmessages'))
		{
			$hard_delete = true;
		}
		else
		{
			$hard_delete = false;
		}

		$dataman =& datamanager_init('GroupMessage', $vbulletin, ERRTYPE_STANDARD);
		$dataman->set_existing($messageinfo);
		$dataman->set_info('hard_delete', $hard_delete);
		$dataman->set_info('reason', $vbulletin->GPC['reason']);

		($hook = vBulletinHook::fetch_hook('group_message_delete')) ? eval($hook) : false;

		$dataman->delete();
		unset($dataman);

		if (!$group['is_owner'] AND can_moderate(0, 'candeletegroupmessages'))
		{
			require_once(DIR . '/includes/functions_log_error.php');
			log_moderator_action($messageinfo,
				($hard_delete ? 'gm_by_x_for_y_removed' : 'gm_by_x_for_y_soft_deleted'),
				array($messageinfo['postusername'], $group['name'])
			);
		}

		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . "groupid=$group[groupid]";
		eval(print_standard_redirect('redirect_groupmessagedelete'));
	}
	else
	{
		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . "groupid=$group[groupid]";
		eval(print_standard_redirect('redirect_groupmessage_nodelete'));
	}
}

// ############################### start retrieve ip ###############################
if ($_REQUEST['do'] == 'viewip')
{
	// check moderator permissions for getting ip
	if (!can_moderate(0, 'canviewips'))
	{
		print_no_permission();
	}

	if (!$messageinfo['gmid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink'])));
	}

	$messageinfo['hostaddress'] = @gethostbyaddr(long2ip($messageinfo['ipaddress']));

	($hook = vBulletinHook::fetch_hook('group_message_getip')) ? eval($hook) : false;

	eval(standard_error(fetch_error('thread_displayip', long2ip($messageinfo['ipaddress']), htmlspecialchars_uni($messageinfo['hostaddress'])), '', 0));
}

// ############################### start report ###############################
if ($_REQUEST['do'] == 'report' OR $_POST['do'] == 'sendemail')
{
	require_once(DIR . '/includes/class_reportitem.php');

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
	$reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);

	if (!$reportthread AND !$reportemail)
	{
		eval(standard_error(fetch_error('emaildisabled')));
	}

	$reportobj =& new vB_ReportItem_GroupMessage($vbulletin);
	$reportobj->set_extrainfo('group', $group);
	$perform_floodcheck = $reportobj->need_floodcheck();

	if ($perform_floodcheck)
	{
		$reportobj->perform_floodcheck_precommit();
	}

	if (!$messageinfo['gmid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('report_start')) ? eval($hook) : false;

	if ($_REQUEST['do'] == 'report')
	{
		// draw nav bar
		$navbits = array();
		$navbits['group.php?' . $vbulletin->session->vars['sessionurl'] . "groupid=$group[groupid]"] = $group['name'];
		$navbits[''] = $vbphrase['report_group_message'];
		$navbits = construct_navbits($navbits);

		require_once(DIR . '/includes/functions_editor.php');
		$textareacols = fetch_textarea_width();
		eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

		eval('$navbar = "' . fetch_template('navbar') . '";');
		$url =& $vbulletin->url;

		($hook = vBulletinHook::fetch_hook('report_form_start')) ? eval($hook) : false;

		$forminfo = $reportobj->set_forminfo($messageinfo);
		eval('print_output("' . fetch_template('reportitem') . '");');
	}

	if ($_POST['do'] == 'sendemail')
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'reason' => TYPE_STR,
		));

		if ($vbulletin->GPC['reason'] == '')
		{
			eval(standard_error(fetch_error('noreason')));
		}

		$reportobj->do_report($vbulletin->GPC['reason'], $messageinfo);

		$url =& $vbulletin->url;
		eval(print_standard_redirect('redirect_reportthanks'));
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'reportpicture' OR $_POST['do'] == 'sendpictureemail')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pictureid' => TYPE_UINT
	));

	require_once(DIR . '/includes/class_reportitem.php');

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	$reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
	$reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);

	if (!$reportthread AND !$reportemail)
	{
		eval(standard_error(fetch_error('emaildisabled')));
	}

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!($permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum'])
		OR !($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums']))
	{
		print_no_permission();
	}

	if ($group['membertype'] != 'member' AND !can_moderate(0, 'caneditalbumpicture'))
	{
		print_no_permission();
	}

	$pictureinfo = fetch_socialgroup_picture($vbulletin->GPC['pictureid'], $group['groupid']);
	if (!$pictureinfo)
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	$userinfo = fetch_userinfo($pictureinfo['userid']);

	$reportobj =& new vB_ReportItem_GroupPicture($vbulletin);
	$reportobj->set_extrainfo('user', $userinfo ? $userinfo : array());
	$reportobj->set_extrainfo('group', $group);

	$perform_floodcheck = $reportobj->need_floodcheck();

	if ($perform_floodcheck)
	{
		$reportobj->perform_floodcheck_precommit();
	}

	($hook = vBulletinHook::fetch_hook('report_start')) ? eval($hook) : false;

	if ($_REQUEST['do'] == 'reportpicture')
	{
		// draw nav bar
		$navbits = construct_navbits(array(
			'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
			'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'] => $group['name'],
			'' => $vbphrase['report_picture']
		));

		require_once(DIR . '/includes/functions_editor.php');
		$textareacols = fetch_textarea_width();
		eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

		eval('$navbar = "' . fetch_template('navbar') . '";');
		$url =& $vbulletin->url;

		($hook = vBulletinHook::fetch_hook('report_form_start')) ? eval($hook) : false;

		$forminfo = $reportobj->set_forminfo($pictureinfo);
		eval('print_output("' . fetch_template('reportitem') . '");');
	}

	if ($_POST['do'] == 'sendpictureemail')
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'reason' => TYPE_STR,
		));

		if ($vbulletin->GPC['reason'] == '')
		{
			eval(standard_error(fetch_error('noreason')));
		}

		$reportobj->do_report($vbulletin->GPC['reason'], $pictureinfo);

		$url =& $vbulletin->url;
		eval(print_standard_redirect('redirect_reportthanks'));
	}
}

// #######################################################################
if ($_POST['do'] == 'insertpictures' OR $_REQUEST['do'] == 'addpictures')
{
	if ($group['membertype'] != 'member')
	{
		standard_error(fetch_error('must_be_group_member'));
	}

	if (!($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_albums']))
	{
		print_no_permission();
	}

	if (!($permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum'])
		OR !($permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum'])
		OR !($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums'] AND $vbulletin->options['socnet_groups_albums_enabled']))
	{
		print_no_permission();
	}
}

// #######################################################################
if ($_POST['do'] == 'insertpictures')
{
	$vbulletin->input->clean_array_gpc('p', array(
 		'pictureurls'  => TYPE_STR
	));

	$pictureids = array();
	$invalidurls = array();

	foreach (preg_split('#\s+#', $vbulletin->GPC['pictureurls']) AS $pictureurl)
	{
		if (!trim($pictureurl))
		{
			continue;
		}

		if (preg_match('#pictureid=([0-9]+)#', $pictureurl, $match) AND $match[1])
		{
			$pictureids["$match[1]"] = $pictureurl;
		}
		else
		{
			$invalidurls[] = htmlspecialchars_uni($pictureurl);
		}
	}

	$pictureexists = array();
	$addpictures = array();

	if ($pictureids)
	{
		$addpicture_sql = array();

		$pictures_sql = $db->query_read("
			SELECT picture.pictureid, picture.userid, picture.caption, picture.extension, picture.filesize, picture.idhash,
				picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height,
				IF(socialgrouppicture.pictureid IS NULL, 0, 1) AS pictureexists, user.username
			FROM " . TABLE_PREFIX . "picture AS picture
			LEFT JOIN " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture ON
				(picture.pictureid = socialgrouppicture.pictureid AND socialgrouppicture.groupid = $group[groupid])
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = picture.userid)
			WHERE picture.pictureid IN (" . implode(',', array_keys($pictureids)) . ")
				AND picture.state = 'visible'
		");
		while ($picture = $db->fetch_array($pictures_sql))
		{
			$picture['dateline'] = TIMENOW;

			if ($picture['userid'] != $vbulletin->userinfo['userid'])
			{
				$invalidurls[] = htmlspecialchars_uni($pictureids["$picture[pictureid]"]);
			}
			else if ($picture['pictureexists'])
			{
				$pictureexists[] = $picture;
			}
			else
			{
				$addpicture_sql[] = "($group[groupid], $picture[pictureid], " . TIMENOW . ")";
				$addpictures[] = $picture;
			}

			($hook = vBulletinHook::fetch_hook('group_picture_insert')) ? eval($hook) : false;

			unset($pictureids["$picture[pictureid]"]);
		}

		if ($addpicture_sql)
		{
			$db->query_write("
				INSERT IGNORE " . TABLE_PREFIX . "socialgrouppicture
					(groupid, pictureid, dateline)
				VALUES
					" . implode(',', $addpicture_sql)
			);
		}
	}

	foreach ($pictureids AS $pictureid => $url)
	{
		$invalidurls[] = htmlspecialchars_uni($url);
	}

	if ($addpictures)
	{
		// rebuild the counter
		$groupdm =& datamanager_init('SocialGroup', $vbulletin, ERRTYPE_STANDARD);
		$groupdm->set_existing($group);
		$groupdm->rebuild_picturecount();
		$groupdm->save();
	}

	($hook = vBulletinHook::fetch_hook('group_picture_insert_rebuild')) ? eval($hook) : false;

	if ($addpictures AND !$pictureexists AND !$invalidurls)
	{
		// all successful, we can just take them to the picture list
		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=grouppictures&groupid=' . $group['groupid'];
		eval(print_standard_redirect('pictures_added'));
	}

	// show information to user about what happened
	$messagebits = '';
	if ($addpictures)
	{
		$picturebits = '';
		foreach ($addpictures AS $picture)
		{
			$picture = prepare_pictureinfo_thumb($picture, $group);
			eval('$picturebits .= "' . fetch_template('socialgroups_picturebit') . '";');
		}

		$message_text = $vbphrase['the_following_pictures_added_group_successfully'];

		eval('$messagebits .= "' . fetch_template('socialgroups_addpicture_messagebit') . '";');
	}

	if ($pictureexists)
	{
		$picturebits = '';
		foreach ($pictureexists AS $picture)
		{
			$picture = prepare_pictureinfo_thumb($picture, $group);
			eval('$picturebits .= "' . fetch_template('socialgroups_picturebit') . '";');
		}

		$message_text = $vbphrase['the_following_pictures_already_part_group'];

		eval('$messagebits .= "' . fetch_template('socialgroups_addpicture_messagebit') . '";');
	}

	if ($invalidurls)
	{
		$urlbits = '';
		foreach ($invalidurls AS $url)
		{
			$urlbits .= "<li>$url</li>";
		}

		eval('$messagebits .= "' . fetch_template('socialgroups_addpicture_invalidurl') . '";');
	}

	if ($messagebits)
	{
		eval('$messages = "' . fetch_template('socialgroups_addpicture_messages') . '";');
	}
	else
	{
		$messages = '';
	}

	define('FROM_INSERT', true);
	$_REQUEST['do'] = 'addpictures';

	($hook = vBulletinHook::fetch_hook('group_picture_insert_errors')) ? eval($hook) : false;
}

// #######################################################################
if ($_REQUEST['do'] == 'addpictures')
{
	if (!defined('FROM_INSERT'))
	{
		$messages = '';
	}

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'] => $group['name'],
		'' => $vbphrase['add_pictures_to_group']
	);

	$templatename = 'socialgroups_addpictures';
}

// #######################################################################
if ($_POST['do'] == 'doremovepicture' OR $_REQUEST['do'] == 'removepicture')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pictureid' => TYPE_UINT
	));

	if (!($permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum'])
		OR !($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums'] AND $vbulletin->options['socnet_groups_albums_enabled']))
	{
		print_no_permission();
	}

	$pictureinfo = fetch_socialgroup_picture($vbulletin->GPC['pictureid'], $group['groupid']);
	if (!$pictureinfo)
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	if ($pictureinfo['userid'] != $vbulletin->userinfo['userid'] AND !fetch_socialgroup_modperm('canremovepicture', $group))
	{
		print_no_permission();
	}
}

// #######################################################################
if ($_POST['do'] == 'doremovepicture')
{
	$vbulletin->input->clean_array_gpc('p', array(
 		'deny' => TYPE_NOHTML
	));

	// You either clicked no or you're a guest
	if (!empty($vbulletin->GPC['deny']))
	{
		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=grouppictures&amp;groupid=' . $group['groupid'];
		eval(print_standard_redirect('action_cancelled'));
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "socialgrouppicture
		WHERE groupid = $group[groupid]
			AND pictureid = $pictureinfo[pictureid]
	");

	$groupdm = datamanager_init('SocialGroup', $vbulletin);
	$groupdm->set_existing($group);
	$groupdm->rebuild_picturecount();
	$groupdm->save();

	if (!$group['is_owner'] AND $pictureinfo['userid'] != $vbulletin->userinfo['userid'] AND can_moderate(0, 'caneditalbumpicture'))
	{
		require_once(DIR . '/includes/functions_log_error.php');
		log_moderator_action($pictureinfo, 'social_group_picture_x_in_y_removed',
			array(fetch_trimmed_title($pictureinfo['caption'], 50), $group['name'])
		);
	}

	($hook = vBulletinHook::fetch_hook('group_picture_delete')) ? eval($hook) : false;

	if ($groupdm->fetch_field('picturecount'))
	{
		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=grouppictures&amp;groupid=' . $group['groupid'];
	}
	else
	{
		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'];
	}
	eval(print_standard_redirect('picture_removed_from_group'));
}

// #######################################################################
if ($_REQUEST['do'] == 'removepicture')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pictureid' => TYPE_UINT
	));

	$confirmdo = 'doremovepicture';
	$confirmaction = 'group.php?do=' . $confirmdo;
	$title_phrase = $vbphrase['remove_picture_from_group'];
	$question_phrase = construct_phrase($vbphrase['confirm_remove_picture_group_x'], $group['name']);

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'] => $group['name'],
		'' => $vbphrase['remove_picture_from_group']
	);

	$templatename = 'socialgroups_confirm';
}

// #######################################################################
if ($_REQUEST['do'] == 'grouppictures')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber' => TYPE_UINT
	));

	if (!($permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum'])
		OR !($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums'] AND $vbulletin->options['socnet_groups_albums_enabled']))
	{
		print_no_permission();
	}

	if (!($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_albums']))
	{
		print_no_permission();
	}

	if ($group['membertype'] != 'member' AND !can_moderate(0, 'caneditalbumpicture'))
	{
		if ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups'] AND can_join_group($group))
		{
			standard_error(fetch_error('must_be_group_member_view_add_pictures_join_x', 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=join&amp;groupid=' . $group['groupid']));
		}
		else
		{
			standard_error(fetch_error('must_be_group_member_view_add_pictures'));
		}
	}

	if ($vbulletin->GPC['pagenumber'] < 1)
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}

	$perpage = $vbulletin->options['album_pictures_perpage'];
	$total_pages = max(ceil($group['rawpicturecount'] / $perpage), 1); // 0 pictures still needs an empty page
	$pagenumber = ($vbulletin->GPC['pagenumber'] > $total_pages ? $total_pages : $vbulletin->GPC['pagenumber']);
	$start = ($pagenumber - 1) * $perpage;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('group_pictures_query')) ? eval($hook) : false;

	$pictures_sql = $db->query_read("
		SELECT picture.pictureid, picture.userid, picture.caption, picture.extension, picture.filesize, picture.idhash,
			picture.thumbnail_filesize, picture.thumbnail_dateline, picture.thumbnail_width, picture.thumbnail_height,
			socialgrouppicture.dateline, user.username
			$hook_query_fields
		FROM " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (picture.pictureid = socialgrouppicture.pictureid)
		INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
			(socialgroupmember.userid = picture.userid AND socialgroupmember.groupid = $group[groupid] AND socialgroupmember.type = 'member')
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = picture.userid)
		$hook_query_joins
		WHERE socialgrouppicture.groupid = $group[groupid]
			$hook_query_where
		ORDER BY socialgrouppicture.dateline DESC
		LIMIT $start, $perpage
	");

	// work out the effective picturebit height/width including any borders and paddings; the +4 works around an IE float issue
	$picturebit_height = $vbulletin->options['album_thumbsize'] + ($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding'] * 2 + 4;
	$picturebit_width = $vbulletin->options['album_thumbsize'] + ($usercss ? 0 : $stylevar['cellspacing']) + $stylevar['cellpadding'] * 2;

	$picturebits = '';
	while ($picture = $db->fetch_array($pictures_sql))
	{
		$picture = prepare_pictureinfo_thumb($picture, $group);

		($hook = vBulletinHook::fetch_hook('group_picturebit')) ? eval($hook) : false;

		eval('$picturebits .= "' . fetch_template('socialgroups_picturebit') . '";');
	}

	$pagenav = construct_page_nav($pagenumber, $perpage, $group['rawpicturecount'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . "do=grouppictures&amp;groupid=$group[groupid]", ''
	);

	$show['add_pictures_link'] = ($group['membertype'] == 'member' AND $permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']);

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'] => $group['name'],
		'' => $vbphrase['pictures']
	);

	$templatename = 'socialgroups_pictures';
}

// #######################################################################
if ($_REQUEST['do'] == 'picture')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pictureid'   => TYPE_UINT,
		'pagenumber'  => TYPE_UINT,
		'perpage'     => TYPE_UINT,
		'commentid'   => TYPE_UINT,
		'showignored' => TYPE_BOOL,
	));

	if (!$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!($permissions['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canviewalbum'])
		OR !($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_albums'] AND $vbulletin->options['socnet_groups_albums_enabled']))
	{
		print_no_permission();
	}

	if (!($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_albums']))
	{
		print_no_permission();
	}

	if ($group['membertype'] != 'member' AND !can_moderate(0, 'caneditalbumpicture'))
	{
		if ($vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups'] AND can_join_group($group))
		{
			standard_error(fetch_error('must_be_group_member_view_add_pictures_join_x', 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=join&amp;groupid=' . $group['groupid']));
		}
		else
		{
			standard_error(fetch_error('must_be_group_member_view_add_pictures'));
		}
	}

	$pictureinfo = fetch_socialgroup_picture($vbulletin->GPC['pictureid'], $group['groupid']);
	if (!$pictureinfo)
	{
		standard_error(fetch_error('invalidid', $vbphrase['picture'], $vbulletin->options['contactuslink']));
	}

	$pictureinfo['adddate'] = vbdate($vbulletin->options['dateformat'], $pictureinfo['dateline'], true);
	$pictureinfo['addtime'] = vbdate($vbulletin->options['timeformat'], $pictureinfo['dateline']);
	$pictureinfo['caption_html'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($pictureinfo['caption'])));

	$navpictures_sql = $db->query_read_slave("
		SELECT socialgrouppicture.pictureid
		FROM " . TABLE_PREFIX . "socialgrouppicture AS socialgrouppicture
		INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (socialgrouppicture.pictureid = picture.pictureid)
		INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
			(socialgroupmember.userid = picture.userid AND socialgroupmember.groupid = $group[groupid] AND socialgroupmember.type = 'member')
		WHERE socialgrouppicture.groupid = $group[groupid]
		ORDER BY socialgrouppicture.dateline DESC
	");
	$pic_location = fetch_picture_location_info($navpictures_sql, $pictureinfo['pictureid']);

	($hook = vBulletinHook::fetch_hook('group_picture')) ? eval($hook) : false;

	$show['edit_picture_option'] = ($pictureinfo['userid'] == $vbulletin->userinfo['userid'] OR can_moderate(0, 'caneditalbumpicture'));
	$show['remove_picture_option'] = ($pictureinfo['userid'] == $vbulletin->userinfo['userid'] OR fetch_socialgroup_modperm('canremovepicture', $group));
	if ($show['edit_picture_option'])
	{
		// we need an album this picture is in to edit it
		$album = $db->query_first_slave("
			SELECT albumid
			FROM " . TABLE_PREFIX . "albumpicture
			WHERE pictureid = $pictureinfo[pictureid]
			LIMIT 1
		");
	}

	$show['reportlink'] = (
		$vbulletin->userinfo['userid']
		AND ($vbulletin->options['rpforumid'] OR
			($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']))
	);

	if ($vbulletin->options['pc_enabled'])
	{
		require_once(DIR . '/includes/functions_picturecomment.php');

		$pagenumber = $vbulletin->GPC['pagenumber'];
		$perpage = $vbulletin->GPC['perpage'];
		$picturecommentbits = fetch_picturecommentbits($pictureinfo, $messagestats, $pagenumber, $perpage, $vbulletin->GPC['commentid'], $vbulletin->GPC['showignored']);

		$pagenavbits = array(
			'do=picture',
			"groupid=$group[groupid]",
			"pictureid=$pictureinfo[pictureid]"
		);
		if ($perpage != $vbulletin->options['pc_perpage'])
		{
			$pagenavbits[] = "pp=$perpage";
		}
		if ($vbulletin->GPC['showignored'])
		{
			$pagenavbits[] = 'showignored=1';
		}

		$pagenav = construct_page_nav($pagenumber, $perpage, $messagestats['total'],
			'group.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavbits),
			''
		);

		$editorid = fetch_picturecomment_editor($pictureinfo, $pagenumber, $messagestats);
		if ($editorid)
		{
			eval('$picturecomment_form = "' . fetch_template('picturecomment_form') . '";');
		}
		else
		{
			$picturecomment_form = '';
		}

		$show['picturecomment_options'] = ($picturecomment_form OR $picturecommentbits);

		eval('$picturecomment_commentarea = "' . fetch_template('picturecomment_commentarea') . '";');
		eval('$picturecomment_css = "' . fetch_template('picturecomment_css') . '";');
	}
	else
	{
		$picturecomment_commentarea = '';
		$picturecomment_css = '';
	}

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'] => $group['name'],
		'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=grouppictures&amp;groupid=' . $group['groupid'] => $vbphrase['pictures'],
		'' => $vbphrase['view_picture']
	);

	$templatename = 'socialgroups_picture';
}

// #######################################################################
if ($_POST['do'] == 'sendinvite')
{
	if (!fetch_socialgroup_modperm('caninvitemoderatemembers', $group))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'username'	=> TYPE_NOHTML
	));

	if ($user = $vbulletin->db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "user
		WHERE username = '" . $vbulletin->db->escape_string($vbulletin->GPC['username']) . "'"
	))
	{
		cache_permissions($user);

		if ($currentmembership = $vbulletin->db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "socialgroupmember
			WHERE userid = " . $user['userid'] . " AND groupid = " . $group['groupid']
		))
		{
			if ($currentmembership['type'] == 'member')
			{
				$errormsg = $vbphrase['this_person_is_already_a_member_of_the_group'];
				$invite_username = $vbulletin->GPC['username'];
				$_REQUEST['do'] = 'manage';
			}
			else if (!($user['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups']) OR
			!($user['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']))
			{
				$errormsg = $vbphrase['this_user_is_not_allowed_to_join_groups'];
				$invite_username = $vbulletin->GPC['username'];
				$_REQUEST['do'] = 'manage';
			}
			else
			{
				$socialgroupmemberdm = datamanager_init('SocialGroupMember', $vbulletin);
				$socialgroupmemberdm->set_existing($currentmembership);
				$socialgroupmemberdm->set('type', 'invited');
				$socialgroupmemberdm->set('dateline', TIMENOW);
				$socialgroupmemberdm->save();
				unset($socialgroupmemberdm);

				if (!$group['is_owner'] AND can_moderate(0, 'candeletesocialgroups'))
				{
					require_once(DIR . '/includes/functions_log_error.php');
					log_moderator_action($group, 'social_group_x_members_managed',
						array($group['name'])
					);
				}

				$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=manage&amp;groupid=' . $group['groupid'];

				eval(print_standard_redirect('successfully_invited_user'));
			}
		}
		else
		{
			if (!($user['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups']) OR
			!($user['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']))
			{
				$errormsg = $vbphrase['this_user_is_not_allowed_to_join_groups'];
				$invite_username = $vbulletin->GPC['username'];
				$_REQUEST['do'] = 'manage';
			}
			else
			{
				$socialgroupmemberdm = datamanager_init('SocialGroupMember', $vbulletin);
				$socialgroupmemberdm->set('userid', $user['userid']);
				$socialgroupmemberdm->set('groupid', $group['groupid']);
				$socialgroupmemberdm->set('type', 'invited');
				$socialgroupmemberdm->set('dateline', TIMENOW);
				$socialgroupmemberdm->save();
				unset($socialgroupmemberdm);

				if (!$group['is_owner'] AND can_moderate(0, 'candeletesocialgroups'))
				{
					require_once(DIR . '/includes/functions_log_error.php');
					log_moderator_action($group, 'social_group_x_members_managed',
						array($group['name'])
					);
				}

				$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=manage&amp;groupid=' . $group['groupid'];

				eval(print_standard_redirect('successfully_invited_user'));
			}


		}
	}
	else
	{
		$errormsg = $vbphrase['user_does_not_exist'];
		$invite_username = $vbulletin->GPC['username'];
		$_REQUEST['do'] = 'manage';
	}
}

// ############# Permission checks for group management ##################

if ($_REQUEST['do'] == 'manage' OR $_POST['do'] == 'cancelinvites')
{
	if (!fetch_socialgroup_modperm('caninvitemoderatemembers', $group))
	{
		print_no_permission();
	}
}

if ($_REQUEST['do'] == 'managemembers' OR $_POST['do'] == 'kickmembers')
{
	if (!fetch_socialgroup_modperm('canmanagemembers', $group))
	{
		print_no_permission();
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'manage' OR $_REQUEST['do'] == 'managemembers')
{
	$members = $vbulletin->db->query_read("
		SELECT socialgroupmember.*, user.username FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = socialgroupmember.userid)
		WHERE groupid = $group[groupid]
			" . ($_REQUEST['do'] == 'managemembers' ? "AND type = 'member'" : "AND type <> 'member'")
	);

	$invitebits = '';
	$moderatebits = '';
	$memberbits = '';
	$i = 0;

	while ($user = $vbulletin->db->fetch_array($members))
	{
		($hook = vBulletinHook::fetch_hook('group_manage_memberbit')) ? eval($hook) : false;

		if ($user['type'] == 'invited')
		{
			$container = 'invitedlist';
			eval('$invitebits .= "' . fetch_template('socialgroups_managebit') . '";');
		}
		else if ($user['type'] == 'moderated')
		{
			$container = 'moderatedlist';
			eval('$moderatebits .= "' . fetch_template('socialgroups_managebit') . '";');
		}
		else if ($user['userid'] != $group['creatoruserid'])
		{
			eval('$memberbits .= "' .fetch_template('socialgroups_managebit') . '";');
		}
	}

	eval('$socialgroups_css = "' . fetch_template('socialgroups_css') . '";');

	$show['manage_members'] = ($_REQUEST['do'] == 'managemembers');

	$navbits = array(
		'group.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['social_groups'],
		'group.php?'. $vbulletin->session->vars['sessionurl'] .'groupid=' . $group['groupid'] => $group['name'],
		'' => $show['manage_members'] ? $vbphrase['manage_members'] : $vbphrase['pending_and_invited_members']
	);
	$custompagetitle =  $group['name'] . ' - ' . ($show['manage_members'] ? $vbphrase['manage_members'] : $vbphrase['pending_and_invited_members']);

	$templatename = ($_REQUEST['do'] == 'managemembers' ? 'socialgroups_managemembers' : 'socialgroups_manage');
}

// #######################################################################
if ($_POST['do'] == 'cancelinvites' OR $_POST['do'] == 'kickmembers')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'ids'	=> TYPE_ARRAY_KEYS_INT
	));

	if (sizeof($vbulletin->GPC['ids']) > 0)
	{
		$socialgroupmemberdm = datamanager_init('SocialGroupMember', $vbulletin);

		$vbulletin->GPC['ids'][] = 0;
		$ids = implode(', ', $vbulletin->GPC['ids']);

		$invites = $vbulletin->db->query_read("
			SELECT * FROM " . TABLE_PREFIX . "socialgroupmember
			WHERE groupid = " . $group['groupid'] . " AND userid IN($ids)" .
			($_POST['do'] == 'cancelinvites' ? " AND type = 'invited'" : '')
		);

		while ($invite = $vbulletin->db->fetch_array($invites))
		{
			($hook = vBulletinHook::fetch_hook('group_kickmember')) ? eval($hook) : false;

			if ($invite['userid'] != $group['creatoruserid'])
			{
				$socialgroupmemberdm->set_existing($invite);
				$socialgroupmemberdm->delete();
			}
		}

		unset ($socialgroupmemberdm);
	}

	if (!$group['is_owner'] AND can_moderate(0, 'candeletesocialgroups'))
	{
		require_once(DIR . '/includes/functions_log_error.php');
		log_moderator_action($group, 'social_group_x_members_managed',
			array($group['name'])
		);
	}

	if (($group['members'] - sizeof($ids) <= 1) AND $_REQUEST['do'] == 'kickmembers')
	{
		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $group['groupid'];
	}
	else
	{
		$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=manage' . ($_REQUEST['do'] == 'kickmembers' ? 'members' : '') . '&amp;groupid=' . $group['groupid'];
	}

	($hook = vBulletinHook::fetch_hook('group_kickmember_complete')) ? eval($hook) : false;

	$phrase = $_POST['do'] == 'cancelinvites' ? 'successfully_removed_invites' : 'successfully_kicked_members';
	eval(print_standard_redirect($phrase));
}

// #######################################################################
if ($_POST['do'] == 'pendingmembers')
{
	if (!fetch_socialgroup_modperm('caninvitemoderatemembers', $group))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'ids'	  => TYPE_ARRAY_KEYS_INT,
		'action'  => TYPE_STR
	));

	$socialgroupmemberdm = datamanager_init('SocialGroupMember', $vbulletin);

	$vbulletin->GPC['ids'][] = 0;

	$ids = implode(', ', $vbulletin->GPC['ids']);

	$members = $vbulletin->db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "socialgroupmember
		WHERE groupid = " . $group['groupid'] . " AND type = 'moderated' AND userid IN ($ids)
	");

	while ($member = $vbulletin->db->fetch_array($members))
	{
		$socialgroupmemberdm->set_existing($member);

		($hook = vBulletinHook::fetch_hook('group_pending_members')) ? eval($hook) : false;

		if ($vbulletin->GPC['action'] == 'deny')
		{
			$socialgroupmemberdm->delete();
		}
		else if ($vbulletin->GPC['action'] == 'accept')
		{
			$socialgroupmemberdm->set('type', 'member');
			$socialgroupmemberdm->save();
		}
	}
	$vbulletin->url = 'group.php?' . $vbulletin->session->vars['sessionurl'] . 'do=manage&amp;groupid=' . $group['groupid'];

	($hook = vBulletinHook::fetch_hook('group_pending_members_complete')) ? eval($hook) : false;

	eval(print_standard_redirect('successfully_managed_members'));
}




// #######################################################################
if ($templatename != '')
{
	($hook = vBulletinHook::fetch_hook('group_complete')) ? eval($hook) : false;

	// make navbar
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');
	$custompagetitle = empty($custompagetitle) ? $pagetitle : $custompagetitle;
	eval('print_output("' . fetch_template($templatename) . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>
