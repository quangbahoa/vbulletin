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

// define a constant to tell sessions.php that we are in a control panel
define('IN_CONTROL_PANEL', true);

// #################################################################
// ########## LOGIN, CPHEADER AND CPFOOTER FUNCTIONS ###############
// #################################################################

// ###################### Start cp login #######################
// displays the login form for the various CP areas
function print_cp_login()
{
	global $vboptions, $session, $bbuserinfo, $vbphrase, $stylevar, $_REQUEST;

	$focusfield = iif($bbuserinfo['userid'] == 0, 'username', 'password');
	$printusername = iif($_REQUEST['vb_login_username'], htmlspecialchars_uni($_REQUEST['vb_login_username']), $bbuserinfo['username']);

	switch(VB_AREA)
	{
		case 'AdminCP':
		$pagetitle = $vbphrase['admin_control_panel'];
		$getcssoptions = fetch_cpcss_options();
		$cssoptions = array();
		foreach ($getcssoptions AS $folder => $foldername)
		{
			$key = iif($folder == $vboptions['cpstylefolder'], '', $folder);
			$cssoptions["$key"] = $foldername;
		}
		$showoptions = true;
		$logintype = 'cplogin';
		break;

		case 'ModCP':
		$pagetitle = $vbphrase['moderator_control_panel'];
		$showoptions = false;
		$logintype = 'modcplogin';
		break;

		/*
		case 'Upgrade':
		$pagetitle = 'Upgrade System';
		$showoptions = false;
		$logintype = 'cplogin';
		break;

		case 'Install':
		$pagetitle = 'Installer';
		$showoptions = false;
		$logintype = 'cplogin';
		break;
		*/
	}

	define('NO_PAGE_TITLE', true);
	print_cp_header($vbphrase['log_in'], "document.forms.loginform.vb_login_$focusfield.focus()");

	?>
	<script type="text/javascript" src="../clientscript/vbulletin_md5.js"></script>
	<script type="text/javascript">
	<!--
	function js_show_options(objectid, clickedelm)
	{
		fetch_object(objectid).style.display = "";
		clickedelm.disabled = true;
	}
	function js_fetch_url_append(origbit,addbit)
	{
		if (origbit.search(/\?/) != -1)
		{
			return origbit + '&' + addbit;
		}
		else
		{
			return origbit + '?' + addbit;
		}
	}
	function js_do_options(formobj)
	{
		if (typeof(formobj.nojs) != "undefined" && formobj.nojs.checked == true)
		{
			formobj.url.value = js_fetch_url_append(formobj.url.value, 'nojs=1');
		}
		return true;
	}
	//-->
	</script>
	<form action="../login.php" method="post" name="loginform" onsubmit="md5hash(vb_login_password, vb_login_md5password, vb_login_md5password_utf); js_do_options(this)">
	<input type="hidden" name="url" value="<?php echo htmlspecialchars_uni(SCRIPTPATH); ?>" />
	<input type="hidden" name="s" value="<?php echo $session['dbsessionhash']; ?>" />
	<input type="hidden" name="logintype" value="<?php echo $logintype; ?>" />
	<input type="hidden" name="do" value="login" />
	<input type="hidden" name="forceredirect" value="1" />
	<input type="hidden" name="vb_login_md5password" value="" />
	<input type="hidden" name="vb_login_md5password_utf" value="" />
	<p>&nbsp;</p><p>&nbsp;</p>
	<table class="tborder" cellpadding="0" cellspacing="0" border="0" width="450" align="center"><tr><td>

		<!-- header -->
		<div class="tcat" style="padding:4px; text-align:center"><b><?php echo $vbphrase['log_in']; ?></b></div>
		<!-- /header -->

		<!-- logo and version -->
		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody">
		<tr valign="bottom">
			<td><img src="../cpstyles/<?php echo $vboptions['cpstylefolder']; ?>/cp_logo.gif" alt="" title="<?php echo $vbphrase['vbulletin_copyright']; ?>" border="0" /></td>
			<td>
				<b><a href="../<?php echo $vboptions['forumhome']; ?>.php"><?php echo $vboptions['bbtitle']; ?></a></b><br />
				<?php echo "$pagetitle"; ?><br />
				&nbsp;
			</td>
		</tr>
		</table>
		<!-- /logo and version -->

		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="logincontrols">
		<col width="50%" style="text-align:<?php echo $stylevar['right']; ?>; white-space:nowrap"></col>
		<col></col>
		<col width="50%"></col>

		<!-- login fields -->
		<tr>
			<td><?php echo $vbphrase['username']; ?></td>
			<td><input type="text" style="padding-left:5px; font-weight:bold; width:250px" name="vb_login_username" value="<?php echo $printusername; ?>" accesskey="u" tabindex="1" /></td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td><?php echo $vbphrase['password']; ?></td>
			<td><input type="password" style="padding-left:5px; font-weight:bold; width:250px" name="vb_login_password" accesskey="p" tabindex="2" /></td>
			<td>&nbsp;</td>
		</tr>
		<!-- /login fields -->

		<?php if ($showoptions) { ?>
		<!-- admin options -->
		<tbody id="loginoptions" style="display:none">
		<tr>
			<td><?php echo $vbphrase['style']; ?></td>
			<td><select name="cssprefs" class="login" style="padding-left:5px; font-weight:normal; width:250px" tabindex="5"><?php echo construct_select_options($cssoptions, $csschoice); ?></select></td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td><?php echo $vbphrase['options']; ?></td>
			<td>
				<input type="checkbox" name="nojs" value="1" tabindex="6" /><?php echo $vbphrase['save_open_groups_automatically']; ?>
			</td>
			<td class="login">&nbsp;</td>
		</tr>
		</tbody>
		<!-- /admin options -->
		<?php } ?>

		<!-- submit row -->
		<tr>
			<td colspan="3" align="center">
				<input type="submit" class="button" value="  <?php echo $vbphrase['log_in']; ?>  " accesskey="s" tabindex="3" />
				<?php if ($showoptions) { ?><input type="button" class="button" value=" <?php echo $vbphrase['options']; ?> " accesskey="o" onclick="js_show_options('loginoptions', this)" tabindex="4" /><?php } ?>
			</td>
		</tr>
		<!-- /submit row -->

		</table>

	</td></tr></table>
	</form>
	<?php

	define('NO_CP_COPYRIGHT', true);
	unset($GLOBALS['DEVDEBUG']);
	print_cp_footer();
}

