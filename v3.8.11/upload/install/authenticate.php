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

if (VB_AREA !== 'Upgrade' AND VB_AREA !== 'Install')
{
	exit;
}

if (!defined('THIS_SCRIPT'))
{
	// don't know how this happened, but we need to define it anyway
	define('THIS_SCRIPT', 'upgrade.php');
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
if (strlen('b54d2f30c39510935e812b415f989a5d') == 32)
{
	/**
	* @ignore
	*/
	define('CUSTOMER_NUMBER', 'b54d2f30c39510935e812b415f989a5d');
}
else
{
	/**
	* @ignore
	*/
	define('CUSTOMER_NUMBER', md5(strtoupper('b54d2f30c39510935e812b415f989a5d')));
}

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (isset($_POST['do']) AND $_POST['do'] == 'login')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'customerid' => TYPE_STR,
	));

	if (md5(strtoupper($vbulletin->GPC['customerid'])) === CUSTOMER_NUMBER)
	{
		setcookie('bbcustomerid', md5(strtoupper($vbulletin->GPC['customerid'])), 0, '/', '');

		// set the style folder
		if (empty($vbulletin->options['cpstylefolder']))
		{
			$vbulletin->options['cpstylefolder'] = 'vBulletin_3_Silver';
		}

		$redirect = '?rand=' . time();

		print_cp_header('', '', "<meta http-equiv=\"Refresh\" content=\"1; URL=$redirect\">");
		?>
		<p>&nbsp;</p><p>&nbsp;</p>
		<blockquote><blockquote><p>
		<b><?php echo $authenticate_phrases['cust_num_success']; ?></b><br />
		<span class="smallfont"><a href="<?php echo $redirect; ?>"><?php echo $authenticate_phrases['redirecting']; ?></a></span>
		</p></blockquote></blockquote>
		<?php

		unset($vbulletin->debug, $GLOBALS['DEVDEBUG']);
		define('NO_CP_COPYRIGHT', true);
		print_cp_footer();
		exit;
	}
}

$vbulletin->input->clean_array_gpc('c', array(
	'bbcustomerid' => TYPE_STR,
));

// #############################################################################
if ($vbulletin->GPC['bbcustomerid'] !== CUSTOMER_NUMBER)
{
	global $stylevar;

	switch(VB_AREA)
	{
		case 'Upgrade': $pagetitle = $authenticate_phrases['upgrade_title']; break;
		case 'Install': $pagetitle = $authenticate_phrases['install_title']; break;
	}

	// set the style folder
	if (empty($vbulletin->options['cpstylefolder']))
	{
		$vbulletin->options['cpstylefolder'] = 'vBulletin_3_Silver';
	}
	// set the forumhome script
	if (empty($vbulletin->options['forumhome']))
	{
		$vbulletin->options['forumhome'] = 'index';
	}
	if (empty($vbulletin->options['bbtitle']))
	{
		if (!empty($bbtitle))
		{
			$vbulletin->options['bbtitle'] = $bbtitle;
		}
		else
		{
			$vbulletin->options['bbtitle'] = $authenticate_phrases['new_installation'];
		}
	}
	// set the version
	$vbulletin->options['templateversion'] = VERSION;

	define('NO_PAGE_TITLE', true);
	print_cp_header($pagetitle, "document.forms.authenticateform.customerid.focus()");

	?>
	<form action="<?php echo THIS_SCRIPT; ?>?do=login" name="authenticateform" method="post">
	<input type="hidden" name="do" value="login" />
	<p>&nbsp;</p><p>&nbsp;</p>
	<table class="tborder" cellpadding="0" cellspacing="0" border="0" width="450" align="center"><tr><td>

		<!-- header -->
		<div class="tcat" style="padding:4px; text-align:center"><b><?php echo $authenticate_phrases['enter_cust_num']; ?></b></div>
		<!-- /header -->

		<!-- logo and version -->
		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="navbody">
		<tr valign="bottom">
			<td><img src="../cpstyles/<?php echo $vbulletin->options['cpstylefolder']; ?>/cp_logo.gif" alt="" border="0" /></td>
			<td>
				<b><a href="../<?php echo $vbulletin->options['forumhome']; ?>.php"><?php echo $vbulletin->options['bbtitle']; ?></a></b><br />
				<?php echo "$pagetitle"; ?><br />
				&nbsp;
			</td>
		</tr>
		</table>
		<!-- /logo and version -->

		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="logincontrols">
		<col width="50%" style="text-align:right; white-space:nowrap"></col>
		<col></col>
		<col width="50%"></col>
		<!-- login fields -->
		<tr valign="top">
			<td>&nbsp;<br /><?php echo $authenticate_phrases['customer_number']; ?><br />&nbsp;</td>
			<td class="smallfont"><input type="text" style="padding-left:5px; font-weight:bold; width:250px" name="customerid" value="" tabindex="1" /><br /><?php echo $authenticate_phrases['cust_num_explanation']; ?></td>
			<td>&nbsp;</td>
		</tr>
		<!-- /login fields -->
		<!-- submit row -->
		<tr>
			<td colspan="3" align="center">
				<input type="submit" class="button" value="<?php echo $authenticate_phrases['enter_system']; ?>" accesskey="s" tabindex="3" />
			</td>
		</tr>
		<!-- /submit row -->
		</table>
	</td></tr></table>
	</form>
	<?php

	unset($vbulletin->debug, $GLOBALS['DEVDEBUG']);
	define('NO_CP_COPYRIGHT', true);
	print_cp_footer();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
