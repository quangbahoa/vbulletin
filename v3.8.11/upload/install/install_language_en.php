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

// moved from installcore to here
$stylevar = array(
	'textdirection' => 'ltr',
	'left' => 'left',
	'right' => 'right',
	'languagecode' => 'en',
	'charset' => 'ISO-8859-1'
);

$authenticate_phrases['install_title'] = 'Installer';
$authenticate_phrases['new_installation'] = 'New Installation';
$authenticate_phrases['enter_system'] = 'Enter Install System';
$authenticate_phrases['enter_cust_num'] = 'Please Enter Your Customer Number';
$authenticate_phrases['customer_number'] = 'Customer Number';
$authenticate_phrases['cust_num_explanation'] = 'This is the number with which you log in to the vBulletin.com Members\' Area';
$authenticate_phrases['cust_num_success'] = 'Customer number entered successfully.';
$authenticate_phrases['redirecting'] = 'Redirecting...';

$phrasetype['global'] = 'GLOBAL';
$phrasetype['cpglobal'] = 'Control Panel Global';
$phrasetype['cppermission'] = 'Permissions';
$phrasetype['forum'] = 'Forum-Related';
$phrasetype['calendar'] = 'Calendar';
$phrasetype['attachment_image'] = 'Attachment / Image';
$phrasetype['style'] = 'Style Tools';
$phrasetype['logging'] = 'Logging Tools';
$phrasetype['cphome'] = 'Control Panel Home Pages';
$phrasetype['promotion'] = 'Promotion Tools';
$phrasetype['user'] = 'User Tools (global)';
$phrasetype['help_faq'] = 'FAQ  / Help Management';
$phrasetype['sql'] = 'SQL Tools';
$phrasetype['subscription'] = 'Subscription Tools';
$phrasetype['language'] = 'Language Tools';
$phrasetype['bbcode'] = 'BB Code Tools';
$phrasetype['stats'] = 'Statistics Tools';
$phrasetype['diagnostics'] = 'Diagnostic Tools';
$phrasetype['maintenance'] = 'Maintenance Tools';
$phrasetype['profile'] = 'Profile Field Tools';
$phrasetype['cprofilefield'] = 'Custom Profile Fields';
$phrasetype['thread'] = 'Thread Tools';
$phrasetype['timezone'] = 'Timezones';
$phrasetype['banning'] = 'Banning Tools';
$phrasetype['reputation'] = 'Reputation';
$phrasetype['wol'] = 'Who\\\'s Online';
$phrasetype['threadmanage'] = 'Thread Management';
$phrasetype['pm'] = 'Private Messaging';
$phrasetype['cpuser'] = 'Control Panel User Management';
$phrasetype['register'] = 'Register';
$phrasetype['accessmask'] = 'Access Masks';
$phrasetype['cron'] = 'Scheduled Tasks';
$phrasetype['moderator'] = 'Moderators';
$phrasetype['cpoption'] = 'Control Panel Options';
$phrasetype['cprank'] = 'Control Panel User Ranks';
$phrasetype['cpusergroup'] = 'Control Panel User Groups';
$phrasetype['holiday'] = 'Holidays';
$phrasetype['posting'] = 'Posting';
$phrasetype['poll'] = 'Polls';
$phrasetype['fronthelp'] = 'Frontend FAQ/Help';
$phrasetype['search'] = 'Searching';
$phrasetype['showthread'] = 'Show Thread';
$phrasetype['postbit'] = 'Postbit';
$phrasetype['forumdisplay'] = 'Forum Display';
$phrasetype['messaging'] = 'Messaging';
$phrasetype['inlinemod'] = 'Inline Moderation';
$phrasetype['plugins'] = 'Plugin System';
$phrasetype['front_end_error'] = 'Error Messages';
$phrasetype['front_end_redirect'] = 'Front-End Redirect Messages';
$phrasetype['email_body'] = 'Email Body Text';
$phrasetype['email_subj'] = 'Email Subject Text';
$phrasetype['vbulletin_settings'] = 'vBulletin Settings';
$phrasetype['cp_help'] = 'Control Panel Help Text';
$phrasetype['faq_title'] = 'FAQ Title';
$phrasetype['faq_text'] = 'FAQ Text';
$phrasetype['stop_message'] = 'Control Panel Stop Message';
$phrasetype['reputationlevel'] = 'Reputation Levels';
$phrasetype['infraction'] = 'User Infractions';
$phrasetype['infractionlevel'] = 'User Infraction Levels';
$phrasetype['notice'] = 'Notices';
$phrasetype['prefix'] = 'Thread Prefixes';
$phrasetype['prefixadmin'] = 'Thread Prefixes (Admin)';
$phrasetype['album'] = 'Albums';
$phrasetype['hvquestion'] = 'Human Verification Questions';
$phrasetype['socialgroups'] = 'Social Groups';

