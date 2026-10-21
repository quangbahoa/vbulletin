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
define('CVS_REVISION', '$RCSfile$ - $Revision: 14370 $');
define('NOZIP', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('diagnostic');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminmaintain'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
log_admin_action();

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// ###################### Start maketestresult #######################
function print_diagnostic_test_result($status, $reasons = array(), $exit = 1)
{
	// $status values = -1: indeterminate; 0: failed; 1: passed
	// $reasons a list of reasons why the test passed/failed
	// $exit values = 0: continue execution; 1: stop here
	global $vbphrase;

	print_form_header('', '');

	print_table_header($vbphrase['results']);

	if (is_array($reasons))
	{
		foreach ($reasons AS $reason)
		{
			print_description_row($reason);
		}
	}
	else if (!empty($reasons))

	{
		print_description_row($reasons);
	}

	print_table_footer();

	if ($exit == 1)
	{
		print_cp_footer();
	}
}

print_cp_header($vbphrase['diagnostics']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

// ###################### Start upload test #######################
if ($_POST['do'] == 'doupload')
{
	// additional checks should be added with testing on other OS's (Windows doesn't handle safe_mode the same as Linux).

	$vbulletin->input->clean_array_gpc('f', array(
		'attachfile' => TYPE_FILE
	));

	print_form_header('', '');
	print_table_header($vbphrase['pertinent_php_settings']);

	$file_uploads = ini_get('file_uploads');
	print_label_row('file_uploads:', iif($file_uploads == 1, $vbphrase['on'], $vbphrase['off']));

	print_label_row('open_basedir:', iif($open_basedir = ini_get('open_basedir'), $open_basedir, '<i>' . $vbphrase['none'] . '</i>'));
	$safe_mode = ini_get('safe_mode');
	print_label_row('safe_mode:', iif($safe_mode == 1, 'On', 'Off'));
	print_label_row('upload_tmp_dir:', iif($upload_tmp_dir = ini_get('upload_tmp_dir'), $upload_tmp_dir, '<i>' . $vbphrase['none'] . '</i>'));
	require_once(DIR . '/includes/functions_file.php');
	print_label_row('upload_max_filesize:', vb_number_format(fetch_max_upload_size(), 1, true));
	print_table_footer();

	if ($vbulletin->superglobal_size['_FILES'] == 0)
	{
		if ($file_uploads === 0)
		{ // don't match NULL
			print_diagnostic_test_result(0, $vbphrase['file_upload_setting_off']);
		}
		else
		{
			print_diagnostic_test_result(0, $vbphrase['unknown_error']);
		}
	}

	if (empty($vbulletin->GPC['attachfile']['tmp_name']))
	{
		print_diagnostic_test_result(0, construct_phrase($vbphrase['no_file_uploaded_and_no_local_file_found'], $vbphrase['test_cannot_continue']));
	}

	if (!is_uploaded_file($vbulletin->GPC['attachfile']['tmp_name']))
	{
		print_diagnostic_test_result(0, construct_phrase($vbphrase['unable_to_find_attached_file'], $vbulletin->GPC['attachfile']['tmp_name'], $vbphrase['test_cannot_continue']));
	}

	$fp = @fopen($vbulletin->GPC['attachfile']['tmp_name'], 'rb');
	if (!empty($fp))
	{
		@fclose($fp);
		if ($vbulletin->options['safeupload'])
		{
			$safeaddntl = $vbphrase['turn_safe_mode_option_off'];
		}
		else
		{
			$safeaddntl = '';
		}
		print_diagnostic_test_result(1, $vbphrase['no_errors_occured_opening_upload']. ' ' . $safeaddntl);
	} // we had problems opening the file as is, but we need to run the other tests before dying

	if ($vbulletin->options['safeupload'])
	{
		if ($vbulletin->options['tmppath'] == '')
		{
			print_diagnostic_test_result(0, $vbphrase['safe_mode_enabled_no_tmp_dir']);
		}
		else if (!is_dir($vbulletin->options['tmppath']))
		{
			print_diagnostic_test_result(0, construct_phrase($vbphrase['safe_mode_dir_not_dir'], $vbulletin->options['tmppath']));
		}
		else if (!is_writable($vbulletin->options['tmppath']))
		{
			print_diagnostic_test_result(0, construct_phrase($vbphrase['safe_mode_not_writeable'], $vbulletin->options['tmppath']));
		}
		$copyto = $vbulletin->options['tmppath'] . '/' . $vbulletin->session->fetch_sessionhash();
		if ($result = @move_uploaded_file($vbulletin->GPC['attachfile']['tmp_name'], $copyto))
		{
			$fp = @fopen($copyto , 'rb');
			if (!empty($fp))
			{
				@fclose($fp);
				print_diagnostic_test_result(1, $vbphrase['file_copied_to_tmp_dir_now_readable']);
			}
			else
			{
				print_diagnostic_test_result(0, $vbphrase['file_copied_to_tmp_dir_now_unreadable']);
			}
			@unlink($copyto);
		}
		else
		{
			print_diagnostic_test_result(0, construct_phrase($vbphrase['unable_to_copy_attached_file'], $copyto));
		}
	}

	if ($open_basedir)
	{
		print_diagnostic_test_result(0, construct_phrase($vbphrase['open_basedir_in_effect'], $open_basedir));
	}

	print_diagnostic_test_result(-1, $vbphrase['test_indeterminate_contact_host']);
}

// ###################### Start mail test #######################
if ($_POST['do'] == 'domail')
{

	$vbulletin->input->clean_array_gpc('p', array(
		'emailaddress' => TYPE_STR,
	));

	print_form_header('', '');
	if ($vbulletin->options['use_smtp'])
	{
		print_table_header($vbphrase['pertinent_smtp_settings']);
		print_label_row('SMTP:', (!empty($vbulletin->options['smtp_tls']) ? 'tls://' : '') . $vbulletin->options['smtp_host'] . ':' . (!empty($vbulletin->options['smtp_port']) ? intval($vbulletin->options['smtp_port']) : 25));
		print_label_row($vbphrase['smtp_username'], $vbulletin->options['smtp_user']);
	}
	else
	{
		print_table_header($vbphrase['pertinent_php_settings']);
		print_label_row('SMTP:', iif($SMTP = @ini_get('SMTP'), $SMTP, '<i>' . $vbphrase['none'] . '</i>'));
		print_label_row('sendmail_from:', iif($sendmail_from = @ini_get('sendmail_from'), $sendmail_from, '<i>' . $vbphrase['none'] . '</i>'));
		print_label_row('sendmail_path:', iif($sendmail_path = @ini_get('sendmail_path'), $sendmail_path, '<i>' . $vbphrase['none'] . '</i>'));
	}
	print_table_footer();

	$emailaddress = $vbulletin->GPC['emailaddress'];

	if (empty($emailaddress))
	{
		print_diagnostic_test_result(0, $vbphrase['please_complete_required_fields']);
	}
	if (!is_valid_email($emailaddress))
	{
		print_diagnostic_test_result(0, $vbphrase['invalid_email_specified']);
	}

	$subject = ($vbulletin->options['needfromemail'] ? $vbphrase['vbulletin_email_test_withf'] : $vbphrase['vbulletin_email_test']);
	$message = construct_phrase($vbphrase['vbulletin_email_test_msg'], $vbulletin->options['bbtitle']);

	if (!class_exists('vB_Mail'))
	{
		require_once(DIR . '/includes/class_mail.php');
	}

	if ($vbulletin->options['use_smtp'])
	{
		$mail =& new vB_SmtpMail($vbulletin);
	}
	else
	{
		$mail =& new vB_Mail($vbulletin);
	}

	$mail->set_debug(true);
	$mail->start($emailaddress, $subject, $message, $vbulletin->options['webmasteremail']);

	// error handling
	@ini_set('display_errors', true);
	if (strpos(@ini_get('disable_functions'), 'ob_start') !== false)
	{
		// alternate method in case OB is disabled; probably not as fool proof
		@ini_set('track_errors', true);
		$oldlevel = error_reporting(0);
	}
	else
	{
		ob_start();
	}

	$mailreturn = $mail->send();

	if (strpos(@ini_get('disable_functions'), 'ob_start') !== false)
	{
		error_reporting($oldlevel);
		$errors = $php_errormsg;
	}
	else
	{
		$errors = ob_get_contents();
		ob_end_clean();
	}
	// end error handling

	if (!$mailreturn OR $errors)
	{
		$results = array();
		if (!$mailreturn)
		{
			$results[] = $vbphrase['mail_function_returned_error'];
		}
		if ($errors)
		{
			$results[] = $vbphrase['mail_function_errors_returned_were'].'<br /><br />' . $errors;
		}
		if (!$vbulletin->options['use_smtp'])
		{
			$results[] = $vbphrase['check_mail_server_configured_correctly'];
		}
		print_diagnostic_test_result(0, $results);
	}
	else
	{
		print_diagnostic_test_result(1, construct_phrase($vbphrase['email_sent_check_shortly'], $emailaddress));
	}
}

// ###################### Start system information #######################
if ($_POST['do'] == 'dosysinfo')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'type' => TYPE_STR
	));

	switch ($vbulletin->GPC['type'])
	{
		case 'mysql_vars':
		case 'mysql_status':
			print_form_header('', '');
			if ($vbulletin->GPC['type'] == 'mysql_vars')
			{
				// use MASTER connection
				$result = $db->query_write('SHOW VARIABLES');
			}
			else if ($vbulletin->GPC['type'] == 'mysql_status')
			{
				$result = $db->query_write('SHOW STATUS');
			}

			$colcount = $db->num_fields($result);
			if ($vbulletin->GPC['type'] == 'mysql_vars')
			{
				print_table_header($vbphrase['mysql_variables'], $colcount);
			}
			else if ($vbulletin->GPC['type'] == 'mysql_status')
			{
				print_table_header($vbphrase['mysql_status'], $colcount);
			}

			$collist = array();
			for ($i = 0; $i < $colcount; $i++)
			{
				$collist[] = $db->field_name($result, $i);
			}
			print_cells_row($collist, 1);
			while ($row = $db->fetch_array($result))
			{
				print_cells_row($row);
			}

			print_table_footer();
			break;
		default:
			$mysqlversion = $db->query_first("SELECT VERSION() AS version");
			if ($mysqlversion['version'] < '3.23')
			{
				print_stop_message('table_status_not_available', $mysqlversion['version']);
			}

			print_form_header('', '');
			$result = $db->query_write("SHOW TABLE STATUS");
			$colcount = $db->num_fields($result);
			print_table_header($vbphrase['table_status'], $colcount);
			$collist = array();
			for ($i = 0; $i < $colcount; $i++)
			{
				$collist[] = $db->field_name($result, $i);
			}
			print_cells_row($collist, 1);
			while ($row = $db->fetch_array($result))
			{
				print_cells_row($row);
			}

			print_table_footer();
			break;
	}
}

