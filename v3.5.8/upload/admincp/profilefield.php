<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 14481 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('profilefield');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_profilefield.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'profilefieldid' => TYPE_UINT,
));

// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['profilefieldid'] != 0, "profilefield id = " . $vbulletin->GPC['profilefieldid']));

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
	$vbulletin->input->clean_array_gpc('p', array(
		'order' => TYPE_ARRAY_UINT,
	));

	if (!empty($vbulletin->GPC['order']))
	{
		$sql = '';
		foreach ($vbulletin->GPC['order'] AS $_profilefieldid => $displayorder)
		{
			$sql .= "WHEN " . intval($_profilefieldid) . " THEN " . intval($displayorder) . "\n";
		}
		$db->query_write("
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

	$vbulletin->input->clean_array_gpc('p', array(
		'type'           => TYPE_STR,
		'profilefield'   => TYPE_ARRAY_STR,
		'modifyfields'   => TYPE_STR,
		'newtype'        => TYPE_STR,
	));

	if ((($vbulletin->GPC['type'] == 'select' OR $vbulletin->GPC['type'] == 'radio') AND empty($vbulletin->GPC['profilefield']['data'])) OR empty($vbulletin->GPC['profilefield']['title']))
	{
		print_stop_message('please_complete_required_fields');
	}
	else if (($vbulletin->GPC['type'] == 'checkbox' OR $vbulletin->GPC['type'] == 'select_multiple') AND empty($vbulletin->GPC['profilefield']['data']) AND empty($vbulletin->GPC['profilefieldid']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($vbulletin->GPC['type'] == 'select' OR $vbulletin->GPC['type'] == 'radio' OR (($vbulletin->GPC['type'] == 'checkbox' OR $vbulletin->GPC['type'] == 'select_multiple') AND empty($vbulletin->GPC['profilefieldid'])))
	{

		$data = explode("\n", htmlspecialchars_uni($vbulletin->GPC['profilefield']['data']));
		if (sizeof($data) > 32 AND ($vbulletin->GPC['type'] == 'checkbox' OR $vbulletin->GPC['type'] == 'select_multiple'))
		{
			print_stop_message('too_many_profile_field_options', sizeof($data));
		}
		foreach ($data AS $index => $value)
		{
			$data["$index"] = trim($value);
		}
		$vbulletin->GPC['profilefield']['data'] = serialize($data);
	}

	if ($vbulletin->GPC['type'] == 'input' OR $vbulletin->GPC['type'] == 'textarea')
	{
		$vbulletin->GPC['profilefield']['data'] = htmlspecialchars_uni($vbulletin->GPC['profilefield']['data']);
	}
	if (!empty($vbulletin->GPC['newtype']) AND $vbulletin->GPC['newtype'] != $vbulletin->GPC['type'])
	{
		$vbulletin->GPC['profilefield']['type'] = $vbulletin->GPC['newtype'];
		if ($vbulletin->GPC['newtype'] == 'textarea')
		{
			$vbulletin->GPC['profilefield']['height'] = 4;
			$vbulletin->GPC['profilefield']['memberlist'] = 0;
		}
		else if ($vbulletin->GPC['newtype'] == 'checkbox')
		{
			$vbulletin->GPC['profilefield']['def'] = $vbulletin->GPC['profilefield']['height'];
		}
		else if ($vbulletin->GPC['newtype'] == 'select_multiple')
		{
			$vbulletin->GPC['profilefield']['height'] = $vbulletin->GPC['profilefield']['def'];
		}
	}
	else
	{
		$vbulletin->GPC['profilefield']['type'] = $vbulletin->GPC['type'];
	}

	if (empty($vbulletin->GPC['profilefieldid']))
	{ // insert
		/*insert query*/
		$db->query_write(fetch_query_sql($vbulletin->GPC['profilefield'], 'profilefield'));
		$vbulletin->GPC['profilefieldid'] = $db->insert_id();
		$db->query_write("ALTER TABLE " . TABLE_PREFIX . "userfield ADD field{$vbulletin->GPC['profilefieldid']} MEDIUMTEXT NOT NULL");
		$db->query_write("OPTIMIZE TABLE " . TABLE_PREFIX . "userfield");
	}
	else
	{
		$db->query_write(fetch_query_sql($vbulletin->GPC['profilefield'], 'profilefield', "WHERE profilefieldid=" . $vbulletin->GPC['profilefieldid']));
	}

	build_hiddenprofilefield_cache();

	if ($vbulletin->GPC['modifyfields'])
	{
		define('CP_REDIRECT', "profilefield.php?do=modifycheckbox&profilefieldid=" . $vbulletin->GPC['profilefieldid']);
	}
	else
	{
		define('CP_REDIRECT', 'profilefield.php?do=modify');
	}
	print_stop_message('saved_x_successfully', $vbulletin->GPC['profilefield']['title']);
}

// ###################### Start add #######################
if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{

	$vbulletin->input->clean_array_gpc('r', array(
		'type'           => TYPE_STR,
	));

	if ($_REQUEST['do'] == 'add')
	{

		if (empty($vbulletin->GPC['type']))
		{
			echo "<p>&nbsp;</p><p>&nbsp;</p>\n";
			print_form_header('profilefield', 'add');
			print_table_header($vbphrase['add_new_user_profile_field']);
			print_label_row($vbphrase['profile_field_type'], '<select name="type" tabindex="1" class="bginput">' . construct_select_options($types) . '</select>', '', 'top', 'profilefieldtype');
			print_submit_row($vbphrase['continue'], 0);
			print_cp_footer();
			exit;
		}

		$maxprofile = $db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "profilefield");

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
		construct_hidden_code('type', $vbulletin->GPC['type']);
		print_table_header($vbphrase['add_new_user_profile_field'] . " <span class=\"normal\">" . $types["{$vbulletin->GPC['type']}"] . "</span>", 2, 0);

	}
	else
	{
		$profilefield = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "profilefield
			WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
		");

		$vbulletin->GPC['type'] =& $profilefield['type'];

		if ($vbulletin->GPC['type'] == 'select' OR $vbulletin->GPC['type'] == 'radio')
		{
			$profilefield['data'] = implode("\n", unserialize($profilefield['data']));
		}
		$profilefield['limit'] = $profilefield['size'];
		$profilefield['perline'] = $profilefield['def'];
		$profilefield['boxheight'] = $profilefield['height'];

		if ($vbulletin->GPC['type'] == 'checkbox')
		{
			echo '<p><b>' . $vbphrase['you_close_before_modifying_checkboxes'] . '</b></p>';
		}
		print_form_header('profilefield', 'update');
		construct_hidden_code('type', $vbulletin->GPC['type']);
		construct_hidden_code('profilefieldid', $vbulletin->GPC['profilefieldid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user_profile_field'], $profilefield['title'], $vbulletin->GPC['profilefieldid'] . " - $profilefield[type]"), 2, 0);
	}

	print_input_row($vbphrase['title'] . '<dfn>' . construct_phrase($vbphrase['maximum_x'], 50) . '</dfn>', 'profilefield[title]', $profilefield['title']);
	if ($vbulletin->GPC['type'] == 'checkbox')
	{
		$extra = '<dfn>' . $vbphrase['choose_limit_choices_add_info'] . '<dfn>';
	}
	print_input_row(construct_phrase($vbphrase['description'] . '<dfn>' . construct_phrase($vbphrase['maximum_x'], 250) . '</dfn>' . $extra), 'profilefield[description]', $profilefield['description']);
	if ($vbulletin->GPC['type'] == 'input')
	{
		print_input_row($vbphrase['default_value_you_may_specify_a_default_registration_value'], 'profilefield[data]', $profilefield['data'], 0);
	}
	if ($vbulletin->GPC['type'] == 'textarea')
	{
		print_textarea_row($vbphrase['default_value_you_may_specify_a_default_registration_value'], 'profilefield[data]', $profilefield['data'], 10, 40, 0);
	}
	if ($vbulletin->GPC['type'] == 'textarea' OR $vbulletin->GPC['type'] == 'input')
	{
		print_input_row($vbphrase['max_length_of_allowed_user_input'], 'profilefield[maxlength]', $profilefield['maxlength']);
		print_input_row($vbphrase['display_size'], 'profilefield[size]', $profilefield['size']);
	}
	if ($vbulletin->GPC['type'] == 'textarea')
	{
		print_input_row($vbphrase['text_area_height'], 'profilefield[height]', $profilefield['height']);
	}
	if ($vbulletin->GPC['type'] == 'select')
	{
		print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'profilefield[data]', $profilefield['data'], 10, 40, 0);
		print_select_row($vbphrase['set_default_if_yes_first'], 'profilefield[def]', array(0 => $vbphrase['none'], 1 => $vbphrase['yes_including_a_blank'], 2 => $vbphrase['yes_but_no_blank_option']),  $profilefield['def']);
	}
	if ($vbulletin->GPC['type'] == 'radio')
	{
		print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'profilefield[data]', $profilefield['data'], 10, 40, 0);
		print_yes_no_row($vbphrase['set_default_if_yes_first'], 'profilefield[def]', $profilefield['def']);
	}
	if ($vbulletin->GPC['type'] == 'checkbox')
	{
		print_input_row($vbphrase['limit_selection'], 'profilefield[size]', $profilefield['limit']);
		print_input_row($vbphrase['boxes_per_line'], 'profilefield[def]', $profilefield['perline']);
		if ($_REQUEST['do'] == 'add')
		{
			print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'profilefield[data]', '', 10, 40, 0);
		}
		else
		{
			print_label_row($vbphrase['fields'], '<input type="image" src="../' . $vbulletin->options['cleargifurl'] . '"><input type="submit" class="button" value="' . $vbphrase['modify'] . '" tabindex="1" name="modifyfields">');
		}
	}
	if ($vbulletin->GPC['type'] == 'select_multiple')
	{
		print_input_row($vbphrase['limit_selection'], 'profilefield[size]', $profilefield['limit']);
		print_input_row($vbphrase['box_height'], 'profilefield[height]', $profilefield['boxheight']);
		if ($_REQUEST['do'] == 'add')
		{
			print_textarea_row(construct_phrase($vbphrase['x_enter_the_options_that_the_user_can_choose_from'], $vbphrase['options']), 'profilefield[data]', '', 10);
		}
		else
		{
			print_label_row($vbphrase['fields'], '<input type="image" src="../' . $vbulletin->options['cleargifurl'] . '"><input type="submit" class="button" value="' . $vbphrase['modify'] . '" tabindex="1" name="modifyfields">');
		}
	}
	if ($_REQUEST['do'] == 'edit')
	{
		if ($vbulletin->GPC['type'] == 'input' OR $vbulletin->GPC['type'] == 'textarea')
		{
			if ($vbulletin->GPC['type'] == 'input')
			{
				$inputchecked = 'checked="checked"';
			}
			else
			{
				$textareachecked = 'checked="checked"';
			}
			print_label_row($vbphrase['profile_field_type'], "
				<label for=\"newtype_input\"><input type=\"radio\" name=\"newtype\" value=\"input\" id=\"newtype_input\" tabindex=\"1\" $inputchecked>" . $vbphrase['single_line_text_box'] . "</label><br />
				<label for=\"newtype_textarea\"><input type=\"radio\" name=\"newtype\" value=\"textarea\" id=\"newtype_textarea\" $textareachecked>" . $vbphrase['multiple_line_text_box'] . "</label>
			", '', 'top', 'newtype');
		}
		else if ($vbulletin->GPC['type'] == 'checkbox' OR $vbulletin->GPC['type'] == 'select_multiple')
		{
			if ($vbulletin->GPC['type'] == 'checkbox')
			{
				$checkboxchecked = 'checked="checked"';
			}
			else
			{
				$multiplechecked = 'checked="checked"';
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
	if ($vbulletin->GPC['type'] != 'textarea')
	{
		print_yes_no_row($vbphrase['show_on_members_list'], 'profilefield[memberlist]', $profilefield['memberlist']);
	}

	if ($vbulletin->GPC['type'] == 'select' OR $vbulletin->GPC['type'] == 'radio')
	{
		print_table_break();
		print_table_header($vbphrase['optional_input']);
		print_yes_no_row($vbphrase['allow_user_to_input_their_own_value_for_this_option'], 'profilefield[optional]', $profilefield['optional']);
		print_input_row($vbphrase['max_length_of_allowed_user_input'], 'profilefield[maxlength]', $profilefield['maxlength']);
		print_input_row($vbphrase['display_size'], 'profilefield[size]', $profilefield['size']);
	}
	if ($vbulletin->GPC['type'] != 'select_multiple' AND $vbulletin->GPC['type'] != 'checkbox')
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
	$vbulletin->input->clean_array_gpc('r', array(
		'id' => TYPE_UINT,
	));

	$boxdata = $db->query_first("
		SELECT data,type
		FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
	");
	$data = unserialize($boxdata['data']);
	foreach ($data AS $index => $value)
	{
		if ($index + 1 == $vbulletin->GPC['id'])
		{
			$oldfield = $value;
			break;
		}
	}

	print_form_header('profilefield', 'dorenamecheckbox');
	construct_hidden_code('profilefieldid', $vbulletin->GPC['profilefieldid']);
	construct_hidden_code('id', $vbulletin->GPC['id']);
	print_table_header($vbphrase['rename']);
	print_input_row($vbphrase['name'], 'newfield', $oldfield);
	print_submit_row($vbphrase['save']);

}

// ###################### Start Rename Checkbox Data #######################
if ($_POST['do'] == 'dorenamecheckbox')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'newfield' => TYPE_NOHTML,
		'id'       => TYPE_UINT
	));

	if (!empty($vbulletin->GPC['newfield']))
	{
		$boxdata = $db->query_first("
			SELECT data
			FROM " . TABLE_PREFIX . "profilefield
			WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
		");
		$data = unserialize($boxdata['data']);
		foreach ($data AS $index => $value)
		{
			if (strtolower($value) == strtolower($vbulletin->GPC['newfield']))
			{
				print_stop_message('this_is_already_option_named_x', $value);
			}
		}

		$index = $vbulletin->GPC['id'] - 1;
		$data["$index"] = $vbulletin->GPC['newfield'];

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "profilefield
			SET data = '" . $db->escape_string(serialize($data)) . "'
			WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
		");
	}
	else
	{
		print_stop_message('please_complete_required_fields');
	}

	define('CP_REDIRECT', "profilefield.php?do=modifycheckbox&profilefieldid=" . $vbulletin->GPC['profilefieldid']);
	print_stop_message('saved_option_x_successfully', $vbulletin->GPC['newfield']);
}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'deletecheckbox')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'id' => TYPE_UINT
	));

	print_form_header('profilefield', 'dodeletecheckbox');
	construct_hidden_code('profilefieldid', $vbulletin->GPC['profilefieldid']);
	construct_hidden_code('id', $vbulletin->GPC['id']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_user_profile_field']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

// ###################### Process Remove Checkbox Option #######################
if ($_POST['do'] == 'dodeletecheckbox')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'id' => TYPE_UINT
	));

	$boxdata = $db->query_first("
		SELECT title, data
		FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
	");
	$data = unserialize($boxdata['data']);

	$db->query_write("UPDATE " . TABLE_PREFIX . "userfield SET temp = field" . $vbulletin->GPC['profilefieldid']);

	foreach ($data AS $index => $value)
	{
		$index;
		$index2 = $index + 1;
		if ($index2 >= $vbulletin->GPC['id'])
		{
			if ($vbulletin->GPC['id'] == $index2)
			{
				build_profilefield_bitfields($vbulletin->GPC['profilefieldid'], $index2); // Delete this value
			}
			else
			{
				build_profilefield_bitfields($vbulletin->GPC['profilefieldid'], $index2, $index);
			}
			if ($index2 == sizeof($data))
			{
				unset($data["$index"]);
			}
			else
			{
				$data["$index"] = $data["$index2"];
			}
		}
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "userfield
		SET field" . $vbulletin->GPC['profilefieldid'] . " = temp,
		temp = ''
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "profilefield
		SET data = '" . $db->escape_string(serialize($data)) . "'
		WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
	");

	define('CP_REDIRECT', "profilefield.php?do=modifycheckbox&profilefieldid=" . $vbulletin->GPC['profilefieldid']);
	print_stop_message('deleted_option_successfully');
}

// ###################### Start Add Checkbox #######################
if ($_POST['do'] == 'addcheckbox')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'newfield'    => TYPE_NOHTML,
		'newfieldpos' => TYPE_UINT,
	));

	if (!empty($vbulletin->GPC['newfield']))
	{
		$boxdata = $db->query_first("
			SELECT data
			FROM " . TABLE_PREFIX . "profilefield
			WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
		");
		$data = unserialize($boxdata['data']);

		if (sizeof($data) >= 32)
		{
 			print_stop_message('too_many_profile_field_options', sizeof($data));
 		}

		foreach ($data AS $index => $value)
		{
			if (strtolower($value) == strtolower($vbulletin->GPC['newfield']))
			{
				print_stop_message('this_is_already_option_named_x', $value);
			}
		}

		$db->query_write("UPDATE " . TABLE_PREFIX . "userfield SET temp = field" . $vbulletin->GPC['profilefieldid']);

		for ($x = sizeof($data); $x >= 0; $x--)
		{
			if ($x > $vbulletin->GPC['newfieldpos'])
			{
				$data["$x"] = $data[$x - 1];
				build_profilefield_bitfields($vbulletin->GPC['profilefieldid'], $x, $x + 1);
			}
			else if ($x == $vbulletin->GPC['newfieldpos'])
			{
				$data["$x"] = $vbulletin->GPC['newfield'];
			}
		}

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "userfield
			SET field" . $vbulletin->GPC['profilefieldid'] . " = temp,
			temp = ''
		");

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "profilefield SET
			data = '" . $db->escape_string(serialize($data)) . "'
			WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
		");

		define('CP_REDIRECT', "profilefield.php?do=modifycheckbox&profilefieldid=" . $vbulletin->GPC['profilefieldid']);
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
	$vbulletin->input->clean_array_gpc('r', array(
		'direction' => TYPE_STR,
		'id'        => TYPE_UINT
	));

	$boxdata = $db->query_first("
		SELECT data
		FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
	");
	$data = unserialize($boxdata['data']);

	$db->query_write("UPDATE " . TABLE_PREFIX . "userfield SET temp = field" . $vbulletin->GPC['profilefieldid']);

	if ($vbulletin->GPC['direction'] == 'up')
	{
		build_bitwise_swap($vbulletin->GPC['profilefieldid'], $vbulletin->GPC['id'], $vbulletin->GPC['id'] - 1);
	}
	else
	{ // Down
		build_bitwise_swap($vbulletin->GPC['profilefieldid'], $vbulletin->GPC['id'], $vbulletin->GPC['id'] + 1);
	}

	foreach ($data AS $index => $value)
	{
		if ($index + 1 == $vbulletin->GPC['id'])
		{
			$temp = $data["$index"];
			if ($vbulletin->GPC['direction'] == 'up')
			{
				$data["$index"] = $data[strval($index - 1)];
				$data[strval($index - 1)] = $temp;
			}
			else

			{ // Down
				$data["$index"] = $data[strval($index + 1)];
				$data[strval($index + 1)] = $temp;
			}
			break;
		}
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "userfield
		SET field" . $vbulletin->GPC['profilefieldid'] . " = temp,
		temp = ''
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "profilefield
		SET data = '" . $db->escape_string(serialize($data)) . "'
		WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
	");

	$_REQUEST['do'] = 'modifycheckbox';

}

// ###################### Start Modify Checkbox Data #######################
if ($_REQUEST['do'] == 'modifycheckbox')
{

	$boxdata = $db->query_first("
		SELECT title, data, type
		FROM " . TABLE_PREFIX . "profilefield
		WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid'] . "
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
				$moveup = "<a href=\"profilefield.php?" . $vbulletin->session->vars['sessionurl'] . "profilefieldid=" . $vbulletin->GPC['profilefieldid'] . "&do=movecheckbox&direction=up&id=$index\"><img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/move_up.gif\" border=\"0\" /></a> ";
			}
			else
			{
				$moveup = '<img src="../' . $vbulletin->options['cleargifurl'] . '" width="11" border="0" alt="" /> ';
			}
			if ($index != sizeof($data))
			{
				$movedown = "<a href=\"profilefield.php?" . $vbulletin->session->vars['sessionurl'] . "profilefieldid=" . $vbulletin->GPC['profilefieldid'] . "&do=movecheckbox&direction=down&id=$index\"><img src=\"../cpstyles/" . $vbulletin->options['cpstylefolder'] . "/move_down.gif\" border=\"0\" /></a> ";
			}
			else
			{
				unset($movedown);
			}
			$output .= "<tr><td>$moveup$movedown</td><td>$value</td><td>".
			construct_link_code($vbphrase['rename'], "profilefield.php?do=renamecheckbox&profilefieldid=" . $vbulletin->GPC['profilefieldid'] . "&id=$index")
			."</td><td>".
			iif(sizeof($xxxdata) > 1, construct_link_code($vbphrase['move'], "profilefield.php?do=movecheckbox&profilefieldid=" . $vbulletin->GPC['profilefieldid'] . "&id=$index"), '')
			. "</td><td>".
			iif(sizeof($data) > 1, construct_link_code($vbphrase['delete'], "profilefield.php?do=deletecheckbox&profilefieldid=" . $vbulletin->GPC['profilefieldid'] . "&id=$index"), '')
			. "</td></tr>";
		}
		$output .= '</table>';
	}
	else
	{
		$output = "<p>" . construct_phrase($vbphrase['this_profile_fields_no_options'], $boxdata['type']) . "</p>";
	}

	print_form_header('', '');
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['user_profile_field'], construct_link_code($boxdata['title'], "profilefield.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;profilefieldid=" . $vbulletin->GPC['profilefieldid']), $vbulletin->GPC['profilefieldid']));
	print_table_break();
	print_table_header($vbphrase['modify']);
	print_description_row($output);
	print_table_footer();


	if (sizeof($data) < 32)
	{
		print_form_header('profilefield', 'addcheckbox');
		construct_hidden_code('profilefieldid', $vbulletin->GPC['profilefieldid']);
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

	print_form_header('profilefield', 'kill');
	construct_hidden_code('profilefieldid', $vbulletin->GPC['profilefieldid']);
	print_table_header($vbphrase['confirm_deletion']);
	print_description_row($vbphrase['are_you_sure_you_want_to_delete_this_user_profile_field']);
	print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);

}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{

	$db->query_write("DELETE FROM " . TABLE_PREFIX . "profilefield WHERE profilefieldid = " . $vbulletin->GPC['profilefieldid']);
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "userfield DROP field" . $vbulletin->GPC['profilefieldid']);
	$db->query_write("OPTIMIZE TABLE " . TABLE_PREFIX . "userfield");

	build_hiddenprofilefield_cache();

	define('CP_REDIRECT', 'profilefield.php?do=modify');
	print_stop_message('deleted_user_profile_field_successfully');
}