#####################################
# custom phrases
#####################################

$customphrases['cprofilefield']['field1_title'] = 'Biography';
$customphrases['cprofilefield']['field1_desc']  = 'A few details about yourself';
$customphrases['cprofilefield']['field2_title'] = 'Location';
$customphrases['cprofilefield']['field2_desc']  = 'Where you live';
$customphrases['cprofilefield']['field3_title'] = 'Interests';
$customphrases['cprofilefield']['field3_desc']  = 'Your hobbies, etc';
$customphrases['cprofilefield']['field4_title'] = 'Occupation';
$customphrases['cprofilefield']['field4_desc']  = 'Your job';

$customphrases['reputationlevel']['reputation1']  = 'is infamous around these parts';
$customphrases['reputationlevel']['reputation2']  = 'can only hope to improve';
$customphrases['reputationlevel']['reputation3']  = 'has a little shameless behaviour in the past';
$customphrases['reputationlevel']['reputation4']  = 'is an unknown quantity at this point';
$customphrases['reputationlevel']['reputation5']  = 'is on a distinguished road';
$customphrases['reputationlevel']['reputation6']  = 'will become famous soon enough';
$customphrases['reputationlevel']['reputation7']  = 'has a spectacular aura about';
$customphrases['reputationlevel']['reputation8']  = 'is a jewel in the rough';
$customphrases['reputationlevel']['reputation9']  = 'is just really nice';
$customphrases['reputationlevel']['reputation10'] = 'is a glorious beacon of light';
$customphrases['reputationlevel']['reputation11'] = 'is a name known to all';
$customphrases['reputationlevel']['reputation12'] = 'is a splendid one to behold';
$customphrases['reputationlevel']['reputation13'] = 'has much to be proud of';
$customphrases['reputationlevel']['reputation14'] = 'has a brilliant future';
$customphrases['reputationlevel']['reputation15'] = 'has a reputation beyond repute';

$customphrases['infractionlevel']['infractionlevel1_title'] = 'Spammed Advertisements';
$customphrases['infractionlevel']['infractionlevel2_title'] = 'Insulted Other Member(s)';
$customphrases['infractionlevel']['infractionlevel3_title'] = 'Signature Rule Violation';
$customphrases['infractionlevel']['infractionlevel4_title'] = 'Inappropriate Language';

#####################################
# phrases for import systems
#####################################
$vbphrase['importing_language'] = 'Importing Language';
$vbphrase['importing_style'] = 'Importing Style';
$vbphrase['importing_admin_help'] = 'Importing Admin Help';
$vbphrase['importing_settings'] = 'Importing Setting';
$vbphrase['please_wait'] = 'Please Wait';
$vbphrase['language'] = 'Language';
$vbphrase['master_language'] = 'Master Language';
$vbphrase['admin_help'] = 'Admin Help';
$vbphrase['style'] = 'Style';
$vbphrase['styles'] = 'Styles';
$vbphrase['settings'] = 'Settings';
$vbphrase['master_style'] = 'MASTER STYLE';
$vbphrase['templates'] = 'Templates';
$vbphrase['css'] = 'CSS';
$vbphrase['stylevars'] = 'Stylevars';
$vbphrase['replacement_variables'] = 'Replacement Variables';
$vbphrase['controls'] = 'Controls';
$vbphrase['rebuild_style_information'] = 'Rebuild Style Information';
$vbphrase['updating_style_information_for_each_style'] = 'Updating style information for each style';
$vbphrase['updating_styles_with_no_parents'] = 'Updating style sets with no parent information';
$vbphrase['updated_x_styles'] = 'Updated %1$s Styles';
$vbphrase['no_styles_needed_updating'] = 'No Styles Needed Updating';
$vbphrase['yes'] = 'Yes';
$vbphrase['no'] = 'No';

