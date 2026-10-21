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
define('THIS_SCRIPT', 'poll');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('poll', 'posting');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache'
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'editpoll',
	'forumrules',
	'newpoll',
	'newpost_usernamecode',
	'polleditbit',
	'pollnewbit',
	'pollpreview',
	'pollresult',
	'pollresults',
	'pollresults_table'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_bbcode_alt.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'newpoll';
}

// shortcut function to make the $navbits for the navbar...
function construct_poll_nav($foruminfo, $threadinfo)
{
	global $vbulletin, $vbphrase;

	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));

	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $vbulletin->forumcache["$forumID"]['title'];
		$navbits['forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$forumID"] = $forumTitle;
	}
	$navbits['showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]"] = $threadinfo['prefix_plain_html'] . ' ' . $threadinfo['title'];

	switch ($_REQUEST['do'])
	{
		case 'newpoll':  $navbits[''] = $vbphrase['post_a_poll']; break;
		case 'polledit': $navbits[''] = $vbphrase['edit_poll']; break;
		case 'showresults': $navbits[''] = $vbphrase['view_poll_results']; break;
		// are there more?
	}

	return construct_navbits($navbits);
}

if ($threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts') AND $vbulletin->userinfo['userid'] != $threadinfo['postuserid']))
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $vbulletin->options['contactuslink'])));
}

if (!$foruminfo['forumid'])
{
	eval(standard_error(fetch_error('invalidid', $vbphrase['forum'], $vbulletin->options['contactuslink'])));
}

// check permissions
$forumperms = fetch_permissions($foruminfo['forumid']);
if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
{
	print_no_permission();
}

if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
{
	if (($_POST['do'] != 'postpoll' AND $_REQUEST['do'] != 'newpoll') OR $threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR !$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}
}

// check if there is a forum password and if so, ensure the user has it set
verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

($hook = vBulletinHook::fetch_hook('poll_start')) ? eval($hook) : false;

