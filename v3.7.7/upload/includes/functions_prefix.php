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

	if (isset($vbulletin->prefixcache))
	{
		return (is_array($vbulletin->prefixcache["$forumid"]) ? $vbulletin->prefixcache["$forumid"] : array());
	}
	else
	{
		$prefixsets = array();
		$prefix_sql = $vbulletin->db->query_read("
			SELECT prefix.*
			FROM " . TABLE_PREFIX . "forumprefixset AS forumprefixset
			INNER JOIN " . TABLE_PREFIX . "prefixset AS prefixset ON (prefixset.prefixsetid = forumprefixset.prefixsetid)
			INNER JOIN " . TABLE_PREFIX . "prefix AS prefix ON (prefix.prefixsetid = prefixset.prefixsetid)
			WHERE forumprefixset.forumid = " . intval($forumid) . "
			ORDER BY prefixset.displayorder, prefix.displayorder
		");
		while ($prefix = $vbulletin->db->fetch_array($prefix_sql))
		{
			$prefixsets["$prefix[prefixsetid]"][] = $prefix['prefixid'];
		}

		($hook = vBulletinHook::fetch_hook('prefix_fetch_array')) ? eval($hook) : false;

		return $prefixsets;
	}
}

/**
* Returns HTML of options/optgroups for direct display in a template for the
* selected forum.
*
* @param	integer	Forum ID to show prefixes from
* @param	string	Selected prefix ID
*
* @return	string	HTML to output
*/
function fetch_prefix_html($forumid, $selectedid = '')
{
	global $vbulletin, $stylevar, $vbphrase;

	$prefix_options = '';
	if ($prefixsets = fetch_prefix_array($forumid))
	{
		foreach ($prefixsets AS $prefixsetid => $prefixes)
		{
			$optgroup_options = '';
			foreach ($prefixes AS $prefixid)
			{
				$optionvalue = $prefixid;
				$optiontitle = htmlspecialchars_uni($vbphrase["prefix_{$prefixid}_title_plain"]);
				$optionselected = ($prefixid == $selectedid ? ' selected="selected"' : '');

				eval('$optgroup_options .= "' . fetch_template('option') . '";');
			}

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
			foreach ($prefixset AS $prefixid)
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
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 25686 $
|| ####################################################################
\*======================================================================*/
?>