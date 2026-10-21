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

class vB_PaidSubscriptionMethod
{
	var $registry = null;
	var $settings = array();

	var $supported_currency = array();
	var $supports_recurring = false;
	var $display_feedback = false;

	var $paymentinfo = array();
	var $transaction_id = '';
	var $type = 0;
	var $error = '';

	function vB_PaidSubscriptionMethod(&$registry)
	{
		if (!is_subclass_of($this, 'vB_PaidSubscriptionMethod'))
		{
			trigger_error('Direct Instantiation of vB_PaidSubscriptionMethod prohibited.', E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;
			if (!is_object($registry->db))
			{
				trigger_error('Database object is not an object', E_USER_ERROR);
			}
		}
		else
		{
			trigger_error('Registry object is not an object', E_USER_ERROR);
		}
	}

	function verify_payment()
	{
		if (!is_subclass_of($this, 'vB_PaidSubscriptionMethod'))
		{
			trigger_error('verify_payment should be overloaded by the child class', E_USER_ERROR);
		}
	}

	function generate_form_html($hash, $cost, $currency, $subinfo, $userinfo)
	{
		$form = array();
		($hook = vBulletinHook::fetch_hook('paidsub_construct_payment')) ? eval($hook) : false;
		return $form;
	}
}

class vB_PaidSubscription
{
	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* The HTML currency symbols
	*
	* @var	_CURRENCYSYMBOLS
	*/
	var $_CURRENCYSYMBOLS = array(
		'usd' => 'US$',
		'gbp' => '&pound;',
		'eur' => '&euro;',
		'cad' => 'CA$',
		'aud' => 'AU$',
	);

	/**
	* The extra paypal option bitfields
	*
	* @var	_SUBSCRIPTIONS
	*/
	var $_SUBSCRIPTIONOPTIONS = array(
		'tax'       => 1,
		'shipping1' => 2,
		'shipping2' => 4,
	);

	/**
	* The subscription cache array, indexed by subscriptionid
	*
	* @var	subscriptioncache
	*/
	var $subscriptioncache = array();

	/**
	* Constructor
	*
	* @param	vB_Registry	Reference to registry object
	*/
	function vB_PaidSubscription(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_PaidSubscription::Registry object is not an object", E_USER_ERROR);
		}
	}

	/**
	* Adds a unix timestamp and an english date together
	*
	* @param	int		Unix timestamp
	* @param	int		Number of units to add to timestamp
	* @param	string	The units of the number parameter
	*
	* @return	int		Unix timestamp
	*/
	function fetch_proper_expirydate($regdate, $length, $units)
	{
		// conver the string to an integer by adding 0
		$length = $length + 0;
		$regdate = $regdate + 0;
		if (!is_int($regdate) OR !is_int($length) OR !is_string($units))
		{ // its not a valid date
			return false;
		}

		$units_full = array(
			'D' => 'day',
			'W' => 'week',
			'M' => 'month',
			'Y' => 'year'
		);
		// lets get a formatted string that strtotime will understand
		$formatted = date('d F Y H:i', $regdate);
	
		// now lets add the appropriate terms
		$time = strtotime("$formatted + $length " . $units_full["$units"]);

		// Protect against possible errors with PHP 5.1.x
		if ($time <= 0)
		{
			trigger_error('strtotime returned an invalid value, upgrade PHP', E_USER_ERROR);
		}

		return $time;
	}