if ($_POST['do'] == 'doversion')
{
	$extensions = array('.php', '.xml', '.js', '.htc', '.css', '.new', '.style', '.sh', '.htm', '.html', '.txt');

	$handle = @opendir(DIR  . '/includes');
	if ($handle)
	{
		$md5_sums_array = array();
		$scanned_md5_files = array();
		$ignored_files = array('/includes/config.php', '/includes/datastore_cache.php', '/includes/config.php.new', '/install/install.php');
		$ignored_dirs = array('/cpstyles/');

		while ($file = readdir($handle))
		{
			if (preg_match('#^md5_sums_.+$#siU', $file, $match))
			{
				include(DIR . "/includes/$match[0]");
				$relative_md5_sums = array();

				// need to fix up directories which are configurable
				foreach ($md5_sums AS $key => $val)
				{
					$admin_dir = strpos($key, '/admincp');
					$mod_dir = strpos($key, '/modcp');

					// not using str_replace since it could be greedy and replace all values of admincp / modcp
					if ($vbulletin->config['Misc']['admincpdir'] !== 'admincp' AND $admin_dir === 0)
					{
						$key = substr_replace($key, $vbulletin->config['Misc']['admincpdir'], 1, strlen('admincp'));
					}
					else if ($vbulletin->config['Misc']['modcpdir'] !== 'modcp' AND $mod_dir === 0)
					{
						$key = substr_replace($key, $vbulletin->config['Misc']['modcpdir'], 1, strlen('modcp'));
					}
					$relative_md5_sums["$key"] = $val;
				}

				$scanned_md5_files[] = $match[0];
				$ignored_files[] = "/includes/$match[0]";
				$md5_sums_array = array_merge_recursive($relative_md5_sums, $md5_sums_array);
			}
		}
		closedir($handle);

		if (empty($md5_sums_array) OR !in_array('md5_sums_vbulletin.php', $scanned_md5_files))
		{
			print_stop_message('unable_to_read_md5_sums');
		}

		$errors = array();
		$file_count = array();

		foreach ($md5_sums_array AS $directory => $md5_sums)
		{
			foreach ($ignored_dirs AS $ignored_dir)
			{
				if (substr($directory, 0, strlen($ignored_dir)) == $ignored_dir)
				{
					continue 2;
				}
			}

			$handle = @opendir(DIR . $directory);
			if ($handle)
			{
				$file_count["$directory"] = 0;

				while ($file = readdir($handle))
				{
					if (is_file(DIR . "$directory/$file") AND !in_array("$directory/$file", $ignored_files) AND substr($file, 0, 1) != '.' AND in_array($ext = '.' . file_extension($file), $extensions))
					{
						$file_count["$directory"]++;

						if ($file == 'index.html' AND trim(file_get_contents(DIR . $directory . '/' . $file)) == '')
						{
							continue;
						}

						// check if file has a record in the MD5 sums array
						if ($md5_sums["$file"])
						{
							$check_md5 = true;

							if (in_array($ext, array('.php', '.js')) AND $fp = @fopen(DIR . $directory . '/' . $file, 'rb'))
							{
								$linenumber = 0;
								$finished = false;
								$matches = array();

								while ($line = fgets($fp, 4096) AND $linenumber <= 10)
								{
									if ($ext == '.php' AND preg_match('#\|\| \# vBulletin (.*?) -#si', $line, $matches))
									{
										$finished = true;
									}
									else if (preg_match('#^\|\| \# vBulletin (.*)$#si', $line, $matches))
									{
										$finished = true;
									}

									$linenumber++;

									if ($finished)
									{
										if (!in_array(trim($matches[1]), array($vbulletin->options['templateversion'], '3.5.8')))
										{
											$check_md5 = false;
											$errors["$directory"]["$file"][] = construct_phrase($vbphrase['file_version_mismatch_x_expected_y'], $matches[1], $vbulletin->options['templateversion']);
										}
										break;
									}
								}
								fclose($fp);
							}

							if ($check_md5 AND md5(str_replace("\r\n", "\n", file_get_contents(DIR . $directory . '/' . $file))) != $md5_sums["$file"])
							{
								$errors["$directory"]["$file"][] = $vbphrase['file_contents_mismatch'];
							}
						}
						else
						{
							// file is not listed in the md5_sums files
							$errors["$directory"]["$file"][] = $vbphrase['file_not_recognized'];
						}

						$md5_sums["$file"] = true;
					}
				}

				// now check for any files listed in the md5 sum files that we have not found
				foreach ($md5_sums AS $file => $value)
				{
					if ($value !== true AND !in_array("$directory/$file", $ignored_files))
					{
						$errors["$directory"]["$file"][] = $vbphrase['file_not_found'];
					}
				}

				closedir($handle);
			}
		}

		print_form_header('diagnostic', 'doversion');
		print_table_header($vbphrase['suspect_file_versions']);

		ksort($file_count);

		foreach ($file_count AS $directory => $file_count)
		{
			print_description_row("<div style=\"float:$stylevar[right]\">" . construct_phrase($vbphrase['scanned_x_files'], $file_count) . "</div>.$directory", 0, 2, 'thead');

			if (is_array($errors["$directory"]))
			{
				foreach ($errors["$directory"] AS $file => $error)
				{
					print_label_row("<strong>$file</strong>", implode('<br />', $error));
				}
			}
		}

		print_submit_row($vbphrase['repeat_process'], false);
	}
	else
	{
		trigger_error(construct_phrase($vbphrase['unable_to_open_x'], 'includes/*'), E_USER_ERROR);
	}
}

