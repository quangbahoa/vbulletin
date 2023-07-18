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

// moved from upgradecore to here
$stylevar = array(
	'textdirection' => 'ltr',
	'left' => 'left',
	'right' => 'right',
	'languagecode' => 'en',
	'charset' => 'ISO-8859-1'
);

$authenticate_phrases['upgrade_title'] = 'Upgrade System';
$authenticate_phrases['enter_system'] = 'Enter Upgrade System';
$authenticate_phrases['enter_cust_num'] = 'Please Enter Your Customer Number';
$authenticate_phrases['customer_number'] = 'Customer Number';
$authenticate_phrases['cust_num_explanation'] = 'This is the number with which you log in to the vBulletin.com Members\' Area';
$authenticate_phrases['cust_num_success'] = 'Customer number entered successfully.';
$authenticate_phrases['redirecting'] = 'Redirecting...';

#####################################
# upgradecore phrases
#####################################
$upgradecore_phrases['vb3_upgrade_system'] = 'vBulletin 3.8 Upgrade System';
$upgradecore_phrases['please_login'] = 'Please Log in:';
$upgradecore_phrases['username'] = '<u>U</u>sername';
$upgradecore_phrases['password'] = '<u>P</u>assword';
$upgradecore_phrases['login'] = 'Log in';
$upgradecore_phrases['php_version_too_old'] = 'This version of vBulletin requires PHP version 5.6.0 or greater. Your PHP is version ' . PHP_VERSION . ', please ask your host to upgrade.';
$upgradecore_phrases['mysql_version_too_old'] = 'This version of vBulletin requires MySQL version 5.5.8 or greater. Your MySQL is version %1$s, please ask your host to upgrade.';
$upgradecore_phrases['ensure_config_exists'] = 'Please make sure you have created the new directory structure required by vBulletin 3';
$upgradecore_phrases['step_x_of_y'] = ' (Step %1$d of %2$d)';
$upgradecore_phrases['unknown'] = 'Unknown';
$upgradecore_phrases['file_not_found'] = 'File Not Found!';
$upgradecore_phrases['xml_file_versions'] = 'XML File Versions:';
$upgradecore_phrases['may_take_some_time'] = '(Please be patient as some parts may take some time)';
$upgradecore_phrases['update_v_number'] = 'Updating Version Number to ' . VERSION . '... ';
$upgradecore_phrases['skipping_v_number_update'] = 'Skipping version number update, you already have a newer version... ';
$upgradecore_phrases['done'] = 'done';
$upgradecore_phrases['step_title'] = 'Step %1$d) %2$s';
$upgradecore_phrases['batch_complete'] = 'Batch complete! Click the button on the right if you are not redirected automatically.';
$upgradecore_phrases['next_batch'] = ' Next Batch';
$upgradecore_phrases['next_step'] = 'Next Step (%1$d/%2$d)';
$upgradecore_phrases['click_button_to_proceed'] = 'Click the button on the right to proceed.';
$upgradecore_phrases['page_x_of_y'] = 'Page %1$d of %2$d';
$upgradecore_phrases['semicolons_file_intro'] = "THE FOLLOWING USER NAMES CONTAIN SEMI COLONS ( ; )\r\nAND *MUST* BE CHANGED IN THE CONTROL PANEL:";
$upgradecore_phrases['dump_data_to_sql'] = 'Dump Data to SQL File';
$upgradecore_phrases['choose_table_to_dump'] = 'Choose Table To Dump';
$upgradecore_phrases['dump_tables'] = 'Dump Table(s)';
$upgradecore_phrases['dump_data_to_csv'] = 'Dump Data to CSV File';
$upgradecore_phrases['backup_individual_table'] = 'Back up <b>individual table</b>';
$upgradecore_phrases['field_seperator'] = 'Field Separator';
$upgradecore_phrases['quote_character'] = 'Quote Character';
$upgradecore_phrases['show_column_names'] = 'Show Column Names';
$upgradecore_phrases['dump_table'] = 'Dump Table';
$upgradecore_phrases['vb_db_dump_completed'] = 'VBULLETIN DATABASE DUMP COMPLETED';
$upgradecore_phrases['dump_all_tables'] = '* DUMP ALL TABLES *';
$upgradecore_phrases['dump_database_desc'] = '<p class="smallfont">From here, you can back up your vBulletin database.</p>
		<p class="smallfont">Please note that if you have a particularly large database,
		this script <i>may</i> not be able to fully back it up.</p>
		<p class="smallfont">For a foolproof backup, login to your server via Telnet or SSH and use the <i>mysqldump</i>
		command on the command line. For more details,
		<a href="https://www.vbulletin.com/manual/html/manual_database_backup" target="_blank">read this</a>.</p>';
$upgradecode_phrases['vb_database_backup_system'] = 'vBulletin Database Backup System';
$upgradecore_phrases['backup_after_upgrade'] = '<p class="smallfont">Backups must be run prior to the upgrade, if the upgrade is complete please use the Admin Control Panel.</p>
		<p class="smallfont">For a foolproof backup, login to your server via Telnet or SSH and use the <i>mysqldump</i>
		command on the command line. For more details,
		<a href="https://www.vbulletin.com/docs/html/maintenance_ssh_backup" target="_blank">read this</a>.</p>';
$upgradecore_phrases['eaccelerator_not_supported'] = 'eAccelerator is no longer supported, try using APCu.';
$upgradecore_phrases['apcu_too_old_5'] = 'Your server is running a version of <b>APCu</b> that is incompatible with this version of vBulletin. Please upgrade APC to version 4.0.11 or newer.';
$upgradecore_phrases['apcu_too_old_7'] = 'Your server is running a version of <b>APCu</b> that is incompatible with this version of vBulletin. Please upgrade APC to version 5.1.5 or newer.';
$upgradecore_phrases['mmcache_not_supported'] = 'Turck MMCache is obsolete and not supported, try using APCu.';
$upgradecore_phrases['altering_x_table'] = 'Altering %1$s Table (%2$d of %3$d)';
$upgradecore_phrases['wrong_bitfield_xml'] = 'The includes/xml/bitfield_vbulletin.xml file is out of date. Please ensure you have uploaded the correct file.';

// this should contain only characters which will show on the file system
$upgradecore_phrases['illegal_user_names'] = 'Illegal User Names.txt';

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
$phrasetype['front_end_error'] = 'Error Messages';
$phrasetype['front_end_redirect'] = 'Front-End Redirect Messages';
$phrasetype['email_body'] = 'Email Body Text';
$phrasetype['email_subj'] = 'Email Subject Text';
$phrasetype['vbulletin_settings'] = 'vBulletin Settings';
$phrasetype['cp_help'] = 'Control Panel Help Text';
$phrasetype['faq_title'] = 'FAQ Title';
$phrasetype['faq_text'] = 'FAQ Text';
$phrasetype['stop_message'] = 'Control Panel Stop Message';
$phrasetype['plugins'] = 'Plugin System';
$phrasetype['inlinemod'] = 'Inline Moderation';
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
# phrases for import systems
#####################################
$vbphrase['please_wait'] = 'Please Wait';
$vbphrase['language'] = 'Language';
$vbphrase['master_language'] = 'MASTER LANGUAGE';
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
$vbphrase['updating_style_information_for_each_style'] = 'Updating style information for each style...';
$vbphrase['updating_styles_with_no_parents'] = 'Updating style sets with no parent information';
$vbphrase['updated_x_styles'] = 'Updated %1$s Style(s)';
$vbphrase['no_styles_needed_updating'] = 'No Styles Needed Updating';
$vbphrase['yes'] = 'Yes';
$vbphrase['no'] = 'No';


#####################################
# global upgrade phrases
#####################################
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
$vbphrase['alter_table_step_x'] = 'Altering %1$s Table (%2$d of %3$d)';
$vbphrase['vbulletin_copyright'] = 'vBulletin v' . VERSION . ', Copyright &copy;2000 - ' . date('Y') . ', vBulletin Solutions Inc.';
$vbphrase['vbulletin_copyright_orig'] = $vbphrase['vbulletin_copyright'];
$vbphrase['processing_complete_proceed'] = 'Processing Complete - Proceed';
$vbphrase['xml_error_x_at_line_y'] = 'XML Error: %1$s at Line %2$s';
$vbphrase['apply_critical_template_change_to_x'] = 'Applying critical template change to template %1$s in styleid %2$s';
#####################################
# upgrade_300b3.php phrases
#####################################

