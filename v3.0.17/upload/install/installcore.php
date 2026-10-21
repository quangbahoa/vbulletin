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
ignore_user_abort(true);
define('VB_AREA', 'Install');

if (!defined('THIS_SCRIPT'))
{
	exit;
}

// try to force display_errors on
@ini_set('display_errors', true);

// #########################################################################
// ########################## VSERVERS SETTING #############################
// ####### Set this to 1 if you're on Vservers and get disconnected ########
// ############## after running an ALTER TABLE command #####################
// #########################################################################
$onvservers = 0;
// #########################################################################

// attempt to extend php timeout limit
if (function_exists('set_time_limit') AND get_cfg_var('safe_mode') == 0)
{
	@set_time_limit(0);
}

// disable shutdown functions
define('NOSHUTDOWNFUNC', 1);
require_once('./install/install_language_en.php');

// check for valid php version
verify_vb3_enviroment();

$stylevar = array(
	'textdirection' => 'ltr',
	'left' => 'left',
	'right' => 'right',
	'languagecode' => 'en',
	'charset' => 'ISO-8859-1'
);

// require necessary files
require_once('./includes/functions.php');
require_once('./includes/adminfunctions.php');
$steptitles = $install_phrases['steps'];
require_once('./install/authenticate.php');

// globalize and initialize variables
globalize($_REQUEST, array(
	'do' => STR,
	'step' => STR,
	'startat' => INT,
	'perpage' => INT
));

$step = iif(empty($step), 'welcome', intval($step));
$nozip = true;
$query = array();
$explain = array();

// assuming we've got through the authentication process, show the upgradeHeader.
if (empty($do))
{
	print_upgrade_header(fetch_step_title($step));
}

// ***************************************************************************************************************************


// #########################################################################
// ############# GENERIC UPGRADE / INSTALL FUNCTIONS PROTOTYPES ############
// #########################################################################



// #########################################################################
// checks the environment for vB3 conditions
// call this BEFORE calling init.php or any other files
function verify_vb3_enviroment()
{
	global $installcore_phrases;
	$errorthrown = false;

	// php version check
	if (PHP_VERSION < '4.0.6')
	{
		$errorthrown = true;
		echo "<p>$installcore_phrases[php_version_too_old]</p>";
	}

	// XML check
	if (!function_exists('xml_set_element_handler'))
	{
		$errorthrown = true;
		echo "<p>$installcore_phrases[need_xml]</p>";
	}

	// MySQL check
	if (!function_exists('mysql_connect'))
	{
		$errorthrown = true;
		echo "<p>$installcore_phrases[need_mysql]</p>";
	}

	// config file check
	if (!file_exists('./includes/config.php'))
	{
		$errorthrown = true;
		echo "<p>$installcore_phrases[need_config_file]</p>";
	}

	if ($errorthrown)
	{
		exit;
	}
}

// #########################################################################
// starts gzip encoding and echoes out the <html> page header
function print_upgrade_header($steptitle = '')
{
	global $vboptions, $nozip, $session, $steptitles, $step, $numsteps, $installcore_phrases;

	if ($vboptions['gzipoutput'] and !headers_sent() and function_exists('ob_start') and function_exists('crc32') and function_exists('gzcompress') and !$nozip)
	{
		ob_start();
	}

	$numsteps = sizeof($steptitles);
	if ($steptitle)
	{
		$stepstring = sprintf($installcore_phrases['step_x_of_y'], $step, $numsteps);
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
	<title><?php echo $installcore_phrases['vb3_install_script'] . " " . $steptitle; ?></title>
	<link rel="stylesheet" href="../cpstyles/vBulletin_3_Default/controlpanel.css" />
	<style type="text/css">
	#all {
		margin: 10px;
	}
	#all p, #all td, #all li, #all div {
		font-size: 11px;
		font-family: verdana, arial, helvetica, sans-serif;
	}
	</style>
</head>
<body style="margin:0px">
<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="border:outset 2px">
<tr>
	<td width="160"><img src="../cpstyles/vBulletin_3_Default/cp_logo.gif" alt="" title="vBulletin 3 &copy;2000 - <?php echo date('Y'); ?> Jelsoft Enterprises Ltd." /></td>
	<td style="padding-left:100px">
		<b><?php echo $installcore_phrases['vb3_install_script']; ?></b><br />
		<?php echo $installcore_phrases['may_take_some_time']; ?><br />
		<br />
		<b style="font-size:10pt;"><?php echo $steptitle; ?></b> <?php echo $stepstring; ?></td>
</tr>
</table>
<div id="all">
<?php
	if ($steptitle)
	{
		echo "<p style=\"font-size:10pt;\"><b><u>$steptitle</u></b></p>\n";
	}

	// spit all this stuff out
	flush();

}

// #########################################################################
// ends gzip encoding & finishes the page off
function print_upgrade_footer()
{
	unset($GLOBALS['DEVDEBUG']);
	echo '</div>';
	print_cp_footer();
}

// #########################################################################
// gets the appropriate step title from the $steptitles array
function fetch_step_title($step)
{
	global $steptitles, $installcore_phrases;
	if (isset($steptitles["$step"]))
	{
		return sprintf($installcore_phrases['step_title'], $step, $steptitles["$step"]);
	}
}

