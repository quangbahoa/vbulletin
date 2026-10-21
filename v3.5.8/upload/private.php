<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('GET_EDIT_TEMPLATES', 'newpm,insertpm');
define('THIS_SCRIPT', 'private');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('posting', 'postbit', 'pm');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'banemail',
	'noavatarperms'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'PM',
	'USERCP_SHELL',
	'usercp_nav_folderbit'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'editfolders' => array(
		'pm_editfolders',
		'pm_editfolderbit',
	),
	'emptyfolder' => array(
		'pm_emptyfolder'
	),
	'showpm' => array(
		'pm_showpm',
		'postbit',
		'postbit_onlinestatus',
		'postbit_reputation',
		'bbcode_code',
		'bbcode_html',
		'bbcode_php',
		'bbcode_quote',
		'im_aim',
		'im_icq',
		'im_msn',
		'im_yahoo',
		'im_skype',
	),
	'newpm' => array(
		'pm_newpm',
	),
	'managepm' => array(
		'pm_movepm',
	),
	'trackpm' => array(
		'pm_trackpm',
		'pm_receipts',
		'pm_receiptsbit',
	),
	'messagelist' => array(
		'pm_messagelist',
		'pm_messagelist_periodgroup',
		'pm_messagelistbit',
		'pm_messagelistbit_user',
		'pm_messagelistbit_ignore',
	)
);
$actiontemplates['insertpm'] =& $actiontemplates['newpm'];

// ################## SETUP PROPER NO DO TEMPLATES #######################
if (empty($_REQUEST['do']))
{
	$temppmid = ($temppmid = intval($_REQUEST['pmid'])) < 0 ? 0 : $temppmid;

	if ($temppmid > 0)
	{
		$actiontemplates['none'] =& $actiontemplates['showpm'];
	}
	else
	{
		$actiontemplates['none'] =& $actiontemplates['messagelist'];
	}
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/functions_misc.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ###################### Start pm code parse #######################
function parse_pm_bbcode($bbcode, $smilies = true)
{
	global $vbulletin;

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
	return $bbcode_parser->parse($bbcode, 'privatemessage', $smilies);
}

// ###################### Start pm update counters #######################
// update the pm counters for $vbulletin->userinfo
function build_pm_counters()
{
	global $vbulletin;

	$pmcount = $vbulletin->db->query_first("
		SELECT
			COUNT(pmid) AS pmtotal,
			SUM(IF(messageread = 0 AND folderid = 0, 1, 0)) AS pmunread
		FROM " . TABLE_PREFIX . "pm AS pm
		WHERE pm.userid = " . $vbulletin->userinfo['userid'] . "
	");

	$pmcount['pmtotal'] = intval($pmcount['pmtotal']);
	$pmcount['pmunread'] = intval($pmcount['pmunread']);

	if ($vbulletin->userinfo['pmtotal'] != $pmcount['pmtotal'] OR $vbulletin->userinfo['pmunread'] != $pmcount['pmunread'])
	{
		// init user data manager
		$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
		$userdata->set_existing($vbulletin->userinfo);
		$userdata->set('pmtotal', $pmcount['pmtotal']);
		$userdata->set('pmunread', $pmcount['pmunread']);
		$userdata->save();
	}
}

// ############################### initialisation ###############################

if (!$vbulletin->options['enablepms'])
{
	eval(standard_error(fetch_error('pm_adminoff')));
}

// check permission to use private messaging
if ($permissions['pmquota'] < 1 OR !$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

//check if the user will receive PMs
if (!$vbulletin->userinfo['receivepm'])
{
	eval(standard_error(fetch_error('pm_turnedoff')));
}

// start navbar
$navbits = array(
	'usercp.php?' . $vbulletin->session->vars['sessionurl'] => $vbphrase['user_control_panel'],
	'private.php?' . $vbulletin->session->vars['sessionurl'] => $vbphrase['private_messages']
);

// select correct part of forumjump
$frmjmpsel['pm'] = 'class="fjsel" selected="selected"';
construct_forum_jump();

$onload = '';
$show['trackpm'] = $cantrackpm = $permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['cantrackpm'];

$vbulletin->input->clean_gpc('r', 'pmid', TYPE_UINT);


// ############################### default do value ###############################
if (empty($_REQUEST['do']))
{
	if (!$vbulletin->GPC['pmid'])
	{
		$_REQUEST['do'] = 'messagelist';
	}
	else
	{
		$_REQUEST['do'] = 'showpm';
	}
}

($hook = vBulletinHook::fetch_hook('private_start')) ? eval($hook) : false;

// ############################### start update folders ###############################
// update the user's custom pm folders
if ($_POST['do'] == 'updatefolders')
{
	$vbulletin->input->clean_gpc('p', 'folder', TYPE_ARRAY_NOHTML);

	if (!empty($vbulletin->GPC['folder']))
	{
		$oldpmfolders = unserialize($vbulletin->userinfo['pmfolders']);
		$pmfolders = array();
		$updatefolders = array();
		foreach ($vbulletin->GPC['folder'] AS $folderid => $foldername)
		{
			$folderid = intval($folderid);
			if ($foldername != '')
			{
				$pmfolders["$folderid"] = $foldername;
			}
			else if (isset($oldpmfolders["$folderid"]))
			{
				$updatefolders[] = $folderid;
			}
		}
		if (!empty($updatefolders))
		{
			$db->query_write("UPDATE " . TABLE_PREFIX . "pm SET folderid=0 WHERE userid=" . $vbulletin->userinfo['userid'] . " AND folderid IN(" . implode(', ', $updatefolders) . ")");
		}

		require_once(DIR . '/includes/functions_databuild.php');
		if (!empty($pmfolders))
		{
			natcasesort($pmfolders);
		}
		build_usertextfields('pmfolders', iif(empty($pmfolders), '', serialize($pmfolders)), $vbulletin->userinfo['userid']);
	}

	($hook = vBulletinHook::fetch_hook('private_updatefolders')) ? eval($hook) : false;

	$itemtype = $vbphrase['private_message'];
	$itemtypes = $vbphrase['private_messages'];
	eval(print_standard_redirect('foldersedited'));
}

// ############################### start empty folders ###############################
if ($_REQUEST['do'] == 'emptyfolder')
{
	$vbulletin->input->clean_gpc('r', 'folderid', TYPE_INT);

	$folderid = $vbulletin->GPC['folderid'];

	// generate navbar
	$navbits[''] = $vbphrase['confirm_deletion'];
	$pmfolders = array('0' => $vbphrase['inbox'], '-1' => $vbphrase['sent_items']);
	if (!empty($vbulletin->userinfo['pmfolders']))
	{
		$pmfolders = $pmfolders + unserialize($vbulletin->userinfo['pmfolders']);
	}
	if (!isset($pmfolders["{$vbulletin->GPC['folderid']}"]))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['folder'], $vbulletin->options['contactuslink'])));
	}
	$folder = $pmfolders["{$vbulletin->GPC['folderid']}"];
	$dateline = TIMENOW;

	($hook = vBulletinHook::fetch_hook('private_emptyfolder')) ? eval($hook) : false;

	$templatename = 'pm_emptyfolder';
}

