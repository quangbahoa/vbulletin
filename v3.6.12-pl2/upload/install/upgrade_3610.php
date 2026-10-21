<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade_3610.php');
define('VERSION', '3.6.10');
define('PREV_VERSION', '3.6.9');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// #############################################################################
// welcome step
if ($vbulletin->GPC['step'] == 'welcome')
{
	if ($vbulletin->options['templateversion'] == PREV_VERSION)
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$vbphrase[upgrade_start_message]";
		echo "<p>&nbsp;</p></blockquote>";
	}
	else
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$vbphrase[upgrade_wrong_version]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}
}

// #############################################################################
if ($vbulletin->GPC['step'] == 1)
{
	require_once(DIR . '/includes/adminfunctions_template.php');

	// special case: memberlist, modifyattachments, reputationbit
	// add missing sessionhash. Let later query add security tokens
	// special case for headinclude: the JS variable
	$templates = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "template
		WHERE styleid > 0
			AND title IN ('memberlist', 'modifyattachments', 'reputationbit')
		ORDER BY title
	");
	while ($template = $db->fetch_array($templates))
	{
		if (strpos($template['template_un'], '$bbuserinfo[securitytoken]') !== false)
		{
			continue;
		}

		switch ($template['title'])
		{
			case 'memberlist':
				$find_text = '<input type="hidden" name="do" value="deleteusergroups" />';
				break;

			case 'modifyattachments':
				$find_text = '<input type="hidden" name="do" value="deleteattachments" />';
				break;

			case 'reputationbit':
				$find_text = '<input type="hidden" name="do" value="addreputation" />';
				break;

			default:
				$find_text = '';
		}

		if (!$find_text)
		{
			continue;
		}

		$template['template_un'] = str_replace(
			$find_text,
			$find_text . "\n\t" . '<input type="hidden" name="s" value="$session[sessionhash]" />',
			$template['template_un']
		);

		$compiled_template = compile_template($template['template_un']);

		$db->query_write(
			"UPDATE " . TABLE_PREFIX . "template SET
				template = '" . $db->escape_string($compiled_template) . "',
				template_un = '" . $db->escape_string($template['template_un']) . "'
			WHERE templateid = $template[templateid]"
		);

		$upgrade->show_message(
			sprintf($vbphrase['apply_critical_template_change_to_x'], $template['title'], $template['styleid'])
		);
	}

	// add the security token to all forms
	$session_var = 'value="$session[sessionhash]"';
	$dbsession_var = 'value="$session[dbsessionhash]"';
	$get_forms_only = array(
		'calendarjump',
		'FAQ',
		'forumjump',
		'im_send_icq',
		'smiliepopup'
	);

	$templates = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "template
		WHERE styleid > 0
			AND (
				template_un LIKE '%" . $db->escape_string_like($session_var) . "%'
				OR
				template_un LIKE '%" . $db->escape_string_like($dbsession_var) . "%'
			)
		ORDER BY title ASC
	");
	$templates_updated = 0;

	while ($template = $db->fetch_array($templates))
	{
		if (strpos($template['template_un'], '$bbuserinfo[securitytoken]') !== false)
		{
			continue;
		}

		if (in_array($template['title'], $get_forms_only))
		{
			continue;
		}

		$new_template = preg_replace(
			'#((' . preg_quote($session_var, '#') . '|' . preg_quote($dbsession_var, '#') . ').*>)#siU',
			"\\1\n\t" . '<input type="hidden" name="securitytoken" value="$bbuserinfo[securitytoken]" />',
			$template['template_un']
		);

		$compiled_template = compile_template($new_template);

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "template SET
				template = '" . $db->escape_string($compiled_template) . "',
				template_un = '" . $db->escape_string($new_template) . "'
			WHERE templateid = $template[templateid]
		");

		$templates_updated++;
	}

	$upgrade->show_message(
		sprintf($upgrade_phrases['upgrade_3610.php']['token_added_x_templates'], $templates_updated)
	);

	// special case for headinclude: the JS variable
	$templates = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "template
		WHERE styleid > 0
			AND title IN ('headinclude')
	");
	while ($template = $db->fetch_array($templates))
	{
		if (strpos($template['template_un'], '$bbuserinfo[securitytoken]') !== false)
		{
			continue;
		}

		$template['template_un'] = str_replace(
			'var SESSIONURL = "$session[sessionurl_js]";',
			'var SESSIONURL = "$session[sessionurl_js]";' . "\n" . 'var SECURITYTOKEN = "$bbuserinfo[securitytoken]";',
			$template['template_un']
		);

		$template['template_un'] = str_replace(
			'var SESSIONURL = "$session[sessionurl]";',
			'var SESSIONURL = "$session[sessionurl]";' . "\n" . 'var SECURITYTOKEN = "$bbuserinfo[securitytoken]";',
			$template['template_un']
		);

		$compiled_template = compile_template($template['template_un']);

		$upgrade->run_query(
			sprintf($vbphrase['apply_critical_template_change_to_x'], $template['title'], $template['styleid']),
			"UPDATE " . TABLE_PREFIX . "template SET
				template = '" . $db->escape_string($compiled_template) . "',
				template_un = '" . $db->escape_string($template['template_un']) . "'
			WHERE templateid = $template[templateid]
		");
	}

	// special case for who's online: a form that should be get
	$templates = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "template
		WHERE styleid > 0
			AND title IN ('WHOSONLINE')
	");
	while ($template = $db->fetch_array($templates))
	{
		if (strpos($template['template_un'], '<form action="online.php" method="get">') !== false)
		{
			continue;
		}

		$template['template_un'] = str_replace(
			'<form action="online.php" method="post">',
			'<form action="online.php" method="get">',
			$template['template_un']
		);

		$compiled_template = compile_template($template['template_un']);

		$upgrade->run_query(
			sprintf($vbphrase['apply_critical_template_change_to_x'], $template['title'], $template['styleid']),
			"UPDATE " . TABLE_PREFIX . "template SET
				template = '" . $db->escape_string($compiled_template) . "',
				template_un = '" . $db->escape_string($template['template_un']) . "'
			WHERE templateid = $template[templateid]
		");
	}

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 2)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15846 $
|| ####################################################################
\*======================================================================*/
?>