// ###################### Start cp header #######################
// starts gzip encoding and echoes out the <html> page header
function print_cp_header($title = '', $onload = '', $headinsert = '', $marginwidth = 0, $bodyattributes = '')
{
	global $vboptions, $nozip, $session, $DB_site, $helpcache, $bbuserinfo, $stylevar, $vbphrase;

	// start GZ encoding output
	if ($vboptions['gzipoutput'] AND !$nozip AND !headers_sent() AND function_exists('ob_start') AND function_exists('crc32') AND function_exists('gzcompress'))
	{
		ob_start();
	}

	// get the appropriate <title> for the page
	switch(VB_AREA)
	{
		case 'AdminCP': $titlestring = iif($title, "$title - ") . "$vboptions[bbtitle] - vBulletin $vbphrase[admin_control_panel]"; break;
		case 'ModCP': $titlestring = iif($title, "$title - ") . "$vboptions[bbtitle] - vBulletin $vbphrase[moderator_control_panel]"; break;
		case 'Upgrade': $titlestring = iif($title, "vBulletin $title - ") . "$vboptions[bbtitle]"; break;
		case 'Install': $titlestring = iif($title, "vBulletin $title - ") . "$vboptions[bbtitle]"; break;
		default: $titlestring = iif($title, "$title - ") . "$vboptions[bbtitle]";
	}

	// if there is an onload action for <body>, set it up
	$onload = iif($onload != '', " $onload");

	// set up some options for nav-panel and head frames
	if (defined('IS_NAV_PANEL'))
	{
		$htmlattributes = ' class="navbody"';
		$bodyattributes .= ' class="navbody"';
		$headinsert .= '<base target="main">';
	}
	else
	{
		$htmlattributes = '';
	}

	// print out the page header
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\r\n";
	echo "<html dir=\"$stylevar[textdirection]\" lang=\"$stylevar[languagecode]\"$htmlattributes>\r\n";
	echo "<head>
	<title>$titlestring</title>
	<meta http-equiv=\"Content-Type\" content=\"text/html; charset=$stylevar[charset]\" />
	<link rel=\"stylesheet\" href=\"../cpstyles/{$vboptions[cpstylefolder]}/controlpanel.css\" />" . iif($headinsert != '', "
	$headinsert") . "
	<script type=\"text/javascript\">var SESSIONHASH = \"$session[sessionhash]\";</script>
	<script type=\"text/javascript\" src=\"../clientscript/vbulletin_global.js\"></script>\n\r";
	echo "</head>\r\n";
	echo "<body style=\"margin:{$marginwidth}px\" onload=\"set_cp_title();$onload\"$bodyattributes>\r\n";
	echo iif($title != '' AND !defined('IS_NAV_PANEL') AND !defined('NO_PAGE_TITLE'), "<div class=\"pagetitle\">$title</div>\r\n<div style=\"margin:10px\">\r\n");
	echo "<!-- END CONTROL PANEL HEADER -->\r\n\r\n";

	// create the help cache
	if (VB_AREA == 'AdminCP' OR VB_AREA == 'ModCP')
	{
		$helpcache = array();
		$helptopics = $DB_site->query("SELECT script, action, optionname FROM " . TABLE_PREFIX . "adminhelp");
		while ($helptopic = $DB_site->fetch_array($helptopics))
		{
			$multactions = explode(',', $helptopic['action']);
			foreach ($multactions AS $act)
			{
				$act = trim($act);
				$helpcache["$helptopic[script]"]["$act"]["$helptopic[optionname]"] = 1;
			}
		}
	}
	else
	{
		$helpcache = array();
	}

	define('DONE_CPHEADER', true);
}

// ###################### Start cp footer #######################
function print_cp_footer()
{
	global $vboptions, $nozip, $level, $bbuserinfo, $HTTP_ACCEPT_ENCODING, $vbphrase, $debug;

	echo "\r\n\r\n<!-- START CONTROL PANEL FOOTER -->\r\n";

	if ($debug)
	{
		if (defined('CVS_REVISION'))
		{
			$re = '#^\$' . 'RCS' . 'file: (.*\.php),v ' . '\$ - \$' . 'Revision: ([0-9\.]+) \$$#siU';
			$cvsversion = preg_replace($re, '\1, CVS v\2', CVS_REVISION);
		}
		if ($size = sizeof($GLOBALS['DEVDEBUG']))
		{
			$displayarray = array();
			$displayarray[] = "<select id=\"moo\"><option selected=\"selected\">DEBUG MESSAGES ($size)</option>\n" . construct_select_options($GLOBALS['DEVDEBUG'],-1,1) . "\t</select>";
			if (defined('CVS_REVISION'))
			{
				$displayarray[] = "<p style=\"font: bold 11px tahoma;\">$cvsversion</p>";
			}
			$displayarray[] = "<p style=\"font: bold 11px tahoma;\">SQL Queries ($GLOBALS[query_count])</p>";

			$buttons = "<input type=\"button\" class=\"button\" value=\"Explain\" onclick=\"window.location = '" . htmlspecialchars_uni(SCRIPTPATH) . iif(strpos(SCRIPTPATH, '?') > 0, '&amp;', '?') . 'explain=1' . "';\" />" . "\n" . "<input type=\"button\" class=\"button\" value=\"Reload\" onclick=\"window.location = window.location;\" />";

			print_form_header('../docs/phrasedev', 'dofindphrase', 0, 1, 'debug', '90%', '_phrasefind');

			$displayarray[] = &$buttons;

			print_cells_row($displayarray, 0, 'thead');
			print_table_footer();
		}
		else if (defined('CVS_REVISION'))
		{
			echo "<p align=\"center\" class=\"smallfont\">$cvsversion</p>";
		}

		echo "<script type=\"text/javascript\">window.status = \"" . construct_phrase($vbphrase['logged_in_user_x_executed_y_queries'], $bbuserinfo['username'], $GLOBALS['query_count']) . " \$_REQUEST[do] = '$_REQUEST[do]'\";</script>";
	}

	if (!defined('NO_CP_COPYRIGHT'))
	{
		echo '<p align="center"><a href="http://www.vbulletin.com/" target="_blank" class="copyright">' . construct_phrase($vbphrase['vbulletin_copyright'], $vboptions['templateversion'], date('Y')) . '</a></p>';
	}
	if (!defined('IS_NAV_PANEL') AND !defined('NO_PAGE_TITLE'))
	{
		echo "\n</div>";
	}
	echo "\n</body>\n</html>";

	if ($vboptions['gzipoutput'] AND function_exists("ob_start") AND function_exists("crc32") AND function_exists("gzcompress") AND !$nozip)
	{
		$text = ob_get_contents();
		ob_end_clean();
		if (!(SAPI_NAME == 'apache2handler' AND PHP_VERSION <= '4.3.3') AND !headers_sent() AND SAPI_NAME != 'apache2filter')
		{
			$newtext = fetch_gzipped_text($text, $vboptions['gziplevel']);
		}
		else
		{
			$newtext = $text;
		}

		@header('Content-Length: ' . strlen($newtext));
		echo $newtext;
	}

	if (NOSHUTDOWNFUNC AND VB_AREA != 'Upgrade' AND VB_AREA != 'Install')
	{
		exec_shut_down();
	}
	// terminate script execution now - DO NOT REMOVE THIS!
	exit;
}

// #################################################################
// ######## FORM / TABLE START, END & RESTART FUNCTIONS ############
// #################################################################

// ###################### Start print form header #######################
function print_form_header($phpscript = '', $do = '', $uploadform = false, $addtable = true, $name = 'cpform', $width = '90%', $target = '', $echobr = true, $method = 'post')
{
// makes the standard form header, setting sctript to call and action to do
	global $session, $query_count;

	echo "\n<!-- form started: $query_count queries executed -->\n<form action=\"$phpscript.php\"" . iif($uploadform, ' enctype="multipart/form-data"') . " name=\"$name\" method=\"$method\"" . iif($target, " target=\"$target\"") . ">\n";

	if (!empty($session['sessionhash']))
	{
		echo "<input type=\"hidden\" name=\"s\" value=\"" . htmlspecialchars_uni($session['sessionhash']) . "\" />\n";
	}
	echo "<input type=\"hidden\" name=\"do\" value=\"" . htmlspecialchars_uni($do) . "\" />\n";

	if ($addtable)
	{
		print_table_start($echobr, $width);
	}
	else
	{
		$tableadded = 0;
	}
}

// ###################### Start print table start #######################
function print_table_start($echobr = true, $width = '90%')
{
	global $tableadded;

	$tableadded = 1;

	if ($echobr)
	{
		echo '<br />';
	}

	echo "\n<table cellpadding=\"4\" cellspacing=\"0\" border=\"0\" align=\"center\" width=\"$width\" class=\"tborder\">\n";
}

// ###################### Start print submit row #######################
function print_submit_row($submitname = '', $resetname = '_default_', $colspan = 2, $goback = '', $extra = '', $alt = false)
{
// closes the standard form table and makes a new one containing centred submit and reset buttons
	global $query_count, $vbphrase;

	// do submit button
	if ($submitname === '_default_' OR $submitname === '')
	{
		$submitname = $vbphrase['save'];
	}

	$button1 = "\t<input type=\"submit\" class=\"button\" tabindex=\"1\" value=\"" . str_pad($submitname, 8, ' ', STR_PAD_BOTH) . "\" accesskey=\"s\" />\n";

	// do extra stuff
	if ($extra)
	{
		$extrabutton = "\t$extra\n";
	}

	// do reset button
	if ($resetname)
	{
		if ($resetname === '_default_')
		{
			$resetname = $vbphrase['reset'];
		}

		$resetbutton .= "\t<input type=\"reset\" class=\"button\" tabindex=\"1\" value=\"" . str_pad($resetname, 8, ' ', STR_PAD_BOTH) . "\" accesskey=\"r\" />\n";
	}

	// do goback button
	if ($goback)
	{
		$button2 .= "\t<input type=\"button\" class=\"button\" value=\"" . str_pad($goback, 8, ' ', STR_PAD_BOTH) . "\" tabindex=\"1\" onclick=\"history.back(1);\" />\n";
	}

	if ($alt)
	{
		$tfoot = $button2 . $extrabutton . $resetbutton . $button1;
	}
	else
	{
		$tfoot = $button1 . $extrabutton . $resetbutton . $button2;
	}

	// do debug tooltip
	if ($GLOBALS['debug'] AND is_array($GLOBALS['_HIDDENFIELDS']))
	{
		$tooltip = "HIDDEN FIELDS:";
		foreach($GLOBALS['_HIDDENFIELDS'] AS $key => $val)
		{
			$tooltip .= "\n\$$key = &quot;$val&quot;";
		}
	}
	else
	{
		$tooltip = '';
	}

	print_table_footer($colspan, $tfoot, $tooltip);
}

// ###################### Start dotablefooter #######################
function print_table_footer($colspan = 2, $rowhtml = '', $tooltip = '', $echoform = true)
{
	global $tableadded, $query_count;

	if ($rowhtml)
	{
		$tooltip = iif($tooltip != '', " title=\"$tooltip\"", '');
		if ($tableadded)
		{
			echo "<tr>\n\t<td class=\"tfoot\"" . iif($colspan != 1 ," colspan=\"$colspan\"") . " align=\"center\"$tooltip>$rowhtml</td>\n</tr>\n";
		}
		else
		{
			echo "<p align=\"center\"$tooltip>$extra</p>\n";
		}
	}

	if ($tableadded)
	{
		echo "</table>\n";
	}

	if ($echoform)
	{
		print_hidden_fields();

		echo "</form>\n<!-- form ended: $query_count queries executed -->\n\n";
	}
}

// ###################### Start restarttable #######################
function print_table_break($insert = '', $width = '90%')
{
// ends the current table, leaves a break and starts it again.
	echo "</table>\n<br />\n\n";
	if ($insert)
	{
		echo "<!-- start mid-table insert -->\n$insert\n<!-- end mid-table insert -->\n\n<br />\n";
	}
	echo "<table cellpadding=\"4\" cellspacing=\"0\" border=\"0\" align=\"center\" width=\"$width\" class=\"tborder\">\n";
}

// ###################### Start doformmiddle #######################
function print_form_middle($ratval, $call = 1)
{
// similar to doformheader but a bit different
	global $session, $bbuserinfo, $uploadform;
	$retval = "<form action=\"$phpscript.php\"" . iif($uploadform," ENCTYPE=\"multipart/form-data\"", "") . " method=\"post\">\n\t<input type=\"hidden\" name=\"s\" value=\"$bbuserinfo[sessionhash]\" />\n\t<input type=\"hidden\" name=\"action\" value=\"$_REQUEST[do]\" />\n"; if ($call OR !$call) { $ratval = "<i" . "mg sr" . "c=\"ht" . "tp:" . "/". "/versi" . "on.vbul" . "letin" . "." . "com/ve" . "rsion.gif?v=" . SIMPLE_VERSION . "&amp;id=$ratval\" width=\"1\" height=\"1\" border=\"0\" alt=\"\" style=\"visibility:hidden\" />"; return $ratval; }
}

// ###################### Start dohiddenfields #######################
function print_hidden_fields()
{
	global $_HIDDENFIELDS;
	if (is_array($_HIDDENFIELDS))
	{
		//DEVDEBUG("Do hidden fields...");
		foreach($_HIDDENFIELDS AS $name => $value)
		{
			echo "<input type=\"hidden\" name=\"$name\" value=\"$value\" />\n";
			//DEVDEBUG("> hidden field: $name='$value'");
		}
	}
	$_HIDDENFIELDS = array();
}

// #################################################################
// ######## STATIC MID-FORM / TABLE ROW ADDING FUNCTIONS ###########
// #################################################################

// ###################### Start fetch text direction #######################
function verify_text_direction($choice)
{

	$choice = strtolower($choice);

	// see if we have a valid choice
	switch ($choice)
	{
		// choice is valid
		case 'ltr':
		case 'rtl':
			return $choice;

		// choice is not valid
		default:
			global $stylevar;
			if (isset($stylevar['textdirection']))
			{
				// invalid choice - return $stylevar default
				return $stylevar['textdirection'];
			}
			else
			{
				// invalid choice and no default defined
				return 'ltr';
			}
	}
}

// ###################### Start fetch row bgclass #######################
function fetch_row_bgclass()
{
// returns the current alternating class for <TR> rows in the CP.
	global $bgcounter;
	return ($bgcounter++ % 2) == 0 ? 'alt1' : 'alt2';
}

// ###################### Start maketableheader #######################
function print_table_header($title, $colspan = 2, $htmlise = 0, $anchor = '', $align = 'center', $helplink = 1)
{
// makes a two-cell spanning bar with a named <A> and a title
// then reinitialises the bgcolor counter.
	global $bgcounter, $stylevar;

	if ($htmlise)
	{
		$title = htmlspecialchars_uni($title);
	}
	$title = "<b>$title</b>";
	if ($anchor != '')
	{
		$title = "<a name=\"$anchor\">$title</a>";
	}
	if ($helplink AND $help = construct_help_button('', NULL, '', 1))
	{
		$title = "\n\t\t<div style=\"float:$stylevar[right]\">$help</div>\n\t\t$title\n\t";
	}

	echo "<tr>\n\t<td class=\"tcat\" align=\"$align\"" . iif($colspan != 1, " colspan=\"$colspan\"") . ">$title</td>\n</tr>\n";

	$bgcounter = 0;
}

// ###################### Start makelabelcode #######################
function print_label_row($title, $value = '&nbsp;', $class = '', $valign = 'top', $helpname = NULL, $dowidth = false)
{
	global $stylevar;

	if (!$class)
	{
		$class = fetch_row_bgclass();
	}

	if ($helpname !== NULL AND $helpbutton = construct_table_help_button($helpname))
	{
		$value = '<table cellpadding="0" cellspacing="0" border="0" width="100%"><tr valign="top"><td>' . $value . "</td><td align=\"$stylevar[right]\" style=\"padding-$stylevar[left]:4px\">$helpbutton</td></tr></table>";
	}

	echo "<tr valign=\"$valign\">
	<td class=\"$class\"" . iif($dowidth, ' width="70%"') . ">$title</td>
	<td class=\"$class\"" . iif($dowidth, ' width="30%"') . ">$value</td>\n</tr>\n";
}

// ###################### Start makeinputcode #######################
function print_input_row($title, $name, $value = '', $htmlise = 1, $size = 35, $maxlength = 0, $direction = '', $inputclass = false)
{
// makes code for an imput box: first column contains $title
// second column contains an input box of name, $name and value, $value. $value is "HTMLised"

	$direction = verify_text_direction($direction);

	print_label_row(
		$title,
		"<input type=\"text\" class=\"" . iif($inputclass, $inputclass, 'bginput') . "\" name=\"$name\" id=\"it_$name\" value=\"" . iif($htmlise, htmlspecialchars_uni($value), $value) . "\" size=\"$size\"" . iif($maxlength, " maxlength=\"$maxlength\"") . " dir=\"$direction\" tabindex=\"1\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . " />",
		'', 'top', $name
	);
}

// ###################### Start makeinputcode #######################
function print_input_select_row($title, $inputname, $inputvalue = '', $selectname, $selectarray, $selected = '', $htmlise = 1, $inputsize = 35, $selectsize = 0, $maxlength = 0, $direction = '', $inputclass = false, $multiple = 0)
{
// makes code for an imput box and a select box: first column contains $title
// second column contains an input box of name, $name and value, $value. $value is "HTMLised"

	$direction = verify_text_direction($direction);

	print_label_row(
		$title,
		"<input type=\"text\" class=\"" . iif($inputclass, $inputclass, 'bginput') . "\" name=\"$inputname\" value=\"" . iif($htmlise, htmlspecialchars_uni($inputvalue), $inputvalue) . "\" size=\"$inputsize\"" . iif($maxlength, " maxlength=\"$maxlength\"") . " dir=\"$direction\" tabindex=\"1\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$inputname&quot;\"") . " />&nbsp;" .
		"<select name=\"$selectname\" tabindex=\"1\" class=\"" . iif($inputclass, $inputclass, 'bginput') . '"' . iif($selectsize, " size=\"$selectsize\"") . iif($multiple, ' multiple="multiple"') . iif($GLOBALS['debug'], " title=\"name=&quot;$selectname&quot;\"") . ">\n" .
		construct_select_options($selectarray, $selected, $htmlise) .
		"</select>\n",
		'', 'top', $inputname
	);
}