// ############################### start confirm empty folders ###############################
if ($_POST['do'] == 'confirmemptyfolder')
{ // confirmation page

	$vbulletin->input->clean_array_gpc('p', array(
		'folderid' => TYPE_INT,
		'dateline' => TYPE_UNIXTIME,
	));

	$deletepms = array();
	// get pms
	$pms = $db->query_read("
		SELECT pmid
		FROM " . TABLE_PREFIX . "pm AS pm
		LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext USING (pmtextid)
		WHERE folderid = " . $vbulletin->GPC['folderid'] . "
			AND userid = " . $vbulletin->userinfo['userid'] . "
			AND dateline < " . $vbulletin->GPC['dateline']
	);
	while ($pm = $db->fetch_array($pms))
	{
		$deletepms[] = $pm['pmid'];
	}

	if (!empty($deletepms))
	{
		// remove pms and receipts!
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "pm WHERE pmid IN (" . implode(',', $deletepms) . ")");
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "pmreceipt WHERE pmid IN (" . implode(',', $deletepms) . ")");
		build_pm_counters();
	}

	($hook = vBulletinHook::fetch_hook('private_confirmemptyfolder')) ? eval($hook) : false;

	$vbulletin->url = 'private.php?' . $vbulletin->session->vars['sessionurl'];
	eval(print_standard_redirect('pm_messagesdeleted'));
}

// ############################### start edit folders ###############################
// edit the user's custom pm folders
if ($_REQUEST['do'] == 'editfolders')
{
	if (!isset($pmfolders))
	{
		$pmfolders = unserialize($vbulletin->userinfo['pmfolders']);
	}

	$folderjump = construct_folder_jump();

	($hook = vBulletinHook::fetch_hook('private_editfolders_start')) ? eval($hook) : false;

	$usedids = array();

	$editfolderbits = '';
	$show['messagecount'] = true;
	if (!empty($pmfolders))
	{
		$show['customfolders'] = true;
		foreach ($pmfolders AS $folderid => $foldername)
		{
			$usedids[] = $folderid;
			$foldertotal = intval($messagecounters["$folderid"]);
			($hook = vBulletinHook::fetch_hook('private_editfolders_bit')) ? eval($hook) : false;
			eval('$editfolderbits .= "' . fetch_template('pm_editfolderbit') . '";');
		}
	}
	else
	{
		$show['customfolders'] = false;
	}
	$show['messagecount'] = false;

	// build the inputs for new folders
	$addfolderbits = '';
	$donefolders = 0;
	$folderid = 0;
	$foldername = '';
	$foldertotal = 0;
	while ($donefolders < 3)
	{
		$folderid ++;
		if (in_array($folderid, $usedids))
		{
			continue;
		}
		else
		{
			$donefolders++;
			($hook = vBulletinHook::fetch_hook('private_editfolders_bit')) ? eval($hook) : false;
			eval('$addfolderbits .= "' . fetch_template('pm_editfolderbit') . '";');
		}
	}

	$inboxtotal = intval($messagecounters[0]);
	$sentitemstotal = intval($messagecounters['-1']);

	// generate navbar
	$navbits[''] = $vbphrase['edit_folders'];

	$templatename = 'pm_editfolders';
}

// ############################### delete pm receipt ###############################
// delete one or more pm receipts
if ($_POST['do'] == 'deletepmreceipt')
{
	$vbulletin->input->clean_gpc('p', 'receipt', TYPE_ARRAY_UINT);


	if (empty($vbulletin->GPC['receipt']))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['private_message_receipt'], $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('private_deletepmreceipt')) ? eval($hook) : false;

	$db->query_write("DELETE FROM " . TABLE_PREFIX . "pmreceipt WHERE userid=" . $vbulletin->userinfo['userid'] . " AND pmid IN(". implode(', ', $vbulletin->GPC['receipt']) . ")");

	if ($db->affected_rows() == 0)
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['private_message_receipt'], $vbulletin->options['contactuslink'])));
	}
	else
	{
		eval(print_standard_redirect('pm_receiptsdeleted'));
	}
}

// ############################### start deny receipt ###############################
// set a receipt as denied
if ($_REQUEST['do'] == 'dopmreceipt')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pmid'		=> TYPE_UINT,
		'confirm'	=> TYPE_BOOL,
		'type'		=> TYPE_NOHTML,
	));

	if (!$vbulletin->GPC['confirm'] AND ($permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['candenypmreceipts']))
	{
		$receiptSql = "UPDATE " . TABLE_PREFIX . "pmreceipt SET readtime = 0, denied = 1 WHERE touserid = " . $vbulletin->userinfo['userid'] . " AND pmid = " . $vbulletin->GPC['pmid'];
	}
	else
	{
		$receiptSql = "UPDATE " . TABLE_PREFIX . "pmreceipt SET readtime = " . TIMENOW . ", denied = 0 WHERE touserid = " . $vbulletin->userinfo['userid'] . " AND pmid = " . $vbulletin->GPC['pmid'];
	}

	($hook = vBulletinHook::fetch_hook('private_dopmreceipt')) ? eval($hook) : false;

	$db->query_write($receiptSql);

	if ($vbulletin->GPC['type'] == 'img')
	{
		header('Content-type: image/gif');
		readfile(DIR . '/' . $vbulletin->options['cleargifurl']);
	}
	else
	{
	?>
<html><head><title><?php echo $vbulletin->options['bbtitle']; ?></title><style type="text/css"><?php echo $style['css']; ?></style></head><body>
<script type="text/javascript">
self.close();
</script>
</body></html>
	<?php
	}
	flush();
	exit;
}

