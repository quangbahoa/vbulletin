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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
chdir('./../');

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('SKIPDB', true);
define('VB_AREA', 'Init');
require_once('./install/init.php');
require_once(DIR . '/includes/functions.php');
exec_header_redirect('upgrade.php');

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023
|| # CVS: $RCSfile$ - $Revision: 32287 $
|| ####################################################################
\*======================================================================*/
