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
define('THIS_SCRIPT', 'poll');
define('NO_REGISTER_GLOBALS', 1);

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
	'newpost_disablesmiliesoption',
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
require_once('./includes/functions_bbcodeparse.php');

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
	global $session, $forumcache, $vbphrase;

	$navbits = array();
	$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));

	foreach ($parentlist AS $forumID)
	{
		$forumTitle = $forumcache["$forumID"]['title'];
		$navbits["forumdisplay.php?$session[sessionurl]f=$forumID"] = $forumTitle;
	}
	$navbits["showthread.php?$session[sessionurl]t=$threadinfo[threadid]"] = $threadinfo['title'];

	switch ($_REQUEST['do'])
	{
		case 'newpoll':  $navbits[''] = $vbphrase['post_a_poll']; break;
		case 'polledit': $navbits[''] = $vbphrase['edit_poll']; break;
		case 'showresults': $navbits[''] = $vbphrase['view_poll_results']; break;
		// are there more?
	}

	return construct_navbits($navbits);
}

$idname = $vbphrase['poll'];

// ############################### start post poll ###############################
if ($_POST['do'] == 'postpoll')
{
	globalize($_POST,
		array('parseurl' => INT, 'disablesmilies' => INT, 'multiple' => INT,
			'preview', 'updatenumber', 'timeout' => INT, 'question' => STR_NOHTML, 'public',
			'polloptions' => INT, 'options'
		)
	);

	$threadid = verify_id('thread', $threadid);

	if ($bbuserinfo['userid'] != $threadinfo['postuserid'] AND !can_moderate($foruminfo['forumid']))
	{
		print_no_permission();
	}

	if ($threadinfo['pollid'])
	{
		eval(print_standard_error('error_pollalready'));
	}

	// decode check boxes
	$parseurl = iif($parseurl, 1, 0);
	$allowsmilie = iif($disablesmilies, 0, 1);
	$multiple = iif($multiple, 1, 0);
	$public = iif($public, 1, 0);
	$preview = iif($preview != '', 1, 0);
	$update = iif($updatenumber != '', 1, 0);

	if ($parseurl)
	{
		require_once('./includes/functions_newpost.php');

		$counter = 0;
		while ($counter++ < $polloptions)
		{ // 0..Pollnum-1 we want, as arrays start with 0
			$options["$counter"] = convert_url_to_bbcode($options["$counter"]);
		}
	}

	if ($vboptions['maxpolloptions'] > 0 AND $polloptions > $vboptions['maxpolloptions'])
	{
		$polloptions = $vboptions['maxpolloptions'];
	}

	// check question and if 2 options or more were given
	$counter = 0;
	$optioncount = 0;
	$badoption = '';
	while ($counter++ < $polloptions)
	{ // 0..Pollnum-1 we want, as arrays start with 0
		if ($vboptions['maxpolllength'] AND strlen($options["$counter"]) > $vboptions['maxpolllength'])
		{
			$badoption .= iif($badoption, ', ') . $counter;
		}
		if (!empty($options["$counter"]))
		{
			$optioncount++;
		}
	}

	if ($badoption)
	{
		eval(print_standard_error('error_polloptionlength'));
	}

	if ($preview OR $update)
	{
		if ($preview)
		{
			$previewpost = 1;

			$counter = 0;
			$pollpreview = '';
			$previewquestion = parse_bbcode(unhtmlspecialchars($question), $foruminfo['forumid'], $allowsmilie);
			while ($counter++ < $polloptions)
			{
				$pollpreviewbits .= "&nbsp;&nbsp; $counter. &nbsp; " . parse_bbcode($options["$counter"], $foruminfo['forumid'], $allowsmilie) . '<br />';
			}

			eval('$pollpreview = "' . fetch_template('pollpreview') . '";');
		}

		$checked = array(
			'multiple'       => iif($multiple, HTML_CHECKED, ''),
			'public'         => iif($public, HTML_CHECKED, ''),
			'parseurl'       => iif($parseurl, HTML_CHECKED, ''),
			'disablesmilies' => iif(!$allowsmilie, HTML_CHECKED, ''),
		);

		$_REQUEST['do'] = 'newpoll';
	}
	else
	{
		if (empty($question) OR $optioncount < 2)
		{
			eval(print_standard_error('error_noquestionoption'));
		}

		if (TIMENOW + ($timeout * 86400) >= 2147483647)
		{ // maximuim size of a 32 bit integer
			eval(print_standard_error('error_maxpolltimeout'));
		}
		$forumperms = fetch_permissions($foruminfo['forumid']);
		if (!($forumperms & CANVIEW) OR !($forumperms & CANPOSTNEW) OR !($forumperms & CANPOSTPOLL))
		{
			print_no_permission();
		}

		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

		// check max images
		if ($vboptions['maximages'] != 0)
		{
			$counter = 0;
			while ($counter++ < $polloptions)
			{ // 0..Pollnum-1 we want, as arrays start with 0
				$maximgtest .= $options["$counter"];
			}
			$parsedmessage = parse_bbcode($maximgtest . $question, $forumid, $allowsmilie, 1);

			require_once('./includes/functions_misc.php');
			if (fetch_character_count($parsedmessage, '<img') > $vboptions['maximages'])
			{
				eval(print_standard_error('error_toomanyimages'));
			}
		}

		$question = fetch_censored_text($question);
		$counter = 0;
		while ($counter++ < $polloptions)
		{ // 0..Pollnum-1 we want, as arrays start with 0
			$options["$counter"] = fetch_censored_text($options["$counter"]);
		}

		$optionsstring = '';  //lets create the option/votenumber string
		$votesstring = '';
		$counter = 0;

		while ($counter++ < $polloptions)
		{
			$options["$counter"] = trim($options["$counter"]);
			if ($options["$counter"] != '')
			{
				$options["$counter"] = str_replace('|', ' | ', $options["$counter"]);
				$optionsstring .= '|||' . $options["$counter"]; //||| is delimter, 0 means no votes (as new poll)
				$votesstring .= '|||0';
			}
		}

		if (substr($optionsstring, 0, 3) == '|||')
		{
			$optionsstring = substr($optionsstring, 3);
		}
		if (substr($votesstring, 0, 3) == '|||')
		{
			$votesstring = substr($votesstring, 3);
		}

		$DB_site->query("
			INSERT INTO " . TABLE_PREFIX . "poll (question,dateline,options,votes,active,numberoptions,timeout,multiple,public)
			VALUES ('" . addslashes($question) . "'," . TIMENOW . ",'" . addslashes($optionsstring) . "','" . addslashes($votesstring) . "',1,$optioncount,'" . addslashes($timeout) . "', $multiple, $public)
		");

		$pollid = $DB_site->insert_id();
		//end create new poll


		// update thread
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "thread
			SET pollid = $pollid
			WHERE threadid=$threadinfo[threadid]
		");

		// update last post icon (if necessary)
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "forum
			SET lasticonid = '-1'
			WHERE forumid = $threadinfo[forumid] AND lastthreadid = $threadinfo[threadid]
		");

		// redirect
		if ($threadinfo['visible'])
		{
			$url = "showthread.php?$session[sessionurl]t=$threadinfo[threadid]";
		}
		else
		{
			$url = "forumdisplay.php?$session[sessionurl]f=$threadinfo[forumid]";
		}

		eval(print_standard_redirect('redirect_postthanks'));

	}
}

// ############################### start new poll ###############################
if ($_REQUEST['do'] == 'newpoll')
{

	globalize($_REQUEST, array('polloptions' => INT, 'parseurl', 'options'));

	$threadid = verify_id('thread', $threadid);
	$threadinfo = fetch_threadinfo($threadid);
	if ($threadinfo['pollid'])
	{
		eval(print_standard_error('error_pollalready'));
	}
	$foruminfo = fetch_foruminfo($threadinfo['forumid']);

	if ($bbuserinfo['userid'] != $threadinfo['postuserid'] AND !can_moderate($foruminfo['forumid']))
	{
		print_no_permission();
	}

	// check permissions
	$forumperms = fetch_permissions($foruminfo['forumid']);
	if (!($forumperms & CANVIEW) OR !($forumperms & CANPOSTNEW) OR !($forumperms & CANPOSTPOLL))
	{
		print_no_permission();
	}

	if (!can_moderate($threadinfo['forumid'], 'caneditpoll') AND $vboptions['addpolltimeout'] AND TIMENOW - ($vboptions['addpolltimeout'] * 60) > $threadinfo['dateline'])
	{
		eval(print_standard_error('error_polltimeout'));
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// stop there being too many
	if ($vboptions['maxpolloptions'] > 0 AND $polloptions > $vboptions['maxpolloptions'])
	{
		$polloptions = $vboptions['maxpolloptions'];
	}
	// stop there being too few
	if ($polloptions <= 1)
	{
		$polloptions = 2;
	}

	$polldate = vbdate($vboptions['dateformat'], TIMENOW);
	$polltime = vbdate($vboptions['timeformat'], TIMENOW);

	$currentpage = urlencode("poll.php?do=newpoll&t=$threadinfo[threadid]");
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// draw nav bar
	$navbits = construct_poll_nav($foruminfo, $threadinfo);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	if (!isset($checked['parseurl']))
	{
		$checked['parseurl'] = HTML_CHECKED;
		$parseurlchecked = HTML_CHECKED;
	}

	if ($foruminfo['allowsmilies'])
	{
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');
	}

	require_once('./includes/functions_bigthree.php');
	construct_forum_rules($foruminfo, $forumperms);

	$counter = 0;
	while ($counter++ < $polloptions)
	{
		$option['number'] = $counter;
		if (is_array($options))
		{
			$option['question'] = htmlspecialchars_uni($options["$counter"]);
		}
		eval('$pollnewbits .= "' . fetch_template('pollnewbit') . '";');
	}

	eval('print_output("' . fetch_template('newpoll') . '");');

}

// ############################### start poll edit ###############################
if ($_REQUEST['do'] == 'polledit')
{

	globalize($_REQUEST, array('pollid' => INT));

	//check if the poll is closed
	$pollid = verify_id('poll', $pollid);
	$pollinfo = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "poll
		WHERE pollid = $pollid
	");

	$threadinfo = $DB_site->query_first("
		SELECT *, NOT ISNULL(deletionlog.primaryid) AS isdeleted
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE pollid = $pollid
			AND open <> 10
	");

	if ((!$threadinfo['visible'] OR $threadinfo['isdeleted'])  AND !can_moderate($threadinfo['forumid']))
	{
		eval(print_standard_error('error_invalidid'));
	}

	$threadcache["$threadinfo[threadid]"] = $threadinfo;

	$foruminfo = fetch_foruminfo($threadinfo['forumid']);
	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	// check if user is allowed to do edit
	if (!can_moderate($threadinfo['forumid'], 'caneditpoll'))
	{
		print_no_permission();
	}

	if (!$pollinfo['active'])
	{
		 $pollinfo['closed'] = HTML_CHECKED;
	}

	$pollinfo['postdate'] = vbdate($vboptions['dateformat'], $pollinfo['dateline']);
	$pollinfo['posttime'] = vbdate($vboptions['timeformat'], $pollinfo['dateline']);

	// draw nav bar
	$navbits = construct_poll_nav($foruminfo, $threadinfo);
	eval('$navbar = "' . fetch_template('navbar') . '";');

	$forumperms = fetch_permissions($threadinfo['forumid']);

	require_once('./includes/functions_bigthree.php');
	construct_forum_rules($foruminfo, $forumperms);

	//get options
	$splitoptions = explode('|||', $pollinfo['options']);
	$splitvotes = explode('|||', $pollinfo['votes']);

	$counter = 0;
	while ($counter++ < $pollinfo['numberoptions'])
	{
		$pollinfo['numbervotes'] += $splitvotes[$counter - 1];
	}

	$counter = 0;
	$pollbits = '';

	$pollinfo['question'] = htmlspecialchars_uni(unhtmlspecialchars($pollinfo['question'])); // what's the advantage of doing this?

	while ($counter++ < $pollinfo['numberoptions'])
	{
		$option['question'] = htmlspecialchars_uni($splitoptions[$counter - 1]);
		$option['votes'] = $splitvotes[$counter - 1];  //get the vote count for the option
		$option['number'] = $counter;  //number of the option

		eval('$pollbits .= "' . fetch_template('polleditbit') . '";');
	}

	$currentpage = urlencode("poll.php?do=polledit&pollid=$pollinfo[pollid]");
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	eval('print_output("' . fetch_template('editpoll') . '");');
}

// ############################### start adding the edit to the db ###############################
if ($_POST['do'] == 'updatepoll')
{

	globalize($_POST, array('pollid' => INT, 'closepoll', 'pollquestion' => STR, 'options', 'pollvotes', 'timeout' => INT));

	//check if the poll is closed
	$pollid = verify_id('poll', $pollid);
	$pollinfo = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "poll
		WHERE pollid = $pollid
	");

	$threadinfo = $DB_site->query_first("
		SELECT *, NOT ISNULL(deletionlog.primaryid) AS isdeleted
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE pollid = $pollid
	");

	if ((!$threadinfo['visible'] OR $threadinfo['isdeleted'])  AND !can_moderate($threadinfo['forumid']))
	{
		eval(print_standard_error('error_invalidid'));
	}

	$threadcache["$threadinfo[threadid]"] = $threadinfo;

	// check if user is allowed to do edit
	if (!can_moderate($threadinfo['forumid'], 'caneditpoll'))
	{
		print_no_permission();
	}

	//check if there are 2 options or more after edit
	$optioncount = 0;
	$votescount = 0;
	$votesstring = '';
	$optionsstring = '';
	$counter = 0;
	while ($counter++ < $pollinfo['numberoptions'] + 2)
	{
		$options["$counter"] = trim($options["$counter"]);
		if ($options["$counter"] != '')
		{
			$options["$counter"] = str_replace('|', ' | ', $options["$counter"]);
			$optionsstring .= '|||' . unhtmlspecialchars($options["$counter"]); //||| is delimter, 0 means no votes (as new poll)
			// sanity check for votes count
			$votesbit = intval($pollvotes["$counter"]);
			if ($votesbit < 0)
			{
				$votesbit = 0;
			}
			$votesstring .= '|||' . $votesbit;
			$optioncount++;
		}
	}

	if (substr($optionsstring, 0, 3) == '|||')
	{
		$optionsstring = substr($optionsstring, 3);
	}
	if (substr($votesstring, 0, 3) == '|||')
	{
		$votesstring = substr($votesstring, 3);
	}

	if (empty($pollquestion) OR $optioncount < 2){
		eval(print_standard_error('error_noquestionoption'));
	}

	if (TIMENOW + ($timeout * 86400) >= 2147483647)
	{ // maximuim size of a 32 bit integer
		eval(print_standard_error('error_maxpolltimeout'));
	}

	if ($closepoll == 'yes')
	{
		$pollactive = 0;
	}
	else
	{
		$pollactive = 1;
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "poll
		SET numberoptions = $optioncount,
		question = '" . addslashes($pollquestion) . "',
		votes = '" . addslashes($votesstring) . "',
		options = '" . addslashes($optionsstring) . "',
		active = $pollactive,
		timeout = $timeout
		WHERE pollid = $pollid
	");

	$pollinfo['threadid'] = $threadinfo['threadid'];
	require_once('./includes/functions_log_error.php');
	log_moderator_action($pollinfo, $vbphrase['poll_edited']);

	$url = "showthread.php?$session[sessionurl]t=$threadinfo[threadid]";
	eval(print_standard_redirect('redirect_editthanks'));
}

// ############################### start show results without vote ###############################
if ($_REQUEST['do'] == 'showresults')
{
	$pollid = intval($_REQUEST['pollid']);
	$pollinfo = verify_id('poll', $pollid, 1, 1);

	$threadinfo = $DB_site->query_first("
		SELECT *, NOT ISNULL(deletionlog.primaryid) AS isdeleted
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE pollid = $pollid AND open <> 10
	");

	if ((!$threadinfo['visible'] OR $threadinfo['isdeleted'])  AND !can_moderate($threadinfo['forumid']))
	{
		eval(print_standard_error('error_invalidid'));
	}

	$threadcache["$threadinfo[threadid]"] = $threadinfo;

	$foruminfo = fetch_foruminfo($threadinfo['forumid']);

	// check permissions
	$forumperms = fetch_permissions($foruminfo['forumid']);
	if (!($forumperms & CANVIEW))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	$counter = 1;
	$pollbits = '';

	$pollinfo['question'] = parse_bbcode(unhtmlspecialchars($pollinfo['question']), $foruminfo['forumid'], 1);

	$splitoptions = explode('|||', $pollinfo['options']);
	$splitvotes = explode('|||', $pollinfo['votes']);

	$pollinfo['numbervotes'] = array_sum($splitvotes);

	if ($pollinfo['numbervotes'] > 0)
	{
		if ($bbuserinfo['userid'] > 0)
		{
			$pollvotes = $DB_site->query("
				SELECT voteoption
				FROM " . TABLE_PREFIX . "pollvote
				WHERE userid = $bbuserinfo[userid] AND
					pollid = $pollid
			");
			$uservote = array();
			while ($pollvote = $DB_site->fetch_array($pollvotes))
			{
				$uservote["$pollvote[voteoption]"] = 1;
			}
		}
	}
	if ($pollinfo['public'])
	{
		$public = $DB_site->query("
			SELECT user.userid,user.username, voteoption
			FROM " . TABLE_PREFIX . "pollvote AS pollvote
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (pollvote.userid = user.userid)
			WHERE pollid = $pollinfo[pollid]
			ORDER BY username ASC
		");
		$allnames = array();
		while ($name = $DB_site->fetch_array($public))
		{
			$allnames["$name[voteoption]"][] = "<a href=\"member.php?$session[sessionurl]u=$name[userid]\">$name[username]</a>";
		}
	}

	foreach ($splitvotes AS $index => $value)
	{
		$option['uservote'] = iif($uservote[$index + 1], '*');
		$option['question'] = parse_bbcode($splitoptions["$index"], $foruminfo['forumid'], 1);
		$option['votes'] = $value;  //get the vote count for the option

		if ($option['votes'] == 0)
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
		$pollendtime = vbdate($vboptions['timeformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
		$pollenddate = vbdate($vboptions['dateformat'], $pollinfo['dateline'] + ($pollinfo['timeout'] * 86400));
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

	eval('$pollresults = "' . fetch_template('pollresults_table') . '";');
	eval('print_output("' . fetch_template('pollresults') . '");');
}


// ############################### start vote on poll ###############################
if ($_POST['do'] == 'pollvote')
{

	globalize($_POST, array('pollid' => INT, 'optionnumber'));

	$pollid = verify_id('poll', $pollid);
	$pollinfo = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "poll
		WHERE pollid = $pollid
	");

	$threadinfo = $DB_site->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "thread
		WHERE pollid = $pollid
			AND open<>10
		");
	$threadcache["$threadinfo[threadid]"] = $threadinfo;

	$foruminfo = fetch_foruminfo($threadinfo['forumid']);

	// other permissions?
	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (!($forumperms & CANVIEW) OR !($forumperms & CANVOTE))
	{
		print_no_permission();
	}

	// check if there is a forum password and if so, ensure the user has it set
	verify_forum_password($foruminfo['forumid'], $foruminfo['password']);

	//check if poll is closed
	if (!$pollinfo['active'] OR !$threadinfo['open'] OR ($pollinfo['dateline'] + ($pollinfo['timeout'] * 86400) < TIMENOW AND $pollinfo['timeout'] != 0))
	{ //poll closed
		 eval(print_standard_error('error_pollclosed'));
	}

	//check if an option was selected
	if ($optionnumber)
	{
		if ($bbuserinfo['userid'] == 0)
		{
			$voted = intval(fetch_bbarray_cookie('poll_voted', $pollid));
			if ($voted)
			{
				//the user has voted before
				eval(print_standard_error('error_useralreadyvote'));
			}
			else
			{
				set_bbarray_cookie('poll_voted', $pollid, 1, 1);
			}
		}
		else if ($bbuserinfo['userid'] AND $uservoteinfo = $DB_site->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "pollvote
			WHERE userid = $bbuserinfo[userid]
				AND pollid = $pollid
		"))
		{
			//the user has voted before
			eval(print_standard_error('error_useralreadyvote'));
		}

		$totaloptions = substr_count($pollinfo['options'], '|||') + 1;

		//Error checking complete, lets get the options
		if ($pollinfo['multiple'])
		{
			$insertsql = '';
			foreach ($optionnumber AS $val => $vote)
			{
				$val = intval($val);
				if ($vote == 'yes' AND $val > 0 AND $val <= $totaloptions)
				{
					if ($insertsql)
					{
						$insertsql .= ',';
					}
					$insertsql .= "($pollid, " . TIMENOW . ", $val, $bbuserinfo[userid])";
				}
			}
			if ($insertsql)
			{
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "pollvote
						(pollid, votedate, voteoption, userid)
					VALUES
						$insertsql
				");
			}
		}
		else
		{
			$optionnumber = intval($optionnumber);
			if ($optionnumber > 0 AND $optionnumber <= $totaloptions)
			{
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "pollvote
						(pollid, votedate, voteoption, userid)
					VALUES
						($pollid, " . TIMENOW . ", $optionnumber, $bbuserinfo[userid])
				");
			}
		}

		$splitvotes = explode('|||', $pollinfo['votes']);
		if ($pollinfo['multiple'])
		{
			foreach ($optionnumber AS $val => $vote)
			{
				$val = intval($val);
				if ($vote == 'yes')
				{
					$splitvotes[$val - 1]++;
				}
			}
		}
		else
		{
			$optionnumber = intval($optionnumber);
			$splitvotes[$optionnumber - 1]++;
		}

		$counter = 0;
		while ($counter < $pollinfo['numberoptions'])
		{
			$votesstring .= '|||' . intval($splitvotes["$counter"]);
			$counter++;
		}
		if (substr($votesstring,0 , 3) == '|||')
		{
			$votesstring = substr($votesstring, 3);
		}

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "poll
			SET votes = '" . addslashes($votesstring) . "',
				voters = voters + 1,
				lastvote = " . TIMENOW . "
			WHERE pollid = $pollid
		");

		//make last reply date == last vote date
		if ($vboptions['updatelastpost'])
		{ //option selected in CP
			$DB_site->query("
				UPDATE " . TABLE_PREFIX . "thread
				SET lastpost = " . TIMENOW . "
				WHERE threadid = $threadinfo[threadid]
			");
		}

		// redirect
		$url = "showthread.php?$session[sessionurl]t=$threadinfo[threadid]";
		eval(print_standard_redirect('redirect_pollvotethanks'));
	}
	else
	{
		eval(print_standard_error('error_nopolloptionselected'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12825 $
|| ####################################################################
\*======================================================================*/
?>