// ###################### Start modify #######################
if ($_REQUEST['do'] == 'modify')
{

	$profilefields = $db->query_read("
		SELECT profilefieldid, title, type, form, displayorder, IF(required=2, 0, required) AS required, editable, hidden, searchable, memberlist
		FROM " . TABLE_PREFIX . "profilefield
	");

	if ($db->num_rows($profilefields))
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

		while ($profilefield = $db->fetch_array($profilefields))
		{
			$fields["{$profilefield['form']}"]["{$profilefield['displayorder']}"]["{$profilefield['profilefieldid']}"] = $profilefield;
		}
		$db->free_result($profilefields);

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
					foreach ($profilefields AS $_profilefieldid => $profilefield)
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
							<td class=\"$bgclass\"><strong>$profilefield[title] <dfn>{$types["{$profilefield['type']}"]}</dfn></strong></td>
							<td class=\"$bgclass\">$options</td>
							<td class=\"$bgclass\">field$_profilefieldid</td>
							<td class=\"$bgclass\"><input type=\"text\" class=\"bginput\" name=\"order[$_profilefieldid]\" value=\"$profilefield[displayorder]\" size=\"5\" /></td>
							<td class=\"$bgclass\">" .
							construct_link_code($vbphrase['edit'], "profilefield.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;profilefieldid=$_profilefieldid") .
							construct_link_code($vbphrase['delete'], "profilefield.php?" . $vbulletin->session->vars['sessionurl'] . "do=remove&profilefieldid=$_profilefieldid") .
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
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14481 $
|| ####################################################################
\*======================================================================*/
?>