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

function fetch_modlogtypes($logtype)
{
	static $modlogtypes = array(
		'closed_thread'                           => 1,
		'opened_thread'                           => 2,
		'thread_moved_to_x'                       => 3,
		'thread_moved_with_redirect_to_a'         => 4,
		'thread_copied_to_x'                      => 5,
		'thread_edited_visible_x_open_y_sticky_z' => 6,
		'thread_merged_with_x'                    => 7,
		'thread_split_to_x'                       => 8,
		'unstuck_thread'                          => 9,
		'stuck_thread'                            => 10,
		'attachment_removed'                      => 11,
		'attachment_uploaded'                     => 12,
		'poll_edited'                             => 13,
		'thread_softdeleted'                      => 14,
		'thread_removed'                          => 15,
		'thread_undeleted'                        => 16,
		'post_x_by_y_softdeleted'                 => 17,
		'post_x_by_y_removed'                     => 18,
		'post_y_by_x_undeleted'                   => 19,
		'post_x_edited'                           => 20,
		'approved_thread'                         => 21,
		'unapproved_thread'                       => 22,
		'thread_merged_from_multiple_threads'     => 23,
		'unapproved_post'                         => 24,
		'approved_post'                           => 25,
		'post_merged_from_multiple_posts'         => 26,
		'approved_attachment'                     => 27,
		'unapproved_attachment'                   => 28,
		'thread_title_x_changed'                  => 29,
		'thread_redirect_removed'                 => 30,

	);

	($hook = vBulletinHook::fetch_hook('fetch_modlogtypes')) ? eval($hook) : false;
		
	return !empty($modlogtypes["$logtype"]) ? $modlogtypes["$logtype"] : 0;
}

function fetch_modlogactions($logaction)
{
	static $modlogactions = array(
		1	=>	'closed_thread',
		2	=>	'opened_thread',
		3	=>	'thread_moved_to_x',
		4	=>	'thread_moved_with_redirect_to_a',
		5	=>	'thread_copied_to_x',
		6	=>	'thread_edited_visible_x_open_y_sticky_z',
		7	=>	'thread_merged_with_x',
		8	=>	'thread_split_to_x',
		9	=>	'unstuck_thread',
		10	=>	'stuck_thread',
		11	=>	'attachment_removed',
		12	=>	'attachment_uploaded',
		13	=>	'poll_edited',
		14	=>	'thread_softdeleted',
		15	=>	'thread_removed',
		16	=>	'thread_undeleted',
		17	=>	'post_x_by_y_softdeleted',
		18	=>	'post_x_by_y_removed',
		19	=>	'post_y_by_x_undeleted',
		20	=>	'post_x_edited',
		21 => 'approved_thread',
		22 => 'unapproved_thread',
		23 => 'thread_merged_from_multiple_threads',
		24 => 'unapproved_post',
		25 => 'approved_post',
		26 => 'post_merged_from_multiple_posts',
		27 => 'approved_attachment',
		28 => 'unapproved_attachment',
		29 => 'thread_title_x_changed',
		30 => 'thread_redirect_removed',
	);

	($hook = vBulletinHook::fetch_hook('fetch_modlogactions')) ? eval($hook) : false;

	return !empty($modlogactions["$logaction"]) ? $modlogactions["$logaction"] : '';
}

