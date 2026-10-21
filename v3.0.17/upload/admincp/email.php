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
define('CVS_REVISION', '$RCSfile$ - $Revision: 9345 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('user', 'cpuser', 'messaging');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once('./includes/adminfunctions_profilefield.php');
require_once('./includes/adminfunctions_user.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminusers'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['email_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'start';
}

// *************************** Send a page of emails **********************
if ($_POST['do'] == 'dosendmail' OR $_POST['do'] == 'makelist')
{
	if (isset($_POST['serializeduser']))
	{
		$_POST['user'] = @unserialize($_POST['serializeduser']);
		$_POST['profile'] = @unserialize($_POST['serializedprofile']);
	}
	$condition = fetch_user_search_sql($_POST['user'], $_POST['profile']);

	if ($_POST['do'] == 'makelist')
	{
		$users = $DB_site->query("
			SELECT email
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
			WHERE $condition
				" . iif(!$_POST['user']['adminemail'], " AND (options & $_USEROPTIONS[adminemail])") . "
		");
		if ($DB_site->num_rows($users) > 0)
		{
			while ($user = $DB_site->fetch_array($users))
			{
				echo $user['email'] . $_POST['septext'];
				flush();
			}
		}
		else
		{
			print_stop_message('no_users_matched_your_query');
		}
	}
	else
	{

		$perpage = intval($_POST['perpage']);
		if (empty($perpage))
		{
			$perpage = 500;
		}
		$startat = intval($_POST['startat']);

		$counter = $DB_site->query_first("
			SELECT COUNT(*) AS total
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
			WHERE $condition
				" . iif(!$_POST['user']['adminemail'], " AND (options & $_USEROPTIONS[adminemail])") . "
		");
		if ($counter['total'] == 0)
		{
			print_stop_message('no_users_matched_your_query');
		}
		else
		{
			$users = $DB_site->query("
				SELECT user.userid,usergroupid,username,email,joindate
				FROM " . TABLE_PREFIX . "user AS user
				LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
				WHERE $condition
					" . iif(!$_POST['user']['adminemail'], " AND (options & $_USEROPTIONS[adminemail])") . "
				ORDER BY userid DESC
				LIMIT $startat, $perpage
			");
			if ($DB_site->num_rows($users))
			{

				$page = $startat / $perpage + 1;
				$totalpages = ceil($counter['total'] / $perpage);

				if (strpos($_POST['message'], '$activateid') OR strpos($_POST['message'], '$activatelink'))
				{
					$hasactivateid = 1;
				}
				else
				{
					$hasactivateid = 0;
				}

				echo '<p><b>' . $vbphrase['emailing'] . '<br />' . construct_phrase($vbphrase['showing_users_x_to_y_of_z'], vb_number_format($startat + 1), iif ($startat+$perpage > $counter['total'], vb_number_format($counter['total']), vb_number_format($startat + $perpage)), vb_number_format($counter['total'])) . '</b></p>';

				while ($user = $DB_site->fetch_array($users))
				{
					echo "$user[userid] - $user[username] .... \n";
					flush();

					$userid = $user['userid'];
					$sendmessage = $_POST['message'];
					$sendmessage = str_replace(
						array('$email', '$username', '$userid'),
						array($user['email'], $user['username'], $user['userid']),
						$_POST['message']
					);
					if ($hasactivateid)
					{
						if ($user['usergroupid'] == 3)
						{ // if in correct usergroup
							if (empty($user['activationid']))
							{ //none exists so create one
								$activate['activationid'] = vbrand(0, 100000000);
								$DB_site->query("
									INSERT INTO " . TABLE_PREFIX . "useractivation
										(userid, dateline, activationid, type, usergroupid)
									VALUES
										($user[userid], " . TIMENOW . ", $activate[activationid], 0, 2)
								");
							}
							else
							{
								$activate['activationid'] = vbrand(0, 100000000);
								$DB_site->query("
									UPDATE " . TABLE_PREFIX . "useractivation
									SET dateline = " . TIMENOW . ",
									activationid = $activate[activationid]
									WHERE userid = $user[userid] AND
									type = 0
								");
							}
							$activate['link'] = $vboptions['bburl'] . "/register.php?a=act&u=$userid&i=$activate[activationid]";
						}
						else
						{
							$activate = array();
						}

						$sendmessage = str_replace(
							array('$activateid', '$activatelink'),
							array($activate['activationid'], $activate['link']),
							$sendmessage
						);

					}
					$sendmessage = str_replace(
						array('$bburl', '$bbtitle'),
						array($vboptions['bburl'], $vboptions['bbtitle']),
						$sendmessage
					);

					if (!$_POST['test'])
					{
						echo $vbphrase['emailing']." \n";
						vbmail($user['email'], $_POST['subject'], $sendmessage, true, $_POST['from']);
					}
					else
					{
						echo $vbphrase['test'] . " ... \n";
					}

					echo $vbphrase['okay'] . "<br />\n";
					flush();

				}
				$_REQUEST['do'] = 'donext';
			}
			else
			{
				define('CP_REDIRECT', 'email.php?$session[sessionurl]');
				print_stop_message('emails_sent_successfully');
			}
		}
	}
}

// *************************** Link to next page of emails to send **********************
if ($_REQUEST['do'] == 'donext')
{

	//if ($page++ == $totalpages)
	//{
	//
	//	define('CP_REDIRECT', 'email.php?$session[sessionurl]');
	//	print_stop_message('emails_sent_successfully');
	//}
	//else
	//{
		$startat += $perpage;

		print_form_header('email', 'dosendmail');
		construct_hidden_code('test', $_POST['test']);
		construct_hidden_code('serializeduser', serialize($_POST['user']));
		construct_hidden_code('from', $_POST['from']);
		construct_hidden_code('subject', $_POST['subject']);
		construct_hidden_code('message', $_POST['message']);
		construct_hidden_code('startat', $startat);
		construct_hidden_code('perpage', $perpage);

		$profilefields = $DB_site->query("SELECT profilefieldid, title, type FROM " . TABLE_PREFIX . "profilefield");
		while ($profilefield = $DB_site->fetch_array($profilefields))
		{
			$varname = 'field' . $profilefield['profilefieldid'];
			if ($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple')
			{
				if (is_array($_POST[$varname]))
				{
					foreach ($_POST[$varname] AS $value)
					{
						construct_hidden_code("${varname}[]", $value);
					}
				}
			}
			else

			{
				construct_hidden_code($varname, $_POST[$varname]);
			}
		}

		print_submit_row($vbphrase['next_page'], 0);
	//}

}

// *************************** Main email form **********************
if ($_REQUEST['do'] == 'start' OR $_REQUEST['do'] == 'genlist')
{
?>
<script type="text/javascript">
function check_all_usergroups(formobj, toggle_status)
{
	for (var i = 0; i < formobj.elements.length; i++)
	{
		var elm = formobj.elements[i];
		if (elm.type == "checkbox" && elm.name == 'user[usergroupid][]')
		{
			elm.checked = toggle_status;
		}
	}
}
</script>
<?php
	if ($_REQUEST['do'] == 'start')
	{
		print_form_header('email', 'dosendmail');
		print_table_header($vbphrase['email_manager']);
		print_yes_no_row($vbphrase['test_email_only'], 'test', 0);
		print_input_row($vbphrase['email_to_send_at_once'], 'perpage', 500);
		print_input_row($vbphrase['from'], 'from', $vboptions['webmasteremail']);
		print_input_row($vbphrase['subject'], 'subject');
		print_textarea_row($vbphrase['message_email'], 'message', '', 10, 50);
		$text = $vbphrase['send'];

	}
	else
	{
		print_form_header('email', 'makelist');
		print_table_header($vbphrase['generate_mailing_list']);
		print_textarea_row($vbphrase['text_to_separate_addresses_by'], 'septext', ' ');
		$text = $vbphrase['go'];
	}

	print_table_break();
	print_table_header($vbphrase['search_criteria']);
	print_user_search_rows(true);

	print_table_break();
	print_submit_row($text);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9345 $
|| ####################################################################
\*======================================================================*/
?>