$upgrade_phrases['upgrade_300b3.php']['steps'] = array(
	1  => 'Create New vBulletin 3 Tables',
	2  => 'Upgrade Templates and do Alter Queries',
	3  => 'Upgrade Calendar',
	4  => 'Alter Forum Table',
	5  => 'Update Forum Last Post info',
	6  => 'Upgrade Private Messages',
	7  => 'Upgrade Users',
	8  => 'Alter User Table (part 1)',
	9  => 'Alter User Table (part 2)',
	10 => 'Upgrade Announcements Table',
	11 => 'Alter Avatar/Smilie/Icon Tables',
	12 => 'Alter Attachments Table',
	13 => 'Update Attachments #1',
	14 => 'Update Attachments #2',
	15 => 'Update Edit Post Log',
	16 => 'Update Thread and Post Table',
	17 => 'Update Posts to Support Threaded View Mode',
	18 => 'Alter Miscellaneous Tables #1',
	19 => 'Alter Miscellaneous Tables #2',
	20 => 'Upgrade BBcode System',
	21 => 'Alter Usergroup Table',
	22 => 'Update Forum Permissions',
	23 => 'Update Moderator Permissions',
	24 => 'Insert Phrase Groups',
	25 => 'Insert Scheduled Tasks',
	26 => 'Update Settings (part 1)',
	27 => 'Update Settings (part 2)',
	28 => 'Import vbulletin-language.xml',
	29 => 'Import vbulletin-adminhelp.xml',
	30 => 'Alter Style Table and Drop Replacementset Table',
	31 => 'Alter Template Table',
	32 => 'Populate User Reputation',
	33 => 'Create vB3 styles Based on Existing vB2 Styles',
	34 => 'Translate vB2 Replacement Variables into vB3 Stylevar/CSS/Replacement Templates',
	35 => 'Move Old Custom Templates into their own Reference Styles',
	36 => 'Drop Redundant Style Tables and Clean-Up Translation Process',
	37 => 'Import vbulletin-style.xml',
	38 => 'Build Style Information',
	39 => 'Import FAQ Entries',
	40 => 'Check for Illegal User Names',
	41 => 'Import Settings and Clean-Up',
	42 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);
$upgrade_phrases['upgrade_300b3.php']['tableprefix_not_empty'] = '$config[\'Database\'][\'tableprefix\'] is not empty!';
$upgrade_phrases['upgrade_300b3.php']['tableprefix_not_empty_fix'] = "Within config.php \$config['Database']['tableprefix'] must be empty for the upgrade to proceed.";
$upgrade_phrases['upgrade_300b3.php']['welcome'] = '<p style="font-size:10pt"><b>Welcome to vBulletin version 3.8</b></p>
	<p>You are about to upgrade your forum, so it has been automatically shut down.</p>
	<p>Clicking the <b>[Next Step]</b> button will begin the installation process on your database \'<i>%1$s</i>\'.</p>
	<p>In order to prevent possible browser crashes during this script, we strongly recommend that you disable any additional toolbars you may be using on your browser, such as the <b>Google</b> toolbar etc.</p>
	<p>It is recommended that you backup your entire database before proceeding.<br /><a href="upgrade_300b3.php?step=backup"><b>Click here if you want to back up your database</b></a>.</p>';
