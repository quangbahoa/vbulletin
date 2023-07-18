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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
chdir('./../');

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('VB_AREA', 'Upgrade');
define('TIMENOW', time());

header('Expires: ' . gmdate("D, d M Y H:i:s", TIMENOW) . ' GMT');
header("Last-Modified: " . gmdate("D, d M Y H:i:s", TIMENOW) . ' GMT');

define('VERSION', '');
define('PREV_VERSION', '');
define('THIS_SCRIPT', 'upgrade.php');

// Wont ever run on PHP 4 //
if (version_compare(PHP_VERSION, '4.9.9', '<'))
{
	echo 'vBulletin is not compatible with PHP 4';
	exit;
}

// ########################## REQUIRE BACK-END ############################
require_once('./install/init.php');
require_once(DIR . '/includes/functions.php');
require_once(DIR . '/install/upgrade_language_en.php');

$_versions = array(
	'300b3',
	'300b4',
	'300b5',
	'300b6',
	'300b7',
	'300g',
	'300rc1',
	'300rc2',
	'300rc3',
	'300rc4',
	'300',
	'301',
	'302',
	'303',
	'304',
	'305',
	'306',
	'307',
	'308',
	'309',
	'3010',
	'3011',
	'3012',
	'3013',
	'3014',
	'3015',
	'30*',
	'350b1',
	'350b2',
	'350b3',
	'350b4',
	'350rc1',
	'350rc2',
	'350rc3',
	'350',
	'351',
	'352',
	'353',
	'354',
	'355',
	'35*',
	'360b1',
	'360b2',
	'360b3',
	'360b4',
	'360rc1',
	'360rc2',
	'360rc3',
	'360',
	'361',
	'362',
	'363',
	'364',
	'365',
	'366',
	'367',
	'368',
	'36*',
	'370b2',
	'370b3',
	'370b4',
	'370b5',
	'370b6',
	'370rc1',
	'370rc2',
	'370rc3',
	'370rc4',
	'370',
	'371',
	'37*',
	'380a2',
	'380b1',
	'380b2',
	'380b3',
	'380b4',
	'380rc1',
	'380rc2',
	'380',
	'381',
	'382',
	'383',
	'384',
	'385',
	'386',
	'387b1',
	'387',
	'388a1',
	'388b1',
	'388b2',
	'388b3',
	'388b4',
	'388',
	'389b1',
	'389b2',
	'389b3',
	'389b4',
	'389b5',
	'389',
	'3810b1',
	'3810b2',
	'3810rc1',
	'3810rc2',
	'3810rc3',
	'3810',
	'3811a1',
	'3811a2',
	'3811a3',
	'3811b1',
	'3811b2',
	'3811b3',
	'3811b4',
	'3811rc1',
	'3811rc2',
	'3811',
);

// #############################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'show' => TYPE_BOOL
));

$db->hide_errors();

if ($log = @$db->query_first("SELECT * FROM " . TABLE_PREFIX . "upgradelog ORDER BY upgradelogid DESC LIMIT 1"))
{
	$gotlog = true;
}
else if ($log = @$db->query_first("SELECT * FROM " . TABLE_PREFIX . "log_upgrade_step ORDER BY upgradelogid DESC LIMIT 1"))
{
	$gotlog = true;
}
else
{
	$gotlog = false;
}
$db->errno = 0;

// add language date columns
$db->query_first("SELECT registereddateoverride, calformat1override, calformat2override FROM " . TABLE_PREFIX . "language LIMIT 1");
if ($db->errno())
{
	// error from query, so we don't have the columns
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "language
			ADD registereddateoverride VARCHAR(20) NOT NULL DEFAULT '',
			ADD calformat1override VARCHAR(20) NOT NULL DEFAULT '',
			ADD calformat2override VARCHAR(20) NOT NULL DEFAULT ''
	");
}
$db->errno = 0;

// add language date column // RC1
$db->query_first("SELECT logdateoverride FROM " . TABLE_PREFIX . "language LIMIT 1");
if ($db->errno())
{
	// error from query, so we don't have the column
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "language
			ADD logdateoverride VARCHAR(20) NOT NULL DEFAULT ''
	");
}
$db->errno = 0;

// add language locale
$db->query_first("SELECT locale FROM " . TABLE_PREFIX . "language LIMIT 1");
if ($db->errno())
{
	// error from query, so we don't have the columns
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "language
		ADD locale VARCHAR(20) NOT NULL DEFAULT ''
	");
}

$db->errno = 0;

// add language charset columns
$db->query_first("SELECT charset FROM " . TABLE_PREFIX . "language LIMIT 1");
if ($db->errno())
{
	// error from query, so we don't have the columns
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "language
		ADD charset VARCHAR(15) NOT NULL DEFAULT ''
	");
}
$db->errno = 0;

