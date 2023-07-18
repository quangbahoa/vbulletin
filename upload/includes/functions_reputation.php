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

// ## Function takes an array from fetch_userinfo and an array from cache_permissions()
// ## Returns the user's reputation altering power (for positive)
function fetch_reppower(&$userinfo, &$perms, $reputation = 'pos')
{
	global $vbulletin;

	// User does not have permission to leave negative reputation
	if (!($perms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cannegativerep']))
	{
		$reputation = 'pos';
	}

	if (!($perms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuserep']))
	{
		$reppower = 0;
	}
	else if ($perms['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'] AND $vbulletin->options['adminpower'])
	{
		$reppower = iif($reputation != 'pos', $vbulletin->options['adminpower'] * -1, $vbulletin->options['adminpower']);
	}
	else if (($userinfo['posts'] < $vbulletin->options['minreputationpost']) OR ($userinfo['reputation'] < $vbulletin->options['minreputationcount']))
	{
		$reppower = 0;
	}
	else
	{
		$reppower = 1;

		if ($vbulletin->options['pcpower'])
		{
			$reppower += intval($userinfo['posts'] / $vbulletin->options['pcpower']);
		}
		if ($vbulletin->options['kppower'])
		{
			$reppower += intval($userinfo['reputation'] / $vbulletin->options['kppower']);
		}
		if ($vbulletin->options['rdpower'])
		{
			$reppower += intval(intval((TIMENOW - $userinfo['joindate']) / 86400) / $vbulletin->options['rdpower']);
		}

		if ($reputation != 'pos')
		{
			// make negative reputation worth half of positive, but at least 1
			$reppower = intval($reppower / 2);
			if ($reppower < 1)
			{
				$reppower = 1;
			}
			$reppower *= -1;
		}
	}

	($hook = vBulletinHook::fetch_hook('reputation_power')) ? eval($hook) : false;

	return $reppower;
}

// ###################### Start getreputationimage #######################
function fetch_reputation_image(&$post, &$perms)
{
	global $stylevar, $vbphrase, $vbulletin;

	if (!$vbulletin->options['reputationenable'])
	{
		return true;
	}

	$reputation_value = $post['reputation'];
	if ($post['reputation'] == 0)
	{
		$reputationgif = 'balance';
		$reputation_value = 0;
	}
	else if ($post['reputation'] < 0)
	{
		$reputationgif = 'neg';
		$reputationhighgif = 'highneg';
		$reputation_value = $post['reputation'] * -1;
	}
	else
	{
		$reputationgif = 'pos';
		$reputationhighgif = 'highpos';
	}

	if ($reputation_value > 500)
	{  // bright green bars take 200 pts not the normal 100
		$reputation_value = ($reputation_value / 2) + 250;
	}

	$reputationbars = intval($reputation_value / 100); // award 1 reputation bar for every 100 points
	if ($reputationbars > 10)
	{
		$reputationbars = 10;
	}

	if (!$post['showreputation'] AND $perms['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canhiderep'])
	{
		$posneg = 'off';
		$post['level'] = $vbphrase['reputation_disabled'];

		eval('$post[\'reputationdisplay\'] = "' . fetch_template('postbit_reputation') . '";');
	}
	else
	{
		if (!$post['reputationlevelid'])
		{
			$post['level'] = $vbulletin->options['reputationundefined'];
		}
		for ($i = 0; $i <= $reputationbars; $i++)
		{
			if ($i >= 5)
			{
				$posneg = $reputationhighgif;
			}
			else
			{
				$posneg = $reputationgif;
			}

			$post['level'] = $vbphrase['reputation' . $post['reputationlevelid']];
			eval('$post[\'reputationdisplay\'] .= "' . fetch_template('postbit_reputation') . '";');
		}
	}

	($hook = vBulletinHook::fetch_hook('reputation_image')) ? eval($hook) : false;

	return true;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