// ############################### start pm receipt tracking ###############################
// message receipt tracking
if ($_REQUEST['do'] == 'trackpm')
{
	if (!$cantrackpm)
	{
		print_no_permission();
	}

	($hook = vBulletinHook::fetch_hook('private_trackpm_start')) ? eval($hook) : false;

	$receipts = array();

	$pmreceipts = $db->query_read("
		SELECT
			pmreceipt.*, pmreceipt.pmid AS receiptid
		FROM " . TABLE_PREFIX . "pmreceipt AS pmreceipt
		WHERE pmreceipt.userid = " . $vbulletin->userinfo['userid'] . "
		ORDER BY pmreceipt.sendtime DESC
	");
	while ($pmreceipt = $db->fetch_array($pmreceipts))
	{
		$pmreceipt['send_date'] = vbdate($vbulletin->options['dateformat'], $pmreceipt['sendtime'], true);
		$pmreceipt['send_time'] = vbdate($vbulletin->options['timeformat'], $pmreceipt['sendtime']);
		$pmreceipt['read_date'] = vbdate($vbulletin->options['dateformat'], $pmreceipt['readtime'], true);
		$pmreceipt['read_time'] = vbdate($vbulletin->options['timeformat'], $pmreceipt['readtime']);
		if ($pmreceipt['readtime'] == 0)
		{
			$receipts['unread'][] = $pmreceipt;
		}
		else
		{
			$receipts['read'][] = $pmreceipt;
		}
	}

	if (!empty($receipts['read']))
	{
		$show['readpm'] = true;
		$numreceipts = sizeof($receipts['read']);
		$tabletitle = $vbphrase['confirmed_private_message_receipts'];
		$tableid = 'pmreceipts_read';
		$collapseobj_tableid =& $vbcollapse["collapseobj_$tableid"];
		$collapseimg_tableid =& $vbcollapse["collapseimg_$tableid"];
		$receiptbits = '';
		foreach ($receipts['read'] AS $receipt)
		{
			($hook = vBulletinHook::fetch_hook('private_trackpm_receiptbit')) ? eval($hook) : false;
			eval('$receiptbits .= "' . fetch_template('pm_receiptsbit') . '";');
		}
		eval('$confirmedreceipts = "' . fetch_template('pm_receipts') . '";');
	}
	else
	{
		$confirmedreceipts = '';
	}

	if (!empty($receipts['unread']))
	{
		$show['readpm'] = false;
		$numreceipts = sizeof($receipts['unread']);
		$tabletitle = $vbphrase['unconfirmed_private_message_receipts'];
		$tableid = 'pmreceipts_unread';
		$collapseobj_tableid =& $vbcollapse["collapseobj_$tableid"];
		$collapseimg_tableid =& $vbcollapse["collapseimg_$tableid"];
		$receiptbits = '';
		foreach ($receipts['unread'] AS $receipt)
		{
			($hook = vBulletinHook::fetch_hook('private_trackpm_receiptbit')) ? eval($hook) : false;
			eval('$receiptbits .= "' . fetch_template('pm_receiptsbit') . '";');
		}
		eval('$unconfirmedreceipts = "' . fetch_template('pm_receipts') . '";');
	}
	else
	{
		$unconfirmedreceipts = '';
	}

	$folderjump = construct_folder_jump();

	// generate navbar
	$navbits[''] = $vbphrase['message_tracking'];

	if ($confirmedreceipts != '' OR $unconfirmedreceipts != '')
	{
		$show['receipts'] = true;
	}

	$templatename = 'pm_trackpm';
}

// ############################### start move pms ###############################
if ($_POST['do'] == 'movepm')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'folderid'	=> TYPE_INT,
		'messageids'=> TYPE_STR,
	));

	$vbulletin->GPC['messageids'] = unserialize($vbulletin->GPC['messageids']);

	if (!is_array($vbulletin->GPC['messageids']) OR empty($vbulletin->GPC['messageids']))
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['private_message'], $vbulletin->options['contactuslink'])));
	}

	$pmids = array();
	foreach ($vbulletin->GPC['messageids'] AS $pmid)
	{
		$id = intval($pmid);
		$pmids["$id"] = $id;
	}

	($hook = vBulletinHook::fetch_hook('private_movepm')) ? eval($hook) : false;

	$db->query_write("UPDATE " . TABLE_PREFIX . "pm SET folderid=" . $vbulletin->GPC['folderid'] . " WHERE userid=" . $vbulletin->userinfo['userid'] . " AND folderid<>-1 AND pmid IN(" . implode(', ', $pmids) . ")");
	$vbulletin->url = 'private.php?' . $vbulletin->session->vars['sessionurl'] . 'folderid=' . $vbulletin->GPC['folderid'];
	eval(print_standard_redirect('pm_messagesmoved'));
}