$upgrade_phrases['upgrade_300b3.php']['safe_mode_warning'] = 'You are currently running PHP in safe mode. While running in safe mode we were unable to set your script timeout limit. It is even more important that you have made a backup on the chance that an error occurs.';
$upgrade_phrases['upgrade_300b3.php']['upgrade_already_run'] = 'We have detected that you have already tried to run the upgrade script. You will not be able to proceed unless you revert to a vB 2.2.x/2.3.x database.';
$upgrade_phrases['upgrade_300b3.php']['moving_maxloggedin_datastore'] = 'Moving maxloggedin special template to new Datastore table';
$upgrade_phrases['upgrade_300b3.php']['new_datastore_values'] = 'Creating new Datastore values';
$upgrade_phrases['upgrade_300b3.php']['removing_special_templates'] = 'Removing Special Templates from the Template Table';
$upgrade_phrases['upgrade_300b3.php']['removing_orphan_pms'] = 'Removing Orphan Private Messages';
$upgrade_phrases['upgrade_300b3.php']['rename_calendar_events'] = 'Renaming calendar_events to event';
$upgrade_phrases['upgrade_300b3.php']['altering_x_table'] = 'Altering %1$s Table (%2$d of %3$d)';
$upgrade_phrases['upgrade_300b3.php']['droping_event_date'] = 'Dropping Event Date from Event table';
$upgrade_phrases['upgrade_300b3.php']['changing_subject_to_title'] = 'Changing "subject" to "title"';
$upgrade_phrases['upgrade_300b3.php']['creating_pub_calendar'] = 'Creating Public Calendar';
$upgrade_phrases['upgrade_300b3.php']['creating_priv_calendar'] = 'Creating Private Calendar';
$upgrade_phrases['upgrade_300b3.php']['moving_pub_events'] = 'Moving Public Events to the Public Calendar';
$upgrade_phrases['upgrade_300b3.php']['moving_priv_events'] = 'Moving Private Events to the Private Calendar';
$upgrade_phrases['upgrade_300b3.php']['drop_public_field'] = 'Dropping Public field from Event table';
$upgrade_phrases['upgrade_300b3.php']['convert_forum_options'] = 'Converting Forum Options storage to bitfield';
$upgrade_phrases['upgrade_300b3.php']['dropping_option_fields'] = 'Dropping Option Fields (%1$d of %2$d)';
$upgrade_phrases['upgrade_300b3.php']['resetting_styleids'] = 'Resetting styleids to default board style';
$upgrade_phrases['upgrade_300b3.php']['updating_forum_child_lists'] = 'Updating Forum Child Lists';
$upgrade_phrases['upgrade_300b3.php']['updating_counters_for_x'] = 'Updating counters for forum <i>%1$s</i>';
$upgrade_phrases['upgrade_300b3.php']['updating_lastpost_info_for_x'] = 'Updating last post info for forum <i>%1$s</i>';
$upgrade_phrases['upgrade_300b3.php']['converting_priv_msg_x'] = 'Converting Private Messages, %1$s';
$upgrade_phrases['upgrade_300b3.php']['insert_priv_msg_txt_from_x'] = 'Inserting private message text from <i>%1$s</i>';
$upgrade_phrases['upgrade_300b3.php']['insert_priv_msg_from_x_to_x'] = 'Inserting private message from <i>%1$s</i> to <i>%2$s</i>';
$upgrade_phrases['upgrade_300b3.php']['update_priv_msg_multiple_recip'] = 'Updating private message text to show multiple recipients';
$upgrade_phrases['upgrade_300b3.php']['insert_priv_msg_receipts'] = 'Inserting private message receipts';
$upgrade_phrases['upgrade_300b3.php']['dropping_vb2_pm_table'] = 'Dropping vBulletin 2 Private Message Table';
$upgrade_phrases['upgrade_300b3.php']['alter_user_table_for_vb3_pm'] = 'Altering user table to support vB3 private message system';
$upgrade_phrases['upgrade_300b3.php']['alter_user_table_vb3_password'] = 'Altering user table to support vB3 password system';
$upgrade_phrases['upgrade_300b3.php']['priv_msg_import_complete'] = 'Private message import complete';
$upgrade_phrases['upgrade_300b3.php']['upgrading_users_x'] = 'Upgrading Users, %1$s';
$upgrade_phrases['upgrade_300b3.php']['found_x_users'] = 'Found %1$d users for this batch...';
$upgrade_phrases['upgrade_300b3.php']['updating_priv_messages_for_x'] = 'Updating private message totals for user <i>%1$s</i>';
$upgrade_phrases['upgrade_300b3.php']['inserting_user_details_usertextfield'] = 'Inserting user details into <i>usertextfield</i> table';
$upgrade_phrases['upgrade_300b3.php']['user_upgrades_complete'] = 'User upgrades complete.';
$upgrade_phrases['upgrade_300b3.php']['updating_user_table_options'] = 'Updating User Table Options';
$upgrade_phrases['upgrade_300b3.php']['drop_user_option_fields'] = 'Dropping User Options Fields (%1$d of 3)';
$upgrade_phrases['upgrade_300b3.php']['update_access_masks'] = 'Updating User Access Masks';
$upgrade_phrases['upgrade_300b3.php']['convert_new_birthday_format'] = 'Converting Birthdays to New Format';
$upgrade_phrases['upgrade_300b3.php']['insert_admin_perms_admin_table'] = 'Inserting administrator permissions into Administrator table';
$upgrade_phrases['upgrade_300b3.php']['updating_announcements'] = 'Updating Announcements';
$upgrade_phrases['upgrade_300b3.php']['announcement_x'] = 'Announcement: %1$s';
$upgrade_phrases['upgrade_300b3.php']['add_index_avatar_table'] = 'Adding Index to Avatar Table';
$upgrade_phrases['upgrade_300b3.php']['move_avatars_to_category'] = 'Moving Avatars to the "Standard Avatars" category';
$upgrade_phrases['upgrade_300b3.php']['move_icons_to_category'] = 'Moving Icons to the "Standard Icons" category';
$upgrade_phrases['upgrade_300b3.php']['move_smilies_to_category'] = 'Moving Smilies to the "Standard Smilies" category';
$upgrade_phrases['upgrade_300b3.php']['update_avatars_per_page'] = 'Updating Avatars Per Page';
$upgrade_phrases['upgrade_300b3.php']['updating_attachments'] = 'Updating Attachments...';
$upgrade_phrases['upgrade_300b3.php']['attachment_x'] = 'Attachment: %1$d';
$upgrade_phrases['upgrade_300b3.php']['remove_orphan_attachments'] = 'Removing Orphan Attachments';
$upgrade_phrases['upgrade_300b3.php']['populating_attachmenttype_table'] = 'Populating Attachment Types Table';
$upgrade_phrases['upgrade_300b3.php']['updating_editpost_log'] = 'Updating Edit Post Log, %1$s';
$upgrade_phrases['upgrade_300b3.php']['found_x_posts'] = 'Found %1$d posts for this batch...';
$upgrade_phrases['upgrade_300b3.php']['post_x'] = 'Post: %1$d';
$upgrade_phrases['upgrade_300b3.php']['post_editlog_complete'] = 'Post Edit Log Update Complete.';
$upgrade_phrases['upgrade_300b3.php']['steps_may_take_several_minutes'] = 'Please note, these steps may take <b>several minutes</b> to complete if you have a large number of posts in your database.';
$upgrade_phrases['upgrade_300b3.php']['altering_post_table'] = 'Altering Post Table...';
$upgrade_phrases['upgrade_300b3.php']['altering_thread_table'] = 'Altering Thread Table...';
$upgrade_phrases['upgrade_300b3.php']['inserting_moderated_threads'] = 'Inserting moderated threads';
$upgrade_phrases['upgrade_300b3.php']['inserting_moderated_posts'] = 'Inserting moderated posts';
$upgrade_phrases['upgrade_300b3.php']['update_posts_support_threaded'] = 'Updating Posts to support Threaded View, %1$s';
$upgrade_phrases['upgrade_300b3.php']['found_x_threads'] = 'Found %1$d threads for this batch...';
$upgrade_phrases['upgrade_300b3.php']['threaded_update_complete'] = 'Threaded View Update Complete.';
$upgrade_phrases['upgrade_300b3.php']['emptying_search'] = 'Emptying Search Index';
$upgrade_phrases['upgrade_300b3.php']['emptying_wordlist'] = 'Emptying Words List';
$upgrade_phrases['upgrade_300b3.php']['remove_bbcodes_hardcoded_now'] = 'Removing BBcodes that are now hard coded ([B], [I], [U], [FONT={option}], [SIZE={option}], [COLOR={option}])';
$upgrade_phrases['upgrade_300b3.php']['inserting_quote_bbcode'] = 'Inserting [QUOTE=\'<i>Username</i>\']----[/QUOTE] bbcode tag';
$upgrade_phrases['upgrade_300b3.php']['select_banned_groups'] = 'Please Select All Usergroups That Contain \'BANNED\' Users';
$upgrade_phrases['upgrade_300b3.php']['explain_banned_groups'] = "In vBulletin 3, 'banned' usergroups need to be explicitly specified.<br /><br />\nIf you have any 'banned' usergroups, please tick those groups here.";
$upgrade_phrases['upgrade_300b3.php']['user_groups'] = 'User Groups:';
$upgrade_phrases['upgrade_300b3.php']['update_some_usergroup_titles'] = 'Updating some usergroup titles';
$upgrade_phrases['upgrade_300b3.php']['updating_usergroup_permissions'] = 'Updating Usergroup Permissions';
$upgrade_phrases['upgrade_300b3.php']['usergroup_x'] = 'Usergroup: <i>%1$s</i>';
$upgrade_phrases['upgrade_300b3.php']['updating_usergroups'] = 'Updating Usergroups';
$upgrade_phrases['upgrade_300b3.php']['updating_generic_options'] = 'Updating Usergroup Generic Options';
$upgrade_phrases['upgrade_300b3.php']['updating_usergroup_calendar'] = 'Updating Usergroup Calendar Permissions';
$upgrade_phrases['upgrade_300b3.php']['creating_priv_calendar_perms'] = 'Creating Private calendar permissions';
$upgrade_phrases['upgrade_300b3.php']['removing_orhpan_forum_perms'] = 'Removing orphan forum permissions';
$upgrade_phrases['upgrade_300b3.php']['backup_forum_perms'] = 'Backing-up forum permissions';
$upgrade_phrases['upgrade_300b3.php']['drop_old_forumperms'] = 'Dropping old forum permissions table';
$upgrade_phrases['upgrade_300b3.php']['usergroup_x_forum_y'] = 'Usergroup: <i>%1$s</i> in forum <i>%2$s</i>';
$upgrade_phrases['upgrade_300b3.php']['reinsert_forum_perms'] = 'Re-inserting forum permissions in new format';
$upgrade_phrases['upgrade_300b3.php']['remove_forum_perms_backup'] = 'Removing forum permissions backup';
$upgrade_phrases['upgrade_300b3.php']['updating_moderator_perms'] = 'Updating Moderator Permissions';
$upgrade_phrases['upgrade_300b3.php']['moderator_x_forum_y'] = 'Moderator <i>%1$s</i> in forum <u>%2$s</u>';
$upgrade_phrases['upgrade_300b3.php']['deleted_not_needed'] = 'deleted - no longer pertinent.';
$upgrade_phrases['upgrade_300b3.php']['insert_phrase_groups'] = 'Inserting Phrase Groups';
$upgrade_phrases['upgrade_300b3.php']['inserting_task_x'] = 'Inserting Task %1$d';
$upgrade_phrases['upgrade_300b3.php']['scheduling_x'] = 'Scheduling %1$s';
$upgrade_phrases['upgrade_300b3.php']['update_setting_group_x'] = 'Updating setting group: <i>%1$s</i>';
$upgrade_phrases['upgrade_300b3.php']['update_settings_within_x'] = 'Updating settings within group: <i>%1$s</i>';
$upgrade_phrases['upgrade_300b3.php']['insert_phrases_nonstandard_groups'] = 'Inserting phrases for non-standard setting groups';
$upgrade_phrases['upgrade_300b3.php']['insert_phrases_nonstandard_settings'] = 'Inserting phrases for non-standard settings';
$upgrade_phrases['upgrade_300b3.php']['saving_your_settings'] = 'Saving your settings for later...';
$upgrade_phrases['upgrade_300b3.php']['building_lang_x'] = 'Building Language: %1$s';
$upgrade_phrases['upgrade_300b3.php']['language_imported_sucessfully'] = 'Language imported sucessfully!';
$upgrade_phrases['upgrade_300b3.php']['ahelp_imported_sucessfully'] = 'Admin Help Imported Sucessfully!';
$upgrade_phrases['upgrade_300b3.php']['renaming_style_table'] = 'Renaming <b>style</b> table';
$upgrade_phrases['upgrade_300b3.php']['removing_default_templates'] = 'Removing DEFAULT templates (these will be replaced later)';
$upgrade_phrases['upgrade_300b3.php']['updating_template_format'] = 'Updating templates to the new format...';
$upgrade_phrases['upgrade_300b3.php']['updating_template_x'] = 'Updating Template: <i>%1$s</i>';
$upgrade_phrases['upgrade_300b3.php']['populating_reputation_levels'] = 'Populating User Reputation Level table';
$upgrade_phrases['upgrade_300b3.php']['set_reputation_to_neutral'] = 'Setting All Users to a neutral reputation';
$upgrade_phrases['upgrade_300b3.php']['bbtitle_vb3_style'] = '%1$s vBulletin 3 Style';
$upgrade_phrases['upgrade_300b3.php']['please_read_txt'] = 'Please Read This Text Carefully!';
$upgrade_phrases['upgrade_300b3.php']['replacement_upgrade_desc'] = '<p><b>NOTE:</b></p>
		<p>The system will attempt to translate your vBulletin2 replacement variables such as <b>{firstaltcolor}</b>
		into settings that will work with vBulletin 3. This will cause your default replacement variables to be
		translated in to vBulletin 3 StyleVars and CSS settings.</p>
		<p>The script will now run through each of your vBulletin 2 styles and create a corresponding new vBulletin 3 style.</p>
		<p>The styles listed below will contain translated versions of your vBulletin 2 style settings, and should be usable straight away
		as vBulletin 3 styles.</p>';
$upgrade_phrases['upgrade_300b3.php']['create_vb3_style_x'] = "Creating vBulletin 3 style: <b>'%1\$s'</b>";
$upgrade_phrases['upgrade_300b3.php']['template_upgrade_desc'] = '<p><b>NOTE:</b></p>
		<p>vBulletin 3 templates are markedly different from those used in vBulletin 2. For this reason, any custom templates
		you may have created will be essentially useless in the vBulletin 3 system.</p>
		<p>However, when customizing your templates for vBulletin 3, you may wish to refer back to your vBulletin 2 templates.
		For this reason, the system will now create separate styles containing your customized vBulletin 2 templates.</p>
		<p>These styles will <i>not</i> be usable as styles for vBulletin 3, but are created simply for your convenience.</p>';