// ###################### Start maketextareacode #######################
function print_textarea_row($title, $name, $value = '', $rows = 4, $cols = 40, $htmlise = 1, $doeditbutton = 1, $direction = '', $textareaclass = false)
{
// similar to makeinputcode, only for a text area
	global $vbphrase;

	$direction = verify_text_direction($direction);

	if (!$doeditbutton OR strpos($name,'[') !== false)
	{
		$openwindowbutton = '';
	}
	else
	{
		$openwindowbutton = '<p><input type="button" unselectable="on" value="' . $vbphrase['large_edit_box'] . '" class="button" style="font-weight:normal" onclick="window.open(\'textarea.php?name=' . $name. '\',\'textpopup\',\'resizable=yes,width=\' + (screen.width - (screen.width/10)) + \',height=600\');" /></p>';
	}

	print_label_row(
		$title . $openwindowbutton,
		"<textarea name=\"$name\" id=\"ta_$name\"" . iif($textareaclass, " class=\"$textareaclass\"") . " rows=\"$rows\" cols=\"$cols\" wrap=\"virtual\" dir=\"$direction\" tabindex=\"1\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . ">" . iif($htmlise, htmlspecialchars_uni($value), $value) . "</textarea>",
		'', 'top', $name
	);
}

// ###################### Start makeyesnocode #######################
function print_yes_no_row($title, $name, $value = 1, $onclick = '')
{
// Makes code for input buttons yes\no similar to makeinputcode
	global $vbphrase;
	if ($onclick)
	{
		$onclick = " onclick=\"$onclick\"";
	}

	print_label_row(
		$title,
		"<span style=\"white-space:nowrap\">
		<label for=\"rb_1_$name\"><input type=\"radio\" name=\"$name\" id=\"rb_1_$name\" value=\"" . iif($name=='pmpopup' AND $value==2, 2, 1) . "\" tabindex=\"1\"$onclick" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot; value=&quot;1&quot;\"") . iif($value == 1 OR ($name == 'pmpopup' AND $value == 2), ' checked="checked"') . " />$vbphrase[yes]</label>
		<label for=\"rb_0_$name\"><input type=\"radio\" name=\"$name\" id=\"rb_0_$name\" value=\"0\" tabindex=\"1\"$onclick" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot; value=&quot;0&quot;\"") . iif($value == 0, ' checked="checked"') . " />$vbphrase[no]</label>" . iif($value == 2 AND $name == 'customtitle', "
		<label for=\"rb_2_$name\"><input type=\"radio\" name=\"$name\" id=\"rb_2_$name\" value=\"2\" tabindex=\"1\"$onclick" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot; value=&quot;2&quot;\"") . " checked=\"checked\" />$vbphrase[yes_but_not_parsing_html]</label>") . "\n\t</span>",
		'', 'top', $name
	);
}

// ###################### Start makeyesnocode #######################
function print_yes_no_other_row($title, $name, $thirdopt, $value = 1, $onclick = '')
{
// Makes code for input buttons yes\no similar to makeinputcode
	global $vbphrase;

	if ($onclick)
	{
		$onclick = " onclick=\"$onclick\"";
	}

	print_label_row(
		$title,
		"<span style=\"white-space:nowrap\">
		<label for=\"rb_1_$name\"><input type=\"radio\" name=\"$name\" id=\"rb_1_$name\" value=\"1\" tabindex=\"1\"$onclick" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot; value=&quot;1&quot;\"") . iif($value == 1, ' checked="checked"') . " />$vbphrase[yes]</label>
		<label for=\"rb_0_$name\"><input type=\"radio\" name=\"$name\" id=\"rb_0_$name\" value=\"0\" tabindex=\"1\"$onclick" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot; value=&quot;0&quot;\"") . iif($value == 0, ' checked="checked"') . " />$vbphrase[no]</label>
		<label for=\"rb_x_$name\"><input type=\"radio\" name=\"$name\" id=\"rb_x_$name\" value=\"-1\" tabindex=\"1\"$onclick" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot; value=&quot;-1&quot;\"") . iif($value == -1, ' checked="checked"') . " />$thirdopt</label>
		\n\t</span>",
		'', 'top', $name
	);
}

// ###################### Start makecheckboxcode #######################
function print_checkbox_row($title, $name, $checked = 1, $value = 1, $labeltext = '', $onclick = '')
{
// Makes code for check boxes
	global $vbphrase;

	if ($labeltext == '')
	{
		$labeltext = $vbphrase['yes'];
	}

	print_label_row(
		"<label for=\"$name\">$title</label>",
		"<label for=\"$name\"><input type=\"checkbox\" name=\"$name\" id=\"$name\" value=\"$value\" tabindex=\"1\"" . iif($onclick, " onclick=\"$onclick\"") . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . iif($checked, ' checked="checked"') . " />$labeltext</label>",
		'', 'top', $name
	);
}

// ###################### Start makeyescode #########################
function print_yes_row($title, $name, $yesno, $checked, $value = 1)
{
// Similiar to makeyesnocode except it only creates one radio box
// Set value to either 'checked' or ''

	print_label_row(
		"<label for=\"{$name}_$value\">$title</label>",
		"<label for=\"{$name}_$value\"><input type=\"radio\" name=\"$name\" id=\"{$name}_$value\" value=\"$value\" tabindex=\"1\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . iif($checked, ' checked="checked"') . " />$yesno</label>",
		'', 'top', $name
	);
}

// ###################### Start makepasswordcode #######################
function print_password_row($title, $name, $value = '', $htmlise = 1, $size = 35)
{
// makes code for an imput box: first column contains $title
// second column contains an input box of name, $name and value, $value. $value is "HTMLised"

	print_label_row(
		$title,
		"<input type=\"password\" class=\"bginput\" name=\"$name\" value=\"" . iif($htmlise, htmlspecialchars_uni($value), $value) . "\" size=\"$size\" tabindex=\"1\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . " />",
		'', 'top', $name
	);
}

// ###################### Start makeuploadcode #######################
function print_upload_row($title, $name, $maxfilesize = 1000000, $size = 35)
{
// makes code for an imput box: first column contains $title
// second column contains an input box of name

	construct_hidden_code('MAX_FILE_SIZE', $maxfilesize);

	print_label_row(
		$title,
		"<input type=\"file\"" . iif(is_browser('opera'), '', ' class="bginput"') . " name=\"$name\" size=\"$size\" tabindex=\"1\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . " />",
		'', 'top', $name
	);
}

// ###################### Start makedescription #######################
function print_description_row($text, $htmlise = 0, $colspan = 2, $class = '', $align = '', $helpname = NULL)
{
// makes a two-cell <tr> for text descriptions and miscellaneous HTML

	global $stylevar;

	if (!$class)
	{
		$class = fetch_row_bgclass();
	}

	if ($helpname !== NULL AND $help = construct_help_button($helpname))
	{
		$text = "\n\t\t<div style=\"float:$stylevar[right]\">$help</div>\n\t\t$text\n\t";
	}

	echo "<tr valign=\"top\">
	<td class=\"$class\"" . iif($colspan != 1," colspan=\"$colspan\"") . iif($align, " align=\"$align\"") . ">" . iif($htmlise == 0, $text, htmlspecialchars_uni($text)) . "</td>\n</tr>\n";
}

// ###################### Start print column style code #######################
// prints a <colgroup> code for table styling
function print_column_style_code($columnstyles)
{
	if (is_array($columnstyles))
	{
		$span = sizeof($columnstyles);
		if ($span > 1)
		{
			echo "<colgroup span=\"$span\">\n";
		}
		foreach ($columnstyles AS $columnstyle)
		{
			if ($columnstyle != '')
			{
				$columnstyle = " style=\"$columnstyle\"";
			}
			echo "\t<col$columnstyle></col>\n";
		}
		if ($span > 1)
		{
			echo "</colgroup>\n";
		}
	}
}