#####################################
# global upgrade phrases
#####################################
$vbphrase['refresh'] = 'Refresh';
$vbphrase['vbulletin_message'] = 'vBulletin Message';
$vbphrase['create_table'] = 'Creating %1$s table';
$vbphrase['remove_table'] = 'Removing %1$s table';
$vbphrase['alter_table'] = 'Altering %1$s table';
$vbphrase['update_table'] = 'Updating %1$s table';
$vbphrase['upgrade_start_message'] = "<p>This script will update your vBulletin installation to version <b>" . VERSION . "</b></p>\n<p>Press the 'Next Step' button to proceed.</p>";
$vbphrase['upgrade_wrong_version'] = "<p>Your vBulletin version does not appear to match with the version for which this script was created (version <b>" . PREV_VERSION . "</b>).</p>\n<p>Please ensure that you are attempting to run the correct script.</p>\n<p>If you are sure this is the script you would like to run, <a href=\"" . THIS_SCRIPT . "?step=1\">click here</a>.</p>";
$vbphrase['file_not_found'] = 'Uh oh, ./install/%1$s doesn\'t appear to exist!';
$vbphrase['importing_file'] = 'Importing %1$s';
$vbphrase['ok'] = 'Okay';
$vbphrase['query_took'] = 'Query took %1$s seconds to execute.';
$vbphrase['done'] = 'Done';
$vbphrase['proceed'] = 'Proceed';
$vbphrase['reset'] = 'Reset';
$vbphrase['vbulletin_copyright'] = 'vBulletin v' . VERSION . ', Copyright &copy;2000 - 2023, vBulletin Solutions Inc.';
$vbphrase['vbulletin_copyright_orig'] = $vbphrase['vbulletin_copyright'];
$vbphrase['xml_error_x_at_line_y'] = 'XML Error: %1$s at Line %2$s';
$vbphrase['default_data_type'] = 'Inserting default data into %1$s';
$vbphrase['processing_complete_proceed'] = 'Processing Complete - Proceed';
#####################################
# upgradecore phrases
#####################################

$installcore_phrases['php_version_too_old'] = 'This version of vBulletin requires PHP version 5.6.0 or greater. Your PHP is version ' . PHP_VERSION . ', please ask your host to upgrade.';
$installcore_phrases['mysql_version_too_old'] = 'This version of vBulletin requires MySQL version 5.5.8 or greater. Your MySQL is version %1$s, please ask your host to upgrade.';
$installcore_phrases['need_xml'] = 'vBulletin ' . VERSION . ' requires that the XML functions in PHP be available. Please ask your host to enable this.';
$installcore_phrases['need_mysql'] = 'vBulletin ' . VERSION . ' requires that the MySQL functions in PHP be available. Please ask your host to enable this.';
$installcore_phrases['need_config_file'] = 'Please make sure you have entered the values in to config.php.new and renamed the file to config.php.';
$installcore_phrases['step_x_of_y'] = ' (Step %1$d of %2$d)';
$installcore_phrases['vb3_install_script'] = 'vBulletin ' . VERSION . ' Install Script';
$installcore_phrases['may_take_some_time'] = '(Please be patient as some parts may take some time)';
$installcore_phrases['step_title'] = 'Step %1$d) %2$s';
$installcore_phrases['batch_complete'] = 'Batch complete! Click the button on the right if you are not redirected automatically.';
$installcore_phrases['next_batch'] = ' Next Batch';
$installcore_phrases['next_step'] = 'Next Step (%1$d/%2$d)';
$installcore_phrases['click_button_to_proceed'] = 'Click the button on the right to proceed.';
$installcore_phrases['page_x_of_y'] = 'Page %1$d of %2$d';
$installcore_phrases['eaccelerator_not_supported'] = 'eAccelerator is no longer supported, try using APCu.';
$installcore_phrases['apcu_too_old_5'] = 'Your server is running a version of <b>APCu</b> that is incompatible with this version of vBulletin. Please upgrade APC to version 4.0.11 or newer.';
$installcore_phrases['apcu_too_old_7'] = 'Your server is running a version of <b>APCu</b> that is incompatible with this version of vBulletin. Please upgrade APC to version 5.1.5 or newer.';
$installcore_phrases['mmcache_not_supported'] = 'Turck MMCache is obsolete and not supported, try using APCu.';
$installcore_phrases['dbname_is_mysql'] = 'The database name specified in <em>includes/config.php</em> as <code>$config[\'Database\'][\'dbname\']</code> must not be <strong>mysql</strong> as this is a reserved database name.<br />Execution has been halted to prevent possible damage.';