// ############################### start pm manager ###############################
// actions for moving pms between folders, and deleting pms
if ($_POST['do'] == 'managepm')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'folderid'		=> TYPE_INT,
		'dowhat'		=> TYPE_NOHTML,
		'pm'			=> TYPE_ARRAY_UINT,
	));

	// check that we have an array to work with
	if (empty($vbulletin->GPC['pm']))
	{
		eval(standard_error(fetch_error('no_private_messages_selected')));
	}


	// make sure the ids we are going to work with are sane
	$messageids = array();
	foreach (array_keys($vbulletin->GPC['pm']) AS $pmid)
	{
		$pmid = intval($pmid);
		$messageids["$pmid"] = $pmid;
	}
	unset($pmid);

	($hook = vBulletinHook::fetch_hook('private_managepm_start')) ? eval($hook) : false;

	// now switch the $dowhat...
	switch($vbulletin->GPC['dowhat'])
	{
		// *****************************
		// move messages to a new folder
		case 'move':
			$totalmessages = sizeof($messageids);
			$messageids = serialize($messageids);
			$folderoptions = construct_folder_jump(0, 0, array($vbulletin->GPC['folderid'], -1));

			switch ($vbulletin->GPC['folderid'])
			{
				case -1:
					$fromfolder = $vbphrase['sent_items'];
					break;
				case 0:
					$fromfolder = $vbphrase['inbox'];
					break;
				default:
				{
					$folders = unserialize($vbulletin->userinfo['pmfolders']);
					$fromfolder = $folders["{$vbulletin->GPC['folderid']}"];
				}
			}

			($hook = vBulletinHook::fetch_hook('private_managepm_move')) ? eval($hook) : false;

			if ($folderoptions)
			{
				$templatename = 'pm_movepm';
			}
			else
			{
				eval(standard_error(fetch_error('pm_nofolders', $vbulletin->options['bburl'], $vbulletin->session->vars['sessionurl'])));
			}
		break;

		// *****************************
		// mark messages as unread
		case 'unread':
			$db->query_write("UPDATE " . TABLE_PREFIX . "pm SET messageread=0 WHERE userid=" . $vbulletin->userinfo['userid'] . " AND pmid IN (" . implode(', ', $messageids) . ")");
			build_pm_counters();
			$readunread = $vbphrase['unread_date'];

			($hook = vBulletinHook::fetch_hook('private_managepm_unread')) ? eval($hook) : false;

			eval(print_standard_redirect('pm_messagesmarkedas'));
		break;

		// *****************************
		// mark messages as read
		case 'read':
			$db->query_write("UPDATE " . TABLE_PREFIX . "pm SET messageread=1 WHERE messageread=0 AND userid=" . $vbulletin->userinfo['userid'] . " AND pmid IN (" . implode(', ', $messageids) . ")");
			build_pm_counters();
			$readunread = $vbphrase['read'];

			($hook = vBulletinHook::fetch_hook('private_managepm_read')) ? eval($hook) : false;

			eval(print_standard_redirect('pm_messagesmarkedas'));
		break;

		// *****************************
		// download as XML
		case 'xml':
			$_REQUEST['do'] = 'downloadpm';
		break;

		// *****************************
		// download as CSV
		case 'csv':
			$_REQUEST['do'] = 'downloadpm';
		break;

		// *****************************
		// download as TEXT
		case 'txt':
			$_REQUEST['do'] = 'downloadpm';
		break;

		// *****************************
		// delete messages completely
		case 'delete':
			$pmids = array();
			$textids = array();

			// get the pmid and pmtext id of messages to be deleted
			$pms = $db->query_read("
				SELECT pmid
				FROM " . TABLE_PREFIX . "pm
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
					AND pmid IN(" . implode(', ', $messageids) . ")
			");

			// check to see that we still have some ids to work with
			if ($db->num_rows($pms) == 0)
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase['private_message'], $vbulletin->options['contactuslink'])));
			}

			// build the final array of pmids to work with
			while ($pm = $db->fetch_array($pms))
			{
				$pmids[] = $pm['pmid'];
			}

			// delete from the pm table using the results from above
			$deletePmSql = "DELETE FROM " . TABLE_PREFIX . "pm WHERE pmid IN(" . implode(', ', $pmids) . ")";
			$db->query_write($deletePmSql);

			build_pm_counters();

			($hook = vBulletinHook::fetch_hook('private_managepm_delete')) ? eval($hook) : false;

			// all done, redirect...
			$vbulletin->url = 'private.php?' . $vbulletin->session->vars['sessionurl'] . 'folderid=' . $vbulletin->GPC['folderid'];
			eval(print_standard_redirect('pm_messagesdeleted'));
		break;

		// *****************************
		// unknown action specified
		default:
			$handled_do = false;
			($hook = vBulletinHook::fetch_hook('private_managepm_action_switch')) ? eval($hook) : false;
			if (!$handled_do)
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase['action'], $vbulletin->options['contactuslink'])));
			}
		break;
	}
}