$upgrade_phrases['upgrade_300b3.php']['create_vb2_refernce_style'] = "Creating a reference style from your template set <b>'%1\$s'</b>";
$upgrade_phrases['upgrade_300b3.php']['x_old_custom_templates'] = '%1$s - Old Custom Templates';
$upgrade_phrases['upgrade_300b3.php']['insert_styles_vb3_table'] = 'Inserting styles into vB3 style table';
$upgrade_phrases['upgrade_300b3.php']['updating_style_parent_list'] = 'Updating style parent lists';
$upgrade_phrases['upgrade_300b3.php']['updating_user_to_new_style'] = 'Updating users to use new style';
$upgrade_phrases['upgrade_300b3.php']['settings_imported_sucessfully'] = 'Settings Imported Sucessfully!';
$upgrade_phrases['upgrade_300b3.php']['translate_replacement_to_stylevars'] = 'Translating replacement variables into StyleVars';
$upgrade_phrases['upgrade_300b3.php']['no_value_to_translate'] = 'no values to translate into StyleVars for this style';
$upgrade_phrases['upgrade_300b3.php']['translating_replacement_to_css'] = 'Translating replacement variables into CSS';
$upgrade_phrases['upgrade_300b3.php']['body_bg_color_x'] = 'body background color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['body_text_color_x'] = 'body text color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['margin_width_x'] = 'margin width: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['link_color_x'] = 'link color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['hover_link_color_x'] = 'hover-link color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['page_bg_color_x'] = 'page background color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['page_text_color_x'] = 'page text color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['table_border_color_x'] = 'table border color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['category_strip_bg_color'] = 'category strip background color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['category_strip_text_color'] = 'category strip text color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['tbl_head_bg_color_x'] = 'table head background color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['tbl_head_text_color_x'] = 'table head text color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['first_alt_color_x'] = 'first alternating color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['second_alt_color_x'] = 'second alternating color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['normal_font_size'] = 'normal font size: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['normal_font_family'] = 'normal font family: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['normal_font_color'] = 'normal font color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['small_font_size'] = 'small font size: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['small_font_family'] = 'small font family: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['small_font_color'] = 'small font color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['highlight_font_size'] = 'highlight font size: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['highlight_font_family'] = 'highlight font family: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['highlight_font_color'] = 'highlight font color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['time_color_x'] = 'time color: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['no_replacements_to_translate'] = 'no values to translate into CSS for this style';
$upgrade_phrases['upgrade_300b3.php']['translating_remaining_replacements'] = 'Translating remaining replacement variables into vB3 replacements';
$upgrade_phrases['upgrade_300b3.php']['no_remaining_replacement_vars'] = 'no remaining replacement variables for this style';
$upgrade_phrases['upgrade_300b3.php']['translate_vb2_style_settings'] = 'Translating vB2 Style Settings';
$upgrade_phrases['upgrade_300b3.php']['add_css_headinclude_to_extra'] = 'Adding CSS data from headinclude template to this style\'s \'extra\' CSS section';
$upgrade_phrases['upgrade_300b3.php']['found_css_data'] = 'Found and added CSS data from headinclude template';
$upgrade_phrases['upgrade_300b3.php']['no_css_data_found'] = 'No CSS data found in this style\'s headinclude template';
$upgrade_phrases['upgrade_300b3.php']['no_headinclude_found'] = 'No headinclude template found for this style';
$upgrade_phrases['upgrade_300b3.php']['insert_style_settings'] = 'Inserting style settings into the database';
$upgrade_phrases['upgrade_300b3.php']['moving_template_x_to_style_x'] = "Moving templates from templateset '%1\$s' into reference style '%2\$s'";
$upgrade_phrases['upgrade_300b3.php']['importing_faq_entries'] = 'Importing FAQ Entries';
$upgrade_phrases['upgrade_300b3.php']['follow_users_contain_semicolons'] = 'The following user names contain semi-colons, and <b>must</b> be changed when you enter the control panel:';
$upgrade_phrases['upgrade_300b3.php']['download_semicolon_users'] = 'We recommend that you use <a href="%1$s"><b>this link</b></a> to download a list of the illegal user names for later reference.';
$upgrade_phrases['upgrade_300b3.php']['no_illegal_users_found'] = 'No illegal user names found.';
$upgrade_phrases['upgrade_300b3.php']['remove_old_settings_storage'] = 'Removing Old Style Options Storage';
$upgrade_phrases['upgrade_300b3.php']['salt_admin_x'] = 'Salting password for administrator: <b>%1$s</b>';
$upgrade_phrases['upgrade_300b3.php']['build_forum_and_usergroup_cache'] = 'Building forum and usergroup caches... ';
$upgrade_phrases['upgrade_300b3.php']['upgrade_complete'] = "You have now successfully upgraded to vBulletin 3.<br />
<br />
	<font size=\"+1\"><b>YOU MUST DELETE THE FOLLOWING FILES BEFORE CONTINUING:</b><br />
	install/install.php</font><br />
	<br />
	When you have done this, Click the 'Proceed' button to continue.<br />
	<br />
	Please note your board is currently <b>off</b><br />
	<br />
	<b>You will need to rebuild your Search Index and Statistics when you return to the control panel.</b><br />
	<br />
	<b>Note: Your upgrade is not complete yet. You are currently running " . VERSION . ".
	Click '<i>Proceed</i>' to continue the upgrade to newer versions.</b>";

$upgrade_phrases['upgrade_300b3.php']['public'] = 'Public';
$upgrade_phrases['upgrade_300b3.php']['public_calendar'] = 'Public Calendar';
$upgrade_phrases['upgrade_300b3.php']['private'] = 'Private';
$upgrade_phrases['upgrade_300b3.php']['private_calendar'] = 'Private Calendar';
$upgrade_phrases['upgrade_300b3.php']['deleted_user'] = 'Deleted User';
$upgrade_phrases['upgrade_300b3.php']['standard_avatars'] = 'Standard Avatars';
$upgrade_phrases['upgrade_300b3.php']['standard_icons'] = 'Standard Icons';
$upgrade_phrases['upgrade_300b3.php']['standard_smilies'] = 'Standard Smilies';
$upgrade_phrases['upgrade_300b3.php']['avatar_setting_title'] = 'Avatars Per Page';
$upgrade_phrases['upgrade_300b3.php']['avatar_setting_desc'] = 'How many avatars do you want to display per-page on the \\\'Edit Avatar\\\' page within the profile editor?';
$upgrade_phrases['upgrade_300b3.php']['registered_user'] = 'Registered User';
// should be the values that vbulletin-language.xml contains
$upgrade_phrases['upgrade_300b3.php']['master_language_title'] = 'English (US)';
$upgrade_phrases['upgrade_300b3.php']['master_language_langcode'] = 'en';
$upgrade_phrases['upgrade_300b3.php']['master_language_charset'] = 'ISO-8859-1';
$upgrade_phrases['upgrade_300b3.php']['master_language_decimalsep'] = '.';
$upgrade_phrases['upgrade_300b3.php']['master_language_thousandsep'] = ',';
$upgrade_phrases['upgrade_300b3.php']['master_language_just_created'] = 'Creating English (US) Language';
$upgrade_phrases['upgrade_300b3.php']['settinggroups'] = array(
		'Turn Your vBulletin on and off' => 'onoff',
		'General Settings' => 'general',
		'Contact Details' => 'contact',
		'Posting Code allowances (vB code / HTML / etc)' => 'postingallow',
		'Forums Home Page Options' => 'forumhome',
		'User and registration options' => 'user',
		'Memberlist options' => 'memberlist',
		'Thread display options' => 'showthread',
		'Forum Display Options' => 'forumdisplay',
		'Search Options' => 'search',
		'Email Options' => 'email',
		'Date / Time options' => 'datetime',
		'Edit Options' => 'editpost',
		'IP Logging Options' => 'ip',
		'Floodcheck Options' => 'floodcheck',
		'Banning Options' => 'banning',
		'Private Messaging Options' => 'pm',
		'Censorship Options' => 'censor',
		'HTTP Headers and output' => 'http',
		'Version Info' => 'version',
		'Templates' => 'templates',
		'Load limiting options' => 'loadlimit',
		'Polls' => 'poll',
		'Avatars' => 'avatar',
		'Attachments' => 'attachment',
		'Custom User Titles' => 'usertitle',
		'Upload Options' => 'upload',
		'Who\'s Online' => 'online',
		'Language Options' => 'OLDlanguage',
		'Spell Check' => 'OLDspellcheck',
		'Calendar' => 'OLDcalendar'
	);
