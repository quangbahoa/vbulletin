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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_REGISTER_GLOBALS', 1);
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
	'rankphp'
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
		'im_yahoo'
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
$actiontemplates['insertpm'] = &$actiontemplates['newpm'];
$actiontemplates['none'] = &$actiontemplates['messagelist'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/functions_user.php');
require_once('./includes/functions_misc.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ###################### Start pm code parse #######################
// wrapper for the private message version of parse_bbcode2()
function parse_pm_bbcode($bbcode, $smilies = 1)
{
	global $vboptions;
	require_once('./includes/functions_bbcodeparse.php');
	return parse_bbcode2($bbcode, $vboptions['privallowhtml'], $vboptions['privallowbbimagecode'], iif($vboptions['privallowsmilies'] AND $smilies, 1, 0), $vboptions['privallowbbcode']);
}

// ###################### Start pm update counters #######################
// update the pm counters for $bbuserinfo
function build_pm_counters()
{
	global $DB_site, $bbuserinfo;

	$pmcount = $DB_site->query_first("
		SELECT
			COUNT(pmid) AS pmtotal,
			SUM(IF(messageread = 0 AND folderid = 0, 1, 0)) AS pmunread
		FROM " . TABLE_PREFIX . "pm AS pm
		WHERE pm.userid = $bbuserinfo[userid]
	");

	$pmcount['pmtotal'] = intval($pmcount['pmtotal']);
	$pmcount['pmunread'] = intval($pmcount['pmunread']);

	if ($bbuserinfo['pmtotal'] != $pmcount['pmtotal'] OR $bbuserinfo['pmunread'] != $pmcount['pmunread'])
	{
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "user SET
				pmtotal = $pmcount[pmtotal],
				pmunread = $pmcount[pmunread]
			WHERE userid = $bbuserinfo[userid]
		");
	}
}

// ############################### initialisation ###############################

if (!$vboptions['enablepms'])
{
	eval(print_standard_error('pm_adminoff'));
}

// check permission to use private messaging
if ($permissions['pmquota'] < 1 OR !$bbuserinfo['userid'])
{
	print_no_permission();
}

//check if the user will receive PMs
if (!$bbuserinfo['receivepm'])
{
	eval(print_standard_error('pm_turnedoff'));
}

// start navbar
$navbits = array(
	"usercp.php?$session[sessionurl]" => $vbphrase['user_control_panel'],
	"private.php?$session[sessionurl]" => $vbphrase['private_messages']
);

// select correct part of forumjump
$frmjmpsel['pm'] = 'class="fjsel" selected="selected"';
construct_forum_jump();

$onload = '';
$show['trackpm'] = $cantrackpm = $permissions['pmpermissions'] & CANTRACKPM;

// ############################### default do value ###############################
if (empty($_REQUEST['do']))
{
	if (empty($_REQUEST['pmid']))
	{
		$_REQUEST['do'] = 'messagelist';
	}
	else
	{
		$_REQUEST['do'] = 'showpm';
	}
}

if ($_POST['do'] == 'addfolder')
{
	globalize($_POST, array('folder'));

	$oldfolders = unserialize($bbuserinfo['pmfolders']);

	foreach($folder AS $folderid => $foldername)
	{
		$foldername = htmlspecialchars_uni(trim($foldername));
		$folderid = intval($folderid);
		if ($foldername != '')
		{
			$oldfolders["$folderid"] = $foldername;
		}
	}

	require_once('./includes/functions_databuild.php');
	if (!empty($oldfolders))
	{
		natcasesort($oldfolders);
	}
	build_usertextfields('pmfolders', iif(empty($oldfolders), '', serialize($oldfolders)), $bbuserinfo['userid']);
	$itemtype = $vbphrase['private_message'];
	$itemtypes = $vbphrase['private_messages'];
	eval(print_standard_redirect('foldersedited'));
}

// ############################### start update folders ###############################
// update the user's custom pm folders
if ($_POST['do'] == 'updatefolders')
{
	if (is_array($_POST['folder']))
	{
		$oldpmfolders = unserialize($bbuserinfo['pmfolders']);
		$pmfolders = array();
		$updatefolders = array();
		foreach ($_POST['folder'] AS $folderid => $foldername)
		{
			$folderid = intval($folderid);
			$foldername = htmlspecialchars_uni(trim($foldername));
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
			$DB_site->query("UPDATE " . TABLE_PREFIX . "pm SET folderid=0 WHERE userid=$bbuserinfo[userid] AND folderid IN(" . implode(', ', $updatefolders) . ")");
		}

		require_once('./includes/functions_databuild.php');
		if (!empty($pmfolders))
		{
			natcasesort($pmfolders);
		}
		build_usertextfields('pmfolders', iif(empty($pmfolders), '', serialize($pmfolders)), $bbuserinfo['userid']);
	}

	$itemtype = $vbphrase['private_message'];
	$itemtypes = $vbphrase['private_messages'];
	eval(print_standard_redirect('foldersedited'));
}

// ############################### start edit folders ###############################
// edit the user's custom pm folders
if ($_REQUEST['do'] == 'editfolders')
{
	if (!isset($pmfolders))
	{
		$pmfolders = unserialize($bbuserinfo['pmfolders']);
	}

	$folderjump = construct_folder_jump();

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
	if (!is_array($_POST['receipt']))
	{
		$idname = $vbphrase['private_message_receipt'];
		eval(print_standard_error('invalidid'));
	}

	$receipts = &$_POST['receipt'];
	foreach ($receipts AS $key => $val)
	{
		$receipts["$key"] = intval($val);
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "pmreceipt WHERE userid=$bbuserinfo[userid] AND pmid IN(". implode(', ', $receipts) . ")");

	if ($DB_site->affected_rows() == 0)
	{
		$idname = $vbphrase['private_message_receipt'];
		eval(print_standard_error('invalidid'));
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
	if ($_REQUEST['confirm'] == 0 AND ($permissions['pmpermissions'] & CANDENYPMRECEIPTS))
	{
		$receiptSql = "UPDATE " . TABLE_PREFIX . "pmreceipt SET readtime=0, denied=1 WHERE touserid=$bbuserinfo[userid] AND pmid=" . intval($_REQUEST['pmid']);
	}
	else
	{
		$receiptSql = "UPDATE " . TABLE_PREFIX . "pmreceipt SET readtime=" . TIMENOW . ", denied=0 WHERE touserid=$bbuserinfo[userid] AND pmid=" . intval($_REQUEST['pmid']);
	}

	$DB_site->shutdown_query($receiptSql);

	if ($_REQUEST['type'] == 'img')
	{
		header('Content-type: image/gif');
		readfile("./$vboptions[cleargifurl]");
	}
	else
	{
	?>
<html><head><title><?php echo $vboptions['bbtitle']; ?></title><style type="text/css"><?php echo $style['css']; ?></style></head><body>
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

	$receipts = array();

	$pmreceipts = $DB_site->query("
		SELECT
			pmreceipt.*, pmreceipt.pmid AS receiptid
		FROM " . TABLE_PREFIX . "pmreceipt AS pmreceipt
		WHERE pmreceipt.userid = $bbuserinfo[userid]
		ORDER BY pmreceipt.sendtime DESC
	");
	while ($pmreceipt = $DB_site->fetch_array($pmreceipts))
	{
		$pmreceipt['send_date'] = vbdate($vboptions['dateformat'], $pmreceipt['sendtime'], true);
		$pmreceipt['send_time'] = vbdate($vboptions['timeformat'], $pmreceipt['sendtime']);
		$pmreceipt['read_date'] = vbdate($vboptions['dateformat'], $pmreceipt['readtime'], true);
		$pmreceipt['read_time'] = vbdate($vboptions['timeformat'], $pmreceipt['readtime']);
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
		$collapseobj_tableid = &$vbcollapse["collapseobj_$tableid"];
		$collapseimg_tableid = &$vbcollapse["collapseimg_$tableid"];
		$receiptbits = '';
		foreach ($receipts['read'] AS $receipt)
		{
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
		$collapseobj_tableid = &$vbcollapse["collapseobj_$tableid"];
		$collapseimg_tableid = &$vbcollapse["collapseimg_$tableid"];
		$receiptbits = '';
		foreach ($receipts['unread'] AS $receipt)
		{
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
	globalize($_POST, array('folderid' => INT, 'messageids'));

	$messageids = unserialize($messageids);

	if (!is_array($messageids) OR empty($messageids))
	{
		$idname = $vbphrase['private_message'];
		eval(print_standard_error('invalidid'));
	}

	$pmids = array();
	foreach ($messageids AS $pmid)
	{
		$id = intval($pmid);
		$pmids["$id"] = $id;
	}

	$DB_site->query("UPDATE " . TABLE_PREFIX . "pm SET folderid=$folderid WHERE userid=$bbuserinfo[userid] AND folderid<>-1 AND pmid IN(" . implode(', ', $pmids) . ")");
	$url = "private.php?$session[sessionurl]folderid=$folderid";
	eval(print_standard_redirect('pm_messagesmoved'));
}

// ############################### start pm manager ###############################
// actions for moving pms between folders, and deleting pms
if ($_POST['do'] == 'managepm')
{

	globalize($_POST, array('dowhat', 'pm', 'folderid' => INT));

	// check that we have an array to work with
	if (!is_array($pm))
	{
		eval(print_standard_error('no_private_messages_selected'));
	}

	// make sure the ids we are going to work with are sane
	$messageids = array();
	foreach (array_keys($pm) AS $pmid)
	{
		$pmid = intval($pmid);
		$messageids["$pmid"] = $pmid;
	}
	unset ($pm, $pmid);

	// now switch the $dowhat...
	switch($dowhat)
	{
		// *****************************
		// move messages to a new folder
		case 'move':
			$totalmessages = sizeof($messageids);
			$messageids = serialize($messageids);
			$folderoptions = construct_folder_jump(0, 0, array($folderid, -1));

			switch ($folderid)
			{
				case -1: $fromfolder = $vbphrase['sent_items']; break;
				case 0: $fromfolder = $vbphrase['inbox']; break;
				default:
				{
					$folders = unserialize($bbuserinfo['pmfolders']);
					$fromfolder = $folders["$folderid"];
				}
			}

			if ($folderoptions)
			{
				$templatename = 'pm_movepm';
			}
			else
			{
				eval(print_standard_error('pm_nofolders'));
			}
		break;

		// *****************************
		// mark messages as unread
		case 'unread':
			$DB_site->query("UPDATE " . TABLE_PREFIX . "pm SET messageread=0 WHERE userid=$bbuserinfo[userid] AND pmid IN (" . implode(', ', $messageids) . ")");
			build_pm_counters();
			$readunread = $vbphrase['unread_date'];
			eval(print_standard_redirect('pm_messagesmarkedas'));
		break;

		// *****************************
		// mark messages as read
		case 'read':
			$DB_site->query("UPDATE " . TABLE_PREFIX . "pm SET messageread=1 WHERE messageread=0 AND userid=$bbuserinfo[userid] AND pmid IN (" . implode(', ', $messageids) . ")");
			build_pm_counters();
			$readunread = $vbphrase['read'];
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
			$pms = $DB_site->query("
				SELECT pmid
				FROM " . TABLE_PREFIX . "pm
				WHERE userid = $bbuserinfo[userid]
					AND pmid IN(" . implode(', ', $messageids) . ")
			");

			// check to see that we still have some ids to work with
			if ($DB_site->num_rows($pms) == 0)
			{
				$idname = $vbphrase['private_message'];
				eval(print_standard_error('invalidid'));
			}

			// build the final array of pmids to work with
			while ($pm = $DB_site->fetch_array($pms))
			{
				$pmids[] = $pm['pmid'];
			}

			// delete from the pm table using the results from above
			$deletePmSql = "DELETE FROM " . TABLE_PREFIX . "pm WHERE pmid IN(" . implode(', ', $pmids) . ")";
			$DB_site->query($deletePmSql);

			build_pm_counters();

			// all done, redirect...
			$url = "private.php?$session[sessionurl]folderid=$folderid";
			eval(print_standard_redirect('pm_messagesdeleted'));
		break;

		// *****************************
		// unknown action specified
		default:
			$idname = $vbphrase['action'];
			eval(print_standard_error('invalidid'));
		break;
	}
}

// ############################### start insert pm ###############################
// either insert a pm into the database, or process the preview and fall back to newpm
if ($_REQUEST['do'] == 'downloadpm')
{
	require_once('./includes/functions_file.php');

	function fetch_touser_string($pm)
	{
		global $bbuserinfo, $vbphrase;

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
			return $bbuserinfo['username'];
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
	$pms = $DB_site->query("
		SELECT dateline AS datestamp, folderid AS folder, title, fromusername AS fromuser, touserarray AS touser, message
		FROM " . TABLE_PREFIX . "pm AS pm
		LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
		WHERE pm.userid = $bbuserinfo[userid] $sql
		ORDER BY folderid, dateline
	");

	// check to see that we have some messages to work with
	if ($DB_site->num_rows($pms) == 0)
	{
		eval(print_standard_error('no_pm_to_download'));
	}

	// set $dowhat if we are not coming from $do==managepm
	if (!isset($dowhat))
	{
		$dowhat = &$_REQUEST['dowhat'];
	}

	// get folder names the easy way...
	construct_folder_jump();

	// do the business...
	switch ($dowhat)
	{
		// *****************************
		// download as XML
		case 'xml':
			$pmfolders = array();

			while ($pm = $DB_site->fetch_array($pms))
			{
				$pmfolders["$pm[folder]"][] = $pm;
			}
			unset($pm);
			$DB_site->free_result($pms);

			$xml = "<?xml version=\"1.0\" encoding=\"$stylevar[charset]\"?>\r\n\r\n";
			$xml .= "<!-- $vboptions[bbtitle]; $vboptions[bburl] -->\r\n";
			$xml .= '<!-- ' . construct_phrase($vbphrase['private_message_dump_for_user_x_y'], $bbuserinfo['username'], vbdate("$vboptions[dateformat] $vboptions[timeformat]", TIMENOW)) . " -->\r\n\r\n";
			$xml .= "<privatemessages>\r\n\r\n";

			require_once('./includes/functions_xml.php');
			foreach ($pmfolders AS $folder => $messages)
			{
				$foldername = &$foldernames["$folder"];
				$xml .= "<folder name=\"$foldername\">\r\n";
				foreach ($messages AS $pm)
				{
					$pm['datestamp'] = vbdate('Y-m-d H:i', $pm['datestamp'], false, false);
					$pm['touser'] = fetch_touser_string($pm);
					$pm['folder'] = $foldernames["$pm[folder]"];
					$pm['message'] = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $pm['message']));
					$pm['message'] = fetch_censored_text($pm['message']);
					$pm['message'] = '<![CDATA[' . xml_escape_cdata($pm['message']) . ']]>';
					unset($pm['folder']);
					$xml .= "\t<privatemessage>\r\n";
					foreach ($pm AS $key => $val)
					{
						$xml .= "\t\t<$key>$val</$key>\r\n";
					}
					$xml .= "\t</privatemessage>\r\n";
				}
				$xml .= "</folder>\r\n\r\n";
			}
			$xml .= "</privatemessages>";

			// download the file
			file_download($xml, "$vbphrase[dump_privatemessages]-$bbuserinfo[username]-" . vbdate($vboptions['dateformat'], TIMENOW) . '.xml', 'text/xml');
		break;

		// *****************************
		// download as CSV
		case 'csv':
			// column headers
			$csv = "$vbphrase[date],$vbphrase[folder],$vbphrase[title],$vbphrase[dump_from],$vbphrase[dump_to],$vbphrase[message]\r\n";

			while ($pm = $DB_site->fetch_array($pms))
			{
				$pm['datestamp'] = vbdate('Y-m-d H:i', $pm['datestamp'], false, false);
				$pm['touser'] = fetch_touser_string($pm);
				$pm['folder'] = $foldernames["$pm[folder]"];
				$pm['title'] = unhtmlspecialchars($pm['title']);
				$pm['message'] = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $pm['message']));
				$pm['message'] = fetch_censored_text($pm['message']);
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
			$DB_site->free_result($pms);

			// download the file
			file_download($csv, "$vbphrase[dump_privatemessages]-$bbuserinfo[username]-" . vbdate($vboptions['dateformat'], TIMENOW) . '.csv', 'text/x-csv');
		break;

		// *****************************
		// download as TEXT
		case 'txt':
			$pmfolders = array();

			while ($pm = $DB_site->fetch_array($pms))
			{
				$pmfolders["$pm[folder]"][] = $pm;
			}
			unset($pm);
			$DB_site->free_result($pms);

			$txt = "$vboptions[bbtitle]; $vboptions[bburl]\r\n";
			$txt .= construct_phrase($vbphrase['private_message_dump_for_user_x_y'], $bbuserinfo['username'], vbdate("$vboptions[dateformat] $vboptions[timeformat]", TIMENOW)) . " -->\r\n\r\n";

			foreach ($pmfolders AS $folder => $messages)
			{
				$foldername = &$foldernames["$folder"];
				$txt .= "################################################################################\r\n";
				$txt .= "$vbphrase[folder] :\t$foldername\r\n";
				$txt .= "################################################################################\r\n\r\n";

				foreach ($messages AS $pm)
				{
					// turn all single \n into \r\n
					$pm['message'] = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $pm['message']));
					$pm['message'] = fetch_censored_text($pm['message']);

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
			file_download($txt, "$vbphrase[dump_privatemessages]-$bbuserinfo[username]-" . vbdate($vboptions['dateformat'], TIMENOW) . '.txt', 'text/plain');
		break;

		// *****************************
		// unknown download format
		default:
			$idname = $vbphrase['file_type'];
			eval(print_standard_error('invalidid'));
		break;
	}
}

// ############################### start insert pm ###############################
// either insert a pm into the database, or process the preview and fall back to newpm
if ($_POST['do'] == 'insertpm')
{
	// get an array of incoming data
	$pm = &$_POST;

	// include useful functions
	require_once('./includes/functions_newpost.php');

	// unwysiwygify the incoming data
	if (isset($pm['WYSIWYG_HTML']))
	{
		require_once('./includes/functions_wysiwyg.php');
		$pm['message'] = convert_wysiwyg_html_to_bbcode($pm['WYSIWYG_HTML'], $vboptions['privallowhtml']);
	}

	// trim the text fields
	$pm['title'] = trim($pm['title']);
	$pm['message'] = trim($pm['message']);

	// parse URLs in message text
	if ($pm['parseurl'])
	{
		$pm['message'] = convert_url_to_bbcode($pm['message']);
	}

	// *************************************************************
	// PREVIEW THE MESSAGE, AND FALL BACK TO 'NEWPM'
	if (isset($pm['preview']))
	{
		$temp = $pm['title'];
		$pm['title'] = htmlspecialchars_uni(fetch_censored_text($pm['title']));
		define('PMPREVIEW', 1);
		$foruminfo = array('forumid' => 'privatemessage');
		$preview = process_post_preview($pm);
		$_REQUEST['do'] = 'newpm';
		$pm['title'] = $temp;
	}

	// *************************************************************
	// PROCESS THE MESSAGE AND INSERT IT INTO THE DATABASE
	else
	{
		$errors = array(); // catches errors
		$recipients = array(); // people that $bbuserinfo has put into the recipient box
		$notfound = array(); // people from the recipient box that are not found in the db
		$checkedusers = array(); // people from the recipient box that were found in the db
		$sendto = array(); // people that will actually receive this message
		$tostring = array(); // the array of users who will appear in the pmtext record

		if ($bbuserinfo['pmtotal'] > $permissions['pmquota'] OR ($bbuserinfo['pmtotal'] == $permissions['pmquota'] AND $pm['savecopy']))
		{
			eval('$errors[] = "' . fetch_phrase('yourpmquotaexceeded', PHRASETYPEID_ERROR) . '";');
		}

		// check that title and message exist
		if ($pm['title'] == '' OR $pm['message'] == '')
		{
			eval('$errors[] = "' . fetch_phrase('nosubject', PHRASETYPEID_ERROR) . '";');
		}

		// check for message flooding
		if ($vboptions['pmfloodtime'] > 0)
		{
			if (!($permissions['adminpermissions'] & CANCONTROLPANEL) AND !can_moderate())
			{
				$floodcheck = $DB_site->query_first("
					SELECT pmtextid, title, dateline
					FROM " . TABLE_PREFIX . "pmtext AS pmtext
					WHERE fromuserid = $bbuserinfo[userid]
					ORDER BY pmtextid DESC
				");
				if ($floodcheck['dateline'] > (TIMENOW - $vboptions['pmfloodtime']))
				{
					$floodchecktime = $vboptions['floodchecktime'];
					$vboptions['floodchecktime'] = $vboptions['pmfloodtime'];
					eval('$errors[] = "' . fetch_phrase('floodcheck', PHRASETYPEID_ERROR) . '";');
					$vboptions['floodchecktime'] = $floodchecktime;
				}
			}
		}

		// check message length
		if ($vboptions['pmmaxchars'] > 0)
		{
			$postlength = vbstrlen($pm['message']);
			if ($postlength > $vboptions['pmmaxchars'])
			{
				$tmp = $vboptions['postmaxchars'];
				$vboptions['postmaxchars'] = $vboptions['pmmaxchars'];
				eval('$errors[] = "' . fetch_phrase('toolong', PHRASETYPEID_ERROR) . '";');
				$vboptions['postmaxchars'] = $tmp;
			}
		}

		// check for valid users
		$pm['recipients'] = trim($pm['recipients']);

		if ($pm['recipients'] == '')
		{
			eval('$errors[] = "' . fetch_phrase('pminvalidrecipient', PHRASETYPEID_ERROR) . '";');
		}
		else if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $pm['recipients'])) // multiple recipients attempted
		{
			$users = preg_split('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $pm['recipients'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($users AS $recipient)
			{
				$recipient = trim($recipient);
				if ($recipient != '')
				{
					$recipients["$recipient"] = addslashes(htmlspecialchars_uni($recipient));
				}
			}
		}
		// just a single user
		else
		{
			$recipients[] = addslashes(htmlspecialchars_uni($pm['recipients']));
		}
		// query recipients
		$checkusers = $DB_site->query("
			SELECT user.*, usertextfield.*
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
			WHERE username='" . implode('\' OR username=\'', $recipients) . "'
			ORDER BY user.username
		");

		// build array of checked users
		while ($checkuser = $DB_site->fetch_array($checkusers))
		{
			$checkuser = array_merge($checkuser, convert_bits_to_array($checkuser['options'], $_USEROPTIONS));
			$arrkey = vbstrtolower($checkuser['username']);

			$perms = fetch_permissions(0, $checkuser['userid'], $checkuser);
			if ($perms['pmquota'] < 1) // can't use pms
			{
				if ($checkuser['options'] & $_USEROPTIONS['receivepm'])
				{	// This will cause the 'can't receive pms' error below to be triggered
					$checkuser['options'] -= $_USEROPTIONS['receivepm'];
				}
			}

			$checkedusers["$arrkey"] = $checkuser;
		}

		// check for max allowed recipients
		if ($permissions['pmsendmax'] > 0)
		{
			$numusers = sizeof($checkedusers);
			if ($numusers > $permissions['pmsendmax'])
			{
				eval('$errors[] = "' . fetch_phrase('pmtoomanyrecipients', PHRASETYPEID_ERROR) . '";');
			}
		}

		// check to see if any recipients were not found
		foreach ($recipients AS $recipient)
		{
			$recipient = stripslashes(vbstrtolower($recipient));
			if (!isset($checkedusers["$recipient"]))
			{
				$notfound[] = $recipient;
			}
		}

		if (!empty($notfound)) // error - some users were not found
		{
			$notfoundhtml = implode('</li><li>', $notfound);
			eval('$errors[] = "' . fetch_phrase('pmrecipientsnotfound', PHRASETYPEID_ERROR) . '";');
		}

		// run through recipients to check if we can insert the message
		foreach ($checkedusers AS $username => $user)
		{
			if (!($user['options'] & $_USEROPTIONS['receivepm']))
			{
				// recipient has private messaging disabled
				eval('$errors[] = "' . fetch_phrase('pmrecipturnedoff', PHRASETYPEID_ERROR) . '";');
			}
			else
			{
				// don't allow a tachy user to sends pms to anyone other than himself
				if (in_coventry($bbuserinfo['userid'], true) AND $user['userid'] != $bbuserinfo['userid'])
				{
					$tostring["$user[userid]"] = $user['username'];
					continue;
				}
				else if (strpos(" $user[ignorelist] ", " $bbuserinfo[userid] ") !== false)
				{
					// recipient is ignoring sender
					if ($permissions['adminpermissions'] & CANCONTROLPANEL)
					{
						$sendto["$username"] = true;
						$tostring["$user[userid]"] = $user['username'];
					}
					else
					{
						// bbuser is being ignored by recipient - do not send, but do not error
						$tostring["$user[userid]"] = $user['username'];
						continue;
					}
				}
				else
				{
					cache_permissions($user, false);
					if ($user['permissions'] < 1)
					{
						// recipient has no pm permission
						eval('$errors[] = "' . fetch_phrase('pmusernotallowed', PHRASETYPEID_ERROR) . '";');
					}
					else
					{
						if ($user['pmtotal'] >= $user['permissions']['pmquota'])
						{
							// recipient is over their pm quota, what access do they have?
							if ($permissions['adminpermissions'] & CANCONTROLPANEL)
							{
								$sendto["$username"] = true;
								$tostring["$user[userid]"] = $user['username'];
							}
							else if ($user['usergroupid'] != 3 AND $user['usergroupid'] != 4)
							{
								$touserinfo = &$user;
								eval(fetch_email_phrases('pmboxfull', $touserinfo['languageid'], '', 'email'));
								vbmail($touserinfo['email'], $emailsubject, $emailmessage, true);
								eval('$errors[] = "' . fetch_phrase('pmquotaexceeded', PHRASETYPEID_ERROR) . '";');
							}
							else
							{
								eval('$errors[] = "' . fetch_phrase('pmquotaexceeded', PHRASETYPEID_ERROR) . '";');
							}
						}
						else
						{
							// okay, send the message!
							$sendto["$username"] = true;
							$tostring["$user[userid]"] = $user['username'];
						}
					}
				}
			}
		}

		// process errors if there are any
		if (!empty($errors))
		{
			define('PMPREVIEW', 1);
			$preview = construct_errors($errors); // this will take the preview's place
			$_REQUEST['do'] = 'newpm';
		}
		// no errors, so insert the message!
		else
		{
			// if there are no errors, insert the message(s)
			if (!empty($sendto) OR $pm['savecopy'])
			{
				$pmtotalSql = array(); // users to update totals without pmpopup
				$pmpopupSql = array(); // users to update totals with pmpopup
				$receiptSql = array(); // receipts to insert

				$title = addslashes(htmlspecialchars_uni(fetch_censored_text($pm['title'])));
				$message = addslashes(fetch_censored_text($pm['message']));
				$signature = intval($pm['signature']);
				$iconid = intval($pm['iconid']);
				$disablesmilies = iif($pm['disablesmilies'], 0, 1);

				// insert private message text
				$DB_site->query("INSERT INTO " . TABLE_PREFIX . "pmtext\n\t(fromuserid, fromusername, title, message, touserarray, iconid, dateline, showsignature, allowsmilie)\nVALUES\n\t($bbuserinfo[userid], '" . addslashes($bbuserinfo['username']) . "', '$title', '$message', '" . addslashes(serialize($tostring)) . "', $iconid, " . TIMENOW . ", $signature, $disablesmilies)");

				// get the inserted private message id
				$pmtextid = $DB_site->insert_id();

				// save a copy into $bbuserinfo's sent items folder
				if ($pm['savecopy'])
				{
					$DB_site->query("INSERT INTO " . TABLE_PREFIX . "pm (pmtextid, userid, folderid, messageread) VALUES ($pmtextid, $bbuserinfo[userid], -1, 1)");
					$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "user SET pmtotal=pmtotal+1 WHERE userid=$bbuserinfo[userid]");
				}

				foreach (array_keys($sendto) AS $username)
				{
					$user = &$checkedusers["$username"];
					$DB_site->query("INSERT INTO " . TABLE_PREFIX . "pm (pmtextid, userid) VALUES ($pmtextid, $user[userid])");
					if ($pm['receipt'])
					{
						$receiptSql[] = "(" . $DB_site->insert_id() . ", $bbuserinfo[userid], $user[userid], '" . addslashes($user['username']) . "', '$title', " . TIMENOW . ")";
					}
					if ($user['pmpopup'])
					{
						$pmpopupSql[] = $user['userid'];
					}
					else
					{
						$pmtotalSql[] = $user['userid'];
					}
					if ($user['emailonpm'] AND $user['usergroupid'] != 3 AND $user['usergroupid'] != 4)
					{
						$touserinfo = &$user;
						eval(fetch_email_phrases('pmreceived', $touserinfo['languageid'], '', 'email'));
						vbmail($touserinfo['email'], $emailsubject, $emailmessage);
					}
				}

				// insert receipts
				if (!empty($receiptSql) AND $cantrackpm)
				{
					$DB_site->query("INSERT INTO " . TABLE_PREFIX . "pmreceipt\n\t(pmid, userid, touserid, tousername, title, sendtime)\nVALUES\n\t" . implode(",\n\t", $receiptSql));
				}

				// update recipient pm totals (no pm-popup)
				if (!empty($pmtotalSql))
				{
					$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "user SET pmtotal=pmtotal+1, pmunread=pmunread+1 WHERE userid IN(" . implode(', ', $pmtotalSql) . ")");
				}

				// update recipient pm totals (with pm-popup)
				if (!empty($pmpopupSql))
				{
					$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "user SET pmtotal=pmtotal+1, pmunread=pmunread+1, pmpopup=2 WHERE userid IN(" . implode(', ', $pmpopupSql) . ")");
				}

				// update replied to / forwarded message 'messageread' status
				if (!empty($pm['pmid']))
				{
					$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "pm SET messageread=" . iif($pm['forward'], 3, 2) . " WHERE userid=$bbuserinfo[userid] AND pmid=" . intval($pm['pmid']));
				}
			}

			$url = "private.php?$session[sessionurl]";
			eval(print_standard_redirect('pm_messagesent'));
		}
	}
}

// ############################### start new pm ###############################
// form for creating a new private message
if ($_REQUEST['do'] == 'newpm')
{
	require_once('./includes/functions_newpost.php');

	// do initial checkboxes
	$checked = array();
	$signaturechecked = iif($bbuserinfo['signature'] != '', HTML_CHECKED);

	// setup for preview display
	if (defined('PMPREVIEW'))
	{
		$postpreview = &$preview;
		$pm['title'] = htmlspecialchars_uni($pm['title']);
		$pm['message'] = htmlspecialchars_uni($pm['message']);
		$pm['recipients'] = htmlspecialchars_uni($pm['recipients']);
		construct_checkboxes($pm);
	}
	else
	{
		// set up for PM reply / forward
		if ($_REQUEST['pmid'])
		{
			if ($pm = $DB_site->query_first("
				SELECT pm.*, pmtext.*
				FROM " . TABLE_PREFIX . "pm AS pm
				LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
				WHERE pm.userid=$bbuserinfo[userid] AND pm.pmid=" . intval($_REQUEST['pmid']) . "
			"))
			{
				// quote reply
				$originalposter = fetch_quote_username($pm['fromusername']);

				// allow quotes to remain with an optional request variable
				// this will fix a problem with forwarded PMs and replying to them
				if ($_REQUEST['stripquote'])
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

				if ($_REQUEST['forward'])
				{
					$pm['title'] = $vbphrase['forward_prefix'] . " $pm[title]";
					$pm['recipients'] = '';
					$pm['forward'] = 1;
				}
				else
				{
					$pm['title'] = $vbphrase['reply_prefix'] . " $pm[title]";
					$pm['recipients'] = &$pm['fromusername'];
					$pm['forward'] = 0;
				}
			}
			else
			{
				$idname = $vbphrase['private_message'];
				eval(print_standard_error('invalidid'));
			}
		}
		// set up for standard new PM
		else
		{
			// insert username(s) of specified recipients
			if ($_REQUEST['userid'])
			{
				$recipients = array();
				if (is_array($_REQUEST['userid']))
				{
					foreach ($_REQUEST['userid'] AS $recipient)
					{
						$recipients[] = intval($recipient);
					}
				}
				else
				{
					$recipients[] = intval($_REQUEST['userid']);
				}
				$users = $DB_site->query("SELECT username FROM " . TABLE_PREFIX . "user AS user WHERE userid IN(" . implode(', ', $recipients) . ")");
				$recipients = array();
				while ($user = $DB_site->fetch_array($users))
				{
					$recipients[] = $user['username'];
				}
				if (empty($recipients))
				{
					$pm['recipients'] = '';
				}
				else
				{
					$pm['recipients'] = implode('; ', $recipients);
				}
			}
		}

		construct_checkboxes(array(
			'savecopy' => true,
			'parseurl' => true,
			'signature' => iif($bbuserinfo['signature'] !== '', true)
		));
	}

	$folderjump = construct_folder_jump(0, $pm['folderid']);

	$posticons = construct_icons($pm['iconid'], $vboptions['privallowicons']);

	require_once('./includes/functions_editor.php');

	// set message box width to usercp size
	$stylevar['messagewidth'] = $stylevar['messagewidth_usercp'];
	construct_edit_toolbar($pm['message'], 0, 'privatemessage', iif($vboptions['privallowsmilies'], 1, 0));

	// generate navbar
	if ($pm['pmid'])
	{
		$navbits["private.php?$session[sessionurl]folderid=$pm[folderid]"] = $foldernames["$pm[folderid]"];
		$navbits["private.php?$session[sessionurl]do=showpm&amp;pmid=$pm[pmid]"] = $pm['title'];
		$navbits[''] = iif($pm['forward'], $vbphrase['forward_message'], $vbphrase['reply_to_private_message']);
	}
	else
	{
		$navbits[''] = $vbphrase['post_new_private_message'];
	}

	$show['sendmax'] = iif($permissions['pmsendmax'], true, false);

	// build forum rules
	$bbcodeon = iif($vboptions['privallowbbcode'], $vbphrase['on'], $vbphrase['off']);
	$imgcodeon = iif($vboptions['privallowbbimagecode'], $vbphrase['on'], $vbphrase['off']);
	$htmlcodeon = iif($vboptions['privallowhtml'], $vbphrase['on'], $vbphrase['off']);
	$smilieson = iif($vboptions['privallowsmilies'], $vbphrase['on'], $vbphrase['off']);

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	eval('$forumrules = "' . fetch_template('forumrules') . '";');

	$templatename = 'pm_newpm';
}

// ############################### start show pm ###############################
// show a private message
if ($_REQUEST['do'] == 'showpm')
{
	require_once('./includes/functions_showthread.php');
	require_once('./includes/functions_bigthree.php');

	globalize($_REQUEST, array('pmid' => INT));

	$pm = $DB_site->query_first("
		SELECT
			pm.*, pmtext.*,
			" . iif($vboptions['privallowicons'], "icon.title AS icontitle, icon.iconpath,") . "
			IF(ISNULL(pmreceipt.pmid), 0, 1) AS receipt, pmreceipt.readtime, pmreceipt.denied
		FROM " . TABLE_PREFIX . "pm AS pm
		LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
		" . iif($vboptions['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
		LEFT JOIN " . TABLE_PREFIX . "pmreceipt AS pmreceipt ON(pmreceipt.pmid = pm.pmid)
		WHERE pm.userid=$bbuserinfo[userid] AND pm.pmid=$pmid
	");

	if (!$pm)
	{
		$idname = $vbphrase['private_message'];
		eval(print_standard_error('invalidid'));
	}

	$folderjump = construct_folder_jump(0, $pm['folderid']);

	// do read receipt
	$show['receiptprompt'] = false;
	if ($pm['receipt'] == 1 AND $pm['readtime'] == 0 AND $pm['denied'] == 0)
	{
		if ($permissions['pmpermissions'] & CANDENYPMRECEIPTS)
		{
			$receipt_question_js = construct_phrase($vbphrase['x_has_requested_a_read_receipt'], unhtmlspecialchars($pm['fromusername']));
			$onload = " onunload=\"askReceipt();\"";
			$show['receiptprompt'] = true;

			// set it to denied just now as some people might have ad blocking that stops the popup appearing
			$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "pmreceipt SET denied = 1 WHERE pmid = $pmid");
		}
		else
		{
			$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "pmreceipt SET readtime = " . TIMENOW . " WHERE pmid = $pmid");
		}
	}
	else if ($pm['receipt'] == 1 AND $pm['denied'] == 1)
	{
		$show['receiptprompt'] = true;
	}

	// integrate user info for the message
	$userid = $pm['fromuserid'];
	$fromuserinfo = fetch_userinfo($userid, 3);

	$pm = array_merge($pm, $fromuserinfo);

	// parse the message
	$pm['message'] = parse_pm_bbcode($pm['message'], $pm['allowsmilie']);

	$show['spacer'] = false;
	$postbit = construct_postbit($pm, 'postbit', 'pm');

	// update message to show read
	if ($pm['messageread'] == 0)
	{
		$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "pm SET messageread=1 WHERE userid=$bbuserinfo[userid] AND pmid=$pmid");
		$DB_site->shutdown_query("UPDATE " . TABLE_PREFIX . "user SET pmunread=pmunread-1 WHERE userid=$bbuserinfo[userid]");
	}

	// generate navbar
	$navbits["private.php?$session[sessionurl]folderid=$pm[folderid]"] = $foldernames["$pm[folderid]"];
	$navbits[''] = $pm['title'];

	$templatename = 'pm_showpm';
}

// ############################### start pm folder view ###############################
if ($_REQUEST['do'] == 'messagelist')
{
	globalize($_REQUEST, array('folderid' => INT, 'perpage' => INT, 'pagenumber' => INT));

	$folderjump = construct_folder_jump(0, $folderid);
	$foldername = $foldernames["$folderid"];

	// count receipts
	$receipts = $DB_site->query_first("
		SELECT
			SUM(IF(readtime <> 0, 1, 0)) AS confirmed,
			SUM(IF(readtime = 0, 1, 0)) AS unconfirmed
		FROM " . TABLE_PREFIX . "pmreceipt
		WHERE userid = $bbuserinfo[userid]
	");

	// get ignored users
	$ignoreusers = preg_split('#\s+#s', $bbuserinfo['ignorelist'], -1, PREG_SPLIT_NO_EMPTY);

	$totalmessages = intval($messagecounters["$folderid"]);

	// build pm counters bar
	$tdwidth = array();
	$tdwidth['folder'] = ceil($totalmessages / $permissions['pmquota'] * 100);
	$tdwidth['total'] = ceil($bbuserinfo['pmtotal'] / $permissions['pmquota'] * 100) - $tdwidth['folder'];
	$tdwidth['quota'] = 100 - $tdwidth['folder'] - $tdwidth['total'];

	$show['thisfoldertotal'] = iif($tdwidth['folder'], true, false);
	$show['allfolderstotal'] = iif($tdwidth['total'], true, false);
	$show['pmicons'] = iif($vboptions['privallowicons'], true, false);

	// build navbar
	$navbits[''] = $foldernames["$folderid"];

	if ($totalmessages == 0)
	{
		$show['messagelist'] = false;
	}
	else
	{
		$show['messagelist'] = true;

		// get a sensible value for $perpage
		sanitize_pageresults($totalmessages, $pagenumber, $perpage, $vboptions['pmmaxperpage'], $vboptions['pmperpage']);
		// work out the $startat value
		$startat = ($pagenumber - 1) * $perpage;

		// array to store private messages in period groups
		$pm_period_groups = array();

		// query private messages
		$pms = $DB_site->query("
			SELECT pm.*, pmtext.*
				" . iif($vboptions['privallowicons'], ", icon.title AS icontitle, icon.iconpath") . "
			FROM " . TABLE_PREFIX . "pm AS pm
			LEFT JOIN " . TABLE_PREFIX . "pmtext AS pmtext ON(pmtext.pmtextid = pm.pmtextid)
			" . iif($vboptions['privallowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = pmtext.iconid)") . "
			WHERE pm.userid=$bbuserinfo[userid] AND pm.folderid=$folderid
			ORDER BY pmtext.dateline DESC
			LIMIT $startat, $perpage
		");
		while ($pm = $DB_site->fetch_array($pms))
		{
			$pm_period_groups[ fetch_period_group($pm['dateline']) ]["$pm[pmid]"] = $pm;
		}
		$DB_site->free_result($pms);

		// display returned messages
		$show['pmcheckbox'] = true;

		require_once('./includes/functions_bigthree.php');

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
			$groupid = $folderid . '_' . $groupid;
			$collapseobj_groupid = &$vbcollapse["collapseobj_pmf$groupid"];
			$collapseimg_groupid = &$vbcollapse["collapseimg_pmf$groupid"];

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

					$pm['senddate'] = vbdate($vboptions['dateformat'], $pm['dateline']);
					$pm['sendtime'] = vbdate($vboptions['timeformat'], $pm['dateline']);

					// get userbit
					if ($folderid == -1)
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
						$userid = &$pm['fromuserid'];
						$username = &$pm['fromusername'];
						eval('$userbit = "' . fetch_template('pm_messagelistbit_user') . '";');
					}

					$show['pmicon'] = iif($pm['iconpath'], true, false);
					$show['unread'] = iif(!$pm['messageread'], true, false);

					eval('$messagelistbits .= "' . fetch_template('pm_messagelistbit') . '";');
				}
			}

			// free up memory not required any more
			unset($pm_period_groups["$groupid"]);

			// build group template
			eval('$messagelist_periodgroups .= "' . fetch_template('pm_messagelist_periodgroup') . '";');
		}

		// build pagenav
		$pagenav = construct_page_nav($totalmessages, "private.php?$session[sessionurl]folderid=$folderid&pp=$perpage");
	}

	if ($folderid == -1)
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

	// print page
	eval('$HTML = "' . fetch_template($templatename) . '";');
	eval('print_output("' . fetch_template('USERCP_SHELL') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13879 $
|| ####################################################################
\*======================================================================*/
?>