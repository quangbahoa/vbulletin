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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'reputation');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('reputation', 'reputationlevel');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'reputation',
	'reputation_adjust',
	'reputation_ajax',
	'reputation_reasonbits',
	'reputation_yourpost',
	'reputationbit',
	'postbit_reputation',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_reputation.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (!$vbulletin->options['reputationenable'])
{
	eval(standard_error(fetch_error('reputationdisabled')));
}

if (!$postinfo['postid'] OR !$threadinfo['threadid'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR (!$postinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts'))OR $postinfo['isdeleted'] OR $threadinfo['isdeleted'])
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['post'], $vbulletin->options['contactuslink'])));
}

$forumperms = fetch_permissions($threadinfo['forumid']);

if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
{
	print_no_permission();
}
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
{
	print_no_permission();
}

if ((!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuserep']) AND $vbulletin->userinfo['userid'] != $postinfo['userid']) OR !$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

$userid = $db->query_first_slave("SELECT userid FROM " . TABLE_PREFIX . "post WHERE postid = $postid");
$userinfo = fetch_userinfo($userid['userid']);

$userid = $userinfo['userid'];

if (!($vbulletin->usergroupcache["$userinfo[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
{
	eval(standard_error(fetch_error('reputationbanned')));
}

if (!$userid)
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink'])));
}

($hook = vBulletinHook::fetch_hook('reputation_start')) ? eval($hook) : false;

if ($_POST['do'] == 'addreputation')
{  // adjust reputation ratings

	$vbulletin->input->clean_array_gpc('p', array(
		'reputation' => TYPE_NOHTML,
		'reason'     => TYPE_STR,
		'ajax'       => TYPE_BOOL,
	));

	if ($vbulletin->GPC['ajax'])
	{
		$vbulletin->GPC['reason'] = convert_urlencoded_unicode($vbulletin->GPC['reason']);
	}

	if ($userid == $vbulletin->userinfo['userid'])
	{
		eval(standard_error(fetch_error('reputationownpost')));
	}

	$score = fetch_reppower($vbulletin->userinfo, $permissions, $vbulletin->GPC['reputation']);
	if ($score < 0 AND empty($vbulletin->GPC['reason']))
	{
		eval(standard_error(fetch_error('reputationreason')));
	}

	// Check if the user has already reputation this post
	if ($repeat = $db->query_first("
		SELECT postid
		FROM " . TABLE_PREFIX . "reputation
		WHERE postid = $postid AND
			whoadded = " . $vbulletin->userinfo['userid']
	))
	{
		eval(standard_error(fetch_error('reputationsamepost')));
	}

	($hook = vBulletinHook::fetch_hook('reputation_add_start')) ? eval($hook) : false;

	if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{
		if ($vbulletin->options['maxreputationperday'] >= $vbulletin->options['reputationrepeat'])
		{
			$klimit = ($vbulletin->options['maxreputationperday'] + 1);
		}
		else
		{
			$klimit = ($vbulletin->options['reputationrepeat'] + 1);
		}
		$checks = $db->query_read("
			SELECT userid, dateline
			FROM " . TABLE_PREFIX . "reputation
			WHERE whoadded = " . $vbulletin->userinfo['userid'] . "
			ORDER BY dateline DESC
			LIMIT 0, $klimit
		");

		$i = 0;
		while ($check = $db->fetch_array($checks))
		{
			if (($i < $vbulletin->options['reputationrepeat']) AND ($check['userid'] == $userid))
			{
				eval(standard_error(fetch_error('reputationsameuser', $userinfo['username'])));
			}
			if (($i + 1) == $vbulletin->options['maxreputationperday'] AND (($check['dateline'] + 86400) > TIMENOW))
			{
				eval(standard_error(fetch_error('reputationtoomany')));
			}
			$i++;
		}
	}

	$userinfo['reputation'] += $score;

	// Determine this user's reputationlevelid.
	$reputationlevel = $db->query_first_slave("
		SELECT reputationlevelid
		FROM " . TABLE_PREFIX . "reputationlevel
		WHERE $userinfo[reputation] >= minimumreputation
		ORDER BY minimumreputation
		DESC LIMIT 1
	");

	// init user data manager
	$userdata = datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
	$userdata->set_existing($userinfo);
	$userdata->set('reputation', $userinfo['reputation']);
	$userdata->set('reputationlevelid', intval($reputationlevel['reputationlevelid']));

	($hook = vBulletinHook::fetch_hook('reputation_add_process')) ? eval($hook) : false;

	$userdata->pre_save();

	/*insert query*/
	$db->query_write("
		INSERT IGNORE INTO " . TABLE_PREFIX . "reputation (postid, reputation, userid, whoadded, reason, dateline)
		VALUES ($postid, $score, $userid, " . $vbulletin->userinfo['userid'] . ", '" . $db->escape_string(fetch_censored_text($vbulletin->GPC['reason'])) . "','" . TIMENOW . "')
	");
	if ($db->affected_rows() == 0)
	{
		// attempt at a flood!
		eval(standard_error(fetch_error('reputationsamepost')));
	}

	$userdata->save();

	($hook = vBulletinHook::fetch_hook('reputation_add_complete')) ? eval($hook) : false;

	if ($score < 0)
	{
		$redirect_phrase = 'redirect_reputationminus';
	}
	else
	{
		$redirect_phrase = 'redirect_reputationadd';
	}

	if (!$vbulletin->GPC['ajax'])
	{
		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "&amp;p=$postid#post$postid";
		eval(print_standard_redirect($redirect_phrase));
		// redirect or close window here
	}
	else
	{
		cache_permissions($userinfo);
		$post = $userinfo;
		$repdisplay = fetch_reputation_image($post, $userinfo['permissions']);

		require_once(DIR . '/includes/class_xml.php');
		require_once(DIR . '/includes/functions_misc.php');
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_tag('reputation', process_replacement_vars(fetch_phrase($redirect_phrase, 'frontredirect', 'redirect_')), array(
			'reppower'   => fetch_reppower($userinfo, $userinfo['permissions']),
			'repdisplay' => process_replacement_vars($post['reputationdisplay']),
			'userid'     => $userinfo['userid'],
		));
		$xml->print_xml();
	}
}
else
{
	$vbulletin->input->clean_array_gpc('p', array(
		'ajax' => TYPE_BOOL,
	));

	if ($vbulletin->userinfo['userid'] == $userid)
	{ // is this your own post?

		($hook = vBulletinHook::fetch_hook('reputation_viewown_start')) ? eval($hook) : false;

		$postreputations = $db->query_read_slave("
			SELECT reputation, reason
			FROM " . TABLE_PREFIX . "reputation
			WHERE postid = $postid
			ORDER BY dateline DESC
		");

		if ($db->num_rows($postreputations) > 0)
		{

			require_once(DIR . '/includes/class_bbcode.php');
			$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

			while ($postreputation = $db->fetch_array($postreputations))
			{
				$total += $postreputation['reputation'];
				if ($postreputation['reputation'] > 0)
				{
					$posneg = 'pos';
				}
				else if ($postreputation['reputation'] < 0)
				{
					$posneg = 'neg';
				}
				else
				{
					$posneg = 'balance';
				}
				if (!empty($postreputation['reason']))
				{
					$reason = $bbcode_parser->parse($postreputation['reason']);
				}
				else
				{
					$reason = $vbphrase['n_a'];
				}

				exec_switch_bg();

				($hook = vBulletinHook::fetch_hook('reputation_viewown_bit')) ? eval($hook) : false;

				eval('$reputation_reasonbits .= "' . fetch_template('reputation_reasonbits') . '";');
			}

			if ($total == 0)
			{
				$reputation = $vbphrase['even'];
			}
			else if ($total > 0 AND $total <= 5)
			{
				$reputation = $vbphrase['somewhat_positive'];
			}
			else if ($total > 5 AND $total <= 15)
			{
				$reputation = $vbphrase['positive'];
			}
			else if ($total > 15 AND $total <= 25)
			{
				$reputation = $vbphrase['very_positive'];
			}
			else if ($total > 25)
			{
				$reputation = $vbphrase['extremely_positive'];
			}
			else if ($total < 0 AND $total >= -5)
			{
				$reputation = $vbphrase['somewhat_negative'];
			}
			else if ($total < -5 AND $total >= -15)
			{
				$reputation = $vbphrase['negative'];
			}
			else if ($total < -15 AND $total >= -25)
			{
				$reputation = $vbphrase['very_negative'];
			}
			else if ($total < -25)
			{
				$reputation = $vbphrase['extremely_negative'];
			}
			($hook = vBulletinHook::fetch_hook('reputation_viewown_complete')) ? eval($hook) : false;
		}
		else
		{
			($hook = vBulletinHook::fetch_hook('reputation_viewown_complete')) ? eval($hook) : false;
			eval(standard_error(fetch_error('reputation_yourpost', $vbulletin->userinfo['reputation'])));
		}

		$show['ajax'] = ($vbulletin->GPC['ajax']);
		eval('$reputationbit = "' . fetch_template('reputation_yourpost') . '";');

	}
	else
	{  // Not Your Post

		if ($repeat = $db->query_first_slave("
			SELECT postid
			FROM " . TABLE_PREFIX . "reputation
			WHERE postid = $postid AND
				whoadded = " . $vbulletin->userinfo['userid']
			))
		{
			eval(standard_error(fetch_error('reputationsamepost')));
		}

		if (!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
		{
			if ($vbulletin->options['maxreputationperday'] >= $vbulletin->options['reputationrepeat'])
			{
				$klimit = ($vbulletin->options['maxreputationperday'] + 1);
			}
			else
			{
				$klimit = ($vbulletin->options['reputationrepeat'] + 1);
			}
			$checks = $db->query_read_slave("
				SELECT userid, dateline
				FROM " . TABLE_PREFIX . "reputation
				WHERE whoadded = " . $vbulletin->userinfo['userid'] . "
				ORDER BY dateline DESC
				LIMIT 0, $klimit
			");

			$i = 0;
			while ($check = $db->fetch_array($checks))
			{
				if (($i < $vbulletin->options['reputationrepeat']) AND ($check['userid'] == $userid))
				{
					eval(standard_error(fetch_error('reputationsameuser', $userinfo['username'])));
				}
				if (($i + 1) == $vbulletin->options['maxreputationperday'] AND (($check['dateline'] + 86400) > TIMENOW))
				{
					eval(standard_error(fetch_error('reputationtoomany')));
				}
				$i++;
			}
		}

		$show['negativerep'] = iif($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cannegativerep'], true, false);
		$show['ajax'] = ($vbulletin->GPC['ajax']);

		($hook = vBulletinHook::fetch_hook('reputation_form')) ? eval($hook) : false;

		$url = $vbulletin->url;
		eval('$reputationbit = "' . fetch_template('reputationbit') . '";');
	}

	if ($vbulletin->GPC['ajax'])
	{
		eval('$reputation = "' . fetch_template('reputation_ajax') . '";');

		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_tag('reputationbit', process_replacement_vars($reputation));
		$xml->print_xml();
	}

	// draw nav bar
	$navbits = array();
	$parentlist = array_reverse(explode(',', $foruminfo['parentlist']));
	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}
	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "p=$postid"] = $threadinfo['prefix_plain_html'] . ' ' . $threadinfo['title'];
	$navbits[''] = $vbphrase['reputation'];
	$navbits = construct_navbits($navbits);

	eval('$navbar = "' . fetch_template('navbar') . '";');

	eval('print_output("' . fetch_template('reputation') . '");');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
