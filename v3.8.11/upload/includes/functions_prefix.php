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

/**
* Fetches an array of prefixes for the specified forum. Returned in format:
* [prefixsetid][] = prefixid
*
* @param	integer	Forum ID to fetch prefixes from
*
* @return	array
*/
function fetch_prefix_array($forumid)
{
	global $vbulletin;

	$forumid = intval($forumid);
	if (isset($vbulletin->prefixcache))
	{
		return ((isset($vbulletin->prefixcache[$forumid]) AND is_array($vbulletin->prefixcache[$forumid])) ? $vbulletin->prefixcache[$forumid] : array());
	}
	else
	{
		$prefixsets = array();
		$prefix_sql = $vbulletin->db->query_read("
			SELECT prefix.*, prefixpermission.usergroupid AS restriction
			FROM " . TABLE_PREFIX . "forumprefixset AS forumprefixset
			INNER JOIN " . TABLE_PREFIX . "prefixset AS prefixset ON (prefixset.prefixsetid = forumprefixset.prefixsetid)
			INNER JOIN " . TABLE_PREFIX . "prefix AS prefix ON (prefix.prefixsetid = prefixset.prefixsetid)
			LEFT JOIN " . TABLE_PREFIX . "prefixpermission AS prefixpermission ON (prefix.prefixid = prefixpermission.prefixid)
			WHERE forumprefixset.forumid = $forumid
			ORDER BY prefixset.displayorder, prefix.displayorder
		");
		while ($prefix = $vbulletin->db->fetch_array($prefix_sql))
		{
			if (empty($prefixsets["$prefix[prefixsetid]"]["$prefix[prefixid]"]))
			{
				$prefixsets[$prefix['prefixsetid']][$prefix['prefixid']] = array(
					'prefixid' => $prefix['prefixid'],
					'restrictions' => array()
				);
			}

			if ($prefix['restriction'])
			{
				$prefixsets[$prefix['prefixsetid']][$prefix['prefixid']]['restrictions'][] = $prefix['restriction'];
			}
		}

		$return = (is_array($prefixsets) ? $prefixsets : array());

		($hook = vBulletinHook::fetch_hook('prefix_fetch_array')) ? eval($hook) : false;

		return $return;
	}
}

/**
 * Prefix Permission Check
 *
 * @param	string	The prefix ID to check
 * @param	array	The restricted usergroups (used when we have the restrictions already)
 *
 * @return 	boolean
 */
function can_use_prefix($prefixid, $restrictions = null)
{
	global $vbulletin;

	if (!is_array($restrictions))
	{
		$restrictions = array();
		$restrictions_db = $vbulletin->db->query_read("
			SELECT prefixpermission.usergroupid
			FROM " . TABLE_PREFIX . "prefixpermission AS prefixpermission
			WHERE prefixpermission.prefixid = '" . $vbulletin->db->escape_string($prefixid) . "'
		");

		while ($restriction = $vbulletin->db->fetch_array($restrictions_db))
		{
			$restrictions[] = intval($restriction['usergroupid']);
		}
	}

	if (empty($restrictions))
	{
		return true;
	}

	$membergroups = fetch_membergroupids_array($vbulletin->userinfo);
	$infractiongroups = explode(',', str_replace(' ', '', $vbulletin->userinfo['infractiongroupids']));

	foreach ($restrictions AS $usergroup)
	{
		if (in_array($usergroup, $infractiongroups))
		{
			return false;
		}
	}

	if (!count(array_diff($membergroups, $restrictions)))
	{
		return false;
	}

	return true;
}

/**
* Returns HTML of options/optgroups for direct display in a template for the
* selected forum.
*
* @param	integer	Forum ID to show prefixes from
* @param	string	Selected prefix ID
* @param	boolean	Whether to check whether the user can use the prefix before returning it in the list
*
* @return	string	HTML to output
*/
function fetch_prefix_html($forumid, $selectedid = '', $permcheck = false)
{
	global $vbulletin, $stylevar, $vbphrase;

	$prefix_options = '';
	if ($prefixsets = fetch_prefix_array($forumid))
	{
		foreach ($prefixsets AS $prefixsetid => $prefixes)
		{
			$optgroup_options = '';
			foreach ($prefixes AS $prefixid => $prefix)
			{
				if ($permcheck AND !can_use_prefix($prefixid, $prefix['restrictions']) AND $prefixid != $selectedid)
				{
					continue;
				}

				$optionvalue = $prefixid;
				$optiontitle = htmlspecialchars_uni($vbphrase["prefix_{$prefixid}_title_plain"]);
				$optionselected = ($prefixid == $selectedid ? ' selected="selected"' : '');

				eval('$optgroup_options .= "' . fetch_template('option') . '";');
			}

			// Make sure we dont try to add the prefix set if we've restricted them all.
			if ($optgroup_options != '')
			{

				// if there's only 1 prefix set available, we don't want to show the optgroup
				if (sizeof($prefixsets) > 1)
				{
					$optgroup_label = htmlspecialchars_uni($vbphrase["prefixset_{$prefixsetid}_title"]);
					eval('$prefix_options .= "' . fetch_template('optgroup') . '";');
				}
				else
				{
					$prefix_options = $optgroup_options;
				}
			}
		}
	}

	return $prefix_options;
}

/**
* Removes the invalid prefixes from a collection of threads in a specific forum.
*
* @param	array|int	An array of thread IDs (or a comma delimited list)
* @param	integer		The forumid to consider the threads to be in
*/
function remove_invalid_prefixes($threadids, $forumid = 0)
{
	global $vbulletin;

	if (!is_array($threadids))
	{
		$threadids = preg_replace('#\s#', '', trim($threadids));
		$threadids = explode(',', $threadids);
	}

	$threadids = array_map('intval', $threadids);

	$valid_prefixes = array();

	if ($forumid)
	{
		// find all valid prefixes in the specified forum
		$valid_prefix_sets = fetch_prefix_array($forumid);
		foreach ($valid_prefix_sets AS $prefixset)
		{
			foreach ($prefixset AS $prefixid => $prefix)
			{
				$valid_prefixes[] = "'" . $vbulletin->db->escape_string($prefixid) . "'";
			}
		}
	}

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "thread SET
			prefixid = ''
		WHERE threadid IN(" . implode(',', $threadids) . ")
			" . ($valid_prefixes ? "AND prefixid NOT IN (" . implode(',', $valid_prefixes) . ")" : '')
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
