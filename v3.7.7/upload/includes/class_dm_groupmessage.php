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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for profile messages
*
* @package	vBulletin
* @version	$Revision: 27272 $
* @date		$Date: 2008-07-28 06:07:15 -0700 (Mon, 28 Jul 2008) $
*/
class vB_DataManager_GroupMessage extends vB_DataManager
{
	/**
	* Array of recognised and required fields for groupmessage, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'gmid'           => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'groupid'        => array(TYPE_UINT,       REQ_YES),
		'postuserid'     => array(TYPE_UINT,       REQ_NO,   VF_METHOD, 'verify_userid'),
		'postusername'   => array(TYPE_NOHTMLCOND, REQ_NO,   VF_METHOD, 'verify_username'),
		'dateline'       => array(TYPE_UNIXTIME,   REQ_AUTO),
		'state'          => array(TYPE_STR,        REQ_NO),
		'title'          => array(TYPE_NOHTMLCOND, REQ_NO,   VF_METHOD),
		'pagetext'       => array(TYPE_STR,        REQ_YES,  VF_METHOD),
		'ipaddress'      => array(TYPE_STR,        REQ_AUTO, VF_METHOD),
		'allowsmilie'    => array(TYPE_UINT,       REQ_NO),
		'reportthreadid' => array(TYPE_UINT,       REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('gmid = %1$s', 'gmid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'groupmessage';

	/**
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		$title = trim($title);

		if ($this->registry->options['titlemaxchars'] AND $title != $this->existing['title'])
		{
			if (!empty($this->info['show_title_error']))
			{
				if (($titlelen = vbstrlen($title)) > $this->registry->options['titlemaxchars'])
				{
					// title too long
					$this->error('title_toolong', $titlelen, $this->registry->options['titlemaxchars']);
					return false;
				}
			}
			else if (empty($this->info['is_automated']))
			{
				// not showing the title length error, just chop it
				$title = vbchop($title, $this->registry->options['titlemaxchars']);
			}
		}

		require_once(DIR . '/includes/functions_newpost.php');
		// censor, remove all caps subjects, and htmlspecialchars title
		$title = fetch_no_shouting_text(fetch_censored_text($title));

		// do word wrapping
		$title = fetch_word_wrapped_string($title);

		return true;
	}

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_GroupMessage(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('groupmessagedata_start')) ? eval($hook) : false;
	}

	/**
	 * Pre-Save code for a SG Message
	 *
	 * @param	boolean	Do we actually run the query?
	 *
	 * @return	boolean	Did this function run successfully?
	 */
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition)
		{
			if ($this->fetch_field('state') === null)
			{
				$this->set('state', 'visible');
			}

			if ($this->fetch_field('dateline') === null)
			{
				$this->set('dateline', TIMENOW);
			}

			if ($this->fetch_field('ipaddress') === null)
			{
				$this->set('ipaddress', ($this->registry->options['logip'] ? IPADDRESS : ''));
			}

			if (!$this->info['preview'])
			{
				if (($this->registry->options['floodchecktime'] > 0 AND empty($this->info['is_automated']) AND $this->fetch_field('postuserid') AND $this->is_flooding()) OR $this->is_duplicate())
				{
					return false;
				}
			}
		}

		if (!$this->verify_image_count('pagetext', 'allowsmilie', 'socialmessage'))
		{
			return false;
		}

		// New posts that aren't automated and are visible should be scanned
		if (!$this->condition AND !empty($this->registry->options['vb_antispam_key']) AND empty($this->info['is_automated']) AND $this->fetch_field('state') == 'visible' AND (!$this->registry->options['vb_antispam_posts'] OR $this->info['user']['posts'] < $this->registry->options['vb_antispam_posts']) AND !can_moderate())
		{
			require_once(DIR . '/includes/class_akismet.php');
			$akismet = new vB_Akismet($this->registry);
			$akismet->akismet_board = $this->registry->options['bburl'];
			$akismet->akismet_key = $this->registry->options['vb_antispam_key'];
			if ($akismet->verify_text(array('user_ip' => IPADDRESS, 'user_agent' => USER_AGENT, 'comment_type' => 'post', 'comment_author' => ($this->info['user']['userid'] ? $this->info['user']['username'] : $this->fetch_field('postusername')), 'comment_content' => $this->fetch_field('pagetext'))) === 'spam')
			{
				$this->set('state', 'moderation');
				$this->spamlog_insert = true;
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('groupmessagedata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	 * Deleted a SG Message
	 *
	 * @return	boolean	Was this message deleted successfully?
	*/
	function delete()
	{
		if ($gmid = $this->existing['gmid'])
		{
			$db =& $this->registry->db;

			if ($this->info['hard_delete'])
			{
				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "deletionlog WHERE primaryid = $gmid AND type = 'groupmessage'
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "groupmessage WHERE gmid = $gmid
				");

				$db->query_write("
					DELETE FROM " . TABLE_PREFIX . "moderation WHERE primaryid = $gmid AND type = 'groupmessage'
				");

				// Logging?
			}
			else
			{
				$this->set('state', 'deleted');
				$this->save();

				$deletionman =& datamanager_init('Deletionlog_GroupMessage', $this->registry, ERRTYPE_SILENT, 'deletionlog');
				$deletionman->set('primaryid', $gmid);
				$deletionman->set('type', 'groupmessage');
				$deletionman->set('userid', $this->registry->userinfo['userid']);
				$deletionman->set('username', $this->registry->userinfo['username']);
				$deletionman->set('reason', $this->info['reason']);
				$deletionman->save();
				unset($deletionman);
			}

			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation WHERE primaryid = $gmid AND type = 'groupmessage'
			");

			if (!$this->info['skip_build_counters'])
			{
				require_once(DIR . '/includes/functions_socialgroup.php');
				build_group_counters($this->existing['groupid']);
			}

			return true;
		}

		return false;
	}

	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('groupmessagedata_delete')) ? eval($hook) : false;
	}


	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		$gmid = intval($this->fetch_field('gmid'));
		$groupid = intval($this->fetch_field('groupid'));

		if (!$this->condition)
		{
			if ($this->fetch_field('groupid'))
			{
				$this->insert_dupehash($this->fetch_field('groupid'));
			}

			if ($this->info['group'])
			{
				$dataman =& datamanager_init('SocialGroup', $this->registry, ERRTYPE_SILENT);
				$dataman->set_existing($this->info['group']);

				if ($this->fetch_field('state') == 'visible' AND $this->fetch_field('dateline') == TIMENOW)
				{
					$dataman->set('lastpost', TIMENOW);
					$dataman->set('lastposter', $this->fetch_field('postusername'));
					$dataman->set('lastposterid', $this->fetch_field('postuserid'));
					$dataman->set('lastgmid', $gmid);
				}

				if ($this->fetch_field('state') == 'visible')
				{
					$dataman->set('visible', 'visible + 1', false);
				}
				else
				if ($this->fetch_field['state'] == 'moderation')
				{
					$dataman->set('moderation', 'moderation + 1', false);
				}
				$dataman->save();
			}
		}

		if ($this->fetch_field('state') == 'moderation')
		{
			/*insert query*/
			$this->dbobject->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "moderation (primaryid, type, dateline) VALUES ($gmid, 'groupmessage', " . TIMENOW . ")");
		}

		if ($this->info['group'])
		{
			$this->update_owner_pending_gm_count($this->info['group']['creatoruserid']);
		}

		($hook = vBulletinHook::fetch_hook('groupmessagedata_postsave')) ? eval($hook) : false;
	}

	/**
	* Updates the counter for the owner of the group that shows how many pending members
	* they have awaiting them to deal with
	*
	* @param	integer	The userid of the owner of the group
	*/
	function update_owner_pending_gm_count($ownerid)
	{
		list($pendingcountforowner) = $this->registry->db->query_first("
			SELECT SUM(moderation) FROM " . TABLE_PREFIX . "socialgroup
			WHERE creatoruserid = " . $ownerid
		, DBARRAY_NUM);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET gmmoderatedcount = " . intval($pendingcountforowner) . "
			WHERE userid = " . $ownerid
		);
	}

	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_userid(&$userid)
	{
		if ($userid == $this->registry->userinfo['userid'])
		{
			$this->info['user'] =& $this->registry->userinfo;
			$return = true;
		}
		else if ($userinfo = fetch_userinfo($userid))
		{	// This case should hit the cache most of the time
			$this->info['user'] =& $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		if ($return)
		{
				$this->do_set('postusername', $this->info['user']['username']);
		}

		return $return;
	}

	/**
	* Converts ip address into an integer
	*
	* @param	string	IP Address
	*
	* @param	bool	Whether the ip is valid
	*/
	function verify_ipaddress(&$ipaddress)
	{
		// need to run it through sprintf to get the integer representation
		$ipaddress = sprintf('%u', ip2long($ipaddress));
		return true;
	}

	/**
	* Verifies the page text is valid and sets it up for saving.
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_pagetext(&$pagetext)
	{
		if (empty($this->info['is_automated']))
		{
			if ($this->registry->options['postmaxchars'] != 0 AND ($postlength = vbstrlen($pagetext)) > $this->registry->options['postmaxchars'])
			{
				$this->error('toolong', $postlength, $this->registry->options['postmaxchars']);
				return false;
			}

			$this->registry->options['postminchars'] = intval($this->registry->options['postminchars']);
			if ($this->registry->options['postminchars'] <= 0)
			{
				$this->registry->options['postminchars'] = 1;
			}
			if (vbstrlen(strip_bbcode($pagetext, $this->registry->options['ignorequotechars'])) < $this->registry->options['postminchars'])
			{
				$this->error('tooshort', $this->registry->options['postminchars']);
				return false;
			}
		}

		return parent::verify_pagetext($pagetext);

	}


	/**
	 * Determines whether the message being posted would constitute flooding
	 *
	 * @return	boolean	Is this classed as flooding?
	 *
	 */
	function is_flooding()
	{
		$floodmintime = TIMENOW - $this->registry->options['floodchecktime'];
		if (!can_moderate() AND $this->fetch_field('dateline') > $floodmintime)
		{
			$flood = $this->registry->db->query_first("
				SELECT dateline
				FROM " . TABLE_PREFIX . "groupmessage_hash
				WHERE postuserid = " . $this->fetch_field('postuserid') . "
					AND dateline > " . $floodmintime . "
				ORDER BY dateline DESC
				LIMIT 1
			");
			if ($flood)
			{
				$this->error(
					'postfloodcheck',
					$this->registry->options['floodchecktime'],
					($flood['dateline'] - $floodmintime)
				);
				return true;
			}
		}

		return false;
	}

	/**
	 * Is this a duplicate post of a message posted with the last 5 minutes?
	 *
	 * @return	boolean	whether this is a duplicate post or not
	 *
	 */
	function is_duplicate()
	{
		$dupemintime = TIMENOW - 300;
		if ($this->fetch_field('dateline') > $dupemintime)
		{
			// ### DUPE CHECK ###
			$dupehash = md5($this->fetch_field('groupid') . $this->fetch_field('pagetext') . $this->fetch_field('postuserid'));
			if ($dupe = $this->registry->db->query_first("
				SELECT hash.groupid
				FROM " . TABLE_PREFIX . "groupmessage_hash AS hash
				WHERE hash.postuserid = " . $this->fetch_field('postuserid') . " AND
					hash.dupehash = '" . $this->registry->db->escape_string($dupehash) . "' AND
					hash.dateline > " . $dupemintime . "
			"))
			{
				// Do we want to only check for the post for this same user, or for all users???
				if ($dupe['groupid'] == $this->fetch_field('groupid'))
				{
					$this->error('duplicate_post');
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Inserts a hash into the database for use with duplicate checking
	 *
	 * @param	integer	Group ID (-1 for current group from DM)
	 *
	 */
	function insert_dupehash($groupid = -1)
	{
		if ($groupid == -1)
		{
			$groupid = $this->fetch_field('groupid');
		}

		$postuserid = $this->fetch_field('postuserid');

		$dupehash = md5($groupid . $this->fetch_field('pagetext') . $postuserid);
		/*insert query*/
		$this->dbobject->query_write("
			INSERT INTO " . TABLE_PREFIX . "groupmessage_hash
			(postuserid, groupid, dupehash, dateline)
			VALUES
			(" . intval($postuserid) . ", " . intval($groupid) . ", '" . $dupehash . "', " . TIMENOW . ")
		");
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 27272 $
|| ####################################################################
\*======================================================================*/
?>