// #########################################################################
// redirects browser to next page in a multi-cycle step
function print_next_page($delay = 1)
{
	global $step, $perpage, $startat, $session, $installcore_phrases;

	log_upgrade_step();

	define('NONEXTSTEP', true);

	$startat = $startat + $perpage;

	print_cp_redirect(THIS_SCRIPT . "?step=$step&startat=$startat#end", $delay);

	?>
	</div>
	<form action="<?php echo THIS_SCRIPT; ?>" method="get">
	<input type="hidden" name="step" value="<?php echo $step; ?>" />
	<input type="hidden" name="startat" value="<?php echo $startat; ?>" />
	<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="padding:4px; border:outset 2px;">
	<tr align="center">
		<td><b><?php echo $installcore_phrases['batch_complete']; ?></b><br />vBulletin &copy;2000 - <?php echo date('Y'); ?> Jelsoft Enterprises Ltd.</td>
		<td><input type="submit" class="button" value="<?php echo $upgradecore_phrases['next_batch']; ?>" /></td>
	</tr>
	</table>
	</form>
	<?php

}

// #########################################################################
// displays a form at the bottom of the page to link to next step
function print_next_step()
{
	global $step, $numsteps, $perpage, $session, $installcore_phrases, $vbphrase;

	// do nothing if print_next_page() or nextStep has already been called
	if (defined('NONEXTSTEP'))
	{
		return;
	}

	define('NONEXTSTEP', true);

	// reset $perpage to tell the upgrade log that any multi-page steps are complete
	$perpage = 0;

	$nextstep = $step + 1;

	if ($step >= $numsteps)
	{
		$formaction = THIS_SCRIPT;
		$buttonvalue = ' ' . $vbphrase['proceed'] . ' ';
		$buttontitle = '';
	}
	else
	{
		$formaction = THIS_SCRIPT;
		$buttonvalue = sprintf($installcore_phrases['next_step'], $nextstep, $numsteps);
		$buttontitle = fetch_step_title($nextstep);

		// automatic advance - enable if you want to get through upgrades quickly without reading the text
		if (defined('UPGRADE_AUTOPROCEED') and $GLOBALS['debug'] and $step != 'welcome')
		{
			print_cp_redirect(THIS_SCRIPT . "?step=$nextstep", 0.5);
		}
	}

	?>
	</div>
	<form action="<?php echo $formaction; ?>" method="get" name="nextStep">
	<input type="hidden" name="step" value="<?php echo $nextstep; ?>" />
	<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="padding:4px; border:outset 2px;">
	<tr align="center">
		<td><?php if (!defined('HIDEPROCEED')) { ?><b><?php echo $installcore_phrases['click_button_to_proceed']; ?></b><br /><?php } ?>vBulletin &copy;2000 - <?php echo date('Y'); ?> Jelsoft Enterprises Ltd.</td>
		<td><?php if (!defined('HIDEPROCEED')) { ?><input type="submit" class="button" value="<?php echo $buttonvalue; ?>" title="<?php echo $buttontitle; ?>" /><?php } ?></td>
	</tr>
	</table>
	</form>
	<?php

}

// #########################################################################
// returns "page (pagenumber) of (totalpages)"
function construct_upgrade_page_hint($numresults, $startat, $perpage)
{
	global $installcore_phrases;
	$numpages = ceil($numresults / $perpage) + 1;
	$curpage = $startat / $perpage + 1;

	return sprintf($installcore_phrases['page_x_of_y'], $curpage, $numpages);
}

// #########################################################################
// runs through the $queries array and does the queries
function exec_queries($useLItag = false, $getids = false)
{
	global $DB_site, $query, $explain, $onvservers, $inserts;

	$inserts = array();

	if (is_array($query))
	{
		echo '<ul>';
		foreach ($query AS $key => $val)
		{
			echo "<li>$explain[$key]</li>\n";
			echo "<!-- ".htmlspecialchars_uni($val)." -->\n\n";
			flush();
			if ($onvservers == 1 and substr($val, 0, 5) == 'ALTER')
			{
				$DB_site->reporterror=0;
			}
			$DB_site->query($val);
			if ($getids)
			{
				$inserts[] = $DB_site->insert_id();
			}
			if ($onvservers == 1 and substr($val, 0, 5) == 'ALTER')
			{
				$DB_site->link_id=0;
				@$DB_site->close();

				sleep(1);
				$DB_site->connect();

				$DB_site->reporterror = 1;
			}
			//echo $DB_site->affected_rows();
		}
		echo '</ul>';
	}

	// the following only unsets the local copy! See unset()'s reference
	//unset($query);
	//unset($explain);
	unset($GLOBALS['query'], $GLOBALS['explain']);
}

// #########################################################################
// echoes out the string and flushes the output
function echo_flush($string)
{
	echo $string;
	flush();
}

// #########################################################################
// ################### FORUM UPDATE / IMPORT FUNCTIONS #####################
// #########################################################################



// ###################### Start makechildlist ########################
// returns the parentlist of a particular forum
function construct_child_list($forumid)
{
	global $DB_site;

	if ($forumid == -1)
	{
		return '-1';
	}

	$childlist = $forumid;

	$children = $DB_site->query("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum
		WHERE parentlist LIKE '%,$forumid,%'
	");
	while ($child = $DB_site->fetch_array($children))
	{
		$childlist .= ',' . $child['forumid'];
	}

	$childlist .= ',-1';

	return $childlist;

}

// ###################### Start updatechildlists #######################
// updates the child list for all forums
function build_forum_child_lists()
{
	global $DB_site;
	$forums = $DB_site->query("SELECT forumid FROM " . TABLE_PREFIX . "forum");
	while ($forum = $DB_site->fetch_array($forums))
	{
		$childlist = construct_child_list($forum['forumid']);
		$DB_site->query("UPDATE " . TABLE_PREFIX . "forum SET childlist = '$childlist' WHERE forumid = $forum[forumid]");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14315 $
|| ####################################################################
\*======================================================================*/
?>