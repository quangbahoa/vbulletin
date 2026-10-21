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

define('IS_NAV_PANEL', true);

// #################################################################
// ############## NAVBODY LINKS/OPTIONS FUNCTIONS ##################
// #################################################################

// ###################### Start construct_nav_spacer #######################
function construct_nav_spacer()
{
	global $_NAV;

	$_NAV .= '<div style="margin-bottom:12px"></div>';
}

// ###################### Start makenavoption #######################
function construct_nav_option($title, $url, $extra = '')
{
// creates an <option> or <a href for the left-panel of index.php
// (depending on value of $cpnavjs)
// NOTE: '&$session[sessionurl]' will be AUTOMATICALLY added to the URL - do not add to your link!
	global $session, $options;
	static $sessionlink, $bubblefix;

	if (!isset($options))
	{
		$options = array();

		if ($session['sessionurl'] == '')
		{
			$sessionlink = '';
		}
		else
		{
			$sessionlink = "&amp;s=$session[sessionhash]";
		}

		// only include the bubble-fix for IE - ignore when encountering the Konqueror/Safari event model
		if (is_browser('ie'))
		{
			$bubblefix = ' onclick="nobub()"';
		}
		else
		{
			$bubblefix = '';
		}
	}

	$options[] = "\t\t<div class=\"navlink-normal\" onclick=\"nav_goto('$url$sessionlink');\" onmouseover=\"this.className='navlink-hover';\" onmouseout=\"this.className='navlink-normal'\"><a href=\"$url$sessionlink\"$bubblefix>$title</a>$_extra</div>\n";
}

// ###################### Start makenavselect #######################
function construct_nav_group($title, $extra = '', $chs = '')
{
// creates a <select> or <table> for the left panel of index.php
// (depending on value of $cpnavjs)

	global $_NAV, $_NAVPREFS, $nojs, $vboptions, $vbphrase, $stylevar, $options, $groupid, $session;
	static $localphrase, $navlinks;

	if (VB_AREA == 'AdminCP')
	{
		if (!isset($groupid))
		{
			$groupid = 0;
			$navlinks = implode(',', $_NAVPREFS);
			$localphrase = array(
				'expand_group' => $vbphrase['expand_setting_group'],
				'collapse_group' => $vbphrase['collapse_setting_group']
			);
		}

		if (in_array($groupid, $_NAVPREFS))
		{
			$dowhat = 'collapse';
			$style = '';
			$tooltip = 'Collapse Group';
		}
		else
		{
			$dowhat = 'expand';
			$style = 'display:none';
			$tooltip = 'Expand Group';
		}

		$_NAV .= "\n\t<a name=\"grp$groupid\"></a>
		<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\" class=\"navtitle\" ondblclick=\"toggle_group($groupid); return false;\">
		<tr>
			<td><strong>$title</strong></td>
			<td align=\"$stylevar[right]\">
				<a href=\"index.php?$session[sessionurl]do=buildnavprefs&amp;nojs=$nojs&amp;prefs=$navlinks&amp;dowhat=$dowhat&amp;id=$groupid#grp$groupid\" target=\"_self\"
					onclick=\"toggle_group($groupid); return false;\"
					oncontextmenu=\"toggle_group($groupid); save_group_prefs($groupid); return false\"
				><img src=\"../cpstyles/$vboptions[cpstylefolder]/cp_$dowhat.gif\" title=\"$tooltip\" id=\"button_$groupid\" alt=\"\" border=\"0\" /></a>
			</td>
		</tr>
		</table>";
		$_NAV .= "
		<div id=\"group_$groupid\" class=\"navgroup\" style=\"$style\">\n";
	}
	else
	{
		$_NAV .= "\n\t
		<div class=\"navtitle\">$title</div>
		<div class=\"navgroup\">\n";
	}

	foreach ($options AS $link)
	{
		$_NAV .= $link;
	}

	$_NAV .= "\t\t</div>\n";

	$options = array();
	$groupid ++;
}