	/**
	* Creates user subscription
	*
	* @param	int		The id of the subscription
	* @param	int		The subid of the subscription, this indicates the length
	* @param	int		The userid the subscription is to be applied to
	* @param	int		The start timestamp of the subscription
	* @param	int		The expiry timestamp of the subscription
	*
	*/
	function build_user_subscription($subscriptionid, $subid, $userid, $regdate = 0, $expirydate = 0)
	{

		//first three variables are pretty self explanitory
		//the 4thrd is used to decide if the user is subscribing to the subscription for the first time or rejoining
		global $vbulletin;

		$subscriptionid = intval($subscriptionid);
		$subid = intval($subid);
		$userid = intval($userid);
	
		$this->cache_user_subscriptions();
		$sub =& $this->subscriptioncache["$subscriptionid"];
		$tmp = unserialize($sub['cost']);
		if (is_array($tmp["$subid"]) AND $subid != -1)
		{
			$sub = array_merge($sub, $tmp["$subid"]);
		}
		unset($tmp);

		$user = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
		$currentsubscription = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscriptionlog WHERE userid = $userid AND subscriptionid = $subscriptionid");

		// no value passed in for regdate and we have a currently active subscription
		if ($regdate <= 0 AND $currentsubscription['regdate'] AND $currentsubscription['status'])
		{
			$regdate = $currentsubscription['regdate'];
		}
		// no value passed and no active subscription
		else if ($regdate <= 0)
		{
			$regdate = TIMENOW;
		}

		if ($expirydate <= 0 AND $currentsubscription['expirydate'] AND $currentsubscription['status'])
		{
			$expirydate_basis = $currentsubscription['expirydate'];
		}
		else if ($expirydate <= 0 OR $expirydate <= $regdate)
		{
			$expirydate_basis = $regdate;
		}

		if ($expirydate_basis)
		{ // active subscription base the value on our current expirydate
			$expirydate = $this->fetch_proper_expirydate($expirydate_basis, $sub['length'], $sub['units']);
		}

		if ($user['userid'] AND $sub['subscriptionid'])
		{
			$userdm =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
			$userdm->set_existing($user);

			//access masks
			$subscription_forums = unserialize($sub['forums']);
			if (is_array($subscription_forums))
			{
				// convert serialized to CSV and update subscription
				$subscription_forums = array_keys($subscription_forums);
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "subscription
					SET forums = '" . $this->registry->db->escape_string(implode(',', $subscription_forums)) . "'
					WHERE subscriptionid = $subscriptionid
				");
			}
			else
			{
				$subscription_forums = preg_split('#,#', $sub['forums'], -1, PREG_SPLIT_NO_EMPTY);
			}
			if (is_array($subscription_forums) AND !empty($subscription_forums))
			{
				// double check since we might not have fetched this -- this might not be necessary
				require_once(DIR . '/includes/functions.php');
				$origsize = sizeof($subscription_forums);

				//require_once(DIR . '/includes/functions_databuild.php');
				//cache_forums();
				$forumlist = "0";

				foreach ($subscription_forums AS $key => $forumid)
				{
					if (!empty($this->registry->forumcache["$forumid"]))
					{
						$forumlist .= ",$forumid";
						$forumsql[] = "($userid, $forumid, 1)";
					}
					else
					{ //oops! it seems that some of the subscribed forums have been deleted, lets unset it
						unset($subscription_forums["$key"]);
					}
				}
				$this->registry->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "access
					WHERE forumid IN ($forumlist) AND
						userid = $userid
				");

				if ($origsize != sizeof($subscription_forums))
				{
					$this->registry->db->query_write("
						UPDATE " . TABLE_PREFIX . "subscription
						SET forums = '" . $this->registry->db->escape_string(implode(',', $subscription_forums)) . "'
						WHERE subscriptionid = $subscriptionid
					");
				}

				if (!empty($forumsql))
				{
					$forumsql = implode($forumsql, ', ');
					/*insert query*/
					$this->registry->db->query_write("
						INSERT INTO " . TABLE_PREFIX . "access
						(userid, forumid, accessmask)
						VALUES " .
						$forumsql
					);
					$userdm->set_bitfield('options', 'hasaccessmask', true);
				}
			}

			$noalter = explode(',', $vbulletin->config['SpecialUsers']['undeletableusers']);
			if (empty($noalter[0]) OR !in_array($userid, $noalter))
			{
				//membergroupids and usergroupid
				if (!empty($sub['membergroupids']))
				{
					$membergroupids = array_merge(fetch_membergroupids_array($user, false), array_diff(fetch_membergroupids_array($sub, false), fetch_membergroupids_array($user, false)));
				}
				else
				{
					$membergroupids = fetch_membergroupids_array($user, false);
				}
				
				if ($sub['nusergroupid'] > 0)
				{
					$userdm->set('usergroupid', $sub['nusergroupid']);
					$userdm->set('displaygroupid', 0);

					if ($user['customtitle'] == 0)
					{
						$usergroup = $this->registry->db->query_first("
							SELECT usertitle
							FROM " . TABLE_PREFIX . "usergroup
							WHERE usergroupid = $sub[nusergroupid]
						");
						if (!empty($usergroup['usertitle']))
						{
							$userdm->set('usertitle', $usergroup['usertitle']);
						}
					}
				}
				$userdm->set('membergroupids', implode($membergroupids, ','));
			}

			$userdm->save();
			unset($userdm);

			if (!$currentsubscription['subscriptionlogid'])
			{
				/*insert query*/
				$this->registry->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "subscriptionlog
					(subscriptionid, userid, pusergroupid, status, regdate, expirydate)
					VALUES
					($subscriptionid, $userid, $user[usergroupid], 1, $regdate, $expirydate)
				");
			}
			else
			{
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "subscriptionlog
					SET status = 1,
					" . iif(!$currentsubscription['status'], "pusergroupid = $user[usergroupid],") . "
					regdate = $regdate,
					expirydate = $expirydate
					WHERE userid = $userid AND
						subscriptionid = $subscriptionid
				");
			}
	
			($hook = vBulletinHook::fetch_hook('paidsub_build')) ? eval($hook) : false;
		}
	}

	/**
	* Removes user subscription
	*
	* @param	int		The id of the subscription
	* @param	int		The userid the subscription is to be removed from
	*
	*/
	function delete_user_subscription($subscriptionid, $userid)
	{
		$subscriptionid = intval($subscriptionid);
		$userid = intval($userid);

		$this->cache_user_subscriptions();
		$sub =& $this->subscriptioncache["$subscriptionid"];
		$user = $this->registry->db->query_first("
			SELECT user.*, subscriptionlog.pusergroupid,
			IF (user.displaygroupid=0, user.usergroupid, user.displaygroupid) AS displaygroupid,
			IF (usergroup.genericoptions & " . $this->registry->bf_ugp_genericoptions['isbannedgroup'] . ", 1, 0) AS isbanned,
			userban.usergroupid AS busergroupid, userban.displaygroupid AS bandisplaygroupid
			FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
			INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
			INNER JOIN " . TABLE_PREFIX . "usergroup AS usergroup USING (usergroupid)
			LEFT JOIN " . TABLE_PREFIX . "userban AS userban ON (userban.userid = user.userid)
			WHERE subscriptionlog.userid = $userid AND
				subscriptionlog.subscriptionid = $subscriptionid
		");

		if ($user['userid'] AND $sub['subscriptionid'])
		{
			$userdm =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
			$userdm->set_existing($user);

			//access masks
			$subscription_forums = unserialize($sub['forums']);
			if (is_array($subscription_forums))
			{
				// convert serialized to CSV and update subscription
				$sub['forums'] = implode(',', array_keys($subscription_forums));
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "subscription
					SET forums = '" . $this->registry->db->escape_string($sub['forums']) . "'
					WHERE subscriptionid = $subscriptionid
				");
				// so the thing below works out
				$subscription_forums = false;
			}
			if (!empty($sub['forums']) AND $subscription_forums === false)
			{
				$this->registry->db->query_write("
					DELETE FROM " . TABLE_PREFIX . "access
					WHERE forumid IN ($sub[forums]) AND
						userid = $userid
				");
			}
			$countaccess = $this->registry->db->query_first("
				SELECT COUNT(*) AS masks
				FROM " . TABLE_PREFIX . "access
				WHERE userid = $userid
			");

			$membergroupids = array_diff(fetch_membergroupids_array($user, false), fetch_membergroupids_array($sub, false));
			$update_userban = false;

			if ($sub['nusergroupid'] == $user['usergroupid'] AND $user['usergroupid'] != $user['pusergroupid'])
			{
				// check if there are other active subscriptions that set the same primary usergroup
				foreach ($this->subscriptioncache AS $subcheck)
				{
					if ($subcheck['nusergroupid'] == $user['usergroupid'] AND $subcheck['subscriptionid'] != $subscriptionid)
					{
						$subids .= ",$subcheck[subscriptionid]";
					}
				}
				if (!empty($subids))
				{
					$activesub = $this->registry->db->query_first("
						SELECT * FROM " . TABLE_PREFIX . "subscriptionlog
						WHERE userid = $userid
							AND subscriptionid IN (0$subids)
							AND status = 1
						ORDER BY expirydate DESC
						LIMIT 1
					");
				}
				if ($activesub)
				{
					// there is at least one active subscription with the same primary usergroup, so alter its resetgroup
					$this->registry->db->query_write("UPDATE " . TABLE_PREFIX . "subscriptionlog SET pusergroupid = $user[pusergroupid] WHERE subscriptionlogid = $activesub[subscriptionlogid]");	
					// don't touch usertitle/displaygroup
					$user['pusergroupid'] = $user['usergroupid'];
					$sub['nusergroupid'] = 0;
				}
				else
				{
					$userdm->set('usergroupid', $user['pusergroupid']);
				}
			}
			else if ($user['isbanned'] AND $user['busergroupid'] == $sub['nusergroupid'])
			{
				$update_userban = true;
				$userbansql['usergroupid'] = $user['pusergroupid'];
			}
			$groups = iif(!empty($sub['membergroupids']), $sub['membergroupids'] . ',') . $sub['nusergroupid'];

			if (in_array ($user['displaygroupid'], explode(',', $groups)))
			{ // they're displaying as one of the usergroups in the subscription
				$user['displaygroupid'] = 0;
			}
			else if ($user['isbanned'] AND in_array ($user['bandisplaygroupid'], explode(',', $groups)))
			{
				$update_userban = true;
				$userbansql['displaygroupid'] = 0;
			}

			// do their old groups still allow custom titles?
			$reset_title = false;
			if ($user['customtitle'] == 1)
			{
				$groups = iif(!empty($user['membergroupids']), $user['membergroupids'] . ',') . $user['pusergroupid'];
				$usergroup = $this->registry->db->query_first("
					SELECT usergroupid
					FROM " . TABLE_PREFIX . "usergroup
					WHERE (genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canusecustomtitle'] . ")
						AND usergroupid IN ($groups)
				");

				if (empty($usergroup['usergroupid']))
				{
					// no custom group any more lets set it back to the default
					$reset_title = true;
				}
			}

			if (($sub['nusergroupid'] > 0 AND $user['customtitle'] == 0) OR $reset_title)
			{ // they need a default title
				$usergroup = $this->registry->db->query_first("
					SELECT usertitle
					FROM " . TABLE_PREFIX . "usergroup
					WHERE usergroupid = $user[pusergroupid]
				");
				if (empty($usergroup['usertitle']))
				{ // should be a title based on minposts it seems then
					$usergroup = $this->registry->db->query_first("
						SELECT title AS usertitle
						FROM " . TABLE_PREFIX . "usertitle
						WHERE minposts <= $user[posts]
						ORDER BY minposts DESC
					");
				}
	
				if ($user['isbanned'])
				{
					$update_userban = true;
					$userbansql['customtitle'] = 0;
					$userbansql['usertitle'] = $usergroup['usertitle'];
				}
				else
				{
					$userdm->set('customtitle', 0);
					$userdm->set('usertitle', $usergroup['usertitle']);
				}
			}
	
			$userdm->set('membergroupids', implode($membergroupids, ','));
			$userdm->set_bitfield('options', 'hasaccessmask', ($countaccess['masks'] ? true : false));
			$userdm->set('displaygroupid', $user['displaygroupid']);

			$userdm->save();
			unset($userdm);

			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "subscriptionlog
				SET status = 0
				WHERE subscriptionid = $subscriptionid AND
				userid = $userid
			");

			if ($update_userban)
			{
				$this->registry->db->query_write(fetch_query_sql($userbansql, 'userban', "WHERE userid = $user[userid]"));
			}

			// also do email activation to stop those sneaky people
			$this->registry->db->query_write("UPDATE " . TABLE_PREFIX . "useractivation SET usergroupid = $user[pusergroupid] WHERE userid = $user[userid]");

			$mysubs = $this->registry->db->query_read("SELECT * FROM " . TABLE_PREFIX . "subscriptionlog WHERE status = 1 AND userid = $userid");
			while ($mysub = $this->registry->db->fetch_array($mysubs))
			{
				$this->build_user_subscription($mysub['subscriptionid'], -1, $userid, $mysub['regdate'], $mysub['expirydate']);
			}

			($hook = vBulletinHook::fetch_hook('paidsub_delete')) ? eval($hook) : false;
		}
	}

	/**
	* Caches the subscriptions from the database into an array
	*/
	function cache_user_subscriptions()
	{
		if (empty($this->subscriptioncache))
		{
			$subscriptions = $this->registry->db->query_read("SELECT * FROM " . TABLE_PREFIX . "subscription ORDER BY displayorder");
			while ($subscription = $this->registry->db->fetch_array($subscriptions))
			{
				$this->subscriptioncache["$subscription[subscriptionid]"] = $subscription;
			}
			$this->registry->db->free_result($subscriptions);
		}
	}

	/**
	* Constructs the payment form
	*
	* @param	string	A 32 character hash corresponding to the entry in the paymentinfo table
	* @param	array	Array containing the API information for the form to be constructed for
	* @param	array	Array containing specific data about the cost and time for the specific subscription period
	* @param	string	The currency of the cost
	* @param	array	Array containing the entry from the subscription table
	* @param	array	Array containing the userinfo of the user purchasing the subscription
	*
	* @return	array|bool	The array containing the form data or false on error
	*/
	function construct_payment($hash, $methodinfo, $timeinfo, $currency, $subinfo, $userinfo)
	{
		if (file_exists(DIR . '/includes/paymentapi/class_' . $methodinfo['classname'] . '.php'))
		{
			require_once(DIR . '/includes/paymentapi/class_' . $methodinfo['classname'] . '.php');
			$api_class = 'vB_PaidSubscriptionMethod_' . $methodinfo['classname'];
			$obj = new $api_class($this->registry);
			if (!empty($methodinfo['settings']))
			{ // need to convert this from a serialized array with types to a single value
				$obj->settings = $this->construct_payment_settings($methodinfo['settings']);
			}
			return $obj->generate_form_html($hash, $timeinfo['cost']["$currency"], $currency, $subinfo, $userinfo, $timeinfo);
		}
		// maybe throw an error about the lack of a class?
		return false;
	}

	/**
	* Prepares the API settings array
	*
	* @param	string	Serialized string
	*
	* @return	array	Array containing the settings after being converted to the correct index format
	*/
	function construct_payment_settings($serialized_settings)
	{
		$methodsettings = unserialize($serialized_settings);
		$settings = array();
		// could probably do with finding a nicer solution to the following
		$settings['_SUBSCRIPTIONOPTIONS'] =& $this->_SUBSCRIPTIONOPTIONS;
		if (is_array($methodsettings))
		{
			foreach ($methodsettings AS $key => $info)
			{
				$settings["$key"] = $info['value'];
			}
		}
		return $settings;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14288 $
|| ####################################################################
\*======================================================================*/
?>