if ($_GET['do'] == 'payments')
{
	$results = array();
	$query = 'cmd=_notify-validate';
	// paypal cURL
	if (function_exists('curl_init') AND $ch = curl_init())
	{
		curl_setopt($ch, CURLOPT_URL, 'http://www.paypal.com/cgi-bin/webscr');
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDSIZE, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
		curl_close($ch);
		$results['Paypal']['cURL'] = ($result == 'INVALID');
	}
	else
	{
		$results['Paypal']['cURL'] = false;
	}
	// paypal streams
	$results['Paypal']['streams'] = false;
	$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
	$header .= "Host: www.paypal.com\r\n";
	$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$header .= "Content-Length: " . strlen($query) . "\r\n\r\n";
	if ($fp = @fsockopen('www.paypal.com', 80, $errno, $errstr, 30))
	{
		socket_set_timeout($fp, 30);
		fwrite($fp, $header . $query);
		while (!feof($fp))
		{
			$result = fgets($fp, 1024);
			if (strcmp($result, 'INVALID') == 0)
			{
				$results['Paypal']['streams'] = true;
				break;
			}
		}
		fclose($fp);
	}

	$query = '';
	// nochex cURL
	if (function_exists('curl_init') AND $ch = curl_init())
	{
		curl_setopt($ch, CURLOPT_URL, 'https://www.nochex.com/nochex.dll/apc/apc');
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_SSLVERSION, 2);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$result = curl_exec($ch);
		curl_close($ch);
		$results['NOCHEX']['cURL'] = ($result == 'DECLINED');
	}
	else
	{
		$results['NOCHEX']['cURL'] = false;
	}
	// nochex streams
	$results['NOCHEX']['streams'] = false;
	if (PHP_VERSION >= '4.3.0' AND function_exists('openssl_open'))
	{
		$context = stream_context_create();

		$header = "POST /nochex.dll/apc/apc HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($query) . "\r\n\r\n";

		if ($fp = @fsockopen('ssl://www.nochex.com', 443))
		{
			fwrite($fp, $header . $query);
			error_reporting(0);
			do
			{
				$result = fread($fp, 1024);
				if (strlen($result) == 0 OR strcmp($result, 'DECLINED') == 0)
				{
					break;
				}
			} while (true);
			error_reporting(E_ALL & ~E_NOTICE);
			fclose($fp);
			$results['NOCHEX']['streams'] = ($result == 'DECLINED');
		}
	}
	// got us some results time to make it into something usable
	print_form_header('', '');
	print_table_header($vbphrase['server_communication']);
	foreach ($results AS $processor => $result)
	{
		print_description_row($processor, 0, 2, 'thead', 'center');
		print_label_row('cURL', iif($result['cURL'], $vbphrase['pass'], $vbphrase['fail']));
		print_label_row($vbphrase['streams'], iif($result['streams'], $vbphrase['pass'], $vbphrase['fail']));
	}
	print_table_footer();
}

