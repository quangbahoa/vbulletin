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

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade_370b6.php');
define('VERSION', '3.7.0 Beta 6');
define('PREV_VERSION', '3.7.0 Beta 5');

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
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	if (!isset($vbulletin->bf_misc_useroptions['vm_enable']))
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$upgradecore_phrases[wrong_bitfield_xml]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}

	// Enable Visitor Messages for all users
	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . 'user'),
		"UPDATE " . TABLE_PREFIX . "user SET options = options | " . $vbulletin->bf_misc_useroptions['vm_enable']
	);

	// Enable visitor messaging as a default for new users
	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . 'setting'),
		"UPDATE " . TABLE_PREFIX . "setting SET
			value = value | " . $vbulletin->bf_misc_regoptions['vm_enable'] . "
		WHERE varname = 'defaultregoptions'"
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'socialgroup', 1, 1),
		'socialgroup',
		'options',
		'int',
		FIELD_DEFAULTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3']['altering_x_table'], 'user', 1, 1),
		'user',
		'gmmoderatedcount',
		'int',
		FIELD_DEFAULTS
	);

	// Switch on/off Group Albums/Messages as per the global options
	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . 'socialgroup'),
		"UPDATE " . TABLE_PREFIX . "socialgroup SET
			options = options | " . ($vbulletin->options['socnet_groups_albums_enabled'] ? $vbulletin->bf_misc_socialgroupoptions['enable_group_albums'] : 0) . " | " . ($vbulletin->options['socnet_groups_msg_enabled'] ? $vbulletin->bf_misc_socialgroupoptions['enable_group_messages'] : 0)
	);
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'picture', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "picture ADD state ENUM('visible', 'moderation') NOT NULL DEFAULT 'visible'",
		MYSQL_ERROR_COLUMN_EXISTS
	);

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'album', 1, 2),
		'album',
		'moderation',
		'int',
		FIELD_DEFAULTS
	);

	if ($upgrade->field_exists('album', 'picturecount'))
	{
		$upgrade->run_query(
			sprintf($upgradecore_phrases['altering_x_table'], 'album', 2, 2),
			"ALTER TABLE " . TABLE_PREFIX . "album CHANGE picturecount visible INT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}

	// Disable Picture Moderation for Users
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 1),
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			albumpermissions = albumpermissions | IF(forumpermissions & " . $vbulletin->bf_ugp_forumpermissions['followforummoderation'] . ", " . $vbulletin->bf_ugp_albumpermissions['picturefollowforummoderation'] . ", 0)
		"
	);

	// Enable Moderate Permission for Moderators
	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'moderator', 1, 1),
		"UPDATE " . TABLE_PREFIX . "moderator SET
			permissions2 = permissions2 | IF(permissions & " . $vbulletin->bf_misc_moderatorpermissions2['canmoderatepicturecomments'] . ", " . $vbulletin->bf_misc_moderatorpermissions2['canmoderatepictures'] . ", 0)
		"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'usergroup', 1, 1),
		"UPDATE " . TABLE_PREFIX . "usergroup SET
			socialgrouppermissions = socialgrouppermissions |
				IF(visitormessagepermissions & " . $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile'] . ", " . $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups'] . ", 0)
		"
	);

	$upgrade->execute();
}

