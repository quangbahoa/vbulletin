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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 11535 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('profilefield');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_profilefield.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action(iif($_REQUEST['profilefieldid'] != 0, "profilefield id = " . $_REQUEST['profilefieldid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['user_profile_field_manager']);

$types = array(
	'input' => $vbphrase['single_line_text_box'],
	'textarea' => $vbphrase['multiple_line_text_box'],
	'radio' => $vbphrase['single_selection_radio_buttons'],
	'select' => $vbphrase['single_selection_menu'],
	'select_multiple' => $vbphrase['multiple_selection_menu'],
	'checkbox' => $vbphrase['multiple_selection_checkbox']
);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start Update Display Order #######################
if ($_POST['do'] == 'displayorder')
{
	globalize($_POST, array('order'));

	if (is_array($order) AND !empty($order))
	{
		$sql = '';
		foreach ($order AS $profilefieldid => $displayorder)
		{
			$sql .= "WHEN " . intval($profilefieldid) . " THEN " . intval($displayorder) . "\n";
		}
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "profilefield
			SET displayorder = CASE profilefieldid
			$sql ELSE displayorder END
		");

		define('CP_REDIRECT', 'profilefield.php?do=modify');
		print_stop_message('saved_display_order_successfully');
	}
	else
	{
		$_REQUEST['do'] = 'modify';
	}
}

// ###################### Start Insert / Update #######################
if ($_POST['do'] == 'update')
{

	globalize($_POST, array(
		'type' => STR,
		'profilefield',
		'profilefieldid' => INT,
		'modifyfields',
		'newtype'
	));

	if ((($type == 'select' OR $type == 'radio') AND empty($profilefield['data'])) OR trim($profilefield['title']) == '')
	{
		print_stop_message('please_complete_required_fields');
	}
	else if (($type == 'checkbox' OR $type == 'select_multiple') AND empty($profilefield['data']) AND empty($profilefieldid))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($type == 'select' OR $type == 'radio' OR (($type == 'checkbox' OR $type == 'select_multiple') AND empty($profilefieldid)))
	{

		$data = explode("\n", htmlspecialchars_uni(trim($profilefield['data'])));
		if (sizeof($data) > 32 AND ($type == 'checkbox' OR $type == 'select_multiple'))
		{
			print_stop_message('too_many_profile_field_options', sizeof($data));
		}
		foreach ($data AS $index => $value)
		{
			$data["$index"] = trim($value);
		}
		$profilefield['data'] = serialize($data);
	}
	if ($type == 'input' OR $type == 'textarea')
	{
		$profilefield['data'] = htmlspecialchars_uni(trim($profilefield['data']));
	}
	if (!empty($newtype) AND $newtype != $type)
	{
		$profilefield['type'] = $newtype;
		if ($newtype == 'textarea')
		{
			$profilefield['height'] = 4;
			$profilefield['memberlist'] = 0;
		}
		else if ($newtype == 'checkbox')
		{
			$profilefield['def'] = $profilefield['height'];
		}
		else if ($newtype == 'select_multiple')
		{
			$profilefield['height'] = $profilefield['def'];
		}
	}
	else
	{
		$profilefield['type'] = $type;
	}

	if (empty($profilefieldid))
	{ // insert
		$DB_site->query(fetch_query_sql($profilefield, 'profilefield'));
		$profilefieldid = $DB_site->insert_id();
		$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "userfield ADD field$profilefieldid MEDIUMTEXT NOT NULL");
		$DB_site->query("OPTIMIZE TABLE " . TABLE_PREFIX . "userfield");
	}
	else
	{
		$DB_site->query(fetch_query_sql($profilefield, 'profilefield', "WHERE profilefieldid=$profilefieldid"));
	}

	build_hiddenprofilefield_cache();

	if ($modifyfields)
	{
		define('CP_REDIRECT', "profilefield.php?do=modifycheckbox&profilefieldid=$profilefieldid");
	}
	else
	{
		define('CP_REDIRECT', 'profilefield.php?do=modify');
	}
	print_stop_message('saved_x_successfully', $profilefield['title']);
}

// ###################### Start add #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{

	globalize($_REQUEST, array(
		'type' => STR,
		'profilefieldid' => INT
	));

	if ($_REQUEST['do'] == 'add')
	{

		if (empty($type))
		{
			echo "<p>&nbsp;</p><p>&nbsp;</p>\n";
			print_form_header('profilefield', 'add');
			print_table_header($vbphrase['add_new_user_profile_field']);
			print_label_row($vbphrase['profile_field_type'], '<select name="type" tabindex="1" class="bginput">' . construct_select_options($types) . '</select>', '', 'top', 'profilefieldtype');
			print_submit_row($vbphrase['continue'], 0);
			print_cp_footer();
			exit;
		}

		$maxprofile = $DB_site->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "profilefield");

		$profilefield = array(
			'maxlength' => 100,
			'size' => 25,
			'height' => 4,
			'def' => 1,
			'memberlist' => 1,
			'searchable' => 1,
			'limit' => 0,
			'perline' => 0,
			'height' => 0,
			'displayorder' => $maxprofile['count'] + 1,
			'boxheight' => 0,
			'editable' => 1
		);

		print_form_header('profilefield', 'update');
		construct_hidden_code('type', $type);
		print_table_header($vbphrase['add_new_user_profile_field'] . " <span class=\"normal\">$types[$type]</span>", 2, 0);

	}
	else
	{
		$profilefield = $DB_site->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "profilefield
			WHERE profilefieldid = $profilefieldid
		");

		$type = $profilefield['type'];

		if ($type == 'select' OR $type == 'radio')
		{
			$profilefield['data'] = implode("\n", unserialize($profilefield['data']));
		}
		$profilefield['limit'] = $profilefield['size'];
		$profilefield['perline'] = $profilefield['def'];
		$profilefield['boxheight'] = $profilefield['height'];

		if ($type == 'checkbox')
		{
			echo '<p><b>' . $vbphrase['you_close_before_modifying_checkboxes'] . '</b></p>';
		}
		print_form_header('profilefield', 'update');
		construct_hidden_code('type', $type);
		construct_hidden_code('profilefieldid', $profilefieldid);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user_profile_field'], $profilefield['title'], "$profilefieldid - $profilefield[type]"), 2, 0);
	}

	print_input_row($vbphrase['title'] . '<dfn>' . construct_phrase($vbphrase['maximum_x'], 50) . '</dfn>', 'profilefield[title]', $profilefield['title']);
	if ($type == 'checkbox')
	{
		$extra = '<dfn>' . $vbphrase['choose_limit_choices_add_info'] . '<dfn>';
	}
	print_input_row(construct_phrase($vbphrase['description'] . '<dfn>' . construct_phrase($vbphrase['maximum_x'], 250) . '</dfn>' . $extra), 'profilefield[description]', $profilefield['description']);
	if ($type == 'input')
	{
		print_input_row($vbphrase['default_value_you_may_specify_a_default_registration_value'], 'profilefield[data]', $profilefield['data'], 0);
	}
	if ($type == 'textarea')
	{
		print_textarea_row($vbphrase['default_value_you_may_specify_a_default_registration_value'], 'profilefield[data]', $profilefield['data'], 10, 40, 0);
	}
	if ($type == 'textarea' OR $type == 'input')
	{
		print_input_row($vbphrase['max_length_of_allowed_user_input'], 'profilefield[maxlength]', $profilefield['maxlength']);
		print_input_row($vbphrase['display_size'], 'profilefield[size]', $profilefield['size']);
	}
	if ($type == 'textarea')
	{
		print_input_row($vbphrase['text_area_height'], 'profilefield[height]', $profilefield['height']);
	}
	if ($type == 'select')
	{
		print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'profilefield[data]', $profilefield['data'], 10, 40, 0);
		print_select_row($vbphrase['set_default_if_yes_first'], 'profilefield[def]', array(0 => $vbphrase['none'], 1 => $vbphrase['yes_including_a_blank'], 2 => $vbphrase['yes_but_no_blank_option']),  $profilefield['def']);
	}
	if ($type == 'radio')
	{
		print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'profilefield[data]', $profilefield['data'], 10, 40, 0);
		print_yes_no_row($vbphrase['set_default_if_yes_first'], 'profilefield[def]', $profilefield['def']);
	}
	if ($type == 'checkbox')
	{
		print_input_row($vbphrase['limit_selection'], 'profilefield[size]', $profilefield['limit']);
		print_input_row($vbphrase['boxes_per_line'], 'profilefield[def]', $profilefield['perline']);
		if ($_REQUEST['do'] == 'add')
		{
			print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'profilefield[data]', '', 10, 40, 0);
		}
		else
		{
			print_label_row($vbphrase['fields'], '<input type="image" src="../' . $vboptions['cleargifurl'] . '"><input type="submit" class="button" value="' . $vbphrase['modify'] . '" tabindex="1" name="modifyfields">');
		}
	}
	if ($type == 'select_multiple')
	{
		print_input_row($vbphrase['limit_selection'], 'profilefield[size]', $profilefield['limit']);
		print_input_row($vbphrase['box_height'], 'profilefield[height]', $profilefield['boxheight']);
		if ($_REQUEST['do'] == 'add')
		{
			print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'profilefield[data]', '', 10);
		}
		else
		{
			print_label_row($vbphrase['fields'], '<input type="image" src="../' . $vboptions['cleargifurl'] . '"><input type="submit" class="button" value="' . $vbphrase['modify'] . '" tabindex="1" name="modifyfields">');
		}
	}
	if ($_REQUEST['do'] == 'edit')
	{
		if ($type == 'input' OR $type == 'textarea')
		{
			if ($type == 'input')
			{
				$inputchecked = HTML_CHECKED;
			}
			else
			{
				$textareachecked = HTML_CHECKED;
			}
			print_label_row($vbphrase['profile_field_type'], "
				<label for=\"newtype_input\"><input type=\"radio\" name=\"newtype\" value=\"input\" id=\"newtype_input\" tabindex=\"1\" $inputchecked>" . $vbphrase['single_line_text_box'] . "</label><br />
				<label for=\"newtype_textarea\"><input type=\"radio\" name=\"newtype\" value=\"textarea\" id=\"newtype_textarea\" $textareachecked>" . $vbphrase['multiple_line_text_box'] . "</label>
			", '', 'top', 'newtype');
		}
		else if ($type == 'checkbox' OR $type == 'select_multiple')
		{
			if ($type == 'checkbox')
			{
				$checkboxchecked = HTML_CHECKED;
			}
			else
			{
				$multiplechecked = HTML_CHECKED;
			}
			print_label_row($vbphrase['profile_field_type'], "
				<label for=\"newtype_checkbox\"><input type=\"radio\" name=\"newtype\" value=\"checkbox\" id=\"newtype_checkbox\" tabindex=\"1\" $checkboxchecked>" . $vbphrase['multiple_selection_checkbox'] . "</label><br />
				<label for=\"newtype_multiple\"><input type=\"radio\" name=\"newtype\" value=\"select_multiple\" id=\"newtype_multiple\" tabindex=\"1\" $multiplechecked>" . $vbphrase['multiple_selection_menu'] . "</label>
			");
		}

	}
	print_input_row($vbphrase['display_order'], 'profilefield[displayorder]', $profilefield['displayorder']);
	//print_yes_no_row($vbphrase['field_required'], 'profilefield[required]', $profilefield['required']);
	print_select_row($vbphrase['field_required'], 'profilefield[required]', array(
		1 => $vbphrase['yes'],
		0 => $vbphrase['no'],
		2 => $vbphrase['no_but_on_register']
	), $profilefield['required']);
	print_select_row($vbphrase['field_editable_by_user'], 'profilefield[editable]', array(
		1 => $vbphrase['yes'],
		0 => $vbphrase['no'],
		2 => $vbphrase['only_at_registration']
	), $profilefield['editable']);
	print_yes_no_row($vbphrase['field_hidden_on_profile'], 'profilefield[hidden]', $profilefield['hidden']);
	print_yes_no_row($vbphrase['field_searchable_on_members_list'], 'profilefield[searchable]', $profilefield['searchable']);
	if ($type != 'textarea')
	{
		print_yes_no_row($vbphrase['show_on_members_list'], 'profilefield[memberlist]', $profilefield['memberlist']);
	}

	if ($type == 'select' OR $type == 'radio')
	{
		print_table_break();
		print_table_header($vbphrase['optional_input']);
		print_yes_no_row($vbphrase['allow_user_to_input_their_own_value_for_this_option'], 'profilefield[optional]', $profilefield['optional']);
		print_input_row($vbphrase['max_length_of_allowed_user_input'], 'profilefield[maxlength]', $profilefield['maxlength']);
		print_input_row($vbphrase['display_size'], 'profilefield[size]', $profilefield['size']);
	}
	if ($type != 'select_multiple' AND $type != 'checkbox')
	{
		print_input_row($vbphrase['regular_expression_require_match'], 'profilefield[regex]', $profilefield['regex']);
	}

	print_table_break();
	print_table_header($vbphrase['display_page']);
	print_select_row($vbphrase['which_page_displays_option'], 'profilefield[form]', array(
		$vbphrase['edit_profile'],
		"$vbphrase[options]: $vbphrase[log_in] / $vbphrase[privacy]",
		"$vbphrase[options]: $vbphrase[messaging] / $vbphrase[notification]",
		"$vbphrase[options]: $vbphrase[thread_viewing]",
		"$vbphrase[options]: $vbphrase[date] / $vbphrase[time]",
		"$vbphrase[options]: $vbphrase[other]"
	), $profilefield['form']);

	print_submit_row($vbphrase['save']);
}

// ###################### Start Rename Checkbox Data #######################
if ($_REQUEST['do'] == 'renamecheckbox')
{
	globalize($_REQUEST, array(
		'id' => INT,
		'profilefieldid' => INT
	));

	$boxdata = $DB_site->query_first("
		SELECT data,type
		FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = $profilefieldid
	");
	$data = unserialize($boxdata['data']);
	foreach ($data AS $index => $value)
	{
		if ($index + 1 == $id)
		{
			$oldfield = $value;
			break;
		}
	}

	print_form_header('profilefield', 'dorenamecheckbox');
	construct_hidden_code('profilefieldid', $profilefieldid);
	construct_hidden_code('id', $id);
	print_table_header($vbphrase['rename']);
	print_input_row($vbphrase['name'], 'newfield', $oldfield);
	print_submit_row($vbphrase['save']);

}

// ###################### Start Rename Checkbox Data #######################
if ($_POST['do'] == 'dorenamecheckbox')
{
	globalize($_POST, array(
		'newfield' => STR_NOHTML,
		'profilefieldid' => INT,
		'id' => INT
	));

	if (!empty($newfield))
	{
		$boxdata = $DB_site->query_first("
			SELECT data
			FROM " . TABLE_PREFIX . "profilefield
			WHERE profilefieldid = $profilefieldid
		");
		$data = unserialize($boxdata['data']);
		foreach ($data AS $index => $value)
		{
			if (strtolower($value) == strtolower($newfield))
			{
				print_stop_message('this_is_already_option_named_x', $value);
			}
		}

		$data[$id - 1] = $newfield;

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "profilefield
			SET data = '" . addslashes(serialize($data)) . "'
			WHERE profilefieldid = $profilefieldid
		");

	}
	else
	{
		print_stop_message('please_complete_required_fields');
	}

	define('CP_REDIRECT', "profilefield.php?do=modifycheckbox&profilefieldid=$profilefieldid");
	print_stop_message('saved_option_x_successfully', $newfield);
}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'deletecheckbox')
{
	globalize($_REQUEST, array(
		'profilefieldid' => INT,
		'id' => INT
	));

	print_form_header('profilefield', 'dodeletecheckbox');
	construct_hidden_code('profilefieldid', $profilefieldid);
	construct_hidden_code('id', $id);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_user_profile_field']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

// ###################### Process Remove Checkbox Option #######################
if ($_POST['do'] == 'dodeletecheckbox')
{
	globalize($_POST, array(
		'profilefieldid' => INT,
		'id' => INT
	));

	$boxdata = $DB_site->query_first("
		SELECT title, data
		FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = $profilefieldid
	");
	$data = unserialize($boxdata['data']);

	$DB_site->query("UPDATE " . TABLE_PREFIX . "userfield SET temp = field$profilefieldid");

	foreach ($data AS $index => $value)
	{
		$index;
		$index2 = $index + 1;
		if ($index2 >= $id)
		{
			if ($id == $index2)
			{
				build_profilefield_bitfields($index2); // Delete this value
			}
			else

			{
				build_profilefield_bitfields($index2, $index);
			}
			if ($index2 == sizeof($data))
			{
				unset($data[$index]);
			}
			else
			{
				$data[$index] = $data[$index2];
			}
		}
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "userfield
		SET field$profilefieldid = temp,
		temp = ''
	");

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "profilefield
		SET data = '" . addslashes(serialize($data)) . "'
		WHERE profilefieldid = $profilefieldid
	");

	define('CP_REDIRECT', "profilefield.php?do=modifycheckbox&profilefieldid=$profilefieldid");
	print_stop_message('deleted_option_successfully');
}

// ###################### Start Add Checkbox #######################
if ($_POST['do'] == 'addcheckbox')
{
	globalize($_POST, array(
		'newfield',
		'profilefieldid' => INT,
		'newfieldpos'
	));

	if (!empty($newfield))
	{

		$newfield = htmlspecialchars_uni(trim($newfield));
		$boxdata = $DB_site->query_first("
			SELECT data
			FROM " . TABLE_PREFIX . "profilefield
			WHERE profilefieldid = $profilefieldid
		");
		$data = unserialize($boxdata['data']);

		if (sizeof($data) >= 32)
		{
			print_stop_message('too_many_profile_field_options', sizeof($data));
		}

		foreach ($data AS $index => $value)
		{
			if (strtolower($value) == strtolower($newfield))
			{
				print_stop_message('this_is_already_option_named_x', $value);
			}
		}

		$DB_site->query("UPDATE " . TABLE_PREFIX . "userfield SET temp = field$profilefieldid");

		for ($x = sizeof($data); $x >= 0; $x--)
		{
			if ($x > $newfieldpos)
			{
				$data[$x] = $data[$x - 1];
				build_profilefield_bitfields($x, $x + 1);
			}
			else if ($x == $newfieldpos)
			{
				$data[$x] = $newfield;
			}
		}

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "userfield
			SET field$profilefieldid = temp,
			temp = ''
		");

		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "profilefield SET
			data = '" . addslashes(serialize($data)) . "'
			WHERE profilefieldid = $profilefieldid
		");

		define('CP_REDIRECT', "profilefield.php?do=modifycheckbox&profilefieldid=$profilefieldid");
		print_stop_message('saved_option_successfully');
	}
	else
	{
		print_stop_message('invalid_option_specified');
	}

}

// ###################### Start Move Checkbox #######################

if ($_REQUEST['do'] == 'movecheckbox')
{
	globalize($_REQUEST, array(
		'profilefieldid' => INT,
		'direction' => STR,
		'id' => INT
	));

	$boxdata = $DB_site->query_first("
		SELECT data
		FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = $profilefieldid
	");
	$data = unserialize($boxdata['data']);

	$DB_site->query("UPDATE " . TABLE_PREFIX . "userfield SET temp = field$profilefieldid");

	if ($direction == 'up')
	{
		build_bitwise_swap($id, $id - 1);
	}
	else
	{ // Down
		build_bitwise_swap($id, $id + 1);
	}

	foreach ($data AS $index => $value)
	{
		if ($index + 1 == $id)
		{
			$temp = $data[$index];
			if ($direction == 'up')
			{
				$data[$index] = $data[$index - 1];
				$data[$index - 1] = $temp;
			}
			else

			{ // Down
				$data[$index] = $data[$index + 1];
				$data[$index + 1] = $temp;
			}
			break;
		}
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "userfield
		SET field$profilefieldid = temp,
		temp = ''
	");

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "profilefield
		SET data = '" . addslashes(serialize($data)) . "'
		WHERE profilefieldid = $profilefieldid
	");

	$_REQUEST['do'] = 'modifycheckbox';

}

// ###################### Start Modify Checkbox Data #######################
if ($_REQUEST['do'] == 'modifycheckbox')
{
	globalize($_REQUEST, array('profilefieldid' => INT));

	$boxdata = $DB_site->query_first("
		SELECT title, data, type
		FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = $profilefieldid
	");

	if ($boxdata['data'] != '')
	{
		$output = '<table cellspacing="0" cellpadding="4"><tr><td><b>' . $vbphrase['move'] . '</b></td><td colspan=2><b>' . $vbphrase['option'] . '</b></td></tr>';
		$data = unserialize($boxdata['data']);
		foreach ($data AS $index => $value)
		{
			$index++;
			if ($index != 1)
			{
				$moveup = "<a href=\"profilefield.php?$session[sessionurl]profilefieldid=$profilefieldid&do=movecheckbox&direction=up&id=$index\"><img src=\"../cpstyles/$vboptions[cpstylefolder]/move_up.gif\" border=\"0\" /></a> ";
			}
			else
			{
				$moveup = "<img src=\"../$vboptions[cleargifurl]\" width=\"11\" border=\"0\" alt=\"\" /> ";
			}
			if ($index != sizeof($data))
			{
				$movedown = "<a href=\"profilefield.php?$session[sessionurl]profilefieldid=$profilefieldid&do=movecheckbox&direction=down&id=$index\"><img src=\"../cpstyles/$vboptions[cpstylefolder]/move_down.gif\" border=\"0\" /></a> ";
			}
			else
			{
				unset($movedown);
			}
			$output .= "<tr><td>$moveup$movedown</td><td>$value</td><td>".
			construct_link_code($vbphrase['rename'], "profilefield.php?do=renamecheckbox&profilefieldid=$profilefieldid&id=$index")
			."</td><td>".
			iif(sizeof($xxxdata) > 1, construct_link_code($vbphrase['move'], "profilefield.php?do=movecheckbox&profilefieldid=$profilefieldid&id=$index"), '')
			. "</td><td>".
			iif(sizeof($data) > 1, construct_link_code($vbphrase['delete'], "profilefield.php?do=deletecheckbox&profilefieldid=$profilefieldid&id=$index"), '')
			. "</td></tr>";
		}
		$output .= '</table>';
	}
	else
	{
		$output = "<p>" . construct_phrase($vbphrase['this_profile_fields_no_options'], $boxdata['type']) . "</p>";
	}

	print_form_header('', '');
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user_profile_field'], construct_link_code($boxdata['title'], "profilefield.php?$session[sessionurl]do=edit&amp;profilefieldid=$profilefieldid"), $profilefieldid));
	print_table_break();
	print_table_header($vbphrase['modify']);
	print_description_row($output);
	print_table_footer();

	if (sizeof($data) < 32)
	{
		print_form_header('profilefield', 'addcheckbox');
		construct_hidden_code('profilefieldid', $profilefieldid);
		print_table_header($vbphrase['add']);
		print_input_row($vbphrase['name'], 'newfield');
		$output = "<select name=\"newfieldpos\" tabindex=\"1\" class=\"bginput\"><option value=\"0\">" . $vbphrase['first']."</option>\n";
		if ($boxdata['data'] != '')
		{
			foreach ($data AS $index => $value)
			{
				$index++;
				$output .= "<option value=\"$index\"" . iif(sizeof($data) == $index, " selected=\"selected\"") . ">" . construct_phrase($vbphrase['after_x'], $value) . "</option>\n";
			}
		}
		print_label_row($vbphrase['postition'], $output);
		print_submit_row($vbphrase['add_new_option']);
	}

}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{
	globalize($_REQUEST, array('profilefieldid' => INT));

	print_form_header('profilefield', 'kill');
	construct_hidden_code('profilefieldid', $profilefieldid);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_user_profile_field']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	globalize($_POST, array('profilefieldid' => INT));

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "profilefield WHERE profilefieldid = $profilefieldid");
	$DB_site->query("ALTER TABLE " . TABLE_PREFIX . "userfield DROP field$profilefieldid");
	$DB_site->query("OPTIMIZE TABLE " . TABLE_PREFIX . "userfield");

	build_hiddenprofilefield_cache();

	define('CP_REDIRECT', 'profilefield.php?do=modify');
	print_stop_message('deleted_user_profile_field_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{

	$profilefields = $DB_site->query("
		SELECT profilefieldid, title, type, form, displayorder, IF(required=2, 0, required) AS required, editable, hidden, searchable, memberlist
		FROM " . TABLE_PREFIX . "profilefield
	");

	if ($DB_site->num_rows($profilefields))
	{
		$forms = array(
			0 => $vbphrase['edit_profile'],
			1 => "$vbphrase[options]: $vbphrase[log_in] / $vbphrase[privacy]",
			2 => "$vbphrase[options]: $vbphrase[messaging] / $vbphrase[notification]",
			3 => "$vbphrase[options]: $vbphrase[thread_viewing]",
			4 => "$vbphrase[options]: $vbphrase[date] / $vbphrase[time]",
			5 => "$vbphrase[options]: $vbphrase[other]",
		);

		$optionfields = array(
			'required' => $vbphrase['required'],
			'editable' => $vbphrase['editable'],
			'hidden' => $vbphrase['hidden'],
			'searchable' => $vbphrase['searchable'],
			'memberlist' => $vbphrase['members_list'],
		);

		$fields = array();

		while ($profilefield = $DB_site->fetch_array($profilefields))
		{
			$fields["$profilefield[form]"]["$profilefield[displayorder]"]["$profilefield[profilefieldid]"] = $profilefield;
		}
		$DB_site->free_result($profilefields);

		// sort by form and displayorder
		ksort($fields);
		foreach (array_keys($fields) AS $key)
		{
			ksort($fields["$key"]);
		}

		$numareas = sizeof($fields);
		$areacount = 0;

		print_form_header('profilefield', 'displayorder');

		foreach ($forms AS $formid => $formname)
		{
			if (is_array($fields["$formid"]))
			{
				print_table_header(construct_phrase($vbphrase['user_profile_fields_in_area_x'], $formname), 5);

				echo "
				<col width=\"50%\" align=\"$stylevar[left]\"></col>
				<col width=\"50%\" align=\"$stylevar[left]\"></col>
				<col align=\"$stylevar[left]\" style=\"white-space:nowrap\"></col>
				<col align=\"center\" style=\"white-space:nowrap\"></col>
				<col align=\"center\" style=\"white-space:nowrap\"></col>
				";

				print_cells_row(array(
					"$vbphrase[title] / $vbphrase[profile_field_type]",
					$vbphrase['options'],
					$vbphrase['name'],
					'<nobr>' . $vbphrase['display_order'] . '</nobr>',
					$vbphrase['controls']
				), 1, '', -1);

				foreach ($fields["$formid"] AS $displayorder => $profilefields)
				{
					foreach ($profilefields AS $profilefieldid => $profilefield)
					{
						$bgclass = fetch_row_bgclass();

						$options = array();
						foreach ($optionfields AS $fieldname => $optionname)
						{
							if ($profilefield["$fieldname"])
							{
								$options[] = $optionname;
							}
						}
						$options = implode(', ', $options) . '&nbsp;';


						echo "
						<tr>
							<td class=\"$bgclass\"><strong>$profilefield[title] <dfn>{$types[$profilefield[type]]}</dfn></strong></td>
							<td class=\"$bgclass\">$options</td>
							<td class=\"$bgclass\">field$profilefieldid</td>
							<td class=\"$bgclass\"><input type=\"text\" class=\"bginput\" name=\"order[$profilefieldid]\" value=\"$profilefield[displayorder]\" size=\"5\" /></td>
							<td class=\"$bgclass\">" .
							construct_link_code($vbphrase['edit'], "profilefield.php?$session[sessionurl]do=edit&amp;profilefieldid=$profilefieldid") .
							construct_link_code($vbphrase['delete'], "profilefield.php?$session[sessionurl]do=remove&profilefieldid=$profilefieldid") .
							"</td>
						</tr>";
					}
				}

				print_description_row("<input type=\"submit\" class=\"button\" value=\"$vbphrase[save_display_order]\" accesskey=\"s\" />", 0, 5, 'tfoot', $stylevar['right']);

				if (++$areacount < $numareas)
				{
					print_table_break('');
				}
			}
		}

		print_table_footer();
	}
	else
	{
		print_stop_message('no_profile_fields_defined');
	}

}
// #############################################################################

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 11535 $
|| ####################################################################
\*======================================================================*/
?>