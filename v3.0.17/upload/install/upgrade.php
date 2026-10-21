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

define('VB_AREA', 'Upgrade');
define('NOSHUTDOWNFUNC', 1);
define('TIMENOW', time());
header('Expires: ' . gmdate("D, d M Y H:i:s", TIMENOW) . ' GMT');
header("Last-Modified: " . gmdate("D, d M Y H:i:s", TIMENOW) . ' GMT');

$phrasegroups = array();
$specialtemplates = array();

// just temporary for my testing purposes - KD
define('VB3UPGRADE', 1);

chdir('../');
require_once('./includes/init.php');
require_once('./includes/functions.php'); // mainly for exec_header_redirect()
require_once('./install/upgrade_language_en.php');
$versionassoc = array(
	'3.0.0 Beta 3' => 2,
	'3.0.0 Beta 4' => 3,
	'3.0.0 Beta 5' => 4,
	'3.0.0 Beta 6' => 5,
	'3.0.0 Beta 7' => 6,
	'3.0.0 Gamma'  => 7,
	'3.0.0 Release Candidate 1' => 8,
	'3.0.0 Release Candidate 2' => 9,
	'3.0.0 Release Candidate 3' => 10,
	'3.0.0 Release Candidate 4' => 11,
	'3.0.0' => 12,
	'3.0.1' => 13,
	'3.0.2' => 14,
	'3.0.3' => 15,
	'3.0.4' => 16,
	'3.0.5' => 17,
	'3.0.6' => 18,
	'3.0.7' => 19,
	'3.0.8' => 20,
	'3.0.9' => 21,
	'3.0.10' => 22,
	'3.0.11' => 23,
	'3.0.12' => 24,
	'3.0.13' => 25,
	'3.0.14' => 26,
	'3.0.15' => 27,
	'3.0.16' => 27,
);

// #############################################################################

$DB_site->reporterror = 0;

if ($log = @$DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "upgradelog ORDER BY upgradelogid DESC LIMIT 1"))
{
	$gotlog = true;
}
else if ($log = @$DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "log_upgrade_step ORDER BY upgradelogid DESC LIMIT 1"))
{
	$gotlog = true;
}
else
{
	$gotlog = false;
}
$DB_site->errno = 0;

// add language date columns
$DB_site->query_first("SELECT registereddateoverride, calformat1override, calformat2override FROM " . TABLE_PREFIX . "language LIMIT 1");
if ($DB_site->geterrno() != 0)
{
	// error from query, so we don't have the columns
	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "language
			ADD registereddateoverride VARCHAR(20) NOT NULL,
			ADD calformat1override VARCHAR(20) NOT NULL,
			ADD calformat2override VARCHAR(20) NOT NULL
	");
}
$DB_site->errno = 0;

// add language date column // RC1
$DB_site->query_first("SELECT logdateoverride FROM " . TABLE_PREFIX . "language LIMIT 1");
if ($DB_site->geterrno() != 0)
{
	// error from query, so we don't have the column
	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "language
			ADD logdateoverride VARCHAR(20) NOT NULL
	");
}
$DB_site->errno = 0;

// add language locale
$DB_site->query_first("SELECT locale FROM " . TABLE_PREFIX . "language LIMIT 1");
if ($DB_site->geterrno() != 0)
{
	// error from query, so we don't have the columns
	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "language
		ADD locale VARCHAR(20) NOT NULL DEFAULT ''
	");
}

$DB_site->errno = 0;

// add language charset columns
$DB_site->query_first("SELECT charset FROM " . TABLE_PREFIX . "language LIMIT 1");
if ($DB_site->geterrno() != 0)
{
	// error from query, so we don't have the columns
	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "language
		ADD charset VARCHAR(15) NOT NULL DEFAULT ''
	");
}
$DB_site->errno = 0;

