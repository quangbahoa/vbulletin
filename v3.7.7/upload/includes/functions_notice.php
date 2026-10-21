<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.7.7 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
* Checks if the specified criteria is between 2 values.
* If either bound is the empty string, it is ignored.
* Bounds are inclusive on either side (>= / <=).
*
* @param	integer			Value to check
* @param	string|integer	Lower bound. If === '', ignored.
* @param	string|integer	Upper bound. If === '', ignored.
*
* @return	boolean			True if between
*/
function check_notice_criteria_between($value, $cond1, $cond2)
{
	if ($cond1 === '')
	{
		// no value for first condition, treat as <= $cond2
		return ($value <= intval($cond2));
	}
	else if ($cond2 === '')
	{
		// no value for second condition, treat as >= $cond1
		return ($value >= intval($cond2));
	}
	else
	{
		// check that value is between (inclusive) the two given conditions
		return ($value >= intval($cond1) AND $value <= intval($cond2));
	}
}

/**
* Fetches the IDs of the notices to display on a particular page.
*
* @return	array	Array of IDs to display
*/
function fetch_relevant_notice_ids()
{
	global $vbulletin, $vbphrase, $stylevar, $foruminfo, $threadinfo, $postinfo;


	$forum_pages = array('poll', 'editpost', 'threadrate', 'postings', 'showthread', 'newthread', 'forumdisplay', 'newreply', 'threadtag', 'inlinemod', 'announcement', 'showpost');

	$ignore_np_notices = explode(',', $_COOKIE[COOKIE_PREFIX . 'np_notices_displayed']);
	$display_notices = array();
	$vbulletin->np_notices_displayed = array();

	($hook = vBulletinHook::fetch_hook('notices_check_start')) ? eval($hook) : false;

	foreach ($vbulletin->noticecache AS $noticeid => $notice)
	{
		foreach ($notice AS $criteriaid => $conditions)
		{
			switch ($criteriaid)
			{
				case 'persistent':
				{
					if ($conditions == 0 AND in_array($noticeid, $ignore_np_notices)) // session cookie set in print_output()
					{
						continue 3;
					}
					break;
				}
				/*case 'notice_x_not_displayed': // this is now handled differently - see $remove_display_notices below
				{
					if (in_array(intval($conditions[0]), $display_notices))
					{
						continue 3;
					}
					break;
				}*/
				case 'in_usergroup_x':
				{
					if (!is_member_of($vbulletin->userinfo, intval($conditions[0])))
					{
						continue 3;
					}
					break;
				}
				case 'not_in_usergroup_x':
				{
					if (is_member_of($vbulletin->userinfo, intval($conditions[0])))
					{
						continue 3;
					}
					break;
				}
				case 'browsing_forum_x':
				{
					if ($foruminfo['forumid'] != intval($conditions[0]) OR !in_array(THIS_SCRIPT, $forum_pages))
					{
						continue 3;
					}
					break;
				}
				case 'browsing_forum_x_and_children':
				{
					if (!in_array(intval($conditions[0]), explode(',', $foruminfo['parentlist'])) OR !in_array(THIS_SCRIPT, $forum_pages))
					{
						continue 3;
					}
					break;
				}
				case 'no_visit_in_x_days':
				{
					if ($vbulletin->userinfo['lastvisit'] > TIMENOW - $conditions[0] * 86400)
					{
						continue 3;
					}
					break;
				}
				case 'has_never_posted':
				{
					if ($vbulletin->userinfo['lastpost'] > 0)
					{
						continue 3;
					}
					break;
				}
				case 'no_posts_in_x_days':
				{
					if ($vbulletin->userinfo['lastpost'] == 0 OR $vbulletin->userinfo['lastpost'] > TIMENOW - $conditions[0] * 86400)
					{
						continue 3;
					}
					break;
				}
				case 'has_x_postcount':
				{
					if (!check_notice_criteria_between($vbulletin->userinfo['posts'], $conditions[0], $conditions[1]))
					{
						continue 3;
					}
					break;
				}
				case 'has_x_reputation':
				{
					if (!check_notice_criteria_between($vbulletin->userinfo['reputation'], $conditions[0], $conditions[1]))
					{
						continue 3;
					}
					break;
				}
				case 'has_x_infraction_points':
				{
					if (!check_notice_criteria_between($vbulletin->userinfo['ipoints'], $conditions[0], $conditions[1]))
					{
						continue 3;
					}
					break;
				}
				case 'pm_storage_x_percent_full':
				{
					if ($vbulletin->userinfo['permissions']['pmquota'])
					{
						$pmboxpercentage = $vbulletin->userinfo['pmtotal'] / $vbulletin->userinfo['permissions']['pmquota'] * 100;
						if (!check_notice_criteria_between($pmboxpercentage, $conditions[0], $conditions[1]))
						{
							continue 3;
						}
					}
					else
					{
						continue 3;
					}
					break;
				}
				case 'username_is':
				{
					if (strtolower($vbulletin->userinfo['username']) != strtolower(trim($conditions[0])))
					{
						continue 3;
					}
					break;
				}
				case 'is_birthday':
				{
					if (substr($vbulletin->userinfo['birthday'], 0, 5) != vbdate('m-d', TIMENOW, false, false))
					{
						continue 3;
					}
					break;
				}
				case 'style_is_x':
				{
					if (STYLEID != intval($conditions[0]))
					{
						continue 3;
					}
					break;
				}
				case 'in_coventry':
				{
					if (!in_array($vbulletin->userinfo['userid'], preg_split('#\s+#', $vbulletin->options['globalignore'], -1, PREG_SPLIT_NO_EMPTY)))
					{
						continue 3;
					}
					break;
				}
				case 'is_date':
				{
					if (vbdate('d-m-Y', TIMENOW, false, false) != $conditions[0])
					{
						continue 3;
					}
					break;
				}
				default:
				{
					$abort = false;

					($hook = vBulletinHook::fetch_hook('notices_check_criteria')) ? eval($hook) : false;

					if ($abort)
					{
						continue 3;
					}
				}
			}
		}

		$display_notices["$noticeid"] = $noticeid;

		if ($notice['persistent'] == 0)
		{
			$vbulletin->np_notices_displayed["$noticeid"] = $noticeid;
		}
	}

	// now go through removing notices using the 'notice_x_not_displayed' criteria
	$remove_display_notices = array();
	foreach ($vbulletin->noticecache AS $noticeid => $notice)
	{
		if (isset($notice['notice_x_not_displayed']) AND isset($display_notices[intval($notice['notice_x_not_displayed'][0])]))
		{
			$remove_display_notices["$noticeid"] = $noticeid;
		}
	}
	foreach ($remove_display_notices AS $noticeid)
	{
		unset($display_notices["$noticeid"], $vbulletin->np_notices_displayed["$noticeid"]);
	}

	return $display_notices;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 26621 $
|| ####################################################################
\*======================================================================*/
?>