// ############################### start insert pm ###############################
// either insert a pm into the database, or process the preview and fall back to newpm
if ($_REQUEST['do'] == 'downloadpm')
{
	$vbulletin->input->clean_gpc('r', 'dowhat', TYPE_NOHTML);

	require_once(DIR . '/includes/functions_file.php');

	function fetch_touser_string($pm)
	{
		global $vbphrase, $vbulletin;

		if ($pm['folder'] == -1)
		{
			$touserarray = unserialize($pm['touser']);
			if (is_array($touserarray))
			{
				return implode(', ', $touserarray);
			}
			else
			{
				return '(' . $vbphrase['unreadable_data'] . ')';
			}
		}
		else
		{
			return $vbulletin->userinfo['username'];
		}
	}

	// set sql condition for selected messages
	if (is_array($messageids))
	{
		$sql = 'AND pm.pmid IN(' . implode(', ', $messageids) . ')';
	}
	// set blank sql condition (get all user's messages)
	else
	{
		$sql = '';
	}

	// query the specified messages
	$pms = $db->query_read("
		SELECT dateline AS datestamp, folderid AS folder, title, fromusername AS fromuser, touserarray AS touser, message
		FROM " . TABLE_PREFIX . "pm AS pm
		LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
		WHERE pm.userid = " . $vbulletin->userinfo['userid'] . " $sql
		ORDER BY folderid, dateline
	");

	// check to see that we have some messages to work with
	if (!$db->num_rows($pms))
	{
		eval(standard_error(fetch_error('no_pm_to_download')));
	}

	// get folder names the easy way...
	construct_folder_jump();

	($hook = vBulletinHook::fetch_hook('private_downloadpm_start')) ? eval($hook) : false;

	// do the business...
	switch ($vbulletin->GPC['dowhat'])
	{
		// *****************************
		// download as XML
		case 'xml':
			$pmfolders = array();

			while ($pm = $db->fetch_array($pms))
			{
				$pmfolders["$pm[folder]"][] = $pm;
			}
			unset($pm);
			$db->free_result($pms);

			require_once(DIR . '/includes/class_xml.php');
			$xml = new XMLexporter();

			$xml->add_group('privatemessages');

			foreach ($pmfolders AS $folder => $messages)
			{
				$foldername =& $foldernames["$folder"];
				$xml->add_group('folder', array('name' => $foldername));
				foreach ($messages AS $pm)
				{
					$pm['datestamp'] = vbdate('Y-m-d H:i', $pm['datestamp'], false, false);
					$pm['touser'] = fetch_touser_string($pm);
					$pm['folder'] = $foldernames["$pm[folder]"];
					$pm['message'] = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $pm['message']));
					$pm['message'] = fetch_censored_text($pm['message']);
					$pm['message'] = $pm['message'];
					unset($pm['folder']);

					($hook = vBulletinHook::fetch_hook('private_downloadpm_bit')) ? eval($hook) : false;

					$xml->add_group('privatemessage');
					foreach ($pm AS $key => $val)
					{
						$xml->add_tag($key, $val);
					}
					$xml->close_group();
				}
				$xml->close_group();
			}

			$xml->close_group();

			$doc = "<?xml version=\"1.0\" encoding=\"$stylevar[charset]\"?>\r\n\r\n";
			$doc .= "<!-- " . $vbulletin->options['bbtitle'] . ';' . $vbulletin->options['bburl'] . " -->\r\n";
			$doc .= '<!-- ' . construct_phrase($vbphrase['private_message_dump_for_user_x_y'], $vbulletin->userinfo['username'], vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], TIMENOW)) . " -->\r\n\r\n";

			$doc .= $xml->output();
			$xml = null;

			// download the file
			file_download($doc, str_replace(array('\\', '/'), '-', "$vbphrase[dump_privatemessages]-" . $vbulletin->userinfo['username'] . "-" . vbdate($vbulletin->options['dateformat'], TIMENOW) . '.xml'), 'text/xml');
		break;

		// *****************************
		// download as CSV
		case 'csv':
			// column headers
			$csv = "$vbphrase[date],$vbphrase[folder],$vbphrase[title],$vbphrase[dump_from],$vbphrase[dump_to],$vbphrase[message]\r\n";

			while ($pm = $db->fetch_array($pms))
			{
				$pm['datestamp'] = vbdate('Y-m-d H:i', $pm['datestamp'], false, false);
				$pm['touser'] = fetch_touser_string($pm);
				$pm['folder'] = $foldernames["$pm[folder]"];
				$pm['title'] = unhtmlspecialchars($pm['title']);
				$pm['message'] = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $pm['message']));
				$pm['message'] = fetch_censored_text($pm['message']);

				($hook = vBulletinHook::fetch_hook('private_downloadpm_bit')) ? eval($hook) : false;

				// make values save
				foreach ($pm AS $key => $val)
				{
					if (preg_match('/\,|"/siU', $val))
					{
						$pm["$key"] = '"' . str_replace('"', '""', $val) . '"';
					}
				}
				// output the message row
				$csv .= implode(',', $pm) . "\r\n";
			}
			unset($pm);
			$db->free_result($pms);

			// download the file
			file_download($csv, str_replace(array('\\', '/'), '-', "$vbphrase[dump_privatemessages]-" . $vbulletin->userinfo['username'] . "-" . vbdate($vbulletin->options['dateformat'], TIMENOW) . '.csv'), 'text/x-csv');
		break;

		// *****************************
		// download as TEXT
		case 'txt':
			$pmfolders = array();

			while ($pm = $db->fetch_array($pms))
			{
				$pmfolders["$pm[folder]"][] = $pm;
			}
			unset($pm);
			$db->free_result($pms);

			$txt = $vbulletin->options['bbtitle'] . ';' . $vbulletin->options['bburl'] . "\r\n";
			$txt .= construct_phrase($vbphrase['private_message_dump_for_user_x_y'], $vbulletin->userinfo['username'], vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], TIMENOW)) . " -->\r\n\r\n";

			foreach ($pmfolders AS $folder => $messages)
			{
				$foldername =& $foldernames["$folder"];
				$txt .= "################################################################################\r\n";
				$txt .= "$vbphrase[folder] :\t$foldername\r\n";
				$txt .= "################################################################################\r\n\r\n";

				foreach ($messages AS $pm)
				{
					// turn all single \n into \r\n
					$pm['message'] = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $pm['message']));
					$pm['message'] = fetch_censored_text($pm['message']);

					($hook = vBulletinHook::fetch_hook('private_downloadpm_bit')) ? eval($hook) : false;

					$txt .= "================================================================================\r\n";
					$txt .= "$vbphrase[dump_from] :\t$pm[fromuser]\r\n";
					$txt .= "$vbphrase[dump_to] :\t" . fetch_touser_string($pm) . "\r\n";
					$txt .= "$vbphrase[date] :\t" . vbdate('Y-m-d H:i', $pm['datestamp'], false, false) . "\r\n";
					$txt .= "$vbphrase[title] :\t" . unhtmlspecialchars($pm['title']) . "\r\n";
					$txt .= "--------------------------------------------------------------------------------\r\n";
					$txt .= "$pm[message]\r\n\r\n";
				}
			}

			// download the file
			file_download($txt, str_replace(array('\\', '/'), '-', "$vbphrase[dump_privatemessages]-" . $vbulletin->userinfo['username'] . "-" . vbdate($vbulletin->options['dateformat'], TIMENOW) . '.txt'), 'text/plain');
		break;

		// *****************************
		// unknown download format
		default:
			eval(standard_error(fetch_error('invalidid', $vbphrase['file_type'], $vbulletin->options['contactuslink'])));
		break;
	}
}