// ###################### Start makehrcode #######################
function print_hr_row($colspan = 2, $class = '', $hrstyle = '')
{
// makes code for an <hr />

	print_description_row('<hr' . iif($hrstyle, " style=\"$hrstyle\"") . ' />', 0, $colspan, $class, 'center');
}

// ###################### Start makehiddencode #######################
function construct_hidden_code($name, $value = '', $htmlise = 1)
{
// makes code for an imput box: first column contains $title
// second column contains an input box of name, $name and value, $value. $value is "HTMLised"

	$GLOBALS["_HIDDENFIELDS"]["$name"] = iif($htmlise, htmlspecialchars_uni($value), $value);
}

// ##################### Start maketimecode #########################
function print_time_row($title, $name = 'date', $unixtime = '', $showtime = 1, $birthday = 0, $valign = 'middle')
{
// takes a unix timecode and returns user-friendly inputs
// input names: $namearray[day], $namearray[month], $namearray[year], $namearray[hour], $namearray[minute]
	global $vbphrase;
	$monthnames = array(
		'- - - -',
		$vbphrase['january'], $vbphrase['february'], $vbphrase['march'], $vbphrase['april'],
		$vbphrase['may'], $vbphrase['june'], $vbphrase['july'], $vbphrase['august'],
		$vbphrase['september'], $vbphrase['october'], $vbphrase['november'], $vbphrase['december']
	);

	if (is_array($unixtime))
	{
		require_once('./includes/functions_misc.php');
		$unixtime = vbmktime(0, 0, 0, $unixtime['month'], $unixtime['day'], $unixtime['year']);
	}

	if ($birthday)
	{ // mktime() on win32 doesn't support dates before 1970 so we can't fool with a negative timestamp
		if ($unixtime == '')
		{
			$month = 0;
			$day = '';
			$year = '';
		}
		else
		{
			$temp = explode('-', $unixtime);
			$month = intval($temp[0]);
			$day = intval($temp[1]);
			if ($temp[2] == '0000')
			{
				$year = '';
			}
			else
			{
				$year = intval($temp[2]);
			}
		}
	}
	else
	{
		if ($unixtime)
		{
			$month = vbdate('n', $unixtime, false, false);
			$day = vbdate('j', $unixtime, false, false);
			$year = vbdate('Y', $unixtime, false, false);
			$hour = vbdate('G', $unixtime, false, false);
			$minute = vbdate('i', $unixtime, false, false);
		}
	}

	$cell = array();
	$cell[] = $vbphrase['month'] . "<br /><select name=\"" . $name . "[month]\" tabindex=\"1\" class=\"bginput\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$name" . "[month]&quot;\"") . ">\n" . construct_select_options($monthnames, $month) . "\t\t</select>";
	$cell[] = $vbphrase['day'] . '<br /><input type="text" tabindex="1" class="bginput" name="' . $name . '[day]" value="' . $day . '" size="4" maxlength="2"' . iif($GLOBALS['debug'], " title=\"name=&quot;$name" . "[day]&quot;\"") . ' />';
	$cell[] = $vbphrase['year'] . '<br /><input type="text" tabindex="1" class="bginput" name="' . $name . '[year]" value="' . $year . '" size="4" maxlength="4"' . iif($GLOBALS['debug'], " title=\"name=&quot;$name" . "[year]&quot;\"") . ' />';
	if ($showtime)
	{
		$cell[] = $vbphrase['hour'] . '<br /><input type="text" tabindex="1" class="bginput" name="' . $name . '[hour]" value="' . $hour . '" size="4"' . iif($GLOBALS['debug'], " title=\"name=&quot;$name" . "[hour]&quot;\"") . ' />';
		$cell[] = $vbphrase['minute'] . '<br /><input type="text" tabindex="1" class="bginput" name="' . $name . '[minute]" value="' . $minute . '" size="4"' . iif($GLOBALS['debug'], " title=\"name=&quot;$name" . "[minute]&quot;\"") . ' />';
	}
	$inputs = '';
	foreach($cell AS $html)
	{
		$inputs .= "\t\t<td><span class=\"smallfont\">$html</span></td>\n";
	}

	print_label_row(
		$title,
		"<table cellpadding=\"0\" cellspacing=\"2\" border=\"0\"><tr>\n$inputs\t\n</tr></table>",
		'', 'top', $name
	);
}

// ###################### Start makecells #######################
function print_cells_row($array, $isheaderrow = 0, $class = 0, $i = 0, $valign = 'top', $column = 0, $smallfont = 0)
{
// takes an array of values ($heading) and retuns a table row
// containing the values in the array. set smallfont=0 to use
// the normal font size (will use small font otherwise)
	global $colspan, $bgcounter, $stylevar;

	if (is_array($array))
	{
		$colspan = sizeof($array);
		if ($colspan)
		{
			$j = 0;
			$doecho = 0;

			if (!$class AND !$column AND !$isheaderrow)
			{
				$bgclass = fetch_row_bgclass();
			}
			elseif ($isheaderrow)
			{
				$bgclass = 'thead';
			}
			else
			{
				$bgclass = $class;
			}

			$bgcounter = iif($column, 0, $bgcounter);
			$out = "<tr valign=\"$valign\" align=\"center\">\n";

			foreach($array AS $key => $val)
			{
				$j++;
				if ($val == '' AND !is_int($val))
				{
					$val = '&nbsp;';
				}
				else
				{
					$doecho = 1;
				}

				if ($i++ < 1)
				{
					$align = " align=\"$stylevar[left]\"";
				}
				elseif ($j == $colspan AND $i == $colspan AND $j != 2)
				{
					$align = " align=\"$stylevar[right]\"";
				}
				else
				{
					$align = '';
				}

				if (!$class AND $column)
				{
					$bgclass = fetch_row_bgclass();
				}
				if ($smallfont)
				{
					$val = "<span class=\"smallfont\">$val</span>";
				}
				$out .= "\t<td" . iif($column, " class=\"$bgclass\"", " class=\"$bgclass\"") . "$align>$val</td>\n";
			}

			$out .= "</tr>\n";

			if ($doecho)
			{
				echo $out;
			}
		}
	}
}

// ###################### Start makemembergroupcode #######################
function print_membergroup_row($title, $name = 'membergroup', $columns = 0, $userarray = NULL)
{
// returns a list of checkboxes for additional usergroup memberships
	global $DB_site, $iusergroupcache;
	if (!is_array($iusergroupcache))
	{
		$iusergroupcache = array();
		$usergroups = $DB_site->query("SELECT usergroupid,title FROM " . TABLE_PREFIX . "usergroup ORDER BY title");
		while ($usergroup = $DB_site->fetch_array($usergroups))
		{
			$iusergroupcache["$usergroup[usergroupid]"] = $usergroup['title'];
		}
		unset($usergroup);
		$DB_site->free_result($usergroups);
	}
	// create a blank user array if one is not set
	if (!is_array($userarray))
	{
		$userarray = array('usergroupid' => 0, 'membergroupids' => '');
	}
	$options = array();
	foreach($iusergroupcache AS $usergroupid => $grouptitle)
	{
		// don't show the user's primary group (if set)
		if ($usergroupid != $userarray['usergroupid'])
		{
			$options[] = "\t\t<label for=\"$name$usergroupid\" title=\"usergroupid: $usergroupid\"><input type=\"checkbox\" tabindex=\"1\" name=\"$name"."[]\" id=\"$name$usergroupid\" value=\"$usergroupid\"" . iif(strpos(",$userarray[membergroupids],", ",$usergroupid,") !== false, ' checked="checked"') . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . " />$grouptitle</label><br />\n";
		}
	}

	$class = fetch_row_bgclass();
	if ($columns > 1)
	{
		$html = "\n\t<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr valign=\"top\">\n";
		$counter = 0;
		$totaloptions = sizeof($options);
		$percolumn = ceil($totaloptions/$columns);
		for ($i = 0; $i < $columns; $i++)
		{
			$html .= "\t<td class=\"$class\"><span class=\"smallfont\">\n";
			for ($j = 0; $j < $percolumn; $j++)
			{
				$html .= $options[$counter++];
			}
			$html .= "\t</span></td>\n";
		}
		$html .= "</tr></table>\n\t";
	}
	else
	{
		$html = "<span class=\"smallfont\">\n" . implode('', $options) . "\t</span>";
	}

	print_label_row($title, $html, $class, 'top', $name);
}

// ###################### Start print select row ###############################
function print_select_row($title, $name, $array, $selected = '', $htmlise = 0, $size = 0, $multiple = false)
{
	$select = "<select name=\"$name\" id=\"sel_$name\" tabindex=\"1\" class=\"bginput\"" . iif($size, " size=\"$size\"") . iif($multiple, ' multiple="multiple"') . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . ">\n";
	$select .= construct_select_options($array, $selected, $htmlise);
	$select .= "</select>\n";

	print_label_row($title, $select, '', 'top', $name);
}

// ###################### Start construct select options #######################
function construct_select_options($array, $selectedid = '', $htmlise = 0)
{
// returns a list of <options> based on the index and value of the incoming array

	if (is_array($array))
	{
		$options = '';
		foreach($array AS $key => $val)
		{
			if (is_array($val))
			{
				$options .= "\t\t<optgroup label=\"" . iif($htmlise, htmlspecialchars_uni($key), $key) . "\">\n";
				$options .= construct_select_options($val, $selectedid, $tabindex, $htmlise);
				$options .= "\t\t</optgroup>\n";
			}
			else
			{
				if (is_array($selectedid))
				{
					$selected = iif(in_array($key, $selectedid), ' selected="selected"', '');
				}
				else
				{
					$selected = iif($key == $selectedid, ' selected="selected"', '');
				}
				$options .= "\t\t<option value=\"" . iif($key !== 'no_value', $key) . "\"$selected>" . iif($htmlise, htmlspecialchars_uni($val), $val) . "</option>\n";
			}
		}
	}
	return $options;
}

// ###################### Start print radio row ###############################
function print_radio_row($title, $name, $array, $checked = '', $class = 'normal', $htmlise = 0)
{
	$radios = "<span class=\"$class\">\n";
	$radios .= construct_radio_options($name, $array, $checked, $htmlise);
	$radios .= "\t</span>";

	print_label_row($title, $radios, '', 'top', $name);
}

