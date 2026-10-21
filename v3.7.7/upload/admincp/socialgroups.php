<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE & ~8192);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 31700 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('socialgroups', 'search');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_socialgroup_search.php');
require_once(DIR . '/includes/functions_socialgroup.php');

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'showform';
}

// Print the Header
print_cp_header($vbphrase['prune_social_groups']);

$vbulletin->input->clean_array_gpc('r', array(
	'userid'    => TYPE_UINT,
));

// #######################################################################
if ($_REQUEST['do'] == 'showform')
{
	print_form_header('socialgroups', 'search');

	print_table_header($vbphrase['search_social_groups']);

	print_input_row($vbphrase['key_words'], 'filtertext');
	print_input_row($vbphrase['members_greater_than'], 'members_gteq', '', true, 5);
	print_input_row($vbphrase['members_less_than'], 'members_lteq', '', true, 5);
	print_time_row($vbphrase['creation_date_is_before'], 'date_lteq', '', false);
	print_time_row($vbphrase['creation_date_is_after'], 'date_gteq', '', false);
	print_input_row($vbphrase['group_created_by'], 'creator');

	print_select_row($vbphrase['group_type'], 'type', array(
		''           => '',
		'public'     => $vbphrase['group_type_public'],
		'moderated'  => $vbphrase['group_type_moderated'],
		'inviteonly' => $vbphrase['group_type_inviteonly']
	));

	print_submit_row($vbphrase['search']);
	print_cp_footer();
}

// #######################################################################
if ($_REQUEST['do'] == 'groupsby' AND !empty($vbulletin->GPC['userid']))
{
	if (verify_id('user', $vbulletin->GPC['userid'], false))
	{
		$vbulletin->GPC['creatoruserid'] = $vbulletin->GPC['userid'];
		$_POST['do'] = 'search';
	}
	else
	{
		print_cp_message($vbphrase['invalid_username']);
	}
}

