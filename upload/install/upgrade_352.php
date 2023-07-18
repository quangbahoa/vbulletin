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

define('THIS_SCRIPT', 'upgrade_352.php');
define('VERSION', '3.5.2');
define('PREV_VERSION', '3.5.1');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// require the code that makes it all work...
require_once('./upgradecore.php');

// #############################################################################
// welcome step
if ($vbulletin->GPC['step'] == 'welcome')
{
	if ($vbulletin->options['templateversion'] == PREV_VERSION)
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$vbphrase[upgrade_start_message]";
		echo "<p>&nbsp;</p></blockquote>";
	}
	else
	{
		echo "<blockquote><p>&nbsp;</p>";
		echo "$vbphrase[upgrade_wrong_version]";
		echo "<p>&nbsp;</p></blockquote>";
		print_upgrade_footer();
	}
}

// #############################################################################
// Misc Alters
if ($vbulletin->GPC['step'] == 1)
{
	if ($upgrade->field_exists('user', 'skype'))
	{
		$upgrade->run_query(
			$upgrade_phrases['upgrade_352.php']['adding_skype_field'],
			"ALTER TABLE " . TABLE_PREFIX . "user CHANGE skype skype CHAR(32) NOT NULL DEFAULT ''"
		);
	}
	else
	{
		$upgrade->add_field(
			$upgrade_phrases['upgrade_352.php']['adding_skype_field'],
			'user',
			'skype',
			'char',
			array('length' => 32, 'attributes' => FIELD_DEFAULTS)
		);
	}

	$upgrade->add_field(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'regimage', 1, 1),
		'regimage',
		'viewed',
		'smallint',
		FIELD_DEFAULTS
	);

	// Ensure the userfield and language tables are MYISAM

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'userfield', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "userfield ENGINE = MYISAM"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'language', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "language ENGINE = MYISAM"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'smilie', 1, 1),
		"ALTER TABLE " . TABLE_PREFIX . "smilie CHANGE smilietext smilietext CHAR(20) NOT NULL DEFAULT ''"
	);

	$upgrade->run_query(
		sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 1),
		"UPDATE " . TABLE_PREFIX . "user SET
			homepage = REPLACE(
				REPLACE(homepage, '\"', '&quot;'),
				'<',
				'&lt;'
			)
		WHERE homepage LIKE '%<%' OR homepage LIKE '%\"%'"
	);

	$upgrade->execute();
}

// #############################################################################
// FINAL step (notice the SCRIPTCOMPLETE define)
if ($vbulletin->GPC['step'] == 2)
{
	// update banned IPs to the new format to maintain compatibility
	if (trim($vbulletin->options['banip']))
	{
		$addresses = preg_split('#\s+#', trim($vbulletin->options['banip']), -1, PREG_SPLIT_NO_EMPTY);

		$new_ips = '';
		foreach ($addresses AS $ip)
		{
			$octets = explode('.', $ip);
			$octet_count = count($octets);

			if ($octet_count == 4 AND $octets[3] === '')
			{
				// a.b.c. format, so make it a.b.c.*
				$ip .= '*';
			}
			else if ($octet_count < 4 AND $ip{strlen($ip) - 1} != '*')
			{
				// less than 4 octets, so this isn't a complete ip
				if (strlen($octets[$octet_count - 1]) == 3)
				{
					// last octet has 3 chars, so make a.123 into a.123.*
					$ip .= '.';
				}

				$ip .= '*';
			}

			$new_ips .= "\n$ip";
		}
		$new_ips = trim($new_ips);

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "setting SET
				value = '" . $db->escape_string($new_ips) . "'
			WHERE varname = 'banip'
		");
	}

	// update forumlist for paid subscriptions to new format
	$subscriptions = $db->query_read("SELECT subscriptionid, forums FROM " . TABLE_PREFIX . "subscription WHERE forums LIKE '{%'");
	while ($subscription = $db->fetch_array($subscriptions))
	{
		$forumlist = vb_unserialize($subscription['forums']);
		if (is_array($forumlist))
		{
			$forumlist = implode(',', $forumlist);
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "subscription SET
					forums = '" . $db->escape_string($forumlist) . "'
				WHERE subscriptionid = $subscription[subscriptionid]
			");
		}
	}

	// tell log_upgrade_step() that the script is done
	define('SCRIPTCOMPLETE', true);
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
