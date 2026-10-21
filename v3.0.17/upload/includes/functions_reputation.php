<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

// ## Function takes an array from fetch_userinfo and an array from cache_permissions()
// ## Returns the user's reputation altering power (for positive)
function fetch_reppower(&$userinfo, &$perms, $reputation = 'pos')
{
	global $vboptions;

	// User does not have permission to leave negative reputation
	if (!($perms['genericpermissions'] & CANNEGATIVEREP))
	{
		$reputation = 'pos';
	}

	if (!($perms['genericpermissions'] & CANUSEREP))
	{
		$reppower = 0;
	}
	else if ($perms['adminpermissions'] & CANCONTROLPANEL AND $vboptions['adminpower'])
	{
		$reppower = iif($reputation != 'pos', $vboptions['adminpower'] * -1, $vboptions['adminpower']);
	}
	else if (($userinfo['posts'] < $vboptions['minreputationpost']) OR ($userinfo['reputation'] < $vboptions['minreputationcount']))
	{
		$reppower = 0;
	}
	else
	{
		$reppower = 1;

		if ($vboptions['pcpower'])
		{
			$reppower += intval($userinfo['posts'] / $vboptions['pcpower']);
		}
		if ($vboptions['kppower'])
		{
			$reppower += intval($userinfo['reputation'] / $vboptions['kppower']);
		}
		if ($vboptions['rdpower'])
		{
			$reppower += intval(intval((TIMENOW - $userinfo['joindate']) / 86400) / $vboptions['rdpower']);
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

	return $reppower;
}

// ###################### Start getreputationimage #######################
function fetch_reputation_image(&$post, &$perms)
{
	global $vboptions, $stylevar, $vbphrase;

	if (!$vboptions['reputationenable'])
	{
		return true;
	}

	$reputation_value = $post['reputation'];
	if ($post['reputation'] == 0)
	{
		$reputationgif = 'balance';
		$reputation_value = $post['reputation'] * -1;
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
		$reputation_value = ($reputation_value - ($reputation_value - 500)) + (($reputation_value - 500) / 2);
	}

	$reputationbars = intval($reputation_value / 100); // award 1 reputation bar for every 100 points
	if ($reputationbars > 10)
	{
		$reputationbars = 10;
	}

	if (!$post['showreputation'] AND $perms['genericpermissions'] & CANHIDEREP)
	{
		$posneg = 'off';
		$post['level'] = $vbphrase['reputation_disabled'];
		eval('$post[\'reputationdisplay\'] = "' . fetch_template('postbit_reputation') . '";');
	}
	else
	{
		if (!$post['reputationlevelid'])
		{
			$post['level'] = $vboptions['reputationundefined'];
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
			eval('$post[\'reputationdisplay\'] .= "' . fetch_template('postbit_reputation') . '";');
		}
	}

	return true;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 9647 $
|| ####################################################################
\*======================================================================*/
?>