// #######################################################################
if ($_POST['do'] == 'search')
{
	$socialgroupsearch = new vB_SGSearch($vbulletin);

	$vbulletin->input->clean_array_gpc('p', array(
		'filtertext'    => TYPE_NOHTML,
		'members_lteq'  => TYPE_UINT,
		'members_gteq'  => TYPE_UINT,
		'date_gteq'     => TYPE_UNIXTIME,
		'date_lteq'     => TYPE_UNIXTIME,
		'creator'       => TYPE_NOHTML,
		'type'          => TYPE_NOHTML
	));

	if ($vbulletin->GPC['creator'] != '')
	{
		$user = $vbulletin->db->query_first_slave("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . $vbulletin->db->escape_string($vbulletin->GPC['creator']) . "'");
		if (!empty($user['userid']))
		{
			$vbulletin->GPC['creatoruserid'] = $user['userid'];
		}
		else
		{
			print_cp_message($vbphrase['invalid_username']);
		}
	}

	$filters = array();

	if (!empty($vbulletin->GPC['filtertext']))
	{
		$filters['text'] = $vbulletin->GPC['filtertext'];
	}

	if (!empty($vbulletin->GPC['date_lteq']))
	{
		$filters['date_lteq'] = $vbulletin->GPC['date_lteq'];
	}

	if (!empty($vbulletin->GPC['date_gteq']))
	{
		$filters['date_gteq'] = $vbulletin->GPC['date_gteq'];
	}

	if (!empty($vbulletin->GPC['members_lteq']))
	{
		$filters['members_lteq'] = $vbulletin->GPC['members_lteq'];
	}

	if (!empty($vbulletin->GPC['members_gteq']))
	{
		$filters['members_gteq'] = $vbulletin->GPC['members_gteq'];
	}

	if (!empty($vbulletin->GPC['creatoruserid']))
	{
		$filters['creator'] = $vbulletin->GPC['creatoruserid'];
	}

	if (!empty($vbulletin->GPC['type']))
	{
		$filters['type'] = $vbulletin->GPC['type'];
	}

	foreach ($filters AS $key => $value)
	{
		$socialgroupsearch->add($key, $value);
	}

	$groups = $socialgroupsearch->fetch_results();

	if (!empty($groups))
	{
		print_form_header('socialgroups','delete');
		print_table_header($vbphrase['search_results']);

		echo '
			<tr>
			<td class="thead"><input type="checkbox" name="allbox" id="cb_checkall" onclick="js_check_all(this.form)" /></td>
			<td width="100%" class="thead"><label for="cb_checkall">' . $vbphrase['check_uncheck_all'] . '</label></td>
			</tr>';

		foreach ($groups AS $group)
		{
			$group = prepare_socialgroup($group);

			$cell = '<span class="shade smallfont" style="float: ' . $stylevar['right'] . '; text-align: ' . $stylevar['right'] . ';">' . $vbphrase['group_desc_' . $group['type']] . '<br />' . construct_phrase($vbphrase['x_members'], $group['members']);

			if ($group['moderatedmembers'])
			{
				$cell .= '<br />' . construct_phrase($vbphrase['x_awaiting_moderation'], $group['moderatedmembers']);
			}

			$ownerlink = '../member.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $group['creatoruserid'];

			$cell .= '</span>
				<div style="text-align: ' . $stylevar['left'] . '"><a href="../group.php?' . $vbulletin->session->vars['sessionurl']. 'groupid=' . $group['groupid'] . '" target="group">' . $group['name'] . '</a></div>
				<div class="smallfont" style="text-align: ' . $stylevar['left'] . '">' . construct_phrase($vbphrase['group_created_by_x'], $ownerlink, $group['creatorusername']) . '</div>';

			if (!empty($group['description']))
			{
				$cell .= '<div style="text-align: ' . $stylevar['left'] . '">' . $group['description'] . '</div>';
			}

			print_cells_row(array(
				'<input type="checkbox" name="ids[' . $group['groupid'] . ']" />',
				$cell
			));

		}

		print_submit_row($vbphrase['delete_selected_groups']);
	}
	else
	{
		print_cp_message($vbphrase['no_groups_found']);
	}
}


// #######################################################################
if ($_POST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'ids' => TYPE_ARRAY_KEYS_INT
	));

	if (empty($vbulletin->GPC['ids']))
	{
		print_cp_message($vbphrase['you_did_not_select_any_groups']);
	}

	print_form_header('socialgroups','confirmdelete');
	print_table_header($vbphrase['confirm_deletion']);

	print_description_row(construct_phrase($vbphrase['are_you_sure_you_want_to_delete_x_groups'], sizeof($vbulletin->GPC['ids'])), false, 2, '', 'center');

	construct_hidden_code('ids', sign_client_string(serialize($vbulletin->GPC['ids'])));

	print_submit_row($vbphrase['yes'], 0, 2, $vbphrase['no']);
}


// #######################################################################
if ($_POST['do'] == 'confirmdelete')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'ids' => TYPE_NOCLEAN
	));

	$ids = @unserialize(verify_client_string($vbulletin->GPC['ids']));

	if (is_array($ids) AND !empty($ids))
	{
		print_form_header('socialgroups', '');
		print_table_header($vbphrase['deleting_groups']);

		$groups = $vbulletin->db->query_read("
			SELECT * FROM " . TABLE_PREFIX . "socialgroup
			WHERE groupid IN (" . implode(',', $ids) . ")
		");

		if ($vbulletin->db->num_rows($groups) == 0)
		{
			print_description_row($vbphrase['no_groups_found']);
		}

		while ($group = $vbulletin->db->fetch_array($groups))
		{
			$socialgroupdm = datamanager_init('SocialGroup', $vbulletin);

			print_description_row(construct_phrase($vbphrase['deleting_x'], $group['name']));

			$socialgroupdm->set_existing($group);
			$socialgroupdm->delete();

			unset($socialgroupdm);
		}
	}
	else
	{
		// This should never happen without playing with the URLs
		print_cp_message($vbphrase['no_groups_selected_or_invalid_input']);
	}

	print_table_footer();

	print_cp_redirect('socialgroups.php', 5);
}

// Print Footer
print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # SVN: $Revision: 31700 $
|| ####################################################################
\*======================================================================*/
?>