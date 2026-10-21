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

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Start vberrorlog #######################
// logs an error or alert to the specified file
function log_vbulletin_error($errstring, $type = 'database')
{
	global $vboptions, $DB_site;

	// do different things depending on the error log type
	switch($type)
	{
		// log database error to file
		case 'database':
			if (!empty($vboptions['errorlogdatabase']))
			{
				$errstring = preg_replace("#(\r\n|\r|\n)#s", "\r\n", $errstring);
				$errfile = $vboptions['errorlogdatabase'];
			}
			break;

		// log admin panel login failure to file
		case 'security':
			if (!empty($vboptions['errorlogsecurity']))
			{
				$errfile = $vboptions['errorlogsecurity'];
				$username = $errstring;
				$errstring  = 'Failed admin logon in ' . $DB_site->appname . ' ' . $vboptions['templateversion'] . "\r\n\r\n";
				$errstring .= 'Date: ' . date('l dS of F Y h:i:s A') . "\r\n";
				$errstring .= "Script: http://$_SERVER[HTTP_HOST]" . SCRIPTPATH . "\r\n";
				$errstring .= 'Referer: ' . REFERRER . "\r\n";
				$errstring .= "Username: $username\r\n";
				$errstring .= 'IP Address: ' . IPADDRESS . "\r\n";
				$errstring .= "Strikes: $GLOBALS[strikes]/5\r\n";
			}
			break;
	}

	// if no filename is specified, exit this function
	if (!($errfile = trim($errfile)) OR is_demo_mode())
	{
		return false;
	}

	// rotate the log file if filesize is greater than $vboptions[errorlogmaxsize]
	if ($vboptions['errorlogmaxsize'] != 0 AND $filesize = @filesize("$errfile.log") AND $filesize >= $vboptions['errorlogmaxsize'])
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
function log_moderator_action($loginfo, $action = '')
{
	global $bbuserinfo, $DB_site;

	$moderatorlog['userid'] = $bbuserinfo['userid'];
	$moderatorlog['dateline'] = TIMENOW;

	$moderatorlog['forumid'] = $loginfo['forumid'];
	$moderatorlog['threadid'] = $loginfo['threadid'];
	$moderatorlog['postid'] = $loginfo['postid'];
	$moderatorlog['pollid'] = $loginfo['pollid'];

	$moderatorlog['action'] = $action;

	$DB_site->query(fetch_query_sql($moderatorlog, 'moderatorlog'));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9680 $
|| ####################################################################
\*======================================================================*/
?>