// add template version column
$db->hide_errors();
$db->query_first("SELECT version FROM " . TABLE_PREFIX . "template LIMIT 1");
if ($db->errno())
{
	// error from query, so we don't have the column
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "template ADD version varchar(30) NOT NULL DEFAULT ''");
}
$db->show_errors();
$db->errno = 0;

// change template 'type' column to 'templatetype'
$db->hide_errors();

$db->query_first("SELECT templatetype FROM " . TABLE_PREFIX . "template LIMIT 1");
$templatetype_missing = ($db->errno());
$db->errno = 0;

$db->query_first("SELECT type FROM " . TABLE_PREFIX . "template LIMIT 1");
$type_missing = ($db->errno());
$db->errno = 0;

$db->hide_errors();
if ($templatetype_missing AND !$type_missing)
{
	// error from query, so we don't have the column
	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "template
		CHANGE `type` `templatetype` SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD typebak SMALLINT UNSIGNED NOT NULL DEFAULT '0'
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "template SET typebak = templatetype
	");

	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "template
		CHANGE templatetype templatetype ENUM('template','stylevar','css','replacement') DEFAULT 'template'
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "template SET
		templatetype = CASE typebak
			WHEN 1 THEN 'stylevar'
			WHEN 2 THEN 'css'
			WHEN 3 THEN 'replacement'
			ELSE 'template' END
	");

	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "style ADD csscolors MEDIUMTEXT");

	$db->query_write("
		ALTER TABLE " . TABLE_PREFIX . "template DROP typebak
	");
}

$db->hide_errors();
// try to add phrase groups since they're necessary
$db->query_write("
	ALTER TABLE " . TABLE_PREFIX . "language
		ADD phrasegroup_accessmask mediumtext,
		ADD phrasegroup_cron mediumtext,
		ADD phrasegroup_moderator mediumtext,
		ADD phrasegroup_cpoption mediumtext,
		ADD phrasegroup_cprank mediumtext,
		ADD phrasegroup_cpusergroup mediumtext
");

// update phrase group list
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['accessmask']}', editrows = 3, fieldname = 'accessmask' WHERE phrasetypeid = 29");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['cron']}', editrows = 3, fieldname = 'cron' WHERE phrasetypeid = 30");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['moderator']}', editrows = 3, fieldname = 'moderator' WHERE phrasetypeid = 31");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['cpoption']}', editrows = 3, fieldname = 'cpoption' WHERE phrasetypeid = 32");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['cprank']}', editrows = 3, fieldname = 'cprank' WHERE phrasetypeid = 33");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['cpusergroup']}', editrows = 3, fieldname = 'cpusergroup' WHERE phrasetypeid = 34");

$db->query_write("
	ALTER TABLE " . TABLE_PREFIX . "language
		ADD phrasegroup_holiday mediumtext,
		ADD phrasegroup_posting mediumtext,
		ADD phrasegroup_poll mediumtext,
		ADD phrasegroup_fronthelp mediumtext,
		ADD phrasegroup_register mediumtext,
		ADD phrasegroup_search mediumtext,
		ADD phrasegroup_showthread mediumtext,
		ADD phrasegroup_postbit mediumtext,
		ADD phrasegroup_forumdisplay mediumtext,
		ADD phrasegroup_messaging mediumtext
");

$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['holiday']}', editrows = 3, fieldname = 'holiday' WHERE phrasetypeid = 35");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrase SET phrasetypeid = 35 WHERE phrasetypeid = 5 AND varname LIKE 'holiday_%'");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['posting']}', editrows = 3, fieldname = 'posting' WHERE phrasetypeid = 36");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['poll']}', editrows = 3, fieldname = 'poll' WHERE phrasetypeid = 37");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['fronthelp']}', editrows = 3, fieldname = 'fronthelp' WHERE phrasetypeid = 38");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['register']}', editrows = 3, fieldname = 'register' WHERE phrasetypeid = 39");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['search']}', editrows = 3, fieldname = 'search' WHERE phrasetypeid = 40");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['showthread']}', editrows = 3, fieldname = 'showthread' WHERE phrasetypeid = 41");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['postbit']}', editrows = 3, fieldname = 'postbit' WHERE phrasetypeid = 42");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['forumdisplay']}', editrows = 3, fieldname = 'forumdisplay' WHERE phrasetypeid = 43");
$db->query_write("UPDATE " . TABLE_PREFIX . "phrasetype SET title = '{$phrasetype['messaging']}', editrows = 3, fieldname = 'messaging' WHERE phrasetypeid = 44");
// end phrase groups