$upgrade_phrases['upgrade_300b3.php']['vb2_default_style_title'] = 'Default';
$upgrade_phrases['upgrade_300b3.php']['new_vb2_default_style_title'] = 'vBulletin 2 Default';
$upgrade_phrases['upgrade_300b3.php']['cron_birthday'] = 'Happy Birthday Email';
$upgrade_phrases['upgrade_300b3.php']['cron_thread_views'] = 'Thread Views';
$upgrade_phrases['upgrade_300b3.php']['cron_user_promo'] = 'User Promotions';
$upgrade_phrases['upgrade_300b3.php']['cron_daily_digest'] = 'Daily Digest';
$upgrade_phrases['upgrade_300b3.php']['cron_weekly_digest'] = 'Weekly Digest';
$upgrade_phrases['upgrade_300b3.php']['cron_activation'] = 'Activation Reminder Email';
$upgrade_phrases['upgrade_300b3.php']['cron_subscriptions'] = 'Subscriptions';
$upgrade_phrases['upgrade_300b3.php']['cron_hourly_cleanup'] = 'Hourly Cleanup #1';
$upgrade_phrases['upgrade_300b3.php']['cron_hourly_cleaup2'] = 'Hourly Cleanup #2';
$upgrade_phrases['upgrade_300b3.php']['cron_attachment_views'] = 'Attachment Views';
$upgrade_phrases['upgrade_300b3.php']['cron_unban_users'] = 'Restore Temporarily Banned Users';
$upgrade_phrases['upgrade_300b3.php']['cron_stats_log'] = 'Daily Statistics Log';
$upgrade_phrases['upgrade_300b3.php']['reputation_-999999'] = 'is infamous around these parts';
$upgrade_phrases['upgrade_300b3.php']['reputation_-50'] = 'can only hope to improve';
$upgrade_phrases['upgrade_300b3.php']['reputation_-10'] = 'has a little shameless behaviour in the past';
$upgrade_phrases['upgrade_300b3.php']['reputation_0'] = 'is an unknown quantity at this point';
$upgrade_phrases['upgrade_300b3.php']['reputation_10'] = 'is on a distinguished road';
$upgrade_phrases['upgrade_300b3.php']['reputation_50'] = 'will become famous soon enough';
$upgrade_phrases['upgrade_300b3.php']['reputation_150'] = 'has a spectacular aura about';
$upgrade_phrases['upgrade_300b3.php']['reputation_250'] = 'is a jewel in the rough';
$upgrade_phrases['upgrade_300b3.php']['reputation_350'] = 'is just really nice';
$upgrade_phrases['upgrade_300b3.php']['reputation_450'] = 'is a glorious beacon of light';
$upgrade_phrases['upgrade_300b3.php']['reputation_550'] = 'is a name known to all';
$upgrade_phrases['upgrade_300b3.php']['reputation_650'] = 'is a splendid one to behold';
$upgrade_phrases['upgrade_300b3.php']['reputation_1000'] = 'has much to be proud of';
$upgrade_phrases['upgrade_300b3.php']['reputation_1500'] = 'has a brilliant future';
$upgrade_phrases['upgrade_300b3.php']['reputation_2000'] = 'has a reputation beyond repute';
$upgrade_phrases['upgrade_300b3.php']['upgrade_from_vb2_not_supported'] = '
	<p>It is not possible to upgrade directly from vBulletin 2 to vBulletin versions 3.8.x and newer due to incompatabilities in data structures.</p>
	<p>If you are currently running vBulletin 2 and would like to upgrade to the latest version of vBulletin, please begin by downloading a version of vBulletin 3.6 from the vBulletin Members\' Area.</p>
	<p>Upload the extracted files from the archive you download over the existing files here and run the upgrade process until it is complete.</p>
	<p>After completing the upgrade process to 3.6.x, download the most recent version of vBulletin from the Members\' area, and upload the extracted files here, then continue to run the upgrade process until it is completed.</p>
';

#####################################
# upgrade_300b4.php phrases
#####################################

$upgrade_phrases['upgrade_300b4.php']['steps'] = array(
	1 => 'Alter database schema',
	2 => 'Adding and deleting data',
	3 => 'Update attachment type cache',
	4 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);
$upgrade_phrases['upgrade_300b4.php']['default_avatar_category'] = 'Inserting default avatar category';
$upgrade_phrases['upgrade_300b4.php']['insert_into_whosonline'] = "Inserting Who's Online Spider cache into %1$sdatastore";
$upgrade_phrases['upgrade_300b4.php']['delete_redundant_cron'] = 'Deleting redundant CRON job';
$upgrade_phrases['upgrade_300b4.php']['attachment_cache_rebuilt'] = 'Attachment Cache Rebuilt';
$upgrade_phrases['upgrade_300b4.php']['generic_avatars'] = 'Generic Avatars';

#####################################
# upgrade_300b5.php phrases
#####################################

$upgrade_phrases['upgrade_300b5.php']['steps'] = array(
	1 => 'Alter database schema',
	2 => 'Alter thread / post table',
	3 => 'Change setting',
	4 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);
$upgrade_phrases['upgrade_300b5.php']['alter_post_title'] = 'Alterting %1$spost title column to VARCHAR(250)<br /><i>(this may take a few minutes on a large board)';
$upgrade_phrases['upgrade_300b5.php']['alter_thread_title'] = 'Alterting %1$sthread title column to VARCHAR(250)<br /><i>(this may take a few minutes on a large board)';
$upgrade_phrases['upgrade_300b5.php']['disabled_timeout_admin'] = 'Disabled "Timeout Admin Login" setting successfully.';
$upgrade_phrases['upgrade_300b5.php']['timeout_admin_not_changed'] = '"Timeout Admin Login" setting not changed.';
$upgrade_phrases['upgrade_300b5.php']['change_setting_value'] = 'Change Setting Value?';
$upgrade_phrases['upgrade_300b5.php']['proceed'] = ' Proceed ';
$upgrade_phrases['upgrade_300b5.php']['setting_info'] = '<b>Disable "Timeout Admin Login" setting?</b> ' .
					'<dfn>This option adds security but can make it harder to login to the administrators\' control panel.<br />' .
					'If you have had problems logging into the control panel in the past or are behind a proxy server ' .
					'(such as AOL\'s proxy servers), you will want to disable this setting (select "yes").</dfn>';
$upgrade_phrases['upgrade_300b5.php']['no_change_needed'] = 'Determining if setting value may need to be changed ... No change necessary.';

#####################################
# upgrade_300b6.php phrases
#####################################