// add template version column
$DB_site->reporterror = 0;
$DB_site->query_first("SELECT version FROM " . TABLE_PREFIX . "template LIMIT 1");
if ($DB_site->geterrno() != 0)
{
	// error from query, so we don't have the column
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "template ADD version varchar(30) NOT NULL DEFAULT ''");
}
$DB_site->reporterror = 1;
$DB_site->errno = 0;

// change template 'type' column to 'templatetype'
$DB_site->reporterror = 0;

$DB_site->query_first("SELECT templatetype FROM " . TABLE_PREFIX . "template LIMIT 1");
$templatetype_missing = ($DB_site->geterrno() != 0);
$DB_site->errno = 0;

$DB_site->query_first("SELECT type FROM " . TABLE_PREFIX . "template LIMIT 1");
$type_missing = ($DB_site->geterrno() != 0);
$DB_site->errno = 0;

$DB_site->reporterror = 0;
if ($templatetype_missing AND !$type_missing)
{
	// error from query, so we don't have the column
	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "template
		CHANGE `type` `templatetype` SMALLINT UNSIGNED NOT NULL,
		ADD typebak SMALLINT UNSIGNED NOT NULL
	");

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "template SET typebak = templatetype
	");

	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "template
		CHANGE templatetype templatetype ENUM('template','stylevar','css','replacement') NOT NULL DEFAULT 'template'
	");

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "template SET
		templatetype = CASE typebak
			WHEN 1 THEN 'stylevar'
			WHEN 2 THEN 'css'
			WHEN 3 THEN 'replacement'
			ELSE 'template' END
	");

	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "style ADD csscolors MEDIUMTEXT NOT NULL");

	$DB_site->query("
		ALTER TABLE " . TABLE_PREFIX . "template DROP typebak
	");
}

$DB_site->reporterror = 0;
// try to add phrase groups since they're necessary
$DB_site->query("
	ALTER IGNORE TABLE " . TABLE_PREFIX . "language
		ADD phrasegroup_accessmask mediumtext NOT NULL,
		ADD phrasegroup_cron mediumtext NOT NULL,
		ADD phrasegroup_moderator mediumtext NOT NULL,
		ADD phrasegroup_cpoption mediumtext NOT NULL,
		ADD phrasegroup_cprank mediumtext NOT NULL,
		ADD phrasegroup_cpusergroup mediumtext NOT NULL
");

// update phrase group list
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['accessmask']}', editrows=3, fieldname='accessmask' WHERE phrasetypeid=29");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['cron']}', editrows=3, fieldname='cron' WHERE phrasetypeid=30");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['moderator']}', editrows=3, fieldname='moderator' WHERE phrasetypeid=31");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['cpoption']}', editrows=3, fieldname='cpoption' WHERE phrasetypeid=32");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['cprank']}', editrows=3, fieldname='cprank' WHERE phrasetypeid=33");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title='{$phrasetype['cpusergroup']}', editrows=3, fieldname='cpusergroup' WHERE phrasetypeid=34");