// ###################### Start options list #######################
if ($_REQUEST['do'] == 'list')
{
	print_form_header('diagnostic', 'doupload', 1);
	print_table_header($vbphrase['upload']);
	print_description_row($vbphrase['upload_test_desc']);
	print_upload_row($vbphrase['filename'], 'attachfile');
	print_submit_row($vbphrase['upload']);

	print_form_header('diagnostic', 'domail');
	print_table_header($vbphrase['email']);
	print_description_row($vbphrase['email_test_explained']);
	print_input_row($vbphrase['email'], 'emailaddress');
	print_submit_row($vbphrase['send']);

	print_form_header('diagnostic', 'doversion');
	print_table_header($vbphrase['suspect_file_versions']);
	print_description_row(construct_phrase($vbphrase['file_versions_explained'], $vbulletin->options['templateversion']));
	print_submit_row($vbphrase['submit']);

	print_form_header('diagnostic', 'dosysinfo');
	print_table_header($vbphrase['system_information']);
	print_description_row($vbphrase['server_information_desc']);
	$selectopts = array(
		'mysql_vars' => $vbphrase['mysql_variables'],
		'mysql_status' => $vbphrase['mysql_status'],
		'table_status' => $vbphrase['table_status']
	);
	$mysqlversion = $db->query_first("SELECT VERSION() AS version");
	if ($mysqlversion['version'] < '3.23')
	{
		unset($selectopts['table_status']);
	}
	print_select_row($vbphrase['view'], 'type', $selectopts);
	print_submit_row($vbphrase['submit']);
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14370 $
|| ####################################################################
\*======================================================================*/
?>
