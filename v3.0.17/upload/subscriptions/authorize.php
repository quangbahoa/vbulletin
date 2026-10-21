<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # All PHP code in this file is 2000-2026 Jelsoft Enterprises Ltd. # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

define('NO_REGISTER_GLOBALS', 1);
define('SESSION_BYPASS', 1);

$phrasegroups = array();
$specialtemplates = array();

chdir('./../');
require('./includes/init.php');
require('./includes/functions.php');
require('./includes/adminfunctions.php');
require('./includes/functions_subscriptions.php');

if (!($vboptions['subscriptionmethods'] & $_SUBSCRIPTIONS['methods']['authorize']))
{
	die("This subscription method is disabled");
}

$check_hash = strtoupper(md5($authorize_md5hash . $vboptions['authorize_loginid'] . $_POST['x_trans_id'] . $_POST['x_amount']));

if ($check_hash == $_POST['x_MD5_Hash'] AND $_POST['x_response_code'] == 1)
{
	$item_number = explode('_', $_POST['x_invoice_num']);
	$subscriptionid = intval($item_number[0]);

	if (empty($item_number[1]) OR empty($item_number[2]))
	{ // non vBulletin subscription
		exit;
	}

	$userid = $DB_site->query_first("SELECT userid, languageid, styleid FROM " . TABLE_PREFIX . "user WHERE userid = " . intval($item_number[1]));
	if ($subscriptionid AND $userid['userid'])
	{
		//its a authorize.net payment and we have some valid ids
		$sub = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = $subscriptionid");
		$cost = unserialize($sub['cost']);

		if ($_POST['x_amount'] == $cost[strtolower($item_number[2])])
		{
			build_user_subscription($subscriptionid, $userid['userid']);
		}
		// echo something back to the user...
		define('LANGUAGEID', iif(empty($userid['languageid']), $vboptions['languageid'], $userid['languageid']));
		$languageinfo = $DB_site->query_first("SELECT phrasegroup_global, options AS lang_options, languagecode AS lang_code, charset AS lang_charset FROM " . TABLE_PREFIX . "language WHERE languageid = " . LANGUAGEID);
		// define language direction (preferable to use $stylevar[textdirection])
		define('LANGUAGE_DIRECTION', iif(($languageinfo['lang_options'] & $_BITFIELD['languageoptions']['direction']), 'ltr', 'rtl'));
		// define html language code (lang="xyz") (preferable to use $stylevar[languagecode])
		define('LANGUAGE_CODE', $languageinfo['lang_code']);
		$vbphrase = unserialize($languageinfo['phrasegroup_global']);
		$styleid = intval($userid['styleid']);

		$style = $DB_site->query_first("
			SELECT * FROM " . TABLE_PREFIX . "style
			WHERE (styleid = $styleid AND userselect = 1) OR styleid = $vboptions[styleid]
			ORDER BY styleid " . iif($styleid > $vboptions['styleid'], 'DESC', 'ASC') . "
			LIMIT 1
		");
		define('STYLEID', $style['styleid']);
		cache_templates(array('STANDARD_REDIRECT', 'headinclude'), $style['templatelist']);
		$stylevar = fetch_stylevars($style, $languageinfo);
		eval('$headinclude = "' . fetch_template('headinclude') . '";');

		$_REQUEST['forceredirect'] = true;
		$url = "$vboptions[bburl]/$vboptions[forumhome].php";
		$nozip = true;

		eval(print_standard_redirect('payment_complete'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12861 $
|| ####################################################################
\*======================================================================*/
?>