// #############################################################################
if ($vbulletin->GPC['step'] == 2)
{
	require_once(DIR . '/install/mysql-schema.php');

	// insert the updated 3.7.0 FAQ Structure
	$upgrade->run_query(
		$upgrade_phrases['upgrade_370b6']['inserting_vb37_faq_structure'],
		$schema['INSERT']['query']['faq']
	);

	// Update Polls
	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'pollvote', 1, 5),
		"ALTER TABLE " . TABLE_PREFIX . "pollvote CHANGE userid userid INT UNSIGNED NULL DEFAULT NULL"
	);

	$upgrade->run_query(
		sprintf($upgradecore_phrases['altering_x_table'], 'pollvote', 2, 5),
		"ALTER TABLE " . TABLE_PREFIX . "pollvote ADD votetype INT UNSIGNED NOT NULL DEFAULT '0'",
		MYSQL_ERROR_COLUMN_EXISTS
	);

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . 'pollvote'),
		"UPDATE " . TABLE_PREFIX . "pollvote AS pollvote, " . TABLE_PREFIX . "poll AS poll
		SET pollvote.votetype = pollvote.voteoption
	 	WHERE pollvote.pollid = poll.pollid
	 		AND poll.multiple = 1
		"
	);

	$upgrade->run_query(
		sprintf($vbphrase['update_table'], TABLE_PREFIX . 'pollvote'),
		"UPDATE " . TABLE_PREFIX . "pollvote SET userid = NULL WHERE userid = 0"
	);

	$upgrade->drop_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pollvote', 3, 5),
		'pollvote',
		'pollid'
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pollvote', 4, 5),
		"ALTER TABLE " . TABLE_PREFIX . "pollvote ADD UNIQUE INDEX pollid (pollid,userid,votetype)",
		MYSQL_ERROR_KEY_EXISTS
	);

	$upgrade->add_index(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'pollvote', 5, 5),
		'pollvote',
		'userid',
		'userid'
	);

	$upgrade->execute();
}