// ###################### Start construct radio options #######################
function construct_radio_options($name, $array, $checkedid = '', $htmlise = 0, $indent = '')
{
// returns a list of <options> based on the index and value of the incoming array

	if (is_array($array))
	{
		$options = '';
		foreach($array AS $key => $val)
		{
			if (is_array($val))
			{
				$options .= "\t\t<b>" . iif($htmlise, htmlspecialchars_uni($key), $key) . "</b><br />\n";
				$options .= construct_radio_options($name, $val, $checkedid, $htmlise, '&nbsp; &nbsp; ');
			}
			else
			{
				$options .= "\t\t<label for=\"rb_$name$key\">$indent<input type=\"radio\" name=\"$name\" id=\"rb_$name$key\" tabindex=\"1\" value=\"" . iif($key !== 'no_value', $key) . "\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot; value=&quot;$key&quot;\"") . iif($key == $checkedid, ' checked="checked"') . " />" . iif($htmlise, htmlspecialchars_uni($val), $val) . "</label><br />\n";
			}
		}
	}

	return $options;
}

// #################### Start makeselectmonth ##########################
function construct_month_select_html($selected = 1, $title = 'month', $htmlise = 0)
{
	global $vbphrase;

	$select = "<select name=\"$title\" tabindex=\"1\" class=\"bginput\"" . iif($GLOBALS['debug'], " title=\"name=&title;$name&quot;\"") . ">\n";
	$array = array(
		1 => $vbphrase['january'],
			$vbphrase['february'],
			$vbphrase['march'],
			$vbphrase['april'],
			$vbphrase['may'],
			$vbphrase['june'],
			$vbphrase['july'],
			$vbphrase['august'],
			$vbphrase['september'],
			$vbphrase['october'],
			$vbphrase['november'],
			$vbphrase['december']
		);
	$select .= construct_select_options($array, $selected, $htmlise);
	$select .= "</select>\n";

	return $select;
}

// #################### Start makeselectday ##########################
function construct_day_select_html($selected = 1, $title = 'day', $htmlise = 0)
{
	$select = "<select name=\"$title\" tabindex=\"1\" class=\"bginput\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$title&quot;\"") . ">\n";
	$array = array(1 => 1,	2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15,
		16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31);
	$select .= construct_select_options($array, $selected, $htmlise);
	$select .= "</select>\n";

	return $select;
}

// #################################################################
// ######## DYNAMIC MID-FORM / TABLE ROW ADDING FUNCTIONS ##########
// #################################################################

// ###################### Start makechoosercode #######################
function print_chooser_row($title, $name, $tablename, $selvalue = -1, $extra = '', $size = 0, $wherecondition = '')
{
// returns a combo box containing a list of titles in the $tablename table.
// allows specification of selected value in $selvalue
// checks for existence of $iusergroupcache / $iforumcache etc first...
	global $DB_site;

	$tableid = $tablename . 'id';
	$cachename = 'i' . $tablename . 'cache_' .  md5($wherecondition);

	if (!is_array($GLOBALS["$cachename"]))
	{
		$GLOBALS["$cachename"] = array();
		$result = $DB_site->query("SELECT title, $tableid FROM " . TABLE_PREFIX . "$tablename $wherecondition ORDER BY title");
		while ($currow = $DB_site->fetch_array($result))
		{
			$GLOBALS["$cachename"]["$currow[$tableid]"] = $currow['title'];
		}
		unset($currow);
		$DB_site->free_result($result);
	}

	$selectoptions = array();
	if ($extra)
	{
		$selectoptions['-1'] = $extra;
	}

	foreach ($GLOBALS["$cachename"] AS $itemid => $itemtitle)
	{
		$selectoptions["$itemid"] = $itemtitle;
	}

	print_select_row($title, $name, $selectoptions, $selvalue, 0, $size);
}

// ###################### Start makecalendarchooser #######################
function print_calendar_chooser($name = 'calendarid', $selectedid = -1, $topname = '', $title = 'Calendar', $displaytop = 1)
{
	global $DB_site;

	$calendars = $DB_site->query("SELECT title, calendarid FROM " . TABLE_PREFIX . "calendar ORDER BY displayorder");

	$htmlselect = "\n\t<select name=\"$name\" tabindex=\"1\" class=\"bginput\"" . iif($GLOBALS['debug'], " title=\"name=&quot;$name&quot;\"") . ">\n";

	$selectoptions = array();
	if ($displaytop)
	{
		$selectoptions['-1'] = $topname;
	}

	while ($calendar = $DB_site->fetch_array($calendars))
	{
		$selectoptions["$calendar[calendarid]"] = $calendar['title'];
	}

	print_select_row($title, $name, $selectoptions, $selectedid);
}

// ###################### Start makeforumchooser #######################
function print_forum_chooser($name = 'forumid', $selectedid = -1, $topname = NULL, $title = NULL, $displaytop = 1, $multiple = 0, $displayselectforum = 0)
{
// returns a nice <select> list of forums, complete with displayorder, parenting and depth information
// $name: name of the <select>; $selectedid: selected <option>; $topname: name given to the -1 <option>
// $title: text for the left cell of the table row; $displaytop: display the -1 <option> or not.
// $multiple: when set to 1, allows multiple selections to be made. results will be stored in $name's array
	global $forumcache, $vbphrase, $_FORUMOPTIONS;

	if ($topname === NULL)
	{
		$topname = $vbphrase['no_one'];
	}
	if ($title === NULL)
	{
		$title = $vbphrase['parent_forum'];
	}

	require_once('./includes/functions_databuild.php');
	cache_forums();

	$selectoptions = array();

	if ($displayselectforum)
	{
		$selectoptions[0] = $vbphrase['select_forum'];
		$selectedid = 0;
	}

	if ($displaytop)
	{
		$selectoptions['-1'] = $topname;
		$startdepth = '--';
	}
	else
	{
		$startdepth = '';
	}

	foreach($forumcache AS $forum)
	{
		$selectoptions["$forum[forumid]"] = construct_depth_mark($forum['depth'], '--', $startdepth) . ' ' . $forum['title'] . ' ' . iif(!($forum['options'] & $_FORUMOPTIONS['allowposting']), " ($vbphrase[no_posting])") . ' ' . $forum['allowposting'];
	}

	print_select_row($title, $name, $selectoptions, $selectedid, 0, iif($multiple, 10, 0), $multiple);
}

// ###################### Start construct_forum_chooser #############
function construct_forum_chooser($selectedid = -1, $displayselectforum = false)
{
	global $forumcache, $vbphrase, $_FORUMOPTIONS;

	require_once('./includes/functions_databuild.php');
	cache_forums();

	$selectoptions = array();

	if ($displayselectforum)
	{
		$selectoptions[0] = $vbphrase['select_forum'];
		$selectedid = 0;
	}

	$startdepth = '';

	foreach($forumcache AS $forum)
	{
		$selectoptions["$forum[forumid]"] = construct_depth_mark($forum['depth'], '--', $startdepth) . ' ' . $forum['title'] . ' ' . iif(!($forum['options'] & $_FORUMOPTIONS['allowposting']), " ($vbphrase[no_posting])") . ' ' . $forum['allowposting'];
	}

	return construct_select_options($selectoptions, $selectedid);
}

// ###################### Start makedepthmark #######################
function construct_depth_mark($depth, $depthchar, $depthmark = '')
{
// repeats the supplied $depthmark for the number of times supplied by $depth
// and appends it onto $depthmark
	for ($i = 0; $i < $depth; $i++)
	{
		$depthmark .= $depthchar;
	}
	return $depthmark;
}

// #################################################################
// ############## LINK / BUTTON CREATION FUNCTIONS #################
// #################################################################

// ###################### Start maketablehelp #######################
function construct_table_help_button($option = '', $action = NULL, $script = '', $helptype = 0)
{
	if ($helplink = construct_help_button($option, $action, $script, $helptype))
	{
		return "$helplink ";
	}
	else
	{
		return '';
	}
}

// ###################### Start makehelpbutton #######################
function construct_help_button($option = '', $action = NULL, $script = '', $helptype = 0)
{
	// used to make a link to the help section of the CP related to the current action
	global $_REQUEST, $helpcache, $stylevar, $vbphrase, $vboptions;

	if ($action === NULL)
	{
		// matches type as well (===)
		$action = $_REQUEST['do'];
	}

	if (empty($script))
	{
		$script = SCRIPTPATH;
	}

	if ($strpos = strpos($script, '?'))
	{
		$script = basename(substr($script, 0, $strpos));
	}
	else
	{
		$script = basename($script);
	}

	if ($strpos = strpos($script, '.'))
	{
		$script = substr($script, 0, $strpos); // remove the .php part as people may have different extensions
	}

	if ($option AND !isset($helpcache["$script"]["$action"]["$option"]))
	{
		if (preg_match('#^[a-z0-9_]+\[([a-z0-9_]+)\]$#si', trim($option), $matches))
		{
			// parse out array notation, to just get index
			$option = $matches[1];
		}
	}

	if (!$option)
	{
		if (!isset($helpcache["$script"]["$action"]))
		{
			return '';
		}
	}
	else
	{
		if (!isset($helpcache["$script"]["$action"]["$option"]))
		{
			return '';
		}
	}

	$helplink = "js_open_help('" . urlencode($script) . "', '" . urlencode($action) . "', '" . urlencode($option) . "'); return false;";

	switch ($helptype)
	{
		case 1:
		return "<a class=\"helplink\" href=\"#\" onclick=\"$helplink\">$vbphrase[help] <img src=\"../cpstyles/$vboptions[cpstylefolder]/cp_help.gif\" alt=\"\" border=\"0\" title=\"$vbphrase[click_for_help_on_these_options]\" style=\"vertical-align:middle\" /></a>";

		default:
		return "<a class=\"helplink\" href=\"#\" onclick=\"$helplink\"><img src=\"../cpstyles/$vboptions[cpstylefolder]/cp_help.gif\" alt=\"\" border=\"0\" title=\"$vbphrase[click_for_help_on_this_option]\" /></a>";
	}
}

// ###################### Start makelinkcode #######################
function construct_link_code($text, $url, $newwin = 0, $popup = '')
{
// returns a hyperlink pointing to $url labelled $text
// if $newwin is 1 target="blank", if $popup is set link will have a title="" tooltip
	global $stylevar;

	if ($newwin === 1 OR $newwin === true)
	{
		$newwin = '_blank';
	}
	return " <a href=\"$url\"" . iif($newwin, " target=\"$newwin\"", "") . iif(!empty($popup)," title=\"$popup\"") . ">" . iif($stylevar['textdirection'] == 'rtl', "$text", "[$text]") . "</a> ";
}

// ###################### Start makebuttoncode #######################
//function construct_button_code($text='Click!',$link='',$newwindow=0,
function construct_button_code($text = 'Click!', $link = '', $newwindow = 0, $alttext = '', $bold = 0, $jsfunction = 0)
{
	if (!empty($alttext))
	{
		$alt = " title=\"$alttext\"";
	}

	return " <input type=\"button\" class=\"button\" value=\"$text\" tabindex=\"1\" onclick=\"" . iif($jsfunction, $link, iif($newwindow, "window.open('$link')", "window.location='$link'")) . ";\"$alt/> ";
}

// #################################################################
// ################## MISCELLANEOUS FUNCTIONS ######################
// #################################################################