// ###################### Start vberrorlog #######################
// logs an error or alert to the specified file
function log_vbulletin_error($errstring, $type = 'database')
{
	global $vbulletin;

	// do different things depending on the error log type
	switch($type)
	{
		// log PHP E_USER_ERROR, E_USER_WARNING, E_WARNING to file
		case 'php':
			if (!empty($vbulletin->options['errorlogphp']))
			{
				$errfile = $vbulletin->options['errorlogphp'];
				$errstring .= "\r\nDate: " . date('l dS of F Y h:i:s A') . "\r\n";
				$errstring .= "Username: {$vbulletin->userinfo[username]}\r\n";
				$errstring .= 'IP Address: ' . IPADDRESS . "\r\n";
			}
			break;

		// log database error to file
		case 'database':
			if (!empty($vbulletin->options['errorlogdatabase']))
			{
				$errstring = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $errstring);
				$errfile = $vbulletin->options['errorlogdatabase'];
			}
			break;

		// log admin panel login failure to file
		case 'security':
			if (!empty($vbulletin->options['errorlogsecurity']))
			{
				$errfile = $vbulletin->options['errorlogsecurity'];
				$username = $errstring;
				$errstring  = 'Failed admin logon in ' . $vbulletin->db->appname . ' ' . $vbulletin->options['templateversion'] . "\r\n\r\n";
				$errstring .= 'Date: ' . date('l dS of F Y h:i:s A') . "\r\n";
				$errstring .= "Script: http://$_SERVER[HTTP_HOST]" . unhtmlspecialchars($vbulletin->scriptpath) . "\r\n";
				$errstring .= 'Referer: ' . REFERRER . "\r\n";
				$errstring .= "Username: $username\r\n";
				$errstring .= 'IP Address: ' . IPADDRESS . "\r\n";
				$errstring .= "Strikes: $GLOBALS[strikes]/5\r\n";
			}
			break;
	}

	// if no filename is specified, exit this function
	if (!($errfile = trim($errfile)) OR (defined('DEMO_MODE') AND DEMO_MODE == true))
	{
		return false;
	}

	// rotate the log file if filesize is greater than $vbulletin->options[errorlogmaxsize]
	if ($vbulletin->options['errorlogmaxsize'] != 0 AND $filesize = @filesize("$errfile.log") AND $filesize >= $vbulletin->options['errorlogmaxsize'])
	{
		@copy("$errfile.log", $errfile . TIMENOW . '.log');
		@unlink("$errfile.log");
	}

	// write the log into the appropriate file
	if ($fp = @fopen("$errfile.log", 'a+'))
	{
		@fwrite($fp, "$errstring\r\n=====================================================\r\n\r\n");
		@fclose($fp);
		return true;
	}
	else
	{
		return false;
	}
}

// ###################### Start moderatorlog #######################
function log_moderator_action(&$loginfo, $logtype, $action = '')
{
	global $vbulletin;

	$modlogsql = array();

	if ($result = fetch_modlogtypes($logtype))
	{
		$logtype =& $result;
	}

	($hook = vBulletinHook::fetch_hook('log_moderator_action')) ? eval($hook) : false;
		
	if (is_array($loginfo[0]))
	{
		foreach ($loginfo AS $index => $log)
		{
			if (is_array($action))
			{
				$action = serialize($action);
			}
			$modlogsql[] = "(" . intval($logtype) . ", " . intval($log['userid']) . ", " . TIMENOW . ", " . intval($log['forumid']) . ", " . intval($log['threadid']) . ", " . intval($log['postid']) . ", " . intval($log['pollid']) . ", " . intval($log['attachmentid']) . ", '" . $vbulletin->db->escape_string($action) . "', '" . $vbulletin->db->escape_string(IPADDRESS) . "')";
		}

		$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "moderatorlog (type, userid, dateline, forumid, threadid, postid, pollid, attachmentid, action, ipaddress) VALUES " . implode(', ', $modlogsql));
	}
	else
	{
		$moderatorlog['userid'] =& $vbulletin->userinfo['userid'];
		$moderatorlog['dateline'] = TIMENOW;

		$moderatorlog['type'] = intval($logtype);

		$moderatorlog['forumid'] = intval($loginfo['forumid']);
		$moderatorlog['threadid'] = intval($loginfo['threadid']);
		$moderatorlog['postid'] = intval($loginfo['postid']);
		$moderatorlog['pollid'] = intval($loginfo['pollid']);
		$moderatorlog['attachmentid'] = intval($loginfo['attachmentid']);

		$moderatorlog['ipaddress'] = IPADDRESS;

		if (is_array($action))
		{
			$action = serialize($action);
		}
		$moderatorlog['action'] = $action;

		/*insert query*/
		$vbulletin->db->query_write(fetch_query_sql($moderatorlog, 'moderatorlog'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13530 $
|| ####################################################################
\*======================================================================*/
?>