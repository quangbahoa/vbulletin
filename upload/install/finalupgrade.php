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
define('PREV_VERSION', '');
define('THIS_SCRIPT', 'finalupgrade.php');

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// #############################################################################
// welcome step
if ($vbulletin->GPC['step'] == 'welcome')
{
	if ($vbulletin->options['templateversion'] == FILE_VERSION)
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo $upgrade_phrases['finalupgrade.php']['upgrade_start_message'];
		echo "<p>&nbsp;</p></blockquote>";
	}
	else
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo sprintf($upgrade_phrases['finalupgrade.php']['upgrade_version_mismatch'], $vbulletin->options['templateversion'], FILE_VERSION);
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}
}

// #############################################################################
// import vbulletin options
if ($vbulletin->GPC['step'] == 1)
{
	// options might need this, so lets sneak it in
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);
	build_forum_permissions();

	vBulletinHook::build_datastore($db);
	build_product_datastore();

	require_once(DIR . '/includes/adminfunctions_options.php');

	if (!($xml = file_read(DIR . '/install/vbulletin-settings.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-settings.xml') . '</p>';
		print_cp_footer();
	}

	if (isset($vbulletin->options['showdeficon']))
	{
		if ($vbulletin->options['showdeficon'] == 1)
		{ // lets show that bug who's boss! (Scott)
			$vbulletin->options['showdeficon'] = "images/icons/icon1.gif";
		}
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-settings.xml');

	xml_import_settings($xml);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import admin help
if ($vbulletin->GPC['step'] == 2)
{
	require_once(DIR . '/includes/adminfunctions_help.php');

	if (!($xml = file_read(DIR . '/install/vbulletin-adminhelp.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-adminhelp.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-adminhelp.xml');

	xml_import_help_topics($xml);
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import language
if ($vbulletin->GPC['step'] == 3)
{
	require_once(DIR . '/includes/adminfunctions_language.php');

	if (!($xml = file_read(DIR . '/install/vbulletin-language.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-language.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-language.xml');

	xml_import_language($xml);
	build_language();
	build_language_datastore();
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

// #############################################################################
// import style
if ($vbulletin->GPC['step'] == 4)
{
	require_once(DIR . '/includes/adminfunctions_template.php');

	if (!($xml = file_read(DIR . '/install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	xml_import_style($xml);
	build_all_styles();
	echo "<br /><span class=\"smallfont\"><b>$vbphrase[ok]</b></span></p>";
}

if ($vbulletin->GPC['step'] == 5)
{
	$gotopage = '../' . $vbulletin->config['Misc']['admincpdir'] . '/index.php';

	echo '<p align="center" class="smallfont"><a href="' . $gotopage . '">' . $vbphrase['proceed'] . '</a></p>';
	echo "\n<script type=\"text/javascript\">\n";
	echo "window.location=\"$gotopage\";";
	echo "\n</script>\n";

	print_upgrade_footer();
	exit;
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