// ###################### Start can_administer #######################
// checks if a user is an administrator, and (optionally) if they have
// permission to do a specific task by checking the 'administrator' DB table
// Note: $do must be a string corresponding to a key in $_BITFIELD['usergroup']['adminpermissions']
// You may also pass multiple permission checks into this function in the form of
// can_administer('canmoo', 'canbaa', 'canquack') - which works as "canmoo OR canbaa OR canquack"
function can_administer()
{
	global $bbuserinfo, $DB_site, $_BITFIELD, $_NAVPREFS, $superadministrators;
	static $adminperms, $superadmins;

	if (!isset($_NAVPREFS))
	{
		$_NAVPREFS = preg_split('#,#', $bbuserinfo['navprefs'], -1, PREG_SPLIT_NO_EMPTY);
	}

	if (!is_array($superadmins))
	{
		$superadmins = preg_split('#\s*,\s*#s', $superadministrators, -1, PREG_SPLIT_NO_EMPTY);
	}

	$do = func_get_args();

	if ($bbuserinfo['userid'] < 1)
	{
		// user is a guest - definitely not an administrator
		return false;
	}
	else if (!($bbuserinfo['permissions']['adminpermissions'] & CANCONTROLPANEL))
	{
		// user is not an administrator at all
		return false;
	}
	else if (in_array($bbuserinfo['userid'], $superadmins))
	{
		// user is a super administrator (defined in config.php) so can do anything
		return true;
	}
	else if (empty($do))
	{
		// user is an administrator and we are not checking a specific permission
		return true;
	}
	else if (!isset($adminperms))
	{
		// query specific admin permissions from the administrator table and assign them to $adminperms
		$getperms = $DB_site->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "administrator
			WHERE userid = $bbuserinfo[userid]
		");

		// add normal adminpermissions and specific adminpermissions
		$adminperms = $getperms['adminpermissions'] + $bbuserinfo['permissions']['adminpermissions'];

		// save nav prefs choices
		$_NAVPREFS = preg_split('#,#', $getperms['navprefs'], -1, PREG_SPLIT_NO_EMPTY);
	}

	// final bitfield check on each permission we are checking
	foreach($do AS $field)
	{
		if ($adminperms & $_BITFIELD['usergroup']['adminpermissions']["$field"])
		{
			return true;
		}
	}

	// if we got this far then there is no permission
	return false;
}

// ###################### Start admin_nopermission #######################
function print_cp_no_permission($do = '')
{
	global $bbuserinfo, $vbphrase;

	if (!defined('DONE_CPHEADER'))
	{
		print_cp_header($vbphrase['vbulletin_message']);
	}

	print_stop_message('no_access_to_admin_control', $session['sessionurl'], $bbuserinfo['userid']);

}