// ############################### start insert pm ###############################
// either insert a pm into the database, or process the preview and fall back to newpm
if ($_POST['do'] == 'insertpm')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'		=> TYPE_BOOL,
		'title'			=> TYPE_NOHTML,
		'message'		=> TYPE_STR,
		'parseurl'		=> TYPE_BOOL,
		'savecopy'		=> TYPE_BOOL,
		'signature'		=> TYPE_BOOL,
		'disablesmilies'=> TYPE_BOOL,
		'receipt'		=> TYPE_BOOL,
		'preview'		=> TYPE_STR,
		'recipients'	=> TYPE_STR,
		'iconid'		=> TYPE_UINT,
		'forward'		=> TYPE_BOOL,
		'folderid'		=> TYPE_INT,
	));

	// include useful functions
	require_once(DIR . '/includes/functions_newpost.php');

	// unwysiwygify the incoming data
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$vbulletin->GPC['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->options['privallowhtml']);
	}

	// parse URLs in message text
	if ($vbulletin->options['privallowbbcode'] AND $vbulletin->GPC['parseurl'])
	{
		$vbulletin->GPC['message'] = convert_url_to_bbcode($vbulletin->GPC['message']);
	}

	$pm['message'] =& $vbulletin->GPC['message'];
	$pm['title'] =& $vbulletin->GPC['title'];
	$pm['parseurl'] =& $vbulletin->GPC['parseurl'];
	$pm['savecopy'] =& $vbulletin->GPC['savecopy'];
	$pm['signature'] =& $vbulletin->GPC['signature'];
	$pm['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
	$pm['receipt'] =& $vbulletin->GPC['receipt'];
	$pm['recipients'] =& $vbulletin->GPC['recipients'];
	$pm['pmid'] =& $vbulletin->GPC['pmid'];
	$pm['iconid'] =& $vbulletin->GPC['iconid'];
	$pm['forward'] =& $vbulletin->GPC['forward'];
	$pm['folderid'] =& $vbulletin->GPC['folderid'];

	// *************************************************************
	// PROCESS THE MESSAGE AND INSERT IT INTO THE DATABASE

	$errors = array(); // catches errors

	if ($vbulletin->userinfo['pmtotal'] > $permissions['pmquota'] OR ($vbulletin->userinfo['pmtotal'] == $permissions['pmquota'] AND $pm['savecopy']))
	{
		$errors[] = fetch_error('yourpmquotaexceeded');
	}

	// check for message flooding
	if ($vbulletin->options['pmfloodtime'] > 0 AND !$vbulletin->GPC['preview'])
	{
		if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) AND !can_moderate())
		{
			$floodcheck = $db->query_first("
				SELECT pmtextid, title, dateline
				FROM " . TABLE_PREFIX . "pmtext AS pmtext
				WHERE fromuserid = " . $vbulletin->userinfo['userid'] . "
				ORDER BY dateline DESC
			");

			if (($timepassed = TIMENOW - $floodcheck['dateline']) < $vbulletin->options['pmfloodtime'])
			{
				$errors[] = fetch_error('pmfloodcheck', $vbulletin->options['pmfloodtime'], ($vbulletin->options['pmfloodtime'] - $timepassed));
			}
		}
	}

	// create the DM to do error checking and insert the new PM
	$pmdm =& datamanager_init('PM', $vbulletin, ERRTYPE_ARRAY);

	$pmdm->set_info('savecopy',   $pm['savecopy']);
	$pmdm->set_info('receipt',    $pm['receipt']);
	$pmdm->set_info('cantrackpm', $cantrackpm);
	$pmdm->set_info('parentpmid', $pm['pmid']);
	$pmdm->set_info('forward',    $pm['forward']);

	$pmdm->set('fromuserid', $vbulletin->userinfo['userid']);
	$pmdm->set('fromusername', $vbulletin->userinfo['username']);
	$pmdm->setr('title', $pm['title']);
	$pmdm->setr('message', $pm['message']);
	$pmdm->set_recipients($pm['recipients'], $permissions);
	$pmdm->setr('iconid', $pm['iconid']);
	$pmdm->set('dateline', TIMENOW);
	$pmdm->setr('showsignature', $pm['signature']);
	$pmdm->set('allowsmilie', $pm['disablesmilies'] ? 0 : 1);

	($hook = vBulletinHook::fetch_hook('private_insertpm_process')) ? eval($hook) : false;

	$pmdm->pre_save();

	// process errors if there are any
	$errors = array_merge($errors, $pmdm->errors);

	if (!empty($errors))
	{
		define('PMPREVIEW', 1);
		$preview = construct_errors($errors); // this will take the preview's place
		$_REQUEST['do'] = 'newpm';
	}
	else if ($vbulletin->GPC['preview'] != '')
	{
		define('PMPREVIEW', 1);
		$foruminfo = array('forumid' => 'privatemessage');
		$preview = process_post_preview($pm);
		$_REQUEST['do'] = 'newpm';
	}
	else
	{
		// everything's good!
		$pmdm->save();

		($hook = vBulletinHook::fetch_hook('private_insertpm_complete')) ? eval($hook) : false;

		$vbulletin->url = 'private.php' . $vbulletin->session->vars['sessionurl_q'];
		eval(print_standard_redirect('pm_messagesent'));
	}
}

// ############################### start new pm ###############################
// form for creating a new private message
if ($_REQUEST['do'] == 'newpm')
{
	require_once(DIR . '/includes/functions_newpost.php');

	($hook = vBulletinHook::fetch_hook('private_newpm_start')) ? eval($hook) : false;

	// do initial checkboxes
	$checked = array();
	$signaturechecked = iif($vbulletin->userinfo['signature'] != '', 'checked="checked"');

	// setup for preview display
	if (defined('PMPREVIEW'))
	{
		$postpreview =& $preview;
		$pm['recipients'] =& htmlspecialchars_uni($pm['recipients']);
		$pm['message'] = htmlspecialchars_uni($pm['message']);
		construct_checkboxes($pm);
	}
	else
	{
		$vbulletin->input->clean_array_gpc('r', array(
			'stripquote'	=> TYPE_BOOL,
			'forward'		=> TYPE_BOOL,
			'userid'		=> TYPE_NOCLEAN,
		));

		// set up for PM reply / forward
		if ($vbulletin->GPC['pmid'])
		{
			if ($pm = $db->query_first("
				SELECT pm.*, pmtext.*
				FROM " . TABLE_PREFIX . "pm AS pm
				LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
				WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.pmid=" . $vbulletin->GPC['pmid'] . "
			"))
			{
				// quote reply
				$originalposter = fetch_quote_username($pm['fromusername']);

				// allow quotes to remain with an optional request variable
				// this will fix a problem with forwarded PMs and replying to them
				if ($vbulletin->GPC['stripquote'])
				{
					$pagetext = strip_quotes($pm['message']);
				}
				else
				{
					// this is now the default behavior -- leave quotes, like vB2
					$pagetext = $pm['message'];
				}
				$pagetext = trim(htmlspecialchars_uni($pagetext));

				eval('$pm[message] = "' . fetch_template('newpost_quote', 1, 0) . '";');

				// work out FW / RE bits
				if (preg_match('#^' . preg_quote($vbphrase['forward_prefix'], '#') . '#i', $pm['title']))
				{
					$pm['title'] = substr($pm['title'], strlen($vbphrase['forward_prefix']) + 1);
				}
				else if (preg_match('#^' . preg_quote($vbphrase['reply_prefix'], '#') . '#i', $pm['title']))
				{
					$pm['title'] = substr($pm['title'], strlen($vbphrase['reply_prefix']) + 1);
				}
				else
				{
					$pm['title'] = preg_replace('#^[a-z]{2}:#i', '', $pm['title']);
				}
				$pm['title'] = trim($pm['title']);

				if ($vbulletin->GPC['forward'])
				{
					$pm['title'] = $vbphrase['forward_prefix'] . " $pm[title]";
					$pm['recipients'] = '';
					$pm['forward'] = 1;
				}
				else
				{
					$pm['title'] = $vbphrase['reply_prefix'] . " $pm[title]";
					$pm['recipients'] = $pm['fromusername'] . ' ; ';
					$pm['forward'] = 0;
				}

				($hook = vBulletinHook::fetch_hook('private_newpm_reply')) ? eval($hook) : false;
			}
			else
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase['private_message'], $vbulletin->options['contactuslink'])));
			}
		}
		// set up for standard new PM
		else
		{
			// insert username(s) of specified recipients
			if ($vbulletin->GPC['userid'])
			{
				$recipients = array();
				if (is_array($vbulletin->GPC['userid']))
				{
					foreach ($vbulletin->GPC['userid'] AS $recipient)
					{
						$recipients[] = intval($recipient);
					}
				}
				else
				{
					$recipients[] = intval($vbulletin->GPC['userid']);
				}
				$users = $db->query_read("SELECT username FROM " . TABLE_PREFIX . "user AS user WHERE userid IN(" . implode(', ', $recipients) . ")");
				$recipients = array();
				while ($user = $db->fetch_array($users))
				{
					$recipients[] = $user['username'];
				}
				if (empty($recipients))
				{
					$pm['recipients'] = '';
				}
				else
				{
					$pm['recipients'] = implode(' ; ', $recipients);
				}
			}

			($hook = vBulletinHook::fetch_hook('private_newpm_blank')) ? eval($hook) : false;
		}

		construct_checkboxes(array(
			'savecopy' => true,
			'parseurl' => true,
			'signature' => iif($vbulletin->userinfo['signature'] !== '', true)
		));
	}

	$folderjump = construct_folder_jump(0, $pm['folderid']);

	$posticons = construct_icons($pm['iconid'], $vbulletin->options['privallowicons']);

	require_once(DIR . '/includes/functions_editor.php');

	// set message box width to usercp size
	$stylevar['messagewidth'] = $stylevar['messagewidth_usercp'];
	$editorid = construct_edit_toolbar($pm['message'], 0, 'privatemessage', iif($vbulletin->options['privallowsmilies'], 1, 0));

	// generate navbar
	if ($pm['pmid'])
	{
		$navbits['private.php?' . $vbulletin->session->vars['sessionurl'] . "folderid=$pm[folderid]"] = $foldernames["$pm[folderid]"];
		$navbits['private.php?' . $vbulletin->session->vars['sessionurl'] . "do=showpm&amp;pmid=$pm[pmid]"] = $pm['title'];
		$navbits[''] = iif($pm['forward'], $vbphrase['forward_message'], $vbphrase['reply_to_private_message']);
	}
	else
	{
		$navbits[''] = $vbphrase['post_new_private_message'];
	}

	$show['sendmax'] = iif($permissions['pmsendmax'], true, false);
	$show['parseurl'] = $vbulletin->options['privallowbbcode'];

	// build forum rules
	$bbcodeon = iif($vbulletin->options['privallowbbcode'], $vbphrase['on'], $vbphrase['off']);
	$imgcodeon = iif($vbulletin->options['privallowbbimagecode'], $vbphrase['on'], $vbphrase['off']);
	$htmlcodeon = iif($vbulletin->options['privallowhtml'], $vbphrase['on'], $vbphrase['off']);
	$smilieson = iif($vbulletin->options['privallowsmilies'], $vbphrase['on'], $vbphrase['off']);

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	eval('$forumrules = "' . fetch_template('forumrules') . '";');

	$templatename = 'pm_newpm';
}

