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
ignore_user_abort(true);

@date_default_timezone_set(date_default_timezone_get());

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_IMPORT_DOTS', true);
define('VB_AREA', 'Install');
define('NOZIP', 1);

if (!defined('TIMENOW'))
{	
	define('TIMENOW', time());
}

chdir('./../');

// ########################## REQUIRE BACK-END ############################
require_once('./install/init.php');

if (!defined('PREV_VERSION'))
{
	define('PREV_VERSION', 'none');
}

require_once(DIR . '/install/install_language_en.php');
require_once(DIR . '/includes/functions.php');
require_once(DIR . '/includes/adminfunctions.php');
$steptitles = $install_phrases['steps'];
require_once(DIR . '/install/authenticate.php');
if (function_exists('set_time_limit') AND !SAFEMODE)
{
	@set_time_limit(0);
}
// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// check for valid php version
verify_vb3_enviroment();

exec_nocache_headers();

$vbulletin->input->clean_array_gpc('r', array(
	'step'    => TYPE_STR,
	'startat' => TYPE_UINT,
	'perpage' => TYPE_UINT,
));

$vbulletin->GPC['step'] = empty($vbulletin->GPC['step']) ? 'welcome' : intval($vbulletin->GPC['step']);
$query = array();
$explain = array();
$hiddenfields = array();