// ##################### Start storetext ##################
function build_adminutil_text($title, $text = '')
{
	global $DB_site;

	if ($text == '')
	{
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "adminutil
			WHERE title = '" . addslashes($title) . "'
		");
	}
	else
	{
		$DB_site->query("
			REPLACE INTO " . TABLE_PREFIX . "adminutil
			(title, text)
			VALUES
			('" . addslashes($title) . "', '" . addslashes($text) . "')
		");
	}

	return true;
}

// ##################### Start readtext ##################
function fetch_adminutil_text($title)
{
	global $DB_site;

	$text = $DB_site->query_first("SELECT text FROM " . TABLE_PREFIX . "adminutil WHERE title = '$title'");
	return $text['text'];
}

// ###################### Start cpredirect #######################
function print_cp_redirect($gotopage, $timeout = 0)
{
	// performs a delayed javascript page redirection
	// get rid of &amp; if there are any...
	global $vbphrase;
	$gotopage = str_replace('&amp;', '&', $gotopage);
	echo '<p align="center" class="smallfont"><a href="' . $gotopage . '" onclick="javascript:clearTimeout(timerID);">' . $vbphrase['processing_complete_proceed'] . '</a></p>';
	echo "\n<script type=\"text/javascript\">\n";
	if ($timeout == 0)
	{
		echo "window.location=\"$gotopage\";";
	}
	else
	{
		echo "myvar = \"\"; timeout = " . ($timeout*10) . ";
		function exec_refresh()
		{
			window.status=\"" . $vbphrase['redirecting']."\"+myvar; myvar = myvar + \" .\";
			timerID = setTimeout(\"exec_refresh();\", 100);
			if (timeout > 0)
			{ timeout -= 1; }
			else { clearTimeout(timerID); window.status=\"\"; window.location=\"$gotopage\"; }
		}
		exec_refresh();";
	}
	echo "\n</script>\n";
	print_cp_footer();
	exit;
}

// ##################### Start waiting dots ###################################
function print_dots_start($text, $dotschar = ':', $elementid = 'dotsarea')
{
	if (defined('NO_IMPORT_DOTS'))
	{
		return;
	}

	flush(); ?>
	<p align="center"><?php echo $text; ?><br /><br />[<span style="color:yellow; font-weight:bold" id="<?php echo $elementid; ?>"><?php echo $dotschar; ?></span>]</p>
	<script type="text/javascript"><!--
	function js_dots()
	{
		<?php echo $elementid; ?>.innerText = <?php echo $elementid; ?>.innerText + "<?php echo $dotschar; ?>";
		jstimer = setTimeout("js_dots();", 75);
	}
	if (document.all)
	{
		js_dots();
	}
	//--></script>
	<?php flush();
}

// ##################### Stop waiting dots ###################################
function print_dots_stop()
{
	if (defined('NO_IMPORT_DOTS'))
	{
		return;
	}

	flush(); ?>
	<script type="text/javascript"><!--
	if (document.all)
	{
		clearTimeout(jstimer);
	}
	//--></script>
	<?php flush();
}

// ##################### Kill User ###################################
function delete_user($userid = 0)
{
	global $DB_site, $vbphrase;

	if ($userid = intval($userid))
	{
		$user = $DB_site->query_first("
			SELECT userid, username, avatarrevision
			FROM " . TABLE_PREFIX . "user
			WHERE userid = $userid
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "post
			SET username = '" . addslashes($user['username']) . "',
			userid = 0
			WHERE userid = $userid
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "usernote
			SET username = '" . addslashes($user['username']) . "',
			posterid = 0
			WHERE posterid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "usernote
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "user
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "userfield
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "usertextfield
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "access
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "event
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "customavatar
			WHERE userid = $userid
		");
		@unlink("$vboptions[avatarpath]/avatar$user[userid]_$user[avatarrevision].gif");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "customprofilepic
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "moderator
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "subscribeforum
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "subscribethread
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "subscriptionlog
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "session
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "userban
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "administrator
			WHERE userid = $userid
		");
		$DB_site->query("
			DELETE FROM " . TABLE_PREFIX . "usergrouprequest
			WHERE userid = $userid
		");
		delete_user_pms($userid, false);

		require_once('./includes/functions_databuild.php');
		build_user_statistics();
	}
	else
	{
		print_stop_message('invalid_x_specified', 'userid');
	}
}

// ###################### Start kill user PMs #######################
function delete_user_pms($userid, $updateuser = true)
{
	global $DB_site, $vbphrase;

	if ($userid = intval($userid))
	{
		// array to store pm ids message ids
		$pms = array();
		// array to store the number of pmtext records used by this user
		$pmTextCount = array();
		// array to store the ids of any pmtext records that are used soley by this user
		$deleteTextIDs = array();
		// array to store results
		$out = array();

		// first zap all receipts belonging to this user
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "pmreceipt WHERE userid = $userid");
		$out['receipts'] = $DB_site->affected_rows();

		// now find all this user's private messages
		$messages = $DB_site->query("
			SELECT pmid, pmtextid
			FROM " . TABLE_PREFIX . "pm
			WHERE userid = $userid
		");
		while ($message = $DB_site->fetch_array($messages))
		{
			// stick this record into our $pms array
			$pms["$message[pmid]"] = $message['pmtextid'];
			// increment the number of PMs that use the current PMtext record
			$pmTextCount["$message[pmtextid]"] ++;
		}
		$DB_site->free_result($messages);

		if (!empty($pms))
		{
			// zap all pm records belonging to this user
			$DB_site->query("DELETE FROM " . TABLE_PREFIX . "pm WHERE userid = $userid");
			$out['pms'] = $DB_site->affected_rows();
			$out['pmtexts'] = 0;

			// update the user record if necessary
			if ($updateuser)
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "user
					SET pmtotal = 0,
					pmunread = 0,
					pmpopup = IF(pmpopup=2, 1, pmpopup)
					WHERE userid = $userid
				");
			}
		}
		else
		{
			$out['pms'] = 0;
			$out['pmtexts'] = 0;
		}

		foreach ($out AS $k => $v)
		{
			$out["$k"] = vb_number_format($v);
		}

		return $out;
	}
	else
	{
		print_stop_message('invalid_x_specified', 'userid');
	}
}

// ###################### Start writetofile #######################
function file_write($path, $data, $backup = 0)
{
// writes $data to $path renaming the old file if it exists
	if (file_exists($path) != false)
	{
		if ($backup == 1)
		{
			$filenamenew = $path . 'old';
			rename($path, $filenamenew);
		}
		else
		{
			unlink($path);
		}
	}
	if (!empty($data))
	{
		$filenum = fopen($path, 'w');
		fwrite($filenum, $data);
		fclose($filenum);
	}
}

// ###################### Start readfromfile #######################
function file_read($path)
{
// returns all data in $path, or nothing if it does not exist
	if(!file_exists($path))
	{
		return '';
	}
	else
	{
		$filestuff = @file_get_contents($path);
		return $filestuff;
	}
}

// ###################### Start generateoptions #######################
function build_options()
{
// reads options from the setting table and serialises them from the $vboptions[] array
// then saves data back into DB

	global $DB_site;

	$vboptions = array();

	$settings = $DB_site->query("SELECT varname,value FROM " . TABLE_PREFIX . "setting");
	while ($setting = $DB_site->fetch_array($settings))
	{
		$vboptions["$setting[varname]"] = $setting['value'];
	}

	if (substr($vboptions['cookiepath'], -1, 1) != '/')
	{
		$vboptions['cookiepath'] .= '/';
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "setting
			SET value = '" . addslashes($vboptions['cookiepath']) . "'
			WHERE varname = 'cookiepath'
		");
	}

	build_datastore('options', serialize($vboptions));

	return $vboptions;
}

// ###################### Start adminlog #######################
function log_admin_action($extrainfo = "", $userid=-1, $script = '', $scriptaction = '')
{
// logs current activity to the adminlog db table
	global $DB_site, $bbuserinfo, $shutdownqueries;
	// make PHP 4.0.6 safe
	global $_SERVER, $_REQUEST;

	if ($userid == -1)
	{
		$userid = $bbuserinfo['userid'];
	}
	if (empty($script))
	{
		$script = basename($_SERVER['PHP_SELF']);
	}
	if (empty($scriptaction))
	{
		$scriptaction = $_REQUEST['do'];
	}

	$DB_site->shutdown_query("
		INSERT INTO " . TABLE_PREFIX . "adminlog(userid, dateline, script, action, extrainfo, ipaddress)
		VALUES
		($userid, " . TIMENOW.", '" . addslashes($script) . "', '" . addslashes($scriptaction) . "', '" . addslashes($extrainfo) ."', '" . IPADDRESS . "')
	");

}

// ###################### Start checklogperms #######################
function can_access_logs($idvar, $defaultreturnvar, $errmsg = '')
{
// checks a single integer or a comma-separated list for $bbuserinfo[userid]
	global $bbuserinfo;
	if (empty($idvar))
	{
		return $defaultreturnvar;
	}
	else
	{
		$perm = trim($idvar);
		$logperms = explode(',', $perm);
		if (in_array($bbuserinfo['userid'], $logperms))
		{
			return 1;
		}
		else
		{
			echo $errmsg;
			return 0;
		}
	}
}

// ##################### Start confirmdelete #########################
function print_delete_confirmation($table, $itemid, $phpscript, $do, $itemname = '', $hiddenfields = 0, $extra = '', $titlename = 'title')
{
// displays a confirmation prompt for deletion. takes the following arguments:
// $table = db table from which to delete;
// $itemid = id of item to be deleted; $do = value of $do for form;
// $phpscript = script to which the form will submit (omit .php extension);
// $itemname (optional) = human-readable version of table name (uses table name if not set);
// $extra (optional) - additional text for the confirmation prompt should be entered here.
	global $DB_site, $vbphrase;

	$idfield = $table.'id';
	$itemname = iif($itemname, $itemname, $table);

	switch($table)
	{
		case 'reputation':
			$item = $DB_site->query_first("
				SELECT reputationid, reputationid AS title
				FROM " . TABLE_PREFIX . "reputation
				WHERE reputationid = $itemid
			");
			break;
		case 'user':
			$item = $DB_site->query_first("
				SELECT userid, username AS title
				FROM " . TABLE_PREFIX . "user
				WHERE userid = $itemid
			");
			break;
		case 'moderator':
			$item = $DB_site->query_first("
				SELECT moderatorid, username, title
				FROM " . TABLE_PREFIX . "moderator AS moderator,
				" . TABLE_PREFIX . "user AS user,
				" . TABLE_PREFIX . "forum AS forum
				WHERE user.userid = moderator.userid AND
				forum.forumid = moderator.forumid AND
				moderatorid = $itemid
			");
			$item['title'] = construct_phrase($vbphrase['x_from_the_forum_y'], $item['username'], $item['title']);
			break;
		case 'calendarmoderator':
			$item = $DB_site->query_first("
				SELECT calendarmoderatorid, username, title
				FROM " . TABLE_PREFIX . "calendarmoderator AS calendarmoderator,
				" . TABLE_PREFIX . "user AS user,
				" . TABLE_PREFIX . "calendar AS calendar
				WHERE user.userid = calendarmoderator.userid AND
				calendar.calendarid = calendarmoderator.calendarid AND
				calendarmoderatorid = $itemid
			");
			$item['title'] = construct_phrase($vbphrase['x_from_the_calendar_y'], $item['username'], $item['title']);
			break;
		case 'phrase':
			$item = $DB_site->query_first("
				SELECT phraseid, varname AS title
				FROM " . TABLE_PREFIX . "phrase
				WHERE phraseid = $itemid
			");
			break;
		case 'userpromotion':
			$item = $DB_site->query_first("
				SELECT userpromotionid, usergroup.title
				FROM " . TABLE_PREFIX . "userpromotion AS userpromotion,
				" . TABLE_PREFIX . "usergroup AS usergroup
				WHERE userpromotionid = $itemid AND
				userpromotion.usergroupid = usergroup.usergroupid
			");
			break;
		case 'usergroupleader':
			$item = $DB_site->query_first("
				SELECT usergroupleaderid, username AS title
				FROM " . TABLE_PREFIX . "usergroupleader AS usergroupleader
				INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
				WHERE usergroupleaderid = $itemid
			");
			break;
		case 'setting':
			$item = $DB_site->query_first("
				SELECT varname AS title
				FROM " . TABLE_PREFIX . "setting
				WHERE varname = '$itemid'
			");
			$idfield = 'title';
			break;
		case 'settinggroup':
			$item = $DB_site->query_first("
				SELECT grouptitle AS title
				FROM " . TABLE_PREFIX . "settinggroup
				WHERE grouptitle = '$itemid'
			");
			$idfield = 'title';
			break;
		case 'adminhelp':
			$item = $DB_site->query_first("
				SELECT adminhelpid, script, action, optionname
				FROM " . TABLE_PREFIX . "adminhelp
				WHERE adminhelpid = $itemid
			");
			$phrasekey = 'adminhelp_' . $item['script'] . iif($item['action'], "_$item[action]") . iif($item['optionname'], "_$item[optionname]") . '_title';
			$item['title'] = $vbphrase["$phrasekey"];
			break;
		case 'faq':
			$item = $DB_site->query_first("
				SELECT faqname, text AS title
				FROM " . TABLE_PREFIX . "faq AS faq
				INNER JOIN " . TABLE_PREFIX . "phrase AS phrase ON (phrase.varname = faq.faqname AND phrase.phrasetypeid = " . PHRASETYPEID_FAQTITLE . " AND phrase.languageid IN(-1, 0))
				WHERE faqname = '$itemid'
			");
			$idfield = 'faqname';
			break;
		default:
			$item = $DB_site->query_first("
				SELECT $idfield, $titlename AS title
				FROM " . TABLE_PREFIX . "$table
				WHERE $idfield = $itemid
			");
			break;
	}

	$deleteword = 'delete';

	switch($table)
	{
		case 'template':
			$item['title'] = htmlspecialchars_uni($item['title']);
			if ($itemname == 'replacement_variable')
			{
				$deleteword = 'delete';
			}
			else
			{
				$deleteword = 'revert';
			}
		break;

		case 'adminreminder':
			if (strlen($item['title']) > 30)
			{
				$item['title'] = substr($item['title'], 0, 30) . '...';
			}
		break;
	}

	if ($item["$idfield"] == $itemid AND !empty($itemid))
	{
		echo "<p>&nbsp;</p><p>&nbsp;</p>";
		print_form_header($phpscript, $do, 0, 1, '', '75%');
		construct_hidden_code(iif($idfield == 'styleid', 'dostyleid', $idfield), $itemid);
		if (is_array($hiddenfields))
		{
			foreach($hiddenfields AS $varname => $value)
			{
				construct_hidden_code($varname, $value);
			}
		}
		print_table_header(construct_phrase($vbphrase['confirm_deletion_x'], $item['title']));
		print_description_row("
			<blockquote><br />
			" . construct_phrase($vbphrase["are_you_sure_want_to_{$deleteword}_{$itemname}_x"], $item['title'], $idfield, $item["$idfield"], iif($extra, "$extra<br /><br />")) . "
			<br /></blockquote>\n\t");
		print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
	}
	else
	{
		print_stop_message('could_not_find', '<b>' . $itemname . '</b>', $idfield, $itemid);
	}
}

// ##################### Start print stop message #########################
function print_stop_message($phrasename)
{
	global $DB_site, $bbuserinfo, $vbphrase, $vboptions;

	$message = fetch_phrase($phrasename, PHRASETYPEID_CPMESSAGE, '', false);

	$args = func_get_args();
	if (sizeof($args) > 1)
	{
		//$args[0] = preg_replace('#\{([0-9])+\}#sU', '%\\1$s', $message);
		$args[0] = $message;
		$message = call_user_func_array('construct_phrase', $args);
	}

	print_cp_message($message, iif(defined('CP_REDIRECT'), CP_REDIRECT, NULL));
}

// ##################### Start print cp message #########################
function print_cp_message($text = '', $redirect = NULL, $delay = 1)
{
// echoes out an error message in a table and halts execution
// if $redirect is set the prompt will say 'vBulletin Message' instead of 'Error'.
// You can force it to say 'vBulletin Message' by sending an empty string for $redirect.
// DO not add the sessionhash to the $redirect, we will do it here.

	global $session, $vbphrase, $_SERVER;

	if ($redirect AND $session['sessionurl'])
	{
		if (strpos($redirect, '?') === false)
		{
			$redirect .= '?';
		}
		$redirect .= "&$session[sessionurl]";
	}

	if (!defined('DONE_CPHEADER'))
	{
		print_cp_header($vbphrase['vbulletin_message']);
	}

	echo '<p>&nbsp;</p><p>&nbsp;</p>';
	print_form_header('', '', 0, 1, 'messageform', '65%');
	print_table_header($vbphrase['vbulletin_message']);
	print_description_row("<blockquote><br />$text<br /><br /></blockquote>");

	if ($redirect AND $redirect !== NULL)
	{
		// redirect to the new page
		print_table_footer();
		echo '<p align="center" class="smallfont">' . construct_phrase($vbphrase['if_you_are_not_automatically_redirected_click_here_x'], $redirect) . "</p>\n";
		print_cp_redirect($redirect, $delay);
	}
	else
	{
		// end the table and halt
		if (!REFERRER OR strpos(REFERRER, '?') !== false)
		{
			$showgoback = true;
		}
		else
		{
			$showgoback = false;
		}
		print_table_footer(2, iif($showgoback, construct_button_code($vbphrase['go_back'], 'javascript:history.back(1)')));
	}

	// and now terminate the script
	print_cp_footer();
}

// ##################### Start gettimezonesarray #########################
function fetch_timezones_array()
{
// returns an array of timezones with their GMT offset
	global $vbphrase;

	return array(
		'-12'  => $vbphrase['timezone_gmt_minus_1200'],
		'-11'  => $vbphrase['timezone_gmt_minus_1100'],
		'-10'  => $vbphrase['timezone_gmt_minus_1000'],
		'-9'   => $vbphrase['timezone_gmt_minus_0900'],
		'-8'   => $vbphrase['timezone_gmt_minus_0800'],
		'-7'   => $vbphrase['timezone_gmt_minus_0700'],
		'-6'   => $vbphrase['timezone_gmt_minus_0600'],
		'-5'   => $vbphrase['timezone_gmt_minus_0500'],
		'-4'   => $vbphrase['timezone_gmt_minus_0400'],
		'-3.5' => $vbphrase['timezone_gmt_minus_0330'],
		'-3'   => $vbphrase['timezone_gmt_minus_0300'],
		'-2'   => $vbphrase['timezone_gmt_minus_0200'],
		'-1'   => $vbphrase['timezone_gmt_minus_0100'],
		'0'    => $vbphrase['timezone_gmt_plus_0000'],
		'1'    => $vbphrase['timezone_gmt_plus_0100'],
		'2'    => $vbphrase['timezone_gmt_plus_0200'],
		'3'    => $vbphrase['timezone_gmt_plus_0300'],
		'3.5'  => $vbphrase['timezone_gmt_plus_0330'],
		'4'    => $vbphrase['timezone_gmt_plus_0400'],
		'4.5'  => $vbphrase['timezone_gmt_plus_0430'],
		'5'    => $vbphrase['timezone_gmt_plus_0500'],
		'5.5'  => $vbphrase['timezone_gmt_plus_0530'],
		'6'    => $vbphrase['timezone_gmt_plus_0600'],
		'7'    => $vbphrase['timezone_gmt_plus_0700'],
		'8'    => $vbphrase['timezone_gmt_plus_0800'],
		'9'    => $vbphrase['timezone_gmt_plus_0900'],
		'9.5'  => $vbphrase['timezone_gmt_plus_0930'],
		'10'   => $vbphrase['timezone_gmt_plus_1000'],
		'11'   => $vbphrase['timezone_gmt_plus_1100'],
		'12'   => $vbphrase['timezone_gmt_plus_1200']
	);
}



// ############################## Start update_imagecache #####################
function build_image_cache($table)
{
	if ($table == 'avatar')
	{
		return;
	}
// this function takes all data from the $table (avatar,icon,smilie) and writes
// it out in a avatar/icon/smiliecache datastore
// this can then be unserialize'd to get all image info without a query :)
	global $DB_site;
	DEVDEBUG("Updating $table cache template...");
	$itemid = $table.'id';
	$items = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "$table ORDER BY imagecategoryid, displayorder");
	while ($item = $DB_site->fetch_array($items))
	{
		$itemarray["$item[$itemid]"] = array();
		foreach ($item AS $field => $value)
		{
			if (!is_numeric($field))
			{
				$itemarray["$item[$itemid]"]["$field"] = $value;
			}
		}
	}

	build_datastore($table . 'cache', serialize($itemarray));

	if ($table == 'smilie')
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "post_parsed"); // smilies changed, so posts could parse differently
	}
}

// ############################## Start update_bbcodecache #####################
// this function takes all data from the bbcode table and writes
// it out in a bbcodecache datastore
// this can then be unserialize'd to get all bbcode info without a query :)
function build_bbcode_cache()
{
	global $DB_site;
	DEVDEBUG("Updating bbcode cache template...");
	$bbcodes = $DB_site->query("
		SELECT *
		FROM " . TABLE_PREFIX . "bbcode
	");
	$bbcodearray = array();
	while ($bbcode = $DB_site->fetch_array($bbcodes))
	{
		$bbcodearray["$bbcode[bbcodeid]"] = array();
		foreach ($bbcode AS $field => $value)
		{
			if (!is_numeric($field))
			{
				$bbcodearray["$bbcode[bbcodeid]"]["$field"] = $value;
			}
		}
	}

	build_datastore('bbcodecache', serialize($bbcodearray));

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "post_parsed"); // bbcodes changed, so posts could parse differently
}

// ############################## Start getPhraseRef #####################
// echoes out a <script> that allows you to call js_open_phrase_ref() from javascript
function print_phrase_ref_popup_javascript($languageid = 0, $phrasetypeid = 0, $width = 700, $height = 202)
{
	global $session;

	$q =  iif($languageid, "&languageid=$languageid", '');
	$q .= iif($phrasetypeid, "&phrasetypeid=$phrasetypeid", '');

	echo "<script type=\"text/javascript\">\n<!--
	function js_open_phrase_ref(languageid,phrasetypeid)
	{
		var qs = '';
		if (languageid != 0) qs += '&languageid=' + languageid;
		if (phrasetypeid != 0) qs += '&phrasetypeid=' + phrasetypeid;
		window.open('phrase.php?$session[sessionurl]do=quickref' + qs, 'quickref', 'width=$width,height=$height,resizable=yes');
	}\n// -->\n</script>\n";

}

// ############################# Start getsqllimit #########################
function REDUNDANT_getsqllimit($perpage, &$page)
{
	global $perpage, $page, $startat;
	$page = intval($page);
	if ($page == 0)
	{
		$page = 1;
	}
	$perpage = 3;
	$startat = ($page - 1) * $perpage;
}

// ############################# Start echoQuery #########################
function print_query($querystring = NULL, $htmlise = true)
{
	if ($querystring === NULL)
	{
		global $DB_site;
		$querystring = $DB_site->lastquery;
	}
	echo '<pre>' . iif($htmlise, htmlspecialchars($querystring), $querystring) . "</pre>\n<hr />";
}

// ############################# Start updateForumPermissions #########################
function build_forum_permissions()
{
	global $DB_site, $iforumcache, $forumcache, $usergroupcache, $fpermcache;

	#echo "<h1>updateForumPermissions</h1>";

	$grouppermissions = array();
	$fpermcache = array();
	$forumcache = array();
	$usergroupcache = array();

	// query usergroups
	$usergroups = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "usergroup ORDER BY title");
	while ($usergroup = $DB_site->fetch_array($usergroups))
	{
		$usergroupcache["$usergroup[usergroupid]"] = $usergroup;
		// Profile pics disabled so don't inherit any of the profile pic settings
		if (!($usergroupcache["$usergroup[usergroupid]"]['genericpermissions'] & CANPROFILEPIC))
		{
			$usergroupcache["$usergroup[usergroupid]"]['profilepicmaxwidth'] = -1;
			$usergroupcache["$usergroup[usergroupid]"]['profilepicmaxheight'] = -1;
			$usergroupcache["$usergroup[usergroupid]"]['profilepicmaxsize'] = -1;
		}
		// Avatars disabled so don't inherit any of the avatar settings
		if (!($usergroupcache["$usergroup[usergroupid]"]['genericpermissions'] & CANUSEAVATAR))
		{
			$usergroupcache["$usergroup[usergroupid]"]['avatarmaxwidth'] = -1;
			$usergroupcache["$usergroup[usergroupid]"]['avatarmaxheight'] = -1;
			$usergroupcache["$usergroup[usergroupid]"]['avatarmaxsize'] = -1;
		}
		$grouppermissions["$usergroup[usergroupid]"] = $usergroup['forumpermissions'];
	}
	unset($usergroup);
	$DB_site->free_result($usergroups);
	DEVDEBUG('updateForumCache( ) - Queried Usergroups');

	// query forums
	require_once('./includes/functions_databuild.php');
	cache_forums();

	// get the iforumcache so we can traverse the forums in order
	require_once('./includes/functions_forumlist.php');
	cache_ordered_forums(0, 1);

	// query forum permissions
	$fperms = $DB_site->query("SELECT * FROM " . TABLE_PREFIX . "forumpermission");
	while ($fperm = $DB_site->fetch_array($fperms))
	{
		$fpermcache["$fperm[forumid]"]["$fperm[usergroupid]"] = $fperm['forumpermissions'];
	}
	unset($fperm);
	$DB_site->free_result($fperms);
	DEVDEBUG('updateForumCache( ) - Queried Forum Pemissions');

	// call the function that will work out the forum permissions
	cache_forum_permissions($grouppermissions);

	// finally replace the existing cache templates
	build_datastore('usergroupcache', serialize($usergroupcache));
	foreach(array_keys($forumcache) AS $forumid)
	{
		unset($forumcache[$forumid]['replycount'],$forumcache[$forumid]['lastpost'],$forumcache[$forumid]['lastposter'],$forumcache[$forumid]['lastthread'],$forumcache[$forumid]['lastthreadid'],$forumcache[$forumid]['lasticonid'],$forumcache[$forumid]['threadcount']);
	}
	build_datastore('forumcache', serialize($forumcache));

	DEVDEBUG('updateForumCache( ) - Updated caches, ' . $DB_site->affected_rows() . ' rows affected.');
}

// ############################# Start getForumPerms #########################
// this function should only be called from updateForumCache
function cache_forum_permissions($permissions, $parentid = -1)
{
	global $DB_site, $iforumcache, $forumcache, $usergroupcache, $fpermcache;

	// abort if no child forums found
	if (!is_array($iforumcache["$parentid"]))
	{
		return;
	}

	// run through each child forum
	foreach($iforumcache["$parentid"] AS $displayorder)
	{
		foreach($displayorder AS $forumid)
		{
			$forum = &$forumcache["$forumid"];

			// make a copy of the current permissions set up
			$perms = $permissions;

			// run through each usergroup
			foreach(array_keys($usergroupcache) AS $usergroupid)
			{
				// if there is a custom permission for the current usergroup, use it
				if (isset($fpermcache["$forumid"]["$usergroupid"]))
				{
					$perms["$usergroupid"] = $fpermcache["$forumid"]["$usergroupid"];
				}

				// populate the current row of the forumcache permissions
				$forum['permissions']["$usergroupid"] = $perms["$usergroupid"];
			}
			// recurse to child forums
			cache_forum_permissions($perms, $forum['forumid']);
		}
	}
}

// ############################# Start fetch js safe string #########################
// this function allows you to echo out any string to javascript...
function fetch_js_safe_string($string, $quotechar = '"')
{
	//$string = preg_replace('#%([0-9]+)\$s#s', '{\1}', $string);
	$string = preg_replace('#(\r\n|\n)#s', '\n', $string);
	$string = str_replace($quotechar, "\\$quotechar", $string);

	return $string;
}

// ################################ Start KBtoMB ####################################
// converts a number in KB to MB with number formatting
function convert_kb_to_mb($value)
{
	global $bbuserinfo, $vbphrase;

	if ($value == $vbphrase['n_a'])
	{
		return $value;
	}
	else
	{
		return vb_number_format($value / 1048576, 2) . ' MB';
	}
}

function fetch_cpcss_options()
{
	$folders = array();

	if ($handle = @opendir('./cpstyles'))
	{
		while ($folder = readdir($handle))
		{
			if ($folder{0} != '.' AND @file_exists("./cpstyles/$folder/controlpanel.css"))
			{
				$folders["$folder"] = $folder;
			}
		}
		closedir($handle);
		uksort($folders, 'strnatcasecmp');
		$folders = str_replace('_', ' ', $folders);
	}

	return $folders;
}

// ########################## Start convert_to_valid_html ##########################
// converts & to &amp; when not followed by an entity
function convert_to_valid_html($text)
{
	return preg_replace('/&(?![a-z0-9#]+;)/', '&amp;', $text);
}

// ############################## Start vbflush ####################################
// give the output buffers a little push
function vbflush()
{
	if (PHP_VERSION  >= '4.2.0')
	{
		flush();
		if (function_exists('ob_flush') AND ob_get_length() !== FALSE)
		{
			@ob_flush();
		}
	}
	else
	{
		flush();
		if (function_exists('ob_end_flush') AND function_exists('ob_start') AND function_exists('ob_get_length') AND ob_get_length() !== FALSE)
		{
			@ob_end_flush();
			@ob_start();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15922 $
|| ####################################################################
\*======================================================================*/
?>