#####################################
# install.php phrases
#####################################
$install_phrases['steps'] = array(
	1  => 'Verify Configuration',
	2  => 'Connect to the database',
	3  => 'Creating Tables',
	4  => 'Altering Tables',
	5  => 'Inserting Default Data',
	6  => 'Importing Language',
	7  => 'Importing Style Information',
	8  => 'Importing Admin Help',
	9  => 'Obtain Some Default Settings',
	10 => 'Import Default Settings',
	11 => 'Obtain User Data',
	12 => 'Setup Default Data',
	13 => 'Install Complete'
);
$install_phrases['welcome'] = '<p style="font-size:10pt"><b>Welcome to vBulletin version 3.8</b></p>
	<p>You are about to perform an install.</p>
	<p>Clicking the <b>[Next Step]</b> button will begin the installation process on your database.</p>
	<p>In order to prevent possible browser crashes during this script, we strongly recommend that you disable any additional toolbars you may be using on your browser, such as the <b>Google</b> toolbar etc.</p>';
$install_phrases['turck'] = '<p><strong>Turck MMCache</strong> has been detected on your server.  <strong>Turck MMCache</strong> is not supported in recent versions of PHP and can cause problems with vBulletin. We recommend that it be disabled.</p>';
$install_phrases['cant_find_config'] = 'We were unable to locate the \'includes/config.php\' file, please confirm that this file exists.';
$install_phrases['cant_read_config'] = 'We were unable to read the \'includes/config.php\' file, please check the permissions.';
$install_phrases['config_exists'] = 'Config file exists and is readable.';
$install_phrases['attach_to_db'] = 'Attempting to attach to database';
$install_phrases['no_db_found_will_create'] = 'No Database found, attempting to create.';
$install_phrases['attempt_to_connect_again'] = 'Attempting to connect again.';
$install_phrases['database_functions_not_detected'] = 'Selected database type \'%1$s\' was not detected within your compilation of PHP.';
$install_phrases['unable_to_create_db'] = 'Unable to create database, please confirm the database name within the \'includes/config.php\' file or ask your host to create a database.';
$install_phrases['database_creation_successful'] = 'Database creation successful!';
$install_phrases['connect_failed'] = 'Connect failed: unexpected error from the database.';
$install_phrases['db_error_num'] = 'Error number: %1$s';
$install_phrases['db_error_desc'] = 'Error description: %1$s';
$install_phrases['check_dbserver'] = 'Please ensure that the database and server is correctly configured and try again.';
$install_phrases['connection_succeeded'] = 'Connection succeeded! The database already exists.';
$install_phrases['vb_installed_maybe_upgrade'] = 'You have already installed vBulletin; do you wish to <a href="upgrade.php">upgrade</a>?';
$install_phrases['wish_to_empty_db'] = 'Click here if you would like to see options for clearing the existing contents of your database.';
$install_phrases['no_connect_permission'] = 'The database has failed to connect because you do not have permission to connect to the server. Please confirm the values entered in the \'includes/config.php\' file.';
$install_phrases['reset_database'] = 'Reset Database';
$install_phrases['delete_tables_instructions'] = '<p>The following is a list of all tables found in your database. Tables that are recognized as part of vBulletin have been selected for you. There may be other tables listed that were not recognized - these will be highlighted in the list.</p>
<p style="font-size:12pt">All selected tables and their contents will be <strong>permanently and irreversibly deleted</strong> from the database when the <em>Delete Selected Tables</em> button is clicked.</p>
<p><a href="install.php?step=2">Click here if you would like to return to the install process without deleting any tables.</a></p>
<p>vBulletin and vBulletin Solutions Inc. can accept no responsibility for any loss of data incurred as a result of deleting database tables.</p>';
$install_phrases['select_deselect_all_tables'] = 'Select / Deselect All Tables';
$install_phrases['delete_selected_tables'] = 'Delete Selected Tables';
$install_phrases['resetting_db'] = 'Resetting database...';
$install_phrases['succeeded'] = 'succeeded';
$install_phrases['script_reported_errors'] = 'The script reported errors in the installation of the tables. Only continue if you are sure that they are not serious.';
$install_phrases['errors_were'] = 'The errors were:';
$install_phrases['tables_setup'] = 'Tables set up successfully.';
$install_phrases['general_settings'] = 'General Settings';
$install_phrases['bbtitle'] = '<b>BB Title</b> <dfn>Title of board. Appears in the title of every page.</dfn>';
$install_phrases['hometitle'] = '<b>Homepage Title</b> <dfn>Name of your homepage. Appears at the bottom of every page.</dfn>';
$install_phrases['bburl'] = '<b>BB URL</b> <dfn>URL (with no final "/") of the BB. For example, <em>http://www.example.com/forums</em></dfn>';
$install_phrases['homeurl'] = '<b>Home URL</b> <dfn>URL of your home page. Appears at the bottom of every page.</dfn>';
$install_phrases['webmasteremail'] = '<b>Webmaster email address</b> <dfn>Email address of the webmaster.</dfn>';
$install_phrases['cookiepath'] = '<b>Cookie Path</b> <dfn>The path to which the cookie is saved. If you run more than one forum on the same domain, it will be necessary to set this to the individual directories of the forums. Otherwise, just leave it as / .<br /><br />Suggested valid values for Cookie Path are available in the drop-down menu opposite. If you have a good reason to want a different setting, tick the checkbox and enter the desired value in the box provided.<br /><br />Please note that your path should <b>always</b> end in a forward-slash; for example \'/forums/\', \'/vbulletin/\' etc.<br /><br /><span class="modlasttendays">Entering an invalid setting can leave you unable to login to your forum.</span></dfn>';
$install_phrases['cookiedomain'] = '<b>Cookie Domain</b> <dfn>This option sets the domain on which the cookie is active. The most common reason to change this setting is that you have two different urls to your forum, i.e. example.com and forums.example.com.  To allow users to stay logged into the forum if they visit via either url, you would set this to <b>.example.com</b> (note the domain begins with a <b>dot</b>.<br /><br />Suggested valid values for Cookie Domain are available in the drop-down menu opposite. If you have a good reason to want a different setting, tick the checkbox and enter the desired value in the box provided.<br /><br /><span class="modlasttendays">You most likely want to leave this setting blank as entering an invalid setting can leave you unable to login to your forum.</span></dfn>';
$install_phrases['suggested_settings'] = 'Suggested Settings';
$install_phrases['custom_setting'] = 'Custom Setting';
$install_phrases['use_custom_setting'] = 'Use Custom Setting (Specify Below)';
$install_phrases['blank'] = '(blank)';
$install_phrases['fill_in_for_admin_account'] = 'Please fill in the form below to setup an administrator account';
$install_phrases['username'] = 'User Name';
$install_phrases['password'] = 'Password';
$install_phrases['confirm_password'] = 'Confirm Password';
$install_phrases['email_address'] = 'Email Address';
$install_phrases['complete_all_data'] = 'You failed to enter all data.<br /><br />Please click the \'Next Step\' button to go back and enter the details.';
$install_phrases['password_not_match'] = 'The \'Password\' and \'Confirm Password\' fields do not match!<br /><br />Please click the \'Next Step\' button to go back and correct this.';
$install_phrases['admin_added'] = 'Administrator Added';
$install_phrases['install_complete'] = '<p>You have now successfully installed vBulletin 3.<br />
	<br />
	<font size="+1"><b>YOU MUST DELETE THE FOLLOWING FILES BEFORE CONTINUING:</b><br />
	install/install.php</font><br />
	<br />
	When you have done this, You may now proceed to your control panel.
	<br />
	The control panel can be found <b><a href="../%1$s/index.php">here</a></b>';