// assuming we've got through the authentication process, show the upgradeHeader.
if (empty($_REQUEST['do']))
{
	print_upgrade_header(fetch_step_title($vbulletin->GPC['step']));
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
	global $installcore_phrases, $vbulletin;

	$errorthrown = false;

	// php version check
	if (version_compare(PHP_VERSION, '5.6.0', '<'))
	{
		$errorthrown = true;
		echo "<p>$installcore_phrases[php_version_too_old]</p>";
	}

	if (defined('MYSQL_VERSION') AND version_compare(MYSQL_VERSION, '5.5.8', '<'))
	{
		$errorthrown = true;
		echo sprintf("<p>$installcore_phrases[mysql_version_too_old]</p>", MYSQL_VERSION);
	}

	// XML check
	if (!function_exists('xml_set_element_handler'))
	{
		// Attempt to load XML extension if we don't have the XML functions
		// already loaded.

		$errorthrown = true;

		if (ini_get('enable_dl') AND !SAFEMODE)
		{
			$extension_dir = ini_get('extension_dir');
			if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
			{
				$extension_file = 'php_xml.dll';
			}
			else
			{
				$extension_file = 'xml.so';
			}
			if ($extension_dir AND file_exists($extension_dir . '/' . $extension_file))
			{
				@ini_set('display_errors', 'On');
				if (dl($extension_file))
				{
					$errorthrown = false;
				}
			}
		}

		if ($errorthrown)
		{
			echo "<p>$installcore_phrases[need_xml]</p>";
		}
	}

	// MySQL check
	if (!function_exists('mysql_connect') AND !function_exists('mysqli_connect'))
	{
		$errorthrown = true;
		echo "<p>$installcore_phrases[need_mysql]</p>";
	}

	// config file check
	if (!file_exists(DIR . '/includes/config.php'))
	{
		$errorthrown = true;
		echo "<p>$installcore_phrases[need_config_file]</p>";
	}

	// check that we are NOT using the 'mysql' database
	if (strtolower($vbulletin->config['Database']['dbname']) == 'mysql')
	{
		$errorthrown = true;
		echo "<p>$installcore_phrases[dbname_is_mysql]</p>";
	}

	if (($err = verify_optimizer_environment()) !== true)
	{
		$errorthrown = true;
		echo "<p>{$installcore_phrases[$err]}</p>";
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
	global $vbulletin, $steptitles, $numsteps, $installcore_phrases, $stylevar;

	if ($vbulletin->options['gzipoutput'] and !headers_sent() and function_exists('ob_start') and function_exists('crc32') and function_exists('gzcompress') and !$vbulletin->nozip)
	{
		ob_start();
	}

	$numsteps = sizeof($steptitles);
	if ($steptitle)
	{
		$stepstring = sprintf($installcore_phrases['step_x_of_y'], $vbulletin->GPC['step'], $numsteps);
	}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html dir="<?php echo $stylevar['textdirection']; ?>" lang="<?php echo $stylevar['languagecode']; ?>">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $stylevar['charset']; ?>" />
	<title><?php echo $installcore_phrases['vb3_install_script'] . " " . $steptitle; ?></title>
	<link rel="stylesheet" href="../cpstyles/vBulletin_3_Silver/controlpanel.css?v=<?php echo $vbulletin->options['simpleversion'];?>" />
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
	<td width="160"><img src="../cpstyles/vBulletin_3_Silver/cp_logo.gif" alt="" title="vBulletin 3 &copy;2000 - <?php echo date('Y'); ?> vBulletin Solutions Inc." /></td>
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
	vbflush();

}

// #########################################################################
// ends gzip encoding & finishes the page off
function print_upgrade_footer()
{
	unset($GLOBALS['DEVDEBUG']);
	//echo '</div>';
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
	global $vbulletin, $installcore_phrases;

	log_upgrade_step();

	define('NONEXTSTEP', true);

	$vbulletin->GPC['startat'] = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	print_cp_redirect(THIS_SCRIPT . "?step={$vbulletin->GPC['step']}&startat={$vbulletin->GPC['startat']}#end", $delay);

	?>
	</div>
	<form action="<?php echo THIS_SCRIPT; ?>" method="get">
	<input type="hidden" name="step" value="<?php echo $vbulletin->GPC['step']; ?>" />
	<input type="hidden" name="startat" value="<?php echo $vbulletin->GPC['startat']; ?>" />
	<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="padding:4px; border:outset 2px;">
	<tr align="center">
		<td><b><?php echo $installcore_phrases['batch_complete']; ?></b><br />vBulletin &copy;2000 - <?php echo date('Y'); ?> vBulletin Solutions Inc.</td>
		<td><input type="submit" class="button" accesskey="s" value="<?php echo $upgradecore_phrases['next_batch']; ?>" /></td>
	</tr>
	</table>
	</form>
	<?php

}

// #########################################################################
// displays a form at the bottom of the page to link to next step
function print_next_step()
{
	global $vbulletin, $numsteps, $installcore_phrases, $vbphrase, $hiddenfields;

	// do nothing if print_next_page() or nextStep has already been called
	if (defined('NONEXTSTEP'))
	{
		return;
	}

	define('NONEXTSTEP', true);

	// reset $perpage to tell the upgrade log that any multi-page steps are complete
	$vbulletin->GPC['perpage'] = 0;

	$nextstep = intval($vbulletin->GPC['step']) + 1;

	if (!empty($step) AND ($step >= $numsteps))
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
		if (!defined('HIDEPROCEED') AND $vbulletin->GPC['step'] >= 3 AND !empty($vbulletin->config['Misc']['upgrade_autoproceed']) AND $vbulletin->debug AND ($vbulletin->GPC['step'] != 'welcome' OR $vbulletin->config['Misc']['upgrade_autoproceed'] == 'full'))
		{
			print_cp_redirect(THIS_SCRIPT . "?step=$nextstep", 0.5);
		}
	}
	
	echo '</div> <!-- end #all -->';

	?>
	<!-- </div> -->
	<form action="<?php echo $formaction; ?>" method="get" name="nextStep">
	<input type="hidden" name="step" value="<?php echo $nextstep; ?>" />
	<?php foreach($hiddenfields AS $varname => $value) { echo "<input type=\"hidden\" name=\"" . htmlspecialchars_uni($varname) . "\" value=\"" . htmlspecialchars_uni($value) . "\" />\r\n"; } ?>
	<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody" style="padding:4px; border:outset 2px;">
	<tr align="center">
		<td><?php if (!defined('HIDEPROCEED')) { ?><b><?php echo $installcore_phrases['click_button_to_proceed']; ?></b><br /><?php } ?>vBulletin &copy;2000 - <?php echo date('Y'); ?> vBulletin Solutions Inc.</td>
		<td><?php if (!defined('HIDEPROCEED')) { ?><input type="submit" class="button" accesskey="s" value="<?php echo $buttonvalue; ?>" title="<?php echo $buttontitle; ?>" /><?php } ?></td>
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
	global $vbulletin, $query, $explain, $inserts;

	$inserts = array();

	if (is_array($query))
	{
		echo '<ul>';
		foreach ($query AS $key => $val)
		{
			if (is_array($val))
			{	// turn error reporting off
				$val = array_pop($val);
				$vbulletin->db->hide_errors();
			}
			echo "<li>$explain[$key]</li>\n";
			echo "<!-- " . htmlspecialchars_uni($val) . " -->\n\n";
			vbflush();
			$vbulletin->db->query_write($val);
			if ($getids)
			{
				$inserts[] = $vbulletin->db->insert_id();
			}
			if (!$vbulletin->db->reporterror)
			{	// Turn error reporting back on
				$vbulletin->db->errno = 0;
				$vbulletin->db->show_errors();
			}
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
	vbflush();
}

// #########################################################################
// ################### FORUM UPDATE / IMPORT FUNCTIONS #####################
// #########################################################################



// ###################### Start makechildlist ########################
// returns the parentlist of a particular forum
function construct_child_list($forumid)
{
	global $vbulletin;

	if ($forumid == -1)
	{
		return '-1';
	}

	$childlist = $forumid;

	$children = $vbulletin->db->query_read("
		SELECT forumid
		FROM " . TABLE_PREFIX . "forum
		WHERE parentlist LIKE '%,$forumid,%'
	");
	while ($child = $vbulletin->db->fetch_array($children))
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
	global $vbulletin;

	$forums = $vbulletin->db->query_read("SELECT forumid FROM " . TABLE_PREFIX . "forum");
	while ($forum = $vbulletin->db->fetch_array($forums))
	{
		$childlist = construct_child_list($forum['forumid']);
		$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "forum SET childlist = '$childlist' WHERE forumid = $forum[forumid]");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
