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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 25957 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('maintenance');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_file.php');
require_once(DIR . '/includes/functions_login.php');

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

print_cp_header($vbphrase['check_vulnerable_passwords']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = ($_POST['doreset'] ? 'reset' : 'check');
}

// checkable periods
$periods = array(
	'0' => $vbphrase['over_any_period'],
	'259200' => construct_phrase($vbphrase['over_x_days_ago'], 3),
	'604800' => $vbphrase['over_1_week_ago'],
	'1209600' => construct_phrase($vbphrase['over_x_weeks_ago'], 2),
	'1814400' => construct_phrase($vbphrase['over_x_weeks_ago'], 3),
	'2592000' => $vbphrase['over_1_month_ago'],
	'5270400' => construct_phrase($vbphrase['over_x_months_ago'], 2),
	'7862400' => construct_phrase($vbphrase['over_x_months_ago'], 3),
	'15724800' => construct_phrase($vbphrase['over_x_months_ago'], 6)
);

// input
$vbulletin->input->clean_array_gpc('p', array(
		'period'        => TYPE_UINT,
		'quantity'      => TYPE_UINT,
		'email'         => TYPE_NOHTML,
		'email_subject' => TYPE_NOHTML,
		'email_from'    => TYPE_NOHTML
));

// selected period
$period = $vbulletin->GPC['period'];

// count affected accounts
$total_affected = $vbulletin->db->query_first("
	SELECT COUNT(userid) AS total_affected 
	FROM " . TABLE_PREFIX . "user 
	WHERE password = MD5(CONCAT(MD5(username),salt)) " .   
	($period ? 'AND lastvisit < ' . (TIMENOW - $period) : '') . "
");
$total_affected = (!empty($total_affected) ? $total_affected['total_affected'] : 0);

// ########################################################################
if ($_POST['do'] == 'reset')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'lastuser'     => TYPE_UINT,
		'completed'     => TYPE_UINT,
		'email_errors'  => TYPE_BOOL,
		'reset_errors'  => TYPE_BOOL
	));
	
	$lastuser = $vbulletin->GPC['lastuser'];
	$completed = $vbulletin->GPC['completed'];
	$reset_errors = $vbulletin->GPC['reset_errors'];
	$email_errors = $vbulletin->GPC['email_errors'];
	
	if (empty($vbulletin->GPC['email_subject']) OR empty($vbulletin->GPC['email']) OR empty($vbulletin->GPC['email_from']))
	{
		print_stop_message('please_complete_required_fields');
	}
	
	if (false === strpos($vbulletin->GPC['email'], '{password}'))
	{
		print_stop_message('you_must_enter_the_password_token_into_the_message');
	}
	
	// select affected users
	$result = $vbulletin->db->query("
		SELECT userid 
		FROM " . TABLE_PREFIX . "user 
		WHERE password = MD5(CONCAT(MD5(username),salt)) " .   
		($period ? 'AND lastvisit < ' . (TIMENOW - $period) : '') . " 
		AND userid > $lastuser  
		LIMIT 0, " . $vbulletin->GPC['quantity'] . "
	");

	if ($total = $vbulletin->db->num_rows($result))
	{
		while ($user = $vbulletin->db->fetch_array($result))
		{
			// fetch their info
			$user = fetch_userinfo($user['userid']);
			
			// set last user processed
			$lastuser = $user['userid'];
			
			// make random password
			$newpassword = substr(md5(vbrand(0, 100000000)), 0, 8);
			
			// send mail to user
			$message = str_replace('{username}', $user['username'], $vbulletin->GPC['email']);
			$message = str_replace('{password}', $newpassword, $message);
			if (!vbmail($user['email'], $vbulletin->GPC['email_subject'], $message, true, $vbulletin->GPC['from']))
			{
				$email_errors = true;
				continue;
			}
			
			// reset the password
			$userdata = datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdata->set_existing($user);
			$userdata->set('password', $newpassword);
			$userdata->save();

			// check reset for errors
			if (sizeof($userdata->errors))
			{
				$reset_errors = true;
				continue;
			}
		}
		$vbulletin->db->free_result($result);
		unset($userdata);
		
		$completed = $completed + $total;
		$_POST['do'] = 'resetnext';
	}
	else
	{
		// display results
		print_table_start();
		print_table_header($vbphrase['passwords_reset']);
		print_description_row(construct_phrase($vbphrase['x_passwords_were_reset'], $completed), false, 2, '', 'center');
		
		if ($reset_errors)
		{
			print_description_row($vbphrase['some_errors_occured_while_resetting_passwords']);
		}
		
		if ($email_errors)
		{
			print_description_row($vbphrase['some_errors_occured_while_sending_emails']);
		}
		
		print_table_footer();
		
		// display check form for resubmit
		$_REQUEST['do'] = 'check';
	}
}