// #############################################################################
// Ask about deleting old FAQ
if ($vbulletin->GPC['step'] == 3)
{
	$vbulletin->input->clean_array_gpc('p', array(
		'upgrade_do' => TYPE_STR,
		'faq' => TYPE_ARRAY_STR,
		'faqexists' => TYPE_ARRAY_STR
	));

	if ($vbulletin->GPC['upgrade_do'] == 'delete_old_faq')
	{
		// create an array of entries that are NOT to be deleted
		$retain_faq_items = array_diff($vbulletin->GPC['faqexists'], $vbulletin->GPC['faq']);

		// if there are items to delete...
		if (!empty($vbulletin->GPC['faq']))
		{
			$delete_faq_items = "'" . implode("', '", array_map(array($db, 'escape_string'), $vbulletin->GPC['faq'])) . "'";

			// delete all items selected on previous form
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "faq WHERE faqname IN($delete_faq_items)");

			// search for any remaining items with faqparent = one of the deleted items
			$orphans_result = $db->query_read("SELECT faqname FROM " . TABLE_PREFIX . "faq WHERE faqparent IN($delete_faq_items) AND faqname NOT IN($delete_faq_items)");
			if ($db->num_rows($orphans_result))
			{
				$orphans = array();
				while ($orphan = $db->fetch_array($orphans_result))
				{
					$orphans[] = $orphan['faqname'];
				}

				// update orphans to have vb_faq as their parent
				$db->query_write("UPDATE " . TABLE_PREFIX . "faq SET faqparent = 'vb_faq' WHERE faqname IN('" . implode("', '", array_map(array($db, 'escape_string'), $orphans)) . "')");

				$retain_faq_items[] = 'vb_faq';
			}
			else
			{
				// check to see if there are any remaining children of vb_faq
				if ($db->query_first("SELECT faqname FROM " . TABLE_PREFIX . "faq WHERE faqparent = 'vb_faq' AND faqname NOT IN($delete_faq_items)"))
				{
					$retain_faq_items[] = 'vb_faq';
				}
				else
				{
					// no remaining children, delete vb_faq
					$db->query_write("DELETE FROM " . TABLE_PREFIX . "faq WHERE faqname = 'vb_faq'");
				}
			}
		}

		// set remaining old default FAQ items to volatile=0 - decouple from vBulletin default
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "faq
			SET volatile = 0
			WHERE volatile = 1
			AND faqname LIKE('vb\\_%')
		");

		// set remaining old default FAQ phrases to languageid=0 - decouple from vBulletin master language
		$db->query_write("
			UPDATE IGNORE " . TABLE_PREFIX . "phrase
			SET languageid = 0
			WHERE languageid = -1
			AND fieldname IN('faqtitle', 'faqtext')
			AND varname LIKE('vb\\_%')
		");

		echo $upgrade_phrases['upgrade_370b6']['selected_faq_items_deleted'];
	}
	else
	{
		define('LANGUAGEID', -1);

		require_once(DIR . '/includes/functions_faq.php');

		function fetch_faq_checkbox_tree($parent = 0)
		{
			global $ifaqcache, $faqcache, $faqjumpbits, $faqparent, $vbphrase, $vbulletin;
			static $output = '';

			if ($parentlist === null)
			{
				$parentlist = $parent;
			}

			if (!is_array($ifaqcache))
			{
				cache_ordered_faq(true);
			}

			if (!is_array($ifaqcache["$parent"]))
			{
				return;
			}

			$output .= "<ul id=\"li_$parent\">";

			foreach($ifaqcache["$parent"] AS $key1 => $faq)
			{
				if ($faq['volatile'])
				{
					$checked = ' checked="checked"';
					$class = '';
				}
				else
				{
					$checked = '';
					$class = ' class="customfaq"';
				}

				$output .= "<li>
					<label for=\"$faq[faqname]\"$class>" .
					"<input type=\"checkbox\" name=\"faq[$faq[faqname]]\" value=\"$faq[faqname]\"$checked id=\"$faq[faqname]\" title=\"$parentlist\" />"
					. $faq['title'] . "</label>";

				construct_hidden_code("faqexists[$faq[faqname]]", $faq['faqname']);

				if (is_array($ifaqcache["$faq[faqname]"]))
				{
					fetch_faq_checkbox_tree($faq['faqname']);
				}
				$output .= "</li>";
			}

			$output .= '</ul>';

			return $output;
		}

		?>
		<style type="text/css">
		#faqlist_checkboxes ul { list-style:none; }
		#faqlist_checkboxes li { margin-top:3px; }
		#faqlist_checkboxes label.customfaq { font-style:italic; }
		</style>
		<div id="faqlist_checkboxes">
		<?php

		print_form_header('upgrade_370b6', '');
		construct_hidden_code('upgrade_do', 'delete_old_faq');
		construct_hidden_code('step', 3);
		//construct_hidden_code('faq[]', 'vb_faq');
		print_table_header($upgrade_phrases['upgrade_370b6.php']['steps'][3]);
		print_description_row($upgrade_phrases['upgrade_370b6']['delete_faq_description']);
		print_description_row(fetch_faq_checkbox_tree('vb_faq'));
		print_submit_row($upgrade_phrases['upgrade_370b6']['delete_selected_faq_items'], $upgrade_phrases['upgrade_370b6']['reset_selection']);

		?>
		</div>
		<script type="text/javascript" src="../clientscript/yui/yahoo-dom-event/yahoo-dom-event.js?v=<?php echo $vbulletin->options['simpleversion'];?>"></script>
		<script type="text/javascript">
		<!--

		function is_checkbox(element)
		{
			return (element.type == "checkbox");
		}

		function toggle_children()
		{
			var checkboxes, i;

			checkboxes = YAHOO.util.Dom.getElementsBy(is_checkbox, "input", "li_" + this.id);
			for (i = 0; i < checkboxes.length; i++)
			{
				checkboxes[i].checked = this.checked;
			}
		}

		var checkboxes = YAHOO.util.Dom.getElementsBy(is_checkbox, "input", "faqlist_checkboxes");
		for (var i = 0; i < checkboxes.length; i++)
		{
			YAHOO.util.Event.on(checkboxes[i], "click", toggle_children);
		}

		//-->
		</script>
		<?php

		define('NONEXTSTEP', true);
	}
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 4)
{
	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);

	// need to reflect new options
	build_bbcode_cache();
}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