$upgrade_phrases['upgrade_300b6.php']['steps'] = array(
	1 => 'Add Index to Thread table',
	2 => 'Alter database schema',
	3 => 'Change Subscription Data',
	4 => 'Rename some Templates and Smilies for new WYSIWYG editor',
	5 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_300b6.php']['alter_thread_table'] = 'Altering %1$sthread Table...<br /><i>(this may take a few seconds on a large board)';
$upgrade_phrases['upgrade_300b6.php']['remove_avatar_cache'] = 'Removing avatar cache';
$upgrade_phrases['upgrade_300b6.php']['update_userban'] = 'Updating ban removal scheduled task to run hourly at 15 minutes past the hour';
$upgrade_phrases['upgrade_300b6.php']['subscription_active'] = 'Setting subscriptions to active';
$upgrade_phrases['upgrade_300b6.php']['rename_old_template'] = 'Renaming <i>%1$s</i> template to <i>%2$s</i>';
$upgrade_phrases['upgrade_300b6.php']['delete_vbcode_color'] = 'Deleting <i>vbcode_color_options</i> template (colors are now defined in the clientscript/vbulletin_editor.js file)';
$upgrade_phrases['upgrade_300b6.php']['smilie_fixes'] = "Making smilie titles nicer (and fixing 'embarra<b>s</b>ment' typo!)";

#####################################
# upgrade_300b7.php phrases
#####################################

$upgrade_phrases['upgrade_300b7.php']['steps'] = array(
	1 => 'Miscellaneous updates #1',
	2 => 'Altering user table',
	3 => 'Important note about bbcode changes',
	4 => 'Modify bbcode system',
	5 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_300b7.php']['redundant_stylevars'] = "Deleting redundant stylevars from " . TABLE_PREFIX . "template table";
$upgrade_phrases['upgrade_300b7.php']['renaming_some_templates'] = 'Renaming some templates';
$upgrade_phrases['upgrade_300b7.php']['ban_removal_fix'] = 'Updating ban removal scheduled task to run hourly at 15 minutes past the hour (fix for error in previous upgrade script)';
$upgrade_phrases['upgrade_300b7.php']['promotion_lastrun_fix'] = 'Resetting last run time for promotions back to September 8th to repair process';
$upgrade_phrases['upgrade_300b7.php']['default_charset'] = 'Setting default charset for languages to <i>ISO-8859-1</i>.';
$upgrade_phrases['upgrade_300b7.php']['comma_var_names'] = 'Getting rid of any commas in phrase variable names';
$upgrade_phrases['upgrade_300b7.php']['delete_quote_email_bbcode'] = 'Deleting [quote] and [email] bbcode tags - these are now hard-coded';
$upgrade_phrases['upgrade_300b7.php']['bbcode_update'] = "<h3>An Important Note...</h3>" .
	 "<p>The following step in this upgrade script is going to <b>delete</b> your <i>[quote]</i>, <i>[quote=username]</i>, <i>[email]</i>, and <i>[email=address]</i> bbcode definitions, as these are now hard-coded and controlled by a template.</p>" .
	 "<p>If you have customized the HTML generated by these tags, we suggest that you now visit the <a href=\"../{$vbulletin->config[Misc][admincpdir]}/bbcode.php?$session[sessionurl]\" target=\"_blank\" title=\"Open BBCode Manager in new window\">BBCode Manager</a> and make a note of your customized HTML.</p>" .
	 "<p>When the script completes, you can then customize the <b>bbcode_quote</b> template to achieve the same results as you originally had.</p>" .
	 "<p>Click the 'Next Step' button to proceed to the next step of the upgrade script.</p>";

#####################################
# upgrade_300g.php phrases
#####################################

$upgrade_phrases['upgrade_300g.php']['steps'] = array(
	1 => 'Miscellaneous updates #1',
	2 => 'Language and Phrase changes',
	3 => 'Paid Subscriptions',
	4 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_300g.php']['remove_duplicate_templates'] = 'Removing duplicate templates within a style ...';
$upgrade_phrases['upgrade_300g.php']['done'] = 'Done!';
$upgrade_phrases['upgrade_300g.php']['rename_searchindex_postindex'] = "Renaming " . TABLE_PREFIX . "searchindex table to " . TABLE_PREFIX . "postindex";
$upgrade_phrases['upgrade_300g.php']['removing_redundant_index_phrase'] = "Removing redundant index on " . TABLE_PREFIX . "phrase";
$upgrade_phrases['upgrade_300g.php']['holiday_to_phrasetype'] = "Adding holiday to " . TABLE_PREFIX . "phrasetype table";
$upgrade_phrases['upgrade_300g.php']['moving_holiday_type'] = "Moving existing holidays to their new phrasetype";
$upgrade_phrases['upgrade_300g.php']['adding_x_to_phrasetype'] = 'Adding %1$s to ' . TABLE_PREFIX . 'phrasetype table';
$upgrade_phrases['upgrade_300g.php']['update_invalid_birthdays'] = "Updating invalid birthdays";
$upgrade_phrases['upgrade_300g.php']['step_already_run'] = 'Step already run.';
$upgrade_phrases['upgrade_300g.php']['updating_subscription_expiry_times'] = 'Updating subscription expiry times.';

#####################################
# upgrade_300rc1.php phrases
#####################################

$upgrade_phrases['upgrade_300rc1.php']['steps'] = array(
	1 => 'Miscellaneous updates #1',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_300rc1.php']['alter_reputation_negative'] = "Altering reputation promotion to allow negative entries";
$upgrade_phrases['upgrade_300rc1.php']['phrase_varname_case_sens'] = "Making phrase variable names case sensitive";
$upgrade_phrases['upgrade_300rc1.php']['add_faq_entry'] = 'Adding FAQ entry';

#####################################
# upgrade_300rc2.php phrases
#####################################

$upgrade_phrases['upgrade_300rc2.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

#####################################
# upgrade_300rc3.php phrases
#####################################

$upgrade_phrases['upgrade_300rc3.php']['steps'] = array(
	1 => 'Fix Some Table Errors',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_300rc3.php']['click_here_auto_redirect'] = 'Click here if you are not automatically redirected.';
$upgrade_phrases['upgrade_300rc3.php']['not_latest_files'] = 'You haven\'t uploaded all of the latest files!<br /><br /><b>Please upload the latest version of adminfunctions_profilefield.php to the includes directory then refresh this page.</b>';
$upgrade_phrases['upgrade_300rc3.php']['fix_sortorder'] = "Fixing sortorder field in " . TABLE_PREFIX . "search table.";
$upgrade_phrases['upgrade_300rc3.php']['fix_logdateoverride'] = "Fixing logdateoverride field in " . TABLE_PREFIX . "language table.";
$upgrade_phrases['upgrade_300rc3.php']['fix_filesize_customavatar'] = "Adding filesize field to " . TABLE_PREFIX . "customavatar table.";
$upgrade_phrases['upgrade_300rc3.php']['fix_filesize_customprofile'] = "Adding filesize field to " . TABLE_PREFIX . "customprofilepic table.";
$upgrade_phrases['upgrade_300rc3.php']['populate_avatar_filesize'] = 'Populating Avatar Filesize.';
$upgrade_phrases['upgrade_300rc3.php']['populate_profile_filesize'] = 'Populating Profile Pic Filesize.';

#####################################
# upgrade_300rc4.php phrases
#####################################

$upgrade_phrases['upgrade_300rc4.php']['steps'] = array(
	1 => 'Fix Some Table Errors',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_300rc4.php']['increase_storage_dateoverride'] = "Increasing the storage size of the dateoverride field in the " . TABLE_PREFIX . "language table.";
$upgrade_phrases['upgrade_300rc4.php']['increase_storage_timeoverride'] = "Increasing the storage size of the timeoverride field in the " . TABLE_PREFIX . "language table.";
$upgrade_phrases['upgrade_300rc4.php']['increase_storage_registereddateoverride'] = "Increasing the storage size of the registereddateoverride field in the " . TABLE_PREFIX . "language table.";
$upgrade_phrases['upgrade_300rc4.php']['increase_storage_calformat1override'] = "Increasing the storage size of the calformat1override field in the " . TABLE_PREFIX . "language table.";
$upgrade_phrases['upgrade_300rc4.php']['increase_storage_calformat2override'] = "Increasing the storage size of the calformat2override field in the " . TABLE_PREFIX . "language table.";
$upgrade_phrases['upgrade_300rc4.php']['increase_storage_logdateoverride'] = "Increasing the storage size of the logdateoverride field in the " . TABLE_PREFIX . "language table.";
$upgrade_phrases['upgrade_300rc4.php']['adding_calendar_mardi_gras'] = "Altering " . TABLE_PREFIX . "calendar table to support new pre-defined holidays: Mardi Gras and Corpus Christi. You will need to enable these holidays in the calendar manager.";

#####################################
# upgrade_300.php phrases
#####################################

$upgrade_phrases['upgrade_300.php']['steps'] = array(
	1 => 'Fix Some Table Errors',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_300.php']['make_reputation_signed'] = 'Make reputation a signed integer';
$upgrade_phrases['upgrade_300.php']['add_birthday_search'] = 'Adding birthday search field';
$upgrade_phrases['upgrade_300.php']['add_index_birthday_search'] = 'Adding index to birthday search field';
$upgrade_phrases['upgrade_300.php']['populate_birhtday_search'] = 'Populating birthday search field';

#####################################
# upgrade_301.php phrases
#####################################

$upgrade_phrases['upgrade_301.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

#####################################
# upgrade_302.php phrases
#####################################

$upgrade_phrases['upgrade_302.php']['steps'] = array(
	1 => 'Miscellaneous table alterations 1/4',
	2 => 'Miscellaneous table alterations 2/4',
	3 => 'Miscellaneous table alterations 3/4',
	4 => 'Miscellaneous table alterations 4/4',
	5 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_302.php']['drop_pmpermissions'] = 'Dropping redundant field';
$upgrade_phrases['upgrade_302.php']['add_thumbnail_filesize'] = 'Adding thumbnail size field to attachment table';
$upgrade_phrases['upgrade_302.php']['change_profilefield'] = 'Increasing storage size of custom profile fields';
$upgrade_phrases['upgrade_302.php']['update_genericpermissions'] = 'Altering Generic Permissions';
$upgrade_phrases['upgrade_302.php']['alter_poll_table'] = 'Adding lastvote field to ' . TABLE_PREFIX . 'poll';
$upgrade_phrases['upgrade_302.php']['alter_user_table'] = 'Adding support for long emails to ' . TABLE_PREFIX . 'user';
$upgrade_phrases['upgrade_302.php']['add_rss_faq'] = 'Adding RSS entry to ' . TABLE_PREFIX . 'faq';
$upgrade_phrases['upgrade_302.php']['add_notes'] = 'Adding notes field to ' . TABLE_PREFIX . 'administrator';
$upgrade_phrases['upgrade_302.php']['add_cpsession_table'] = 'Adding cpsession table';
$upgrade_phrases['upgrade_302.php']['fix_blank_charset'] = 'Adding Default charset';

#####################################
# upgrade_303.php phrases
#####################################

$upgrade_phrases['upgrade_303.php']['steps'] = array(
	1 => 'Miscellaneous table alterations 1/1',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_303.php']['note'] = '<p>If you have thumbnails enabled, store attachments as files, and did not rebuild your thumbnails after upgrading to 3.0.2, please make sure to do so after this upgrade is complete as your thumbnails will not work until you do so.</p>';
$upgrade_phrases['upgrade_303.php']['rebuild_usergroupcache'] = 'Rebuilding Usergroupcache';

#####################################
# upgrade_303 - 309.php phrases
#####################################

$upgrade_phrases['upgrade_304.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_305.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_306.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];

$upgrade_phrases['upgrade_307.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_307.php']['update_calendarpermissions'] = 'Altering Calendar Permissions';
$upgrade_phrases['upgrade_307.php']['import_birthdaydatecut_option'] = 'Importing Old Birthday Datecut Option';

$upgrade_phrases['upgrade_308.php']['steps'] =& $upgrade_phrases['upgrade_303.php']['steps'];
$upgrade_phrases['upgrade_309.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_3010.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_3011.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_3012.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_3013.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_3014.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];
$upgrade_phrases['upgrade_3015.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];

#####################################
# upgrade_350b1.php phrases
#####################################

$upgrade_phrases['upgrade_350b1.php']['steps'] = array(
	1 => 'Miscellaneous table alterations 1/6',
	2 => 'Miscellaneous table alterations 2/6',
	3 => 'Miscellaneous table alterations 3/6',
	4 => 'Miscellaneous table alterations 4/6',
	5 => 'Miscellaneous table alterations 5/6',
	6 => 'Miscellaneous table alterations 6/6',
	7 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_350b1.php']['update_forumpermissions'] = 'Altering Forum Permissions';
$upgrade_phrases['upgrade_350b1.php']['support_multiple_products'] = 'Adding Support for Multiple Products';
$upgrade_phrases['upgrade_350b1.php']['update_adminpermissions'] = 'Altering Admin Permissions';
$upgrade_phrases['upgrade_350b1.php']['cron_event_reminder'] = 'Event Reminder';

#####################################
# upgrade_350b2.php phrases
#####################################

$upgrade_phrases['upgrade_350b2.php']['steps'] = array(
	1 => 'Miscellaneous table alterations 1/1',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

#####################################
# upgrade_350b3.php phrases
#####################################

$upgrade_phrases['upgrade_350b3.php']['steps'] = array(
	1 => 'Converting various settings',
	2 => 'Upgrading Payment System',
	3 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_350b3.php']['translating_allowvbcodebuttons'] = 'Converting $vbulletin->options[\'allowvbcodebuttons\'] and $vbulletin->options[\'quickreply\'] into new integrated $vbulletin->options[\'editormodes\'] setting';
$upgrade_phrases['upgrade_350b3.php']['translating_quickreply'] = 'Converting $vbulletin->options[\'quickreply\'] and $vbulletin->options[\'quickreplyclick\'] into new integrated $vbulletin->options[\'quickreply\'] setting.';
$upgrade_phrases['upgrade_350b3.php']['converting_phrases_x_of_y'] = 'Converting Phrases (Step %1$d of %2$d)';
$upgrade_phrases['upgrade_350b3.php']['paymentapi_data'] = 'Inserting default Payment API data';
$upgrade_phrases['upgrade_350b1.php']['note'] = '<p><h3>You are storing Avatars in the file system.  vB 3.5.0 now has the option to store Profile Pictures in the file system as well using a consolidated option for both image types.  When your upgrade has completed, go into your Admin CP and move all of your avatars back into the database via <em>Avatars->User Picture Storage Type</em>. When the process is completed, go back to the same option and move your pictures back to the file system.  Now your avatars and profile pictures will be stored in the file system. If you fail to do this, your users\' profile pictures will not display.</h3></p>';

#####################################
# upgrade_350b4.php phrases
#####################################

$upgrade_phrases['upgrade_350b4.php']['steps'] = array(
	1 => 'Miscellaneous table alterations 1/1',
	2 => 'Upgrading Payment System',
	3 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_350b4.php']['adding_payment_api_x_settings'] = 'Adding Payment API %1$s Settings';
$upgrade_phrases['upgrade_350b4.php']['invert_moderate_permission'] = 'Inverting \'Always Moderate This Group\'s Posts\' Permission';

#####################################
# upgrade_350rc1.php phrases
#####################################

$upgrade_phrases['upgrade_350rc1.php']['steps'] = array(
	1 => 'Setting up product management system',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_350rc1.php']['control_panel_hook_support'] = 'Adding support for hooks in the control panels';

#####################################
# upgrade_350rc2.php phrases
#####################################

$upgrade_phrases['upgrade_350rc2.php']['steps'] = array(
	1 => 'Updating product fields',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

#####################################
# upgrade_350rc3.php phrases
#####################################

$upgrade_phrases['upgrade_350rc3.php']['steps'] = array(
	1 => 'Miscellaneous table alterations',
	2 => 'Thread table alterations',
	3 => 'Post table alterations',
	4 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_350rc3.php']['please_wait_message'] = 'The next step may take awhile. Please be patient.';
$upgrade_phrases['upgrade_350rc3.php']['updating_payment_api_x_settings'] = 'Updating Payment API %1$s Settings';

#####################################
# upgrade_350.php phrases
#####################################

$upgrade_phrases['upgrade_350.php']['steps'] = array(
	1 => 'Miscellaneous table alterations',
	2 => 'Thread table alterations',
	3 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

#####################################
# upgrade_351.php phrases
#####################################

$upgrade_phrases['upgrade_351.php']['steps'] = array(
	1 => 'Miscellaneous table alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_351.php']['delete_vb_product'] = 'Delete vBulletin from product table.';

#####################################
# upgrade_352.php phrases
#####################################

$upgrade_phrases['upgrade_352.php']['steps'] =& $upgrade_phrases['upgrade_351.php']['steps'];

$upgrade_phrases['upgrade_352.php']['adding_skype_field'] = 'Adding Skype field to user table';

#####################################
# upgrade_353.php phrases
#####################################

$upgrade_phrases['upgrade_353.php']['steps'] =& $upgrade_phrases['upgrade_351.php']['steps'];

$upgrade_phrases['upgrade_353.php']['remove_352_xss_plugin'] = 'Removing XSS Plugin Fix for 3.5.2, if installed';

#####################################
# upgrade_354.php phrases
#####################################

$upgrade_phrases['upgrade_354.php']['steps'] =& $upgrade_phrases['upgrade_351.php']['steps'];

$upgrade_phrases['upgrade_355.php']['steps'] =& $upgrade_phrases['upgrade_301.php']['steps'];

#####################################
# upgrade_360b1.php phrases
#####################################

$upgrade_phrases['upgrade_360b1.php']['steps'] = array(
	1 => 'Large table alterations (1/2)',
	2 => 'Large table alterations (2/2)',
	3 => 'Misc Table Alterations',
	4 => 'Misc Table Alterations',
	5 => 'Misc Table Alterations',
	6 => 'Misc Table Alterations',
	7 => 'Signature Permissions',
	8 => 'Infractions',
	9 => 'Paid Subscription Updates',
	10 => 'Super Moderator Permission Updates',
	11 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_360b1.php']['please_wait_message'] = 'The next step may take awhile. Please be patient. If the step appears to freeze, you may refresh.';

$upgrade_phrases['upgrade_360b1.php']['updating_cron'] = 'Updating Scheduled Task';
$upgrade_phrases['upgrade_360b1.php']['updating_subscriptions'] = 'Updating Subscription';
$upgrade_phrases['upgrade_360b1.php']['updating_holidays'] = 'Updating Holiday';
$upgrade_phrases['upgrade_360b1.php']['updating_profilefields'] = 'Updating Custom Profile Fields';
$upgrade_phrases['upgrade_360b1.php']['updating_reputationlevels'] = 'Updating Reputation Levels';
$upgrade_phrases['upgrade_360b1.php']['invert_banned_flag'] = "Inverting 'This Usergroup is a 'Banned' Group' Flag";
$upgrade_phrases['upgrade_360b1.php']['install_canignorequota_permission'] = "Setting 'Can Ignore Quota' Permission";
$upgrade_phrases['upgrade_360b1.php']['infractionlevel1_title'] = 'Spammed Advertisements';
$upgrade_phrases['upgrade_360b1.php']['infractionlevel2_title'] = 'Insulted Other Member(s)';
$upgrade_phrases['upgrade_360b1.php']['infractionlevel3_title'] = 'Signature Rule Violation';
$upgrade_phrases['upgrade_360b1.php']['infractionlevel4_title'] = 'Inappropriate Language';
$upgrade_phrases['upgrade_360b1.php']['rssposter_title'] = 'RSS Poster';
$upgrade_phrases['upgrade_360b1.php']['infractions_title'] = 'Infraction Cleanup';
$upgrade_phrases['upgrade_360b1.php']['ccbill_title'] = 'CCBill Reversal Check';

$upgrade_phrases['upgrade_360b1.php']['rename_post_parsed'] = "Renaming " . TABLE_PREFIX . "post_parsed table to " . TABLE_PREFIX . "postparsed";

$upgrade_phrases['upgrade_360b1.php']['updating_thread_redirects'] = 'Updating Thread Redirects';

$upgrade_phrases['upgrade_360b1.php']['super_moderator_x_updated'] = 'Super Moderator \'%1$s\' updated';

$upgrade_phrases['upgrade_360b1.php']['lastpostid_notice'] = '<strong>In order to complete the upgrade, the Thread Info and Forum Info <em>must</em> be rebuilt within the Update Counters part of the Admin Control Panel.</strong>';

#####################################
# upgrade_360b2.php phrases
#####################################

$upgrade_phrases['upgrade_360b2.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

#####################################
# upgrade_360b3.php phrases
#####################################

$upgrade_phrases['upgrade_360b3.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);


$upgrade_phrases['upgrade_360b4.php']['steps'] =& $upgrade_phrases['upgrade_360b2.php']['steps'];
$upgrade_phrases['upgrade_360rc1.php']['steps'] =& $upgrade_phrases['upgrade_360b2.php']['steps'];
$upgrade_phrases['upgrade_360rc2.php']['steps'] =& $upgrade_phrases['upgrade_360b2.php']['steps'];
$upgrade_phrases['upgrade_360rc3.php']['steps'] =& $upgrade_phrases['upgrade_360b2.php']['steps'];
$upgrade_phrases['upgrade_360.php']['steps'] =& $upgrade_phrases['upgrade_360b2.php']['steps'];
$upgrade_phrases['upgrade_361.php']['steps'] =& $upgrade_phrases['upgrade_360b2.php']['steps'];

$upgrade_phrases['upgrade_361.php']['rename_podcasturl'] = "Renaming " . TABLE_PREFIX . "podcasturl table to " . TABLE_PREFIX . "podcastitem";

$upgrade_phrases['upgrade_362.php']['steps'] =& $upgrade_phrases['upgrade_360b3.php']['steps'];
$upgrade_phrases['upgrade_363.php']['steps'] =& $upgrade_phrases['upgrade_360b3.php']['steps'];

$upgrade_phrases['upgrade_364.php']['steps'] =& $upgrade_phrases['upgrade_360b2.php']['steps'];
$upgrade_phrases['upgrade_365.php']['steps'] =& $upgrade_phrases['upgrade_360b3.php']['steps'];
$upgrade_phrases['upgrade_366.php']['steps'] =& $upgrade_phrases['upgrade_360b2.php']['steps'];
$upgrade_phrases['upgrade_367.php']['steps'] =& $upgrade_phrases['upgrade_360b3.php']['steps'];
$upgrade_phrases['upgrade_368.php']['steps'] =& $upgrade_phrases['upgrade_360b3.php']['steps'];

$upgrade_phrases['upgrade_370b2.php']['steps'] = array(
	1 => 'Table Creations',
	2 => 'Large Table Alterations',
	3 => 'Misc Table Alterations',
	4 => 'Default Data Insertion',
	5 => 'Userlist Rebuilding',
	6 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_370b2.php']['build_userlist'] = '<p>Rebuilding userlist for %1$s</p>';
$upgrade_phrases['upgrade_370b2.php']['build_userlist_complete'] = '<p>Rebuilding of userlists is complete</p>';

$upgrade_phrases['upgrade_370b3.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Altering Table Defaults',
	3 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_370b4.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Table Data Updates',
	3 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_370b5.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_370b6.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Inserting New FAQ',
	3 => 'Delete Old FAQ?',
	4 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_370b6']['inserting_vb37_faq_structure'] = 'Inserting vBulletin 3.7.0 FAQ Structure';
$upgrade_phrases['upgrade_370b6']['delete_selected_faq_items'] = 'Delete Selected FAQ Items';
$upgrade_phrases['upgrade_370b6']['delete_faq_description'] = '
	In the previous step, a new vBulletin FAQ tree was added to your board.
	This means that you now have both the old and new FAQ trees installed.<br />
	<br />
	Below is a list of all FAQ items attached to the old FAQ tree.
	If you have never changed any of these, leave them all selected for deletion.
	If you have added new FAQ items yourself, or have modified any of the existing entries, simply deselect those items you wish to keep.<br />
	<br />
	When you have finished making your selection, click the <strong>' . $upgrade_phrases['upgrade_370b6']['delete_selected_faq_items'] . '</strong> button at the bottom of the list.
	If no items are selected, none will be deleted and the upgrade process will continue.
';
$upgrade_phrases['upgrade_370b6']['reset_selection'] = 'Reset Selection';
$upgrade_phrases['upgrade_370b6']['selected_faq_items_deleted'] = 'The FAQ items you selected have now been deleted.';

$upgrade_phrases['upgrade_370rc1.php']['steps'] = $upgrade_phrases['upgrade_370b5.php']['steps'];

$upgrade_phrases['upgrade_370rc2.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_370rc3.php']['dropping_old_table_x'] = 'Dropping Old %1$s Table';
$upgrade_phrases['upgrade_370rc3.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_370rc4.php']['steps'] = array(
	1 => 'Critical Template Changes',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_370rc4.php']['token_added_x_templates'] = 'Adding required security token to %1$s customized template(s)';

$upgrade_phrases['upgrade_370.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_371.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_380a2.php']['steps'] = array(
	1 => 'Table Creations',
	2 => 'Misc Table Alterations',
	3 => 'Group Discussion Conversions',
	4 => 'Permission Updates',
	5 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_380a2.php']['alter_index_on_x'] = 'Altering index on %1$s';
$upgrade_phrases['upgrade_380a2.php']['create_index_on_x'] = 'Creating index for %1$s';
$upgrade_phrases['upgrade_380a2.php']['creating_default_group_category'] = 'Creating default social group category';
$upgrade_phrases['upgrade_380a2.php']['fulltext_index_on_x'] = 'Creating fulltext index for %1$s';
$upgrade_phrases['upgrade_380a2.php']['convert_messages_to_discussion'] = 'Converting social group messages to discussions';
$upgrade_phrases['upgrade_380a2.php']['set_discussion_titles'] = 'Setting initial discussion titles';
$upgrade_phrases['upgrade_380a2.php']['update_last_post'] = 'Initialising discussion last post information';
$upgrade_phrases['upgrade_380a2.php']['update_discussion_counters'] = 'Updating discussion counters';
$upgrade_phrases['upgrade_380a2.php']['update_group_message_counters'] = 'Updating social group message counters';
$upgrade_phrases['upgrade_380a2.php']['update_group_discussion_counters'] = 'Updating social group discussion counters';
$upgrade_phrases['upgrade_380a2.php']['uncategorized'] = 'Uncategorized';
$upgrade_phrases['upgrade_380a2.php']['uncategorized_description'] = 'Uncategorized Social Groups';
$upgrade_phrases['upgrade_380a2.php']['move_groups_to_default_category'] = 'Moving social groups to uncategorized category';
$upgrade_phrases['upgrade_380a2.php']['updating_profile_categories'] = 'Adding Privacy Settings to Profile Field Categories';
$upgrade_phrases['upgrade_380a2.php']['update_hv_options'] = 'Updating Human Verification options';
$upgrade_phrases['upgrade_380a2.php']['update_album_update_counters'] = 'Updating Album Update counters';
$upgrade_phrases['upgrade_380a2.php']['granting_permissions'] = 'Granting Permissions for new features';

$upgrade_phrases['upgrade_380b1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_380b2.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_380b3.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_380b4.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_380rc1.php']['rebuild_event_cache'] = 'Rebuilding the event cache';

$upgrade_phrases['upgrade_380rc1.php']['steps'] = array(
	1 => 'Cache Rebuilds',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_380rc2.php']['updating_mail_ssl_setting'] = 'Updating SSL mail setting';
$upgrade_phrases['upgrade_380rc2.php']['steps'] = array(
	1 => 'Setting Updates',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_380.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_381.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_382.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_383.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_384.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_385.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_386.php']['steps'] = array(
	1 => 'Misc Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_387b1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_387.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_388a1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_388b1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_388b2.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_388b3.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_388b4.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_388.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_389b1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_389b2.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_389b3.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_389b4.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_389b5.php']['steps'] = array(
	1 => 'Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_389.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3810b1.php']['steps'] = array(
	1 => 'IPv6 Table Alterations',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3810b2.php']['steps'] = array(
	1 => 'Moderator Table Alterations',
	2 => 'Password History Table Alterations',
	3 => 'User Table Alterations (Part A)',
	4 => 'User Table Alterations (Part B)',
	5 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3810rc1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3810rc2.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3810rc3.php']['steps'] = array(
	1 => 'Update Read Marking Option',
	2 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3810rc3.php']['update_read_marking'] = 'Updated Read Marking Option';

$upgrade_phrases['upgrade_3810.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811a1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811a2.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811a3.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811b1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811b2.php']['steps'] = array(
	1 => 'IPv6 Table Alterations',
	2 => 'Group Message IPv6 Updates',
	3 => 'Picture Comment IPv6 Updates',
	4 => 'Visitor Message IPv6 Updates',
	5 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811b3.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811b4.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811rc1.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811rc2.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

$upgrade_phrases['upgrade_3811.php']['steps'] = array(
	1 => 'Upgrade to vBulletin ' . VERSION . ' Complete!'
);

#####################################
# finalupgrade.php phrases
#####################################

$upgrade_phrases['finalupgrade.php']['steps'] = array(
	1 => 'Import latest options',
	2 => 'Import latest admin help',
	3 => 'Import latest language',
	4 => 'Import latest style',
	5 => 'All Done',
);

$upgrade_phrases['finalupgrade.php']['upgrade_start_message'] = "<p>This script will update your templates, settings, language and admin help to the latest versions.</p>\n<p>Press the 'Next Step' button to proceed.</p>";
$upgrade_phrases['finalupgrade.php']['upgrade_version_mismatch'] = '<p>Your vBulletin (%1$s) does not appear to be running the version that was downloaded (%2$s).</p>
		<p>This generally means your upgrade was not successful. Please ensure that you have uploaded the latest files and <a href="upgrade.php">click here</a> to try again.</p>
		<p>If you are sure you wish to continue with this part of the upgrade, <a href="finalupgrade.php?step=1">click here</a>.</p>';

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 93992 $
|| # $Date: 2017-05-10 05:18:19 -0700 (Wed, 10 May 2017) $
|| ####################################################################
\*======================================================================*/
?>
