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
define('CVS_REVISION', '$RCSfile$ - $Revision: 8893 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('sql', 'user', 'cpuser');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
log_admin_action(iif($_POST['query'], "query = '" . htmlspecialchars_uni($_POST['query'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['execute_sql_query']);

if (!$debug)
{
	$userids = explode(',', str_replace(' ', '', $canrunqueries));
	if (!in_array($bbuserinfo['userid'], $userids))
	{
		print_stop_message('no_permission_queries');
	}
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// define auto queries
$queryoptions = array(
	'-1'  => '',
	$vbphrase['all_users'] => array(
		'10'  => $vbphrase['yes'] . ' - ' . $vbphrase['invisible_mode'],
		'20'  => $vbphrase['yes'] . ' - ' . $vbphrase['allow_vcard_download'],
		'30'  => $vbphrase['yes'] . ' - ' . $vbphrase['receive_admin_emails'],
		'40'  => $vbphrase['yes'] . ' - ' . $vbphrase['display_email'],
		'50'  => $vbphrase['yes'] . ' - ' . $vbphrase['receive_private_messages'],
		'60'  => $vbphrase['yes'] . ' - ' . $vbphrase['send_notification_email_when_a_private_message_is_received'],
		'70'  => $vbphrase['yes'] . ' - ' . $vbphrase['pop_up_notification_box_when_a_private_message_is_received'],
		'80'  => $vbphrase['no'] . ' - ' . $vbphrase['invisible_mode'],
		'90'  => $vbphrase['no'] . ' - ' . $vbphrase['allow_vcard_download'],
		'100' => $vbphrase['no'] . ' - ' . $vbphrase['receive_admin_emails'],
		'110' => $vbphrase['no'] . ' - ' . $vbphrase['display_email'],
		'120' => $vbphrase['no'] . ' - ' . $vbphrase['receive_private_messages'],
		'130' => $vbphrase['no'] . ' - ' . $vbphrase['send_notification_email_when_a_private_message_is_received'],
		'140' => $vbphrase['no'] . ' - ' . $vbphrase['pop_up_notification_box_when_a_private_message_is_received'],
		'150' => $vbphrase['on'] . ' - ' . $vbphrase['display_signatures'],
		'160' => $vbphrase['on'] . ' - ' . $vbphrase['display_avatars'],
		'170' => $vbphrase['on'] . ' - ' . $vbphrase['display_images'],
		'175' => $vbphrase['on'] . ' - ' . $vbphrase['display_reputation'],
		'180' => $vbphrase['off'] . ' - ' . $vbphrase['display_signatures'],
		'190' => $vbphrase['off'] . ' - ' . $vbphrase['display_avatars'],
		'200' => $vbphrase['off'] . ' - ' . $vbphrase['display_images'],
		'205' => $vbphrase['off'] . ' - ' . $vbphrase['display_reputation'],
		'210' => $vbphrase['subscribe_choice_none'],
		'220' => $vbphrase['subscribe_choice_0'],
		'230' => $vbphrase['subscribe_choice_1'],
		'240' => $vbphrase['subscribe_choice_2'],
		'250' => $vbphrase['subscribe_choice_3'],
		'270' => $vbphrase['thread_display_mode'] . ' - ' . $vbphrase['linear'],
		'280' => $vbphrase['thread_display_mode'] . ' - ' . $vbphrase['threaded'],
		'290' => $vbphrase['thread_display_mode'] . ' - ' . $vbphrase['hybrid'],
		'260' => $vbphrase['posts'] . ' - ' . $vbphrase['oldest_first'],
		'265' => $vbphrase['posts'] . ' - ' . $vbphrase['newest_first'],
		'300' => $vbphrase['do_not_show_editor_toolbar'],
		'310' => $vbphrase['show_standard_editor_toolbar'],
		'320' => $vbphrase['show_enhanced_editor_toolbar'],
	),
);

//	$vbphrase['all_forums'] => array(
//	),
//);

// ##################### START DO QUERY #####################

if ($_POST['do'] == 'doquery')
{
	globalize($_POST, array(
		'query' => STR,
		'autoquery' => INT,
		'perpage' => INT,
		'page' => INT,
		'confirmquery' => INT
	));

	if ($page < 1)
	{
		$page = 1;
	}

	if (!$confirmquery)
	{
		if (!$autoquery AND !$query)
		{
			print_stop_message('please_complete_required_fields');
		}

		if ($autoquery)
		{
			switch($autoquery)
			{
				case 10:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[invisible] WHERE NOT (options & $_USEROPTIONS[invisible])";
					break;
				case 20:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[showvcard] WHERE NOT (options & $_USEROPTIONS[showvcard])";
					break;
				case 30:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[adminemail] WHERE NOT (options & $_USEROPTIONS[adminemail])";
					break;
				case 40:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[showemail] WHERE NOT (options & $_USEROPTIONS[showemail])";
					break;
				case 50:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[receivepm] WHERE NOT (options & $_USEROPTIONS[receivepm])";
					break;
				case 60:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[emailonpm] WHERE NOT (options & $_USEROPTIONS[emailonpm])";
					break;
				case 70:
					$query = "UPDATE " . TABLE_PREFIX . "user SET pmpopup = 1";
					break;
				case 80:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[invisible] WHERE options & $_USEROPTIONS[invisible]";
					break;
				case 90:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[showvcard] WHERE options & $_USEROPTIONS[showvcard]";
					break;
				case 100:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[adminemail] WHERE options & $_USEROPTIONS[adminemail]";
					break;
				case 110:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[showemail] WHERE options & $_USEROPTIONS[showemail]";
					break;
				case 120:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[receivepm] WHERE options & $_USEROPTIONS[receivepm]";
					break;
				case 130:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[emailonpm] WHERE options & $_USEROPTIONS[emailonpm]";
					break;
				case 140:
					$query = "UPDATE " . TABLE_PREFIX . "user SET pmpopup = 0";
					break;
				case 150:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[showsignatures] WHERE NOT (options & $_USEROPTIONS[showsignatures])";
					break;
				case 160:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[showavatars] WHERE NOT (options & $_USEROPTIONS[showavatars])";
					break;
				case 170:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[showimages] WHERE NOT (options & $_USEROPTIONS[showimages])";
					break;
				case 175:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[showreputation] WHERE NOT (options & $_USEROPTIONS[showreputation])";
					break;
				case 180:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[showsignatures] WHERE options & $_USEROPTIONS[showsignatures]";
					break;
				case 190:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[showavatars] WHERE options & $_USEROPTIONS[showavatars]";
					break;
				case 200:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[showimages] WHERE options & $_USEROPTIONS[showimages]";
					break;
				case 205:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[showreputation] WHERE options & $_USEROPTIONS[showreputation]";
					break;
				case 210:
					$query = "UPDATE " . TABLE_PREFIX . "user SET autosubscribe = -1";
					break;
				case 220:
					$query = "UPDATE " . TABLE_PREFIX . "user SET autosubscribe = 0";
					break;
				case 230:
					$query = "UPDATE " . TABLE_PREFIX . "user SET autosubscribe = 1";
					break;
				case 240:
					$query = "UPDATE " . TABLE_PREFIX . "user SET autosubscribe = 2";
					break;
				case 250:
					$query = "UPDATE " . TABLE_PREFIX . "user SET autosubscribe = 3";
					break;
				case 260:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options - $_USEROPTIONS[postorder] WHERE options & $_USEROPTIONS[postorder]";
					break;
				case 265:
					$query = "UPDATE " . TABLE_PREFIX . "user SET options = options + $_USEROPTIONS[postorder] WHERE NOT (options & $_USEROPTIONS[postorder])";
					break;
				case 270:
					$query = "UPDATE " . TABLE_PREFIX . "user SET threadedmode = 0";
					break;
				case 280:
					$query = "UPDATE " . TABLE_PREFIX . "user SET threadedmode = 1";
					break;
				case 290:
					$query = "UPDATE " . TABLE_PREFIX . "user SET threadedmode = 2";
					break;
				case 300:
					$query = "UPDATE " . TABLE_PREFIX . "user SET showvbcode = 0";
					break;
				case 310:
					$query = "UPDATE " . TABLE_PREFIX . "user SET showvbcode = 1";
					break;
				case 320:
					$query = "UPDATE " . TABLE_PREFIX . "user SET showvbcode = 2";
					break;
				default:

			}
		}
	}

	if (substr($query, -1) == ';')
	{
		$query = substr($query, 0, -1);
	}
	$DB_site->reporterror = 0;

	print_form_header('', '');
	print_table_header($vbphrase['query'] . iif($autoquery>0, ' (' . $queryoptions["$vbphrase[all_users]"]["$autoquery"] . ')'));
	print_description_row('<code>' . nl2br(htmlspecialchars_uni($query)) . '</code>', 0, 2, '');
	print_description_row(construct_button_code($vbphrase['restart'], "queries.php?$session[url]"), 0, 2, 'tfoot', 'center');
	print_table_footer();

	preg_match("#^([A-Z]+) #si", $query, $regs);
	$querytype = strtoupper($regs[1]);

	switch ($querytype)
	{
		// queries that perform data changes **********************************************************
		case 'UPDATE':
		case 'INSERT':
		case 'REPLACE':
		case 'DELETE':
		case 'ALTER':
		case 'CREATE':
		case 'DROP':
			if (!$confirmquery)
			{
				print_form_header('queries', 'doquery');
				construct_hidden_code('do', 'doquery');
				construct_hidden_code('query', $query);
				construct_hidden_code('perpage', $perpage);
				construct_hidden_code('confirmquery', 1);
				print_table_header($vbphrase['confirm_query_execution']);
				print_description_row($vbphrase['query_may_modify_database']);
				print_submit_row($vbphrase['continue'], false, 2, $vbphrase['go_back']);
			}
			else
			{
				$DB_site->query($query);
				print_form_header('queries', 'doquery');
				print_table_header($vbphrase['vbulletin_message']);
				if ($errornum = $DB_site->geterrno())
				{
					print_description_row(construct_phrase($vbphrase['an_error_occured_while_attempting_to_run_your_query'], $errornum, nl2br(htmlspecialchars_uni($DB_site->geterrdesc()))));
				}
				else
				{
					print_description_row(construct_phrase($vbphrase['affected_rows'], vb_number_format($DB_site->affected_rows())));
				}
				print_table_footer();
			}
			break;

		// EXPLAIN and SELECT **********************************************************
		case 'EXPLAIN':
		case 'SELECT':
		default:
			$query_mod = preg_replace('# LIMIT ([0-9,]+)#i', '', $query);
			$counter = $DB_site->query($query);

			print_form_header('queries', 'doquery', 0, 1, 'queryform');
			construct_hidden_code('do', 'doquery');
			construct_hidden_code('query', $query);
			construct_hidden_code('perpage', $perpage);
			if ($errornum = $DB_site->geterrno())
			{
				print_table_header($vbphrase['vbulletin_message']);
				print_description_row(construct_phrase($vbphrase['an_error_occured_while_attempting_to_run_your_query'], $errornum, nl2br(htmlspecialchars_uni($DB_site->geterrdesc()))));
				$extras = '';
			}
			else
			{
				$numrows = $DB_site->num_rows($counter);
				$numpages = ceil($numrows / $perpage);
				if ($page == -1)
				{
					$page = $numpages;
				}
				$startat = ($page - 1) * $perpage;
				if ($querytype == 'SELECT')
				{
					$query_mod = "$query_mod LIMIT $startat, $perpage";
				}
				else
				{
					$query_mod = $query;
				}
				$result = $DB_site->query($query_mod);

				$colcount = $DB_site->num_fields($result);
				print_table_header("$vbphrase[results]: " . vb_number_format($numrows) . ', ' . construct_phrase($vbphrase['page_x_of_y'], $page, $numpages), $colcount);
				if ($numrows)
				{
					$collist = array();
					for ($i = 0; $i < $colcount; $i++)
					{
						$collist[] = $DB_site->field_name($result, $i);
					}
					print_cells_row($collist, 1);

					while ($record = $DB_site->fetch_array($result))
					{
						foreach ($record AS $colname => $value)
						{
							$record["$colname"] = htmlspecialchars_uni($value);
						}
						print_cells_row($record, 0, '', -$colcount);
					}

					if ($numpages > 1)
					{
						$extras = '<b>' . $vbphrase['page'] . '</b> <select name="page" tabindex="1" onchange="document.queryform.submit();" class="bginput">';
						for ($i = 1; $i <= $numpages; $i++)
						{
							$selected = iif($i == $page, HTML_SELECTED);
							$extras .= "<option value=\"$i\" $selected>$i</option>";
						}
						$extras .= '</select> <input type="submit" class="button" tabindex="1" value="' . $vbphrase['go'] . '" accesskey="s" />';
					}
					else
					{
						$extras = '';
					}
				}
				else
				{
					$extras = '';
				}
			}
			print_table_footer($colcount, $extras);
			break;
	}
}

// ##################### START MODIFY #####################
if ($_REQUEST['do'] == 'modify')
{
	print_form_header('queries', 'doquery');
	print_table_header($vbphrase['execute_sql_query']);
	print_select_row($vbphrase['auto_query'], 'autoquery', $queryoptions, -1);
	print_textarea_row($vbphrase['manual_query'], 'query', '', 10, 55);
	print_input_row($vbphrase['results_to_show_per_page'], 'perpage', 20);
	print_submit_row($vbphrase['continue']);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 8893 $
|| ####################################################################
\*======================================================================*/
?>