$install_phrases['alter_table_type_x'] = 'Changing ' . TABLE_PREFIX . '%1$s to a %2$s type';
$install_phrases['default_calendar'] = 'Default Calendar';
$install_phrases['category_title'] = 'Main Category';
$install_phrases['category_desc'] = 'Main Category Description';
$install_phrases['forum_title'] = 'Main Forum';
$install_phrases['forum_desc'] = 'Main Forum Description';
$install_phrases['posticon_1'] = 'Post';
$install_phrases['posticon_2'] = 'Arrow';
$install_phrases['posticon_3'] = 'Lightbulb';
$install_phrases['posticon_4'] = 'Exclamation';
$install_phrases['posticon_5'] = 'Question';
$install_phrases['posticon_6'] = 'Cool';
$install_phrases['posticon_7'] = 'Smile';
$install_phrases['posticon_8'] = 'Angry';
$install_phrases['posticon_9'] = 'Unhappy';
$install_phrases['posticon_10'] = 'Talking';
$install_phrases['posticon_11'] = 'Red face';
$install_phrases['posticon_12'] = 'Wink';
$install_phrases['posticon_13'] = 'Thumbs down';
$install_phrases['posticon_14'] = 'Thumbs up';
$install_phrases['generic_avatars'] = 'Generic Avatars';
$install_phrases['generic_smilies'] = 'Generic Smilies';
$install_phrases['generic_icons'] = 'Generic Icons';
// should be the values that vbulletin-language.xml contains
$install_phrases['master_language_title'] = 'English (US)';
$install_phrases['master_language_langcode'] = 'en';
$install_phrases['master_language_charset'] = 'ISO-8859-1';
$install_phrases['master_language_decimalsep'] = '.';
$install_phrases['master_language_thousandsep'] = ',';
$install_phrases['default_style'] = 'Default Style';
$install_phrases['smilie_smile'] = 'Smile';
$install_phrases['smilie_embarrass'] = 'Embarrassment';
$install_phrases['smilie_grin'] = 'Big Grin';
$install_phrases['smilie_wink'] = 'Wink';
$install_phrases['smilie_tongue'] = 'Stick Out Tongue';
$install_phrases['smilie_cool'] = 'Cool';
$install_phrases['smilie_roll'] = 'Roll Eyes (Sarcastic)';
$install_phrases['smilie_mad'] = 'Mad';
$install_phrases['smilie_eek'] = 'EEK!';
$install_phrases['smilie_confused'] = 'Confused';
$install_phrases['smilie_frown'] = 'Frown';
$install_phrases['socialgroups_uncategorized'] = 'Uncategorized';
$install_phrases['socialgroups_uncategorized_description'] = 'Uncategorized Social Groups';
$install_phrases['usergroup_guest_title'] = 'Unregistered / Not Logged In';
$install_phrases['usergroup_guest_usertitle'] = 'Guest';
$install_phrases['usergroup_registered_title'] = 'Registered Users';
$install_phrases['usergroup_activation_title'] = 'Users Awaiting Email Confirmation';
$install_phrases['usergroup_coppa_title'] = '(COPPA) Users Awaiting Moderation';
$install_phrases['usergroup_super_title'] = 'Super Moderators';
$install_phrases['usergroup_super_usertitle'] = 'Super Moderator';
$install_phrases['usergroup_admin_title'] = 'Administrators';
$install_phrases['usergroup_admin_usertitle'] = 'Administrator';
$install_phrases['usergroup_mod_title'] = 'Moderators';
$install_phrases['usergroup_mod_usertitle'] = 'Moderator';
$install_phrases['usergroup_banned_title'] = 'Banned Users';
$install_phrases['usergroup_banned_usertitle'] = 'Banned';
$install_phrases['usertitle_jnr'] = 'Junior Member';
$install_phrases['usertitle_mbr'] = 'Member';
$install_phrases['usertitle_snr'] = 'Senior Member';

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