// ########################################################################
if ($_POST['do'] == 'resetnext')
{
	print_form_header('passwordcheck', 'reset', false, true, 'cpform_reset');
	print_description_row(construct_phrase($vbphrase['x_accounts_processed'], $completed), false, 2, '', 'center');
	construct_hidden_code('email_errors', $email_errors);
	construct_hidden_code('reset_errors', $reset_errors);
	construct_hidden_code('email', $vbulletin->GPC['email'], false);
	construct_hidden_code('email_subject', $vbulletin->GPC['email_subject'], false);
	construct_hidden_code('email_from', $vbulletin->GPC['email_from'], false);
	construct_hidden_code('quantity', $vbulletin->GPC['quantity']);
	construct_hidden_code('completed', $completed);
	construct_hidden_code('lastuser', $lastuser);
	print_submit_row($vbphrase['continue'], 0);
	print_table_footer();

	?>
	<script type="text/javascript">
	<!--
	if (document.cpform_reset)
	{
		function send_submit()
		{
			var submits = YAHOO.util.Dom.getElementsBy(
				function(element) { return (element.type == "submit") },
				"input", this
			);
			var submit_button;

			for (var i = 0; i < submits.length; i++)
			{
				submit_button = submits[i];
				submit_button.disabled = true;
				setTimeout(function() { submit_button.disabled = false; }, 10000);
			}

			return false;
		}

		YAHOO.util.Event.on(document.cpform_reset, 'submit', send_submit);
		send_submit.call(document.cpform_reset);
		document.cpform_reset.submit();
	}
	// -->
	</script>
	<?php
	vbflush();
}
// ########################################################################
if ($_REQUEST['do'] == 'check')
{
	// postback or default values
	$email = construct_phrase(($vbulletin->GPC['email'] ? $vbulletin->GPC['email'] : $vbphrase['vulnerable_password_reset_email']), $vbulletin->options['bbtitle'], $vbulletin->options['bburl']);
	$email_subject = construct_phrase(($vbulletin->GPC['email_subject'] ? $vbulletin->GPC['email_subject'] : $vbphrase['vulnerable_password_reset_email_subject']), $vbulletin->options['bbtitle']);
	$email_from = ($vbulletin->GPC['email_from'] ? $vbulletin->GPC['email_from'] : $vbulletin->options['webmasteremail']);
	$quantity = ($vbulletin->GPC['quantity'] ? $vbulletin->GPC['quantity'] : min($total_affected, 100));
	
	// display notice and check options
	print_form_header('passwordcheck', 'check');
	print_table_header($vbphrase['check_vulnerable_passwords']);
	print_description_row($vbphrase['password_check_notice']);
	print_select_row($vbphrase['check_accounts_with_last_activity'], 'period', $periods, $period);
	print_description_row('<strong>' . ($period ? construct_phrase($vbphrase['affected_accounts_that_were_last_active_x_y'], strtolower($periods[$period]), $total_affected) : construct_phrase($vbphrase['affected_accounts_x'], $total_affected)) . '</strong>');
	construct_hidden_code('email', $email, false);
	construct_hidden_code('email_subject', $email_subject, false);
	construct_hidden_code('email_from', $email_from, false);
	construct_hidden_code('quantity', $quantity);
	print_submit_row($vbphrase['check_affected_accounts'], false);
	print_table_footer();

	// display reset options
	print_form_header('passwordcheck', 'reset');
	print_table_header($vbphrase['reset_vulnerable_passwords']);
	print_column_style_code(array('width: 40%','width: 60%'));
	print_select_row($vbphrase['reset_accounts_with_last_activity'], 'period', $periods, $period);
	print_input_row($vbphrase['email_to_send_at_once'], 'quantity', $quantity);
	print_input_row($vbphrase['email_subject'], 'email_subject', $email_subject, false, 80);
	print_input_row($vbphrase['email_from'], 'email_from', $email_from, false, 80);
	print_textarea_row($vbphrase['password_vulnerability_email_message_label'], 'email', $email, 40, 80);
	print_submit_row($vbphrase['reset_vulnerable_passwords'], false);
	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 25957 $
|| ####################################################################
\*======================================================================*/