// adding field names for "special" phrasegroups
$db->query_write("
	UPDATE " . TABLE_PREFIX . "phrasetype SET
	fieldname = CASE phrasetypeid
		WHEN 1000 THEN 'error'
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

// product columns for 3.5
$db->query_first("SELECT product FROM " . TABLE_PREFIX . "adminhelp LIMIT 1");
$product_missing = ($db->errno());
$db->errno = 0;
if ($product_missing)
{
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "phrasetype ADD product VARCHAR(25) NOT NULL DEFAULT ''");
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "phrase ADD product VARCHAR(25) NOT NULL DEFAULT ''");
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "template ADD product VARCHAR(25) NOT NULL DEFAULT ''");
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "setting ADD product VARCHAR(25) NOT NULL DEFAULT ''");
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "settinggroup ADD product VARCHAR(25) NOT NULL DEFAULT ''");
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "adminhelp ADD product VARCHAR(25) NOT NULL DEFAULT ''");
}
// need to do this here or we might get problems if options are built before the end of the script
$db->query_write("REPLACE INTO " . TABLE_PREFIX . "adminutil (title, text) VALUES ('datastorelock', '0')");

$db->query_first("SELECT datatype FROM " . TABLE_PREFIX . "setting LIMIT 1");
$datatype_missing = ($db->errno());
$db->errno = 0;
if ($datatype_missing)
{
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "setting ADD datatype ENUM('free', 'number', 'boolean', 'bitfield', 'username') NOT NULL DEFAULT 'free'");
}

$db->show_errors();

// #############################################################################

function fetch_short_version($version)
{
	if (preg_match('/^(\w+\s+)?(\d+)\.(\d+)\.(\d+)(\s+(a|alpha|b|beta|g|gamma|rc|release candidate|gold|stable|final|pl|patch level)(\s+(\d+))?)?$/siU', $version, $regs))
	{
		switch (strtolower($regs[6]))
		{
			case 'alpha':
				$regs[6] = 'a';
				break;
			case 'beta':
				$regs[6] = 'b';
				break;
			case 'gamma':
				$regs[6] = 'g';
				break;
			case 'release candidate':
				$regs[6] = 'rc';
				break;
			case 'patch level':
				$regs[6] = 'pl';
				break;
			case 'gold':
			case 'stable':
			case 'final':
				$regs[6] = '';
				$regs[7] = '';
				break;
		}

		return $regs[2] . $regs[3] . $regs[4] . $regs[6] . $regs[8];
	}
	else
	{
		return $version;
	}
}

if ($gotlog AND preg_match('/^upgrade_(\w+)\.php$/siU', $log['script'], $reg))
{
	//echo '<pre>'; print_r($log); echo '</pre>';
	// get the script version from the last log entry
	$scriptver =& $reg[1];

	if ($log['step'] == 0)
	{
		// the last entry has step = 0, meaning the script completed...
		$versionkey = array_search($scriptver, $_versions);
		$shorten = 0;
		while ($versionkey === false
			   AND (empty($wildversion) OR $wildversion != '*'))
		{
			$wildversion =  substr_replace($scriptver, '*', --$shorten);
			$versionkey = array_search($wildversion, $_versions);
		}
		++$versionkey;

		// to handle the case when we are running the version before a wildcard version
		while (strpos($_versions["$versionkey"], '*') !== false)
		{
			++$versionkey;
		}

		if ($versionkey !== false AND isset($_versions["$versionkey"]))
		{
			// found the next script, link to that
			$link = 'upgrade_' . $_versions["$versionkey"] . '.php';
		}
		else
		{
			$link = 'finalupgrade.php';
		}
	}
	else if ($log['perpage'])
	{
		// link to the same script, same step with $perpage added to $startat
		$link = "upgrade_$scriptver.php?step=$log[step]&amp;startat=" . ($log['startat'] + $log['perpage']);
	}
	else
	{
		// link to same script with step number incremented
		$link = "upgrade_$scriptver.php?step=" . ($log['step'] + 1);
	}
}
else
{
	$shortver = fetch_short_version($vbulletin->versionnumber);
	$versionkey = array_search($shortver, $_versions);
	$shorten = 0;
	while ($versionkey === false
		   AND (empty($wildversion) OR $wildversion != '*'))
	{
		$wildversion =  substr_replace($shortver, '*', --$shorten);
		$versionkey = array_search($wildversion, $_versions);
	}

	++$versionkey;

	// to handle the case when we are running the version before a wildcard version
	while (!empty($_versions["$versionkey"]) AND strpos($_versions["$versionkey"], '*') !== false)
	{
		++$versionkey;
	}

	if ($versionkey !== false AND isset($_versions[$versionkey]))
	{
		// we know what script this version needs to go to
		$link = 'upgrade_' . $_versions[$versionkey] . '.php';
	}
	else if (intval($vbulletin->versionnumber) == 3)
	{
		// assume we are finished
		$link = 'finalupgrade.php';
	}
	else
	{
		// no log and invalid version, so assume it's 2.x
		$link = 'upgrade_300b3.php';
	}
}

if ($vbulletin->GPC['show'])
{
	echo "<p><a href=\"$link\">$link</a></p>";
	echo "<p><a href=\"upgrade.php\">[{$vbphrase['refresh']}]</a></p>";
}
else
{
	exec_header_redirect($link);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93992 $
|| # $Date: 2017-05-10 05:18:19 -0700 (Wed, 10 May 2017) $
|| ####################################################################
\*======================================================================*/
?>