function print_nav_panel()
{
	global $_NAV, $_NAVPREFS, $groupid, $vboptions, $vbphrase, $nojs, $session;

	$controls = "<div align=\"center\"><a href=\"index.php?$session[sessionurl]do=home\">$vbphrase[control_panel_home]</a></div>";

	if (VB_AREA != 'AdminCP')
	{
		echo $controls . $_NAV;
		return;
	}

	if ($nojs)
	{
		$controls .= "<div align=\"center\">
			<a href=\"index.php?$session[sessionurl]do=navprefs&amp;nojs=$nojs&amp;numgroups=$groupid&amp;expand=1\" onclick=\"expand_all_groups(1); return false;\" target=\"_self\">" . $vbphrase['expand_all'] . "</a>
			|
			<a href=\"index.php?$session[sessionurl]do=navprefs&amp;nojs=$nojs&amp;numgroups=$groupid&amp;expand=0\" onclick=\"expand_all_groups(0); return false;\" target=\"_self\">" . $vbphrase['collapse_all'] . "</a>
		</div>";
	}
	else
	{
		$controls .= "<div align=\"center\">
			<a href=\"index.php?$session[sessionurl]do=navprefs&amp;nojs=$nojs&amp;numgroups=$groupid&amp;expand=1\" onclick=\"expand_all_groups(1); return false;\" target=\"_self\">" . $vbphrase['expand_all'] . "</a>
			|
			<a href=\"index.php?$session[sessionurl]do=navprefs&amp;nojs=$nojs&amp;numgroups=$groupid&amp;expand=0\" onclick=\"expand_all_groups(0); return false;\" target=\"_self\">" . $vbphrase['collapse_all'] . "</a>
			<br />
			<a href=\"#\" onclick=\"save_group_prefs(-1); return false\">$vbphrase[save_prefs]</a>
			|
			<a href=\"#\" onclick=\"read_group_prefs(); return false\">$vbphrase[revert_prefs]</a>
		</div>";
	}

	$navprefs = array();
	for ($i = 0; $i < $groupid; $i++)
	{
		$navprefs["$i"] = iif(in_array($i, $_NAVPREFS), 1, 0);
	}

	?>
	<script type="text/javascript">
	<!--
	var expanded = false;
	var autosave = <?php echo iif($nojs, 'true', 'false'); ?>;
	var navprefs = new Array(<?php echo implode(',', $navprefs); ?>);

	function nobub()
	{
		window.event.cancelBubble = true;
	}

	function nav_goto(targeturl)
	{
		parent.frames.main.location = targeturl;
	}

	function open_close_group(group, doOpen)
	{
		var curdiv = fetch_object("group_" + group);
		var curbtn = fetch_object("button_" + group);

		if (doOpen)
		{
			curdiv.style.display = "";
			curbtn.src = "../cpstyles/<?php echo $vboptions['cpstylefolder']; ?>/cp_collapse.gif";
			curbtn.title = "<?php echo $localphrase['collapse_group']; ?>";
		}
		else
		{
			curdiv.style.display = "none";
			curbtn.src = "../cpstyles/<?php echo $vboptions['cpstylefolder']; ?>/cp_expand.gif";
			curbtn.title = "<?php echo $localphrase['expand_group']; ?>";
		}

	}

	function toggle_group(group)
	{
		var curdiv = fetch_object("group_" + group);

		if (curdiv.style.display == "none")
		{
			open_close_group(group, true);
		}
		else
		{
			open_close_group(group, false);
		}

		if (autosave)
		{
			save_group_prefs(group);
		}
	}

	function expand_all_groups(doOpen)
	{
		for (var i = 0; i < <?php echo $groupid; ?>; i++)
		{
			open_close_group(i, doOpen);
		}

		if (autosave)
		{
			save_group_prefs(-1);
		}
	}

	function save_group_prefs(groupid)
	{
		var opengroups = new Array();
		var counter = 0;

		for (var i = 0; i < <?php echo $groupid; ?>; i++)
		{
			if (fetch_object("group_" + i).style.display != "none")
			{
				opengroups[counter] = i;
				counter++;
			}
		}

		window.location = "index.php?<?php echo $session['sessionurl_js']; ?>do=savenavprefs&nojs=<?php echo $nojs; ?>&navprefs=" + opengroups.join(",") + "#grp" + groupid;
	}

	function read_group_prefs()
	{
		for (var i = 0; i < <?php echo $groupid; ?>; i++)
		{
			open_close_group(i, navprefs[i]);
		}
	}
	//-->
	</script>
	<?php

	echo $controls . $_NAV . $_controls;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9003 $
|| ####################################################################
\*======================================================================*/
?>