$DB_site->query("
	ALTER IGNORE TABLE " . TABLE_PREFIX . "language
		ADD phrasegroup_holiday mediumtext NOT NULL,
		ADD phrasegroup_posting mediumtext NOT NULL,
		ADD phrasegroup_poll mediumtext NOT NULL,
		ADD phrasegroup_fronthelp mediumtext NOT NULL,
		ADD phrasegroup_register mediumtext NOT NULL,
		ADD phrasegroup_search mediumtext NOT NULL,
		ADD phrasegroup_showthread mediumtext NOT NULL,
		ADD phrasegroup_postbit mediumtext NOT NULL,
		ADD phrasegroup_forumdisplay mediumtext NOT NULL,
		ADD phrasegroup_messaging mediumtext NOT NULL
");

$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['holiday']}', editrows = 3, fieldname = 'holiday' WHERE phrasetypeid = 35");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrase SET phrasetypeid = 35 WHERE phrasetypeid = 5	AND varname LIKE 'holiday_%'");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['posting']}', editrows = 3, fieldname = 'posting' WHERE phrasetypeid = 36");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['poll']}', editrows = 3, fieldname = 'poll' WHERE phrasetypeid = 37");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['fronthelp']}', editrows = 3, fieldname = 'fronthelp' WHERE phrasetypeid = 38");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['register']}', editrows = 3, fieldname = 'register' WHERE phrasetypeid = 39");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['search']}', editrows = 3, fieldname = 'search' WHERE phrasetypeid = 40");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['showthread']}', editrows = 3, fieldname = 'showthread' WHERE phrasetypeid = 41");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['postbit']}', editrows = 3, fieldname = 'postbit' WHERE phrasetypeid = 42");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['forumdisplay']}', editrows = 3, fieldname = 'forumdisplay' WHERE phrasetypeid = 43");
$DB_site->query("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['messaging']}', editrows = 3, fieldname = 'messaging' WHERE phrasetypeid = 44");
// end phrase groups

// adding field names for "special" phrasegroups
$DB_site->query("
	UPDATE " . TABLE_PREFIX . "phrasetype SET
	fieldname = CASE phrasetypeid
		WHEN 1000 THEN 'fronterror'
		WHEN 2000 THEN 'frontredirect'
		WHEN 3000 THEN 'emailbody'
		WHEN 4000 THEN 'emailsubject'
		WHEN 5000 THEN 'vbsettings'
		WHEN 6000 THEN 'cphelptext'
		WHEN 7000 THEN 'faqtitle'
		WHEN 8000 THEN 'faqtext'
		WHEN 9000 THEN 'cpstopmsg'
		ELSE fieldname END
	WHERE phrasetypeid >= 1000
");

$DB_site->reporterror = 1;

// #############################################################################

if (!function_exists('exec_header_redirect'))
{
	// in case someone hasn't uploaded the new functions.php yet...
	function exec_header_redirect($url)
	{
		header("Location: $url");
		exit;
	}
}

if ($gotlog)
{
	// get the script number of the last log entry
	preg_match('/^upgrade([0-9]+)\./siU', $log['script'], $reg);
	$scriptnumber = &$reg[1];

	if ($log['step'] == 0)
	{
		// the last entry has step=0, meaning the script completed...
		$scriptnumber++;
		if (file_exists("./install/upgrade$scriptnumber.php"))
		{
			// found the next script - link to that
			$link = "upgrade$scriptnumber.php";
		}
		else
		{
			// next script not found - upgrade is complete!
			exec_header_redirect("../$admincpdir/index.php");
			exit;
		}
	}
	else if ($log['perpage'])
	{
		// link to the same script, same step, with $perpage added to $startat
		$link = "upgrade$scriptnumber.php?step=$log[step]&amp;startat=" . ($log['startat'] + $log['perpage']);
	}
	else
	{
		// link to the same script with +1 to the step number
		$link = "upgrade$scriptnumber.php?step=" . ($log['step'] + 1);
	}
}
else
{
	if ($versionassoc["$versionnumber"])
	{
		// we know what script this version needs to go to
		$link = 'upgrade' . $versionassoc["$versionnumber"] . '.php';
	}
	else if (intval($versionnumber) == 3)
	{
		// on 3.0 and we don't have a version assoc, so assume that we're finished
		exec_header_redirect("../$admincpdir/index.php");
		exit;
	}
	else
	{
		// no log, and invalid version, so assume it's 2.x
		$link = 'upgrade1.php';
	}
}

if ($_REQUEST['show'])
{
	echo "<p><a href=\"$link\">$link</a></p>";
	echo "<p><a href=\"upgrade.php\">[{$vbphrase[refresh]}]</a></p>";
}
else
{
	exec_header_redirect($link);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15912 $
|| ####################################################################
\*======================================================================*/
?>