// ############################### start post poll ###############################
if ($_POST['do'] == 'postpoll')
{
	// Reused in template
	$polloptions = $vbulletin->input->clean_gpc('p', 'polloptions', TYPE_UINT);
	$question = $vbulletin->input->clean_gpc('p', 'question', TYPE_NOHTML);
	$timeout = $vbulletin->input->clean_gpc('p', 'timeout', TYPE_UINT);

	$vbulletin->input->clean_array_gpc('p', array(
		'preview'        => TYPE_STR,
		'updatenumber'   => TYPE_STR,
		'public'         => TYPE_BOOL,
		'parseurl'       => TYPE_BOOL,
		'multiple'       => TYPE_BOOL,
		'options'        => TYPE_ARRAY_STR
	));

	($hook = vBulletinHook::fetch_hook('poll_post_start')) ? eval($hook) : false;

	if ($threadinfo['pollid'])
	{
		eval(standard_error(fetch_error('pollalready')));
	}

	if ($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] AND !can_moderate($foruminfo['forumid'], 'caneditpoll'))
	{
		print_no_permission();
	}

	// check permissions
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostpoll']))
	{
		print_no_permission();
	}

	if (!can_moderate($threadinfo['forumid'], 'caneditpoll') AND $vbulletin->options['addpolltimeout'] AND TIMENOW - ($vbulletin->options['addpolltimeout'] * 60) > $threadinfo['dateline'])
	{
		eval(standard_error(fetch_error('polltimeout', $vbulletin->options['addpolltimeout'])));
	}

	if (!$threadinfo['open'])
	{
		eval(standard_error(fetch_error('threadclosed')));
	}

	if ($vbulletin->options['maxpolloptions'] > 0 AND $polloptions > $vbulletin->options['maxpolloptions'])
	{
		$polloptions = $vbulletin->options['maxpolloptions'];
	}

	if ($vbulletin->GPC['parseurl'] AND $foruminfo['allowbbcode'])
	{
		require_once(DIR . '/includes/functions_newpost.php');

		$counter = 0;
		while ($counter++ < $polloptions)
		{ // 0..Pollnum-1 we want, as arrays start with 0
			$vbulletin->GPC['options']["$counter"] = convert_url_to_bbcode($vbulletin->GPC['options']["$counter"]);
		}
	}

	// check question and if 2 options or more were given
	$counter = 0;
	$optioncount = 0;
	$badoption = '';
	while ($counter++ < $polloptions)
	{ // 0..Pollnum-1 we want, as arrays start with 0
		if ($vbulletin->options['maxpolllength'] AND vbstrlen($vbulletin->GPC['options']["$counter"]) > $vbulletin->options['maxpolllength'])
		{
			$badoption .= iif($badoption, ', ') . $counter;
		}
		if (!empty($vbulletin->GPC['options']["$counter"]))
		{
			$optioncount++;
		}
	}

	if ($badoption)
	{
		eval(standard_error(fetch_error('polloptionlength', $vbulletin->options['maxpolllength'], $badoption)));
	}

	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	if ($vbulletin->GPC['preview'] != '' OR $vbulletin->GPC['updatenumber'] != '')
	{
		if ($vbulletin->GPC['preview'] != '')
		{
			$previewpost = 1;

			$counter = 0;
			$pollpreview = '';
			$previewquestion = $bbcode_parser->parse(unhtmlspecialchars($question), $foruminfo['forumid'], $foruminfo['allowsmilies']);
			while ($counter++ < $polloptions)
			{
				$pollpreviewbits .= "&nbsp;&nbsp; $counter. &nbsp; " . $bbcode_parser->parse($vbulletin->GPC['options']["$counter"], $foruminfo['forumid'], $foruminfo['allowsmilies']) . '<br />';
			}

			eval('$pollpreview = "' . fetch_template('pollpreview') . '";');
		}

		$checked = array(
			'multiple'       => ($vbulletin->GPC['multiple'] ? 'checked="checked"' : ''),
			'public'         => ($vbulletin->GPC['public'] ? 'checked="checked"' : ''),
			'parseurl'       => ($vbulletin->GPC['parseurl'] ? 'checked="checked"' : ''),
		);

		$_REQUEST['do'] = 'newpoll';
	}
	else
	{
		if ($question == '' OR $optioncount < 2)
		{
			eval(standard_error(fetch_error('noquestionoption')));
		}

		if (TIMENOW + ($vbulletin->GPC['timeout'] * 86400) >= 2147483647)
		{ // maximuim size of a 32 bit integer
			eval(standard_error(fetch_error('maxpolltimeout')));
		}

		// check max images
		if ($vbulletin->options['maximages'])
		{
			$counter = 0;
			while ($counter++ < $polloptions)
			{ // 0..Pollnum-1 we want, as arrays start with 0
				$maximgtest .= $vbulletin->GPC['options']["$counter"];
			}

			$img_parser = new vB_BbCodeParser_ImgCheck($vbulletin, fetch_tag_list());
			$parsedmessage = $img_parser->parse($maximgtest . $question, $foruminfo['forumid'], $foruminfo['allowsmilies'], true);

			require_once(DIR . '/includes/functions_misc.php');
			$imagecount = fetch_character_count($parsedmessage, '<img');
			if ($imagecount > $vbulletin->options['maximages'])
			{
				eval(standard_error(fetch_error('toomanyimages', $imagecount, $vbulletin->options['maximages'])));
			}
		}

		$question = fetch_censored_text($question);
		$counter = 0;
		while ($counter++ < $polloptions)
		{ // 0..Pollnum-1 we want, as arrays start with 0
			$vbulletin->GPC['options']["$counter"] = fetch_censored_text($vbulletin->GPC['options']["$counter"]);
		}

		// Add the poll
		$poll = datamanager_init('Poll', $vbulletin, ERRTYPE_STANDARD);

		$counter = 0;
		while ($counter++ < $polloptions)
		{
			if ($vbulletin->GPC['options']["$counter"] != '')
			{
				$poll->set_option($vbulletin->GPC['options']["$counter"]);
			}
		}

		$poll->set('question',	$question);
		$poll->set('dateline',	TIMENOW);
		$poll->set('active',	'1');
		$poll->set('timeout',	$vbulletin->GPC['timeout']);
		$poll->set('multiple',	$vbulletin->GPC['multiple']);
		$poll->set('public',	$vbulletin->GPC['public']);

		($hook = vBulletinHook::fetch_hook('poll_post_process')) ? eval($hook) : false;

		$pollid = $poll->save();
		//end create new poll

		// update thread
		$threadman = datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
		$threadman->set_existing($threadinfo);
		$threadman->set('pollid', $pollid);
		$threadman->save();

		// update last post icon (if necessary)
		cache_ordered_forums(1);

		if ($vbulletin->forumcache["$threadinfo[forumid]"]['lastthreadid'] == $threadinfo['threadid'])
		{
			$forumdm = datamanager_init('Forum', $vbulletin, ERRTYPE_SILENT);
			$forumdm->set_existing($vbulletin->forumcache["$threadinfo[forumid]"]);
			$forumdm->set('lasticonid', '-1');
			$forumdm->save();
			unset($forumdm);
		}

		// redirect
		if ($threadinfo['visible'] AND $forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])
		{
			$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
		}
		else
		{
			$vbulletin->url = 'forumdisplay.php?' . $vbulletin->session->vars['sessionurl'] . "f=$threadinfo[forumid]";
		}

		($hook = vBulletinHook::fetch_hook('poll_post_complete')) ? eval($hook) : false;

		if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
		{
			eval(print_standard_redirect('redirect_postthanks_nopermission'));
		}
		else
		{
			eval(print_standard_redirect('redirect_postthanks'));
		}

	}
}

// ############################### start new poll ###############################
if ($_REQUEST['do'] == 'newpoll')
{
	// Reused in template.
	$polloptions = $vbulletin->input->clean_gpc('r', 'polloptions', TYPE_UINT);

	($hook = vBulletinHook::fetch_hook('poll_newform_start')) ? eval($hook) : false;

	if ($threadinfo['pollid'])
	{
		eval(standard_error(fetch_error('pollalready')));
	}

	if ($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] AND !can_moderate($foruminfo['forumid'], 'caneditpoll'))
	{
		print_no_permission();
	}

	// check permissions
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostnew']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canpostpoll']))
	{
		print_no_permission();
	}

	if (!can_moderate($threadinfo['forumid'], 'caneditpoll') AND $vbulletin->options['addpolltimeout'] AND TIMENOW - ($vbulletin->options['addpolltimeout'] * 60) > $threadinfo['dateline'])
	{
		eval(standard_error(fetch_error('polltimeout', $vbulletin->options['addpolltimeout'])));
	}

	if (!$threadinfo['open'])
	{
		eval(standard_error(fetch_error('threadclosed')));
	}

	// stop there being too many
	if ($vbulletin->options['maxpolloptions'] > 0 AND $polloptions > $vbulletin->options['maxpolloptions'])
	{
		$polloptions = $vbulletin->options['maxpolloptions'];
	}
	// stop there being too few
	if ($polloptions <= 1)
	{
		$polloptions = 2;
	}

	$polldate = vbdate($vbulletin->options['dateformat'], TIMENOW);
	$polltime = vbdate($vbulletin->options['timeformat'], TIMENOW);

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// draw nav bar
	$navbits = construct_poll_nav($foruminfo, $threadinfo);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	if (!isset($checked['parseurl']))
	{
		$checked['parseurl'] = 'checked="checked"';
		$parseurlchecked = 'checked="checked"';
	}

	require_once(DIR . '/includes/functions_bigthree.php');
	construct_forum_rules($foruminfo, $forumperms);

	$counter = 0;
	while ($counter++ < $polloptions)
	{
		$option['number'] = $counter;
		if (is_array($vbulletin->GPC['options']))
		{
			$option['question'] = htmlspecialchars_uni($vbulletin->GPC['options']["$counter"]);
		}
		eval('$pollnewbits .= "' . fetch_template('pollnewbit') . '";');
	}

	$show['parseurl'] = $foruminfo['allowbbcode'];

	($hook = vBulletinHook::fetch_hook('poll_newform_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('newpoll') . '");');

}

// ############################### start poll edit ###############################
if ($_REQUEST['do'] == 'polledit')
{
	if (!$pollinfo['pollid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['poll'], $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('poll_editform_start')) ? eval($hook) : false;

	// check if user is allowed to do edit
	if (!can_moderate($threadinfo['forumid'], 'caneditpoll'))
	{
		print_no_permission();
	}

	if ($vbulletin->options['maxpolloptions'] > 0 AND $pollinfo['numberoptions'] > $vbulletin->options['maxpolloptions'])
	{
		$pollinfo['numberoptions'] = $vbulletin->options['maxpolloptions'];
	}

	if (!$pollinfo['active'])
	{
		 $pollinfo['closed'] = 'checked="checked"';
	}

	if($pollinfo['public'])
	{
		$show['makeprivate'] = true;
		$pollinfo['public'] = 'checked="checked"';
	}

	$pollinfo['postdate'] = vbdate($vbulletin->options['dateformat'], $pollinfo['dateline']);
	$pollinfo['posttime'] = vbdate($vbulletin->options['timeformat'], $pollinfo['dateline']);

	// draw nav bar
	$navbits = construct_poll_nav($foruminfo, $threadinfo);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	require_once(DIR . '/includes/functions_bigthree.php');
	construct_forum_rules($foruminfo, $forumperms);

	//get options
	$splitoptions = explode('|||', $pollinfo['options']);
	$splitoptions = array_map('rtrim', $splitoptions);

	$splitvotes = explode('|||', $pollinfo['votes']);

	$counter = 0;
	while ($counter++ < $pollinfo['numberoptions'])
	{
		$pollinfo['numbervotes'] += $splitvotes[$counter - 1];
	}

	$counter = 0;
	$pollbits = '';

	$pollinfo['question'] = $pollinfo['question'];

	while ($counter++ < $pollinfo['numberoptions'])
	{
		$option['question'] = htmlspecialchars_uni($splitoptions[$counter - 1]);
		$option['votes'] = $splitvotes[$counter - 1];  //get the vote count for the option
		$option['number'] = $counter;  //number of the option

		eval('$pollbits .= "' . fetch_template('polleditbit') . '";');
	}

	if ($vbulletin->options['maxpolloptions'] > 0)
	{
		$show['additional_option1'] = ($pollinfo['numberoptions'] < $vbulletin->options['maxpolloptions']);
		$show['additional_option2'] = ($pollinfo['numberoptions'] < ($vbulletin->options['maxpolloptions'] - 1));
	}
	else
	{
		$show['additional_option1'] = true;
		$show['additional_option2'] = true;
	}

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	($hook = vBulletinHook::fetch_hook('poll_editform_complete')) ? eval($hook) : false;

	eval('print_output("' . fetch_template('editpoll') . '");');
}

// ############################### start adding the edit to the db ###############################
if ($_POST['do'] == 'updatepoll')
{
	if (!$pollinfo['pollid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['poll'], $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('poll_update_start')) ? eval($hook) : false;

	// check if user is allowed to do edit
	if (!can_moderate($threadinfo['forumid'], 'caneditpoll'))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'closepoll'    => TYPE_BOOL,
		'pollquestion' => TYPE_NOHTML,
		'options'      => TYPE_ARRAY_STR,
		'pollvotes'    => TYPE_ARRAY_UINT,
		'timeout'      => TYPE_UINT,
		'public'       => TYPE_BOOL,
	));

	$poll = datamanager_init('Poll', $vbulletin, ERRTYPE_STANDARD);
	$poll->set_existing($pollinfo);

	//check if there are 2 options or more after edit
	$optioncount = 0;

	foreach ($vbulletin->GPC['options'] AS $counter => $optionvalue)
	{
		if ($optionvalue != '')
		{
			$poll->set_option($optionvalue, $counter - 1, intval($vbulletin->GPC['pollvotes']["$counter"]));
			$optioncount++;
		}
		else
		{
			$poll->set_option('', $counter - 1);
		}

	}

	if ($vbulletin->GPC['pollquestion'] == '' OR $optioncount < 2)
	{
		eval(standard_error(fetch_error('noquestionoption')));
	}

	if (TIMENOW + ($vbulletin->GPC['timeout'] * 86400) >= 2147483647)
	{ // maximuim size of a 32 bit integer
		eval(standard_error(fetch_error('maxpolltimeout')));
	}

	$poll->set('question', $vbulletin->GPC['pollquestion']);
	$poll->set('active', $vbulletin->GPC['closepoll'] ? 0 : 1);
	$poll->set('timeout', $vbulletin->GPC['timeout']);

	// only let a poll go from public to private, not the other way about
	if ($pollinfo['public'])
	{
		$poll->set('public', $vbulletin->GPC['public']);
	}

	($hook = vBulletinHook::fetch_hook('poll_update_process')) ? eval($hook) : false;

	$poll->save();

	$pollinfo['threadid'] = $threadinfo['threadid'];
	require_once(DIR . '/includes/functions_log_error.php');
	log_moderator_action($pollinfo, 'poll_edited');

	($hook = vBulletinHook::fetch_hook('poll_update_complete')) ? eval($hook) : false;

	$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
	eval(print_standard_redirect('redirect_editthanks'));
}

// ############################### start show results without vote ###############################
if ($_REQUEST['do'] == 'showresults')
{
	if (!$pollinfo['pollid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['poll'], $vbulletin->options['contactuslink'])));
	}

	($hook = vBulletinHook::fetch_hook('poll_results_start')) ? eval($hook) : false;

	$counter = 1;
	$pollbits = '';

	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$pollinfo['question'] = $bbcode_parser->parse(unhtmlspecialchars($pollinfo['question']), $foruminfo['forumid'], 1);

	$splitoptions = explode('|||', $pollinfo['options']);
	$splitoptions = array_map('rtrim', $splitoptions);

	$splitvotes = explode('|||', $pollinfo['votes']);

	$pollinfo['numbervotes'] = array_sum($splitvotes);

	if ($vbulletin->userinfo['userid'] > 0)
	{
		$pollvotes = $db->query_read_slave("
			SELECT voteoption
			FROM " . TABLE_PREFIX . "pollvote
			WHERE userid = " . $vbulletin->userinfo['userid'] . " AND
				pollid = $pollid
		");
		$uservote = array();
		while ($pollvote = $db->fetch_array($pollvotes))
		{
			$uservote["$pollvote[voteoption]"] = 1;
		}
	}

	if ($pollinfo['public'])
	{
		$public = $db->query_read_slave("
			SELECT user.userid, user.usergroupid, user.displaygroupid, user.username, voteoption, user.infractiongroupid
			FROM " . TABLE_PREFIX . "pollvote AS pollvote
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (pollvote.userid = user.userid)
			WHERE pollid = $pollinfo[pollid]
			ORDER BY username ASC
		");
		$allnames = array();
		while ($name = $db->fetch_array($public))
		{
			fetch_musername($name);
			$allnames["$name[voteoption]"][] = '<a href="member.php?' . $vbulletin->session->vars['sessionurl'] . "u=$name[userid]\">$name[musername]</a>";
		}
	}

	foreach ($splitvotes AS $index => $value)
	{
		$option['uservote'] = iif($uservote[$index + 1], '*');
		$option['question'] = $bbcode_parser->parse($splitoptions["$index"], $foruminfo['forumid'], true);
		$option['votes'] = $value;  //get the vote count for the option

		if ($option['votes'] <= 0)
		{
			$option['percent'] = 0;
		}
		else if ($pollinfo['multiple'])
		{
			$option['percent'] = vb_number_format(($option['votes'] < $pollinfo['voters']) ? $option['votes'] / $pollinfo['voters'] * 100 : 100, 2);
		}
		else
		{
			$option['percent'] = vb_number_format(($options['votes'] < $pollinfo['numbervotes']) ? $option['votes'] / $pollinfo['numbervotes'] * 100 : 100, 2);
		}

		$option['graphicnumber'] = $counter % 6 + 1;
		$option['barnumber'] = round($option['percent']) * 2;
		$option['remainder'] = 201 - $option['barnumber'];
		$option['votes'] = vb_number_format($option['votes']);

		$option['open'] = $stylevar['left'][0];
		$option['close'] = $stylevar['right'][0];

		$show['pollvoters'] = false;
		if ($pollinfo['public'] AND $value)
		{
			$names = $allnames[($index+1)];
			unset($allnames[($index+1)]);
			if (!empty($names))
			{
				$names = implode(', ', $names);
				$show['pollvoters'] = true;
			}
		}

		($hook = vBulletinHook::fetch_hook('poll_results_bit')) ? eval($hook) : false;

		eval('$pollbits .= "' . fetch_template('pollresult') . '";');
		$counter++;
	}

	if ($pollinfo['multiple'])
	{
		$pollinfo['numbervotes'] = $pollinfo['voters'];
		$show['multiple'] = true;
	}

	if (can_moderate($threadinfo['forumid'], 'caneditpoll'))
	{
		$show['editpoll'] = true;
	}
	else
	{
		$show['editpoll'] = false;
	}

	if ($pollinfo['timeout'])
	{
		$pollendtime = vbdate($vbulletin->options['timeformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
		$pollenddate = vbdate($vbulletin->options['dateformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
		$show['pollenddate'] = true;
	}
	else
	{
		$show['pollenddate'] = false;
	}

	// Phrase parts below
	if ($nopermission)
	{
		$pollstatus = $vbphrase['you_may_not_vote_on_this_poll'];
	}
	else if ($showresults)
	{
		$pollstatus = $vbphrase['this_poll_is_closed'];
	}
	else if ($uservoted)
	{
		$pollstatus = $vbphrase['you_have_already_voted_on_this_poll'];
	}

	// draw nav bar
	$navbits = construct_poll_nav($foruminfo, $threadinfo);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	($hook = vBulletinHook::fetch_hook('poll_results_complete')) ? eval($hook) : false;

	eval('$pollresults = "' . fetch_template('pollresults_table') . '";');
	eval('print_output("' . fetch_template('pollresults') . '");');
}


// ############################### start vote on poll ###############################
if ($_POST['do'] == 'pollvote')
{
	if (!$pollinfo['pollid'])
	{
		eval(standard_error(fetch_error('invalidid', $vbphrase['poll'], $vbulletin->options['contactuslink'])));
	}

	if ($pollinfo['multiple'])
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'optionnumber' => TYPE_ARRAY_BOOL,
		));
	}
	else
	{
		$vbulletin->input->clean_array_gpc('p', array(
			'optionnumber' => TYPE_UINT
		));
	}

	($hook = vBulletinHook::fetch_hook('poll_vote_start')) ? eval($hook) : false;

	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canvote']))
	{
		print_no_permission();
	}

	//check if poll is closed
	if (!$pollinfo['active'] OR !$threadinfo['open'] OR ($pollinfo['dateline'] + ($pollinfo['timeout'] * 86400) < TIMENOW AND $pollinfo['timeout'] != 0))
	{ //poll closed
		 eval(standard_error(fetch_error('pollclosed')));
	}

	//check if an option was selected
	if (!empty($vbulletin->GPC['optionnumber']))
	{
		if (!$vbulletin->userinfo['userid'])
		{
			$voted = intval(fetch_bbarray_cookie('poll_voted', $pollid));
			if ($voted)
			{
				//the user has voted before
				eval(standard_error(fetch_error('useralreadyvote')));
			}
			else
			{
				set_bbarray_cookie('poll_voted', $pollid, 1, 1);
			}
		}
		// Query master to reduce the chance of multiple poll votes
		else if ($uservoteinfo = $db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "pollvote
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND pollid = $pollid
		"))
		{
			//the user has voted before
			eval(standard_error(fetch_error('useralreadyvote')));
		}

		$totaloptions = substr_count($pollinfo['options'], '|||') + 1;

		//Error checking complete, lets get the options
		if ($pollinfo['multiple'])
		{
			$insertsql = '';
			$skip_voters = false;
			foreach ($vbulletin->GPC['optionnumber'] AS $val => $vote)
			{
				$val = intval($val);
				if ($vote AND $val > 0 AND $val <= $totaloptions)
				{
					$pollvote = datamanager_init('PollVote', $vbulletin, ERRTYPE_STANDARD);
					$pollvote->set_info('skip_voters', $skip_voters);
					$pollvote->set('pollid',     $pollid);
					$pollvote->set('votedate',   TIMENOW);
					$pollvote->set('voteoption', $val);
					if (!$vbulletin->userinfo['userid'])
					{
						$pollvote->set('userid', NULL, false);
					}
					else
					{
						$pollvote->set('userid', $vbulletin->userinfo['userid']);
					}
					$pollvote->set('votetype', $val);
					if (!$pollvote->save(true, false, false, false, true))
					{
						$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
						eval(print_standard_redirect('redirect_pollvoteduplicate'));
					}

					$skip_voters = true;
				}
				else
				{
					eval(standard_error(fetch_error('invalid_poll_option')));
				}
			}
		}
		else if ($vbulletin->GPC['optionnumber'] > 0 AND $vbulletin->GPC['optionnumber'] <= $totaloptions)
		{
				$pollvote = datamanager_init('PollVote', $vbulletin, ERRTYPE_STANDARD);
				$pollvote->set('pollid',     $pollid);
				$pollvote->set('votedate',   TIMENOW);
				$pollvote->set('voteoption', $vbulletin->GPC['optionnumber']);
				if (!$vbulletin->userinfo['userid'])
				{
					$pollvote->set('userid', NULL, false);
				}
				else
				{
					$pollvote->set('userid', $vbulletin->userinfo['userid']);
				}
				$pollvote->set('votetype',   0);
				if (!$pollvote->save(true, false, false, false, true))
				{
					$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
					eval(print_standard_redirect('redirect_pollvoteduplicate'));
				}
		}
		else
		{
			eval(standard_error(fetch_error('invalid_poll_option')));
		}

		// make last reply date == last vote date
		if ($vbulletin->options['updatelastpost'])
		{
			// option selected in CP
			$threadman = datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_existing($threadinfo);
			$threadman->set('lastpost', TIMENOW);
			$threadman->save();
		}

		($hook = vBulletinHook::fetch_hook('poll_vote_complete')) ? eval($hook) : false;

		// redirect
		$vbulletin->url = 'showthread.php?' . $vbulletin->session->vars['sessionurl'] . "t=$threadinfo[threadid]";
		eval(print_standard_redirect('redirect_pollvotethanks'));
	}
	else
	{
		($hook = vBulletinHook::fetch_hook('poll_vote_complete')) ? eval($hook) : false;

		eval(standard_error(fetch_error('nopolloptionselected')));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