// ############################### start show pm ###############################
// show a private message
if ($_REQUEST['do'] == 'showpm')
{
	require_once(DIR . '/includes/class_postbit.php');
	require_once(DIR . '/includes/functions_bigthree.php');

	$vbulletin->input->clean_gpc('r', 'pmid', TYPE_UINT);

	($hook = vBulletinHook::fetch_hook('private_showpm_start')) ? eval($hook) : false;

	$pm = $db->query_first("
		SELECT
			pm.*, pmtext.*,
			" . iif($vbulletin->options['privallowicons'], "icon.title AS icontitle, icon.iconpath,") . "
			IF(ISNULL(pmreceipt.pmid), 0, 1) AS receipt, pmreceipt.readtime, pmreceipt.denied
		FROM " . TABLE_PREFIX . "pm AS pm
		LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
		" . iif($vbulletin->options['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
		LEFT JOIN " . TABLE_PREFIX . "pmreceipt AS pmreceipt ON(pmreceipt.pmid = pm.pmid)
		WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.pmid=" . $vbulletin->GPC['pmid'] . "
	");

	if (!$pm)
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['private_message'], $vbulletin->options['contactuslink'])));
	}

	$folderjump = construct_folder_jump(0, $pm['folderid']);

	// do read receipt
	$show['receiptprompt'] = $show['receiptpopup'] = false;
	if ($pm['receipt'] == 1 AND $pm['readtime'] == 0 AND $pm['denied'] == 0)
	{
		if ($permissions['pmpermissions'] & $vbulletin->bf_ugp_pmpermissions['candenypmreceipts'])
		{
			// set it to denied just now as some people might have ad blocking that stops the popup appearing
			$show['receiptprompt'] = $show['receiptpopup'] = true;
			$receipt_question_js = construct_phrase($vbphrase['x_has_requested_a_read_receipt'], unhtmlspecialchars($pm['fromusername']));
			$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pmreceipt SET denied = 1 WHERE pmid = $pm[pmid]");
		}
		else
		{
			// they can't deny pm receipts so do not show a popup or prompt
			$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pmreceipt SET readtime = " . TIMENOW . " WHERE pmid = $pm[pmid]");
		}
	}
	else if ($pm['receipt'] == 1 AND $pm['denied'] == 1)
	{
		$show['receiptprompt'] = true;
	}

	$postbit_factory =& new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$postbit_obj =& $postbit_factory->fetch_postbit('pm');
	$postbit = $postbit_obj->construct_postbit($pm);

	// update message to show read
	if ($pm['messageread'] == 0)
	{
		$db->shutdown_query("UPDATE " . TABLE_PREFIX . "pm SET messageread=1 WHERE userid=" . $vbulletin->userinfo['userid'] . " AND pmid=$pm[pmid]");

		if (!$pm['folderid'])
		{
			$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdm->set_existing($vbulletin->userinfo);
			$userdm->set('pmunread', 'IF(pmunread >= 1, pmunread - 1, 0)', false);
			$userdm->save(true, true);
			unset($userdm);
		}
	}

	// generate navbar
	$navbits['private.php?' . $vbulletin->session->vars['sessionurl'] . "folderid=$pm[folderid]"] = $foldernames["{$pm['folderid']}"];
	$navbits[''] = $pm['title'];

	$templatename = 'pm_showpm';
}

// ############################### start pm folder view ###############################
if ($_REQUEST['do'] == 'messagelist')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'folderid'		=> TYPE_INT,
		'perpage'		=> TYPE_UINT,
		'pagenumber'	=> TYPE_UINT
	));

	($hook = vBulletinHook::fetch_hook('private_messagelist_start')) ? eval($hook) : false;

	$folderid = $vbulletin->GPC['folderid'];

	$folderjump = construct_folder_jump(0, $vbulletin->GPC['folderid']);
	$foldername = $foldernames["{$vbulletin->GPC['folderid']}"];

	// count receipts
	$receipts = $db->query_first("
		SELECT
			SUM(IF(readtime <> 0, 1, 0)) AS confirmed,
			SUM(IF(readtime = 0, 1, 0)) AS unconfirmed
		FROM " . TABLE_PREFIX . "pmreceipt
		WHERE userid = " . $vbulletin->userinfo['userid']
	);

	// get ignored users
	$ignoreusers = preg_split('#\s+#s', $vbulletin->userinfo['ignorelist'], -1, PREG_SPLIT_NO_EMPTY);

	$totalmessages = intval($messagecounters["{$vbulletin->GPC['folderid']}"]);

	// build pm counters bar
	$tdwidth = array();
	$tdwidth['folder'] = ceil($totalmessages / $permissions['pmquota'] * 100);
	$tdwidth['total'] = ceil($vbulletin->userinfo['pmtotal'] / $permissions['pmquota'] * 100) - $tdwidth['folder'];
	$tdwidth['quota'] = 100 - $tdwidth['folder'] - $tdwidth['total'];

	$show['thisfoldertotal'] = iif($tdwidth['folder'], true, false);
	$show['allfolderstotal'] = iif($tdwidth['total'], true, false);
	$show['pmicons'] = iif($vbulletin->options['privallowicons'], true, false);

	// build navbar
	$navbits[''] = $foldernames["{$vbulletin->GPC['folderid']}"];

	if ($totalmessages == 0)
	{
		$show['messagelist'] = false;
	}
	else
	{
		$show['messagelist'] = true;

		// get a sensible value for $perpage
		sanitize_pageresults($totalmessages, $vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage'], $vbulletin->options['pmmaxperpage'], $vbulletin->options['pmperpage']);
		// work out the $startat value
		$startat = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'];

		// array to store private messages in period groups
		$pm_period_groups = array();

		// query private messages
		$pms = $db->query_read("
			SELECT pm.*, pmtext.*
				" . iif($vbulletin->options['privallowicons'], ", icon.title AS icontitle, icon.iconpath") . "
			FROM " . TABLE_PREFIX . "pm AS pm
			LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
			" . iif($vbulletin->options['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
			WHERE pm.userid=" . $vbulletin->userinfo['userid'] . " AND pm.folderid=" . $vbulletin->GPC['folderid'] . "
			ORDER BY pmtext.dateline DESC
			LIMIT $startat, " . $vbulletin->GPC['perpage'] . "
		");
		while ($pm = $db->fetch_array($pms))
		{
			$pm_period_groups[ fetch_period_group($pm['dateline']) ]["{$pm['pmid']}"] = $pm;
		}
		$db->free_result($pms);

		// display returned messages
		$show['pmcheckbox'] = true;

		require_once(DIR . '/includes/functions_bigthree.php');

		foreach ($pm_period_groups AS $groupid => $pms)
		{
			if (preg_match('#^(\d+)_([a-z]+)_ago$#i', $groupid, $matches))
			{
				$groupname = construct_phrase($vbphrase["x_$matches[2]_ago"], $matches[1]);
			}
			else
			{
				$groupname = $vbphrase["$groupid"];
			}
			$groupid = $vbulletin->GPC['folderid'] . '_' . $groupid;
			$collapseobj_groupid =& $vbcollapse["collapseobj_pmf$groupid"];
			$collapseimg_groupid =& $vbcollapse["collapseimg_pmf$groupid"];

			$messagesingroup = sizeof($pms);
			$messagelistbits = '';

			foreach ($pms AS $pmid => $pm)
			{
				if (in_array($pm['fromuserid'], $ignoreusers))
				{
					// from user is on Ignore List
					eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit_ignore') . '";');
				}
				else
				{
					switch($pm['messageread'])
					{
						case 0: // unread
							$pm['statusicon'] = 'new';
						break;

						case 1: // read
							$pm['statusicon'] = 'old';
						break;

						case 2: // replied to
							$pm['statusicon'] = 'replied';
						break;

						case 3: // forwarded
							$pm['statusicon'] = 'forwarded';
						break;
					}

					$pm['senddate'] = vbdate($vbulletin->options['dateformat'], $pm['dateline']);
					$pm['sendtime'] = vbdate($vbulletin->options['timeformat'], $pm['dateline']);

					// get userbit
					if ($vbulletin->GPC['folderid'] == -1)
					{
						$users = unserialize($pm['touserarray']);
						$tousers = array();
						if (!empty($users))
						{
							foreach ($users AS $userid => $username)
							{
								eval('$tousers[] = "' . fetch_template('pm_messagelistbit_user') . '";');
							}
						}
						$userbit = implode(', ', $tousers);
					}
					else
					{
						$userid =& $pm['fromuserid'];
						$username =& $pm['fromusername'];
						eval('$userbit = "' . fetch_template('pm_messagelistbit_user') . '";');
					}

					$show['pmicon'] = iif($pm['iconpath'], true, false);
					$show['unread'] = iif(!$pm['messageread'], true, false);

					($hook = vBulletinHook::fetch_hook('private_messagelist_messagebit')) ? eval($hook) : false;

					eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit') . '";');
				}
			}

			// free up memory not required any more
			unset($pm_period_groups["$groupid"]);

			($hook = vBulletinHook::fetch_hook('private_messagelist_period')) ? eval($hook) : false;

			// build group template
			eval('$messagelist_periodgroups .= "' . fetch_template('pm_messagelist_periodgroup') . '";');
		}

		// build pagenav
		$pagenav = construct_page_nav($vbulletin->GPC['pagenumber'], $vbulletin->GPC['perpage'], $totalmessages, 'private.php?' . $vbulletin->session->vars['sessionurl'] . 'folderid=' . $vbulletin->GPC['folderid'] . '&pp=' . $vbulletin->GPC['perpage']);
	}

	if ($vbulletin->GPC['folderid'] == -1)
	{
		$show['sentto'] = true;
		$show['movetofolder'] = false;
	}
	else
	{
		$show['sentto'] = false;
		$show['movetofolder'] = true;
	}

	$templatename = 'pm_messagelist';

}

// #############################################################################

if ($templatename != '')
{
	// draw cp nav bar
	construct_usercp_nav($templatename);

	// build navbar
	$navbits = construct_navbits($navbits);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('private_complete')) ? eval($hook) : false;

	// print page
	eval('$HTML = "' . fetch_template($templatename) . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15160 $
|| ####################################################################
\*======================================================================*/
?>