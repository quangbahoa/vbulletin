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

if (!class_exists('vB_DataManager'))
{
	exit;
}

require_once(DIR . '/includes/functions_newpost.php');

/**
* Base data manager for threads and posts. Uninstantiable.
*
* @package	vBulletin
* @version	$Revision: 14266 $
* @date		$Date: 2006-02-10 08:51:31 -0600 (Fri, 10 Feb 2006) $
*/
class vB_DataManager_ThreadPost extends vB_DataManager
{
	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_ThreadPost(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		if (!is_subclass_of($this, 'vB_DataManager_ThreadPost'))
		{
			trigger_error("Direct Instantiation of vB_DataManager_ThreadPost class prohibited.", E_USER_ERROR);
		}

		parent::vB_DataManager($registry, $errtype);
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
		else if ($userinfo = $this->dbobject->query_first("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid = $userid"))
		{
			$this->info['user'] =& $userinfo;
			$return = true;
		}
		else
		{
			$this->error('no_users_matched_your_query');
			$return = false;
		}

		if ($return == true)
		{
			if (isset($this->validfields['username']))
			{
				$this->do_set('username', $this->info['user']['username']);
			}
			else if (isset($this->validfields['postusername']))
			{
				$this->do_set('postusername', $this->info['user']['username']);
			}
		}

		return $return;
	}

	/**
	* Verifies that the provided username is valid, and attempts to correct it if it is not valid
	*
	* @param	string	Username
	*
	* @return	boolean	Returns true if the username is valid, or has been corrected to be valid
	*/
	function verify_username(&$username)
	{
		// this is duplicated from the user manager

		// fix extra whitespace and invisible ascii stuff
		$username = trim(preg_replace('#\s+#si', ' ', strip_blank_ascii($username, ' ')));

		$length = vbstrlen($username);
		if ($length < $this->registry->options['minuserlength'])
		{
			// name too short
			$this->error('usernametooshort', $this->registry->options['minuserlength']);
			return false;
		}
		else if ($length > $this->registry->options['maxuserlength'])
		{
			// name too long
			$this->error('usernametoolong', $this->registry->options['maxuserlength']);
			return false;
		}
		else if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $username))
		{
			// name contains semicolons
			$this->error('username_contains_semi_colons');
			return false;
		}
		else if ($username != fetch_censored_text($username))
		{
			// name contains censored words
			$this->error('censorfield', $this->registry->options['contactuslink']);
			return false;
		}
		else if ($this->dbobject->query_first("
			SELECT userid, username FROM " . TABLE_PREFIX . "user
			WHERE userid != " . intval($this->existing['userid']) . "
			AND
			(
				username = '" . $this->dbobject->escape_string(htmlspecialchars_uni($username)) . "'
				OR
				username = '" . $this->dbobject->escape_string(htmlspecialchars_uni(preg_replace('/&#([0-9]+);/esiU', "convert_int_to_utf8('\\1')", $username))) . "'
			)
		"))
		{
			// name is already in use
			$this->error('usernametaken', $username, $this->registry->session->vars['sessionurl']);
			return false;
		}
		else if (!empty($this->registry->options['illegalusernames']))
		{
			// check for illegal username
			$usernames = preg_split('/\s+/', $this->registry->options['illegalusernames'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($usernames AS $val)
			{
				if (strpos(strtolower($username), strtolower($val)) !== false)
				{
					// wierd error to show, but hey...
					$this->error('usernametaken', $username, $this->registry->session->vars['sessionurl']);
					return false;
				}
			}
		}

		// if we got here, everything is okay
		$username = htmlspecialchars_uni($username);

		$this->user['userid'] = 0;
		return true;
	}

	/**
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// censor, remove all caps subjects, and htmlspecialchars post title
		$title = htmlspecialchars_uni(fetch_no_shouting_text(fetch_censored_text($title)));

		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		// do word wrapping
		if ($this->registry->options['wordwrap'] != 0)
		{
			$title = fetch_word_wrapped_string($title);
		}

		$title = trim($title);

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

		return parent::verify_pagetext($pagetext);

	}

	/**
	* Verifies the number of images in the post text.
	*
	* @return	bool	Whether the post passes the image count check
	*/
	function verify_image_count(&$field, $type = 'smilie')
	{
		if ($type == 'smilie')
		{
			$allowsmilie =& $field;
		}
		else
		{
			$allowsmilie =& $this->fetch_field('allowsmilie', 'post');
		}

		if ($type == 'pagetext')
		{
			$pagetext =& $field;
		}
		else
		{
			$pagetext =& $this->fetch_field('pagetext', 'post');
		}

		if ($allowsmilie !== null AND $pagetext !== null)
		{
			// check max images
			require_once(DIR . '/includes/functions_misc.php');
			require_once(DIR . '/includes/class_bbcode_alt.php');
			$bbcode_parser =& new vB_BbCodeParser_ImgCheck($this->registry, fetch_tag_list());

			if ($this->registry->options['maximages'])
			{
				$imagecount = fetch_character_count($bbcode_parser->parse($pagetext, $this->info['forum']['forumid'], $allowsmilie, true), '<img');
				if ($imagecount > $this->registry->options['maximages'])
				{
					$this->error('toomanyimages', $imagecount, $this->registry->options['maximages']);
					return false;
				}
			}
		}

		return true;
	}

	/**
	* Verifies that the icon selected is valid.
	*
	* @param	integer	The ID of the icon
	*
	* @return	bool	Whether the icon is valid
	*/
	function verify_iconid(&$iconid)
	{
		if ($iconid)
		{
			// try to improve permission checking on icons
			if (!$this->info['user'])
			{
				$userid = $this->fetch_field('userid');
				if (!$userid)
				{
					$userid = $this->fetch_field('postuserid');
				}

				$this->set_info('user', fetch_userinfo($userid));
			}

			if ($this->info['user'])
			{
				$membergroups = fetch_membergroupids_array($this->info['user']);
			}
			else
			{
				// this is assumed to be a guest; go magic numbers!
				$membergroups = array(1);
			}
			$imagecheck = $this->dbobject->query_read("
				SELECT usergroupid FROM " . TABLE_PREFIX . "icon AS icon
				INNER JOIN " . TABLE_PREFIX . "imagecategorypermission USING (imagecategoryid)
				WHERE icon.iconid = $iconid
					AND usergroupid IN (" . $this->dbobject->escape_string(implode(',', $membergroups)) . ")
			");

			if ($this->dbobject->num_rows($imagecheck) == sizeof($membergroups))
			{
				$iconid = 0;
			}
		}

		return true;
	}

	/**
	* Fetches the amount of attachments associated with a posthash and user
	*
	* @param	string	Post hash
	* @param	integer	User ID associated with post hash (-1 means current user)
	*
	* @return	integer	Number of attachments
	*/
	function fetch_attachment_count($posthash, $userid = -1)
	{
		if ($userid == -1)
		{
			$userid = $this->fetch_field('userid', 'post');
		}
		$userid = intval($userid);

		$attachcount = $this->dbobject->query_first("
			SELECT COUNT(*) AS count
			FROM " . TABLE_PREFIX . "attachment
			WHERE posthash = '" . $this->dbobject->escape_string($posthash) . "'
				AND userid = $userid
		");

		return intval($attachcount['count']);
	}

	function insert_dupehash($threadid = -1)
	{
		if ($threadid == -1)
		{
			$threadid = $this->fetch_field('threadid');
		}

		$type = ($threadid > 0 ? 'reply' : 'thread');

		$forumid = $this->fetch_field('forumid');
		if (!$forumid)
		{
			$forumid = $this->info['forum']['forumid'];
		}

		$userid = $this->fetch_field('postuserid');
		if (!$userid)
		{
			$userid = $this->fetch_field('userid');
		}

		$dupehash = md5($forumid . $this->fetch_field('title') . $this->fetch_field('pagetext', 'post') . $userid . $type);

		/*insert query*/
		$this->dbobject->query_write("
			INSERT INTO " . TABLE_PREFIX . "posthash
			(userid, threadid, dupehash, dateline)
			VALUES
			(" . intval($userid) . ", " . intval($threadid) . ", '" . $dupehash . "', " . TIMENOW . ")
		");
	}

	function email_moderators($fields)
	{
		if (!$this->info['forum'] OR in_coventry($this->fetch_field('userid', 'post'), true))
		{
			return;
		}

		$mod_emails = fetch_moderator_newpost_emails($fields, $this->info['forum']['parentlist'], $newpost_lang);

		if (!empty($mod_emails))
		{
			$foruminfo = $this->info['forum'];
			$foruminfo['title_clean'] = unhtmlspecialchars($foruminfo['title_clean']);

			$threadinfo['title'] = unhtmlspecialchars($this->table == 'thread' ? $this->fetch_field('title') : $this->info['thread']['title']);
			$threadinfo['threadid'] = $this->fetch_field('threadid');

			$post['message'] = strip_bbcode($this->post['pagetext']);

			$email = ($this->info['user']['email'] ? $this->info['user']['email'] : $this->registry->userinfo['email']);
			$browsing_user = $this->registry->userinfo['username'];

			// ugly hack -- should be fixed in the future
			$this->registry->userinfo['username'] = unhtmlspecialchars($this->info['user']['username'] ? $this->info['user']['username'] : $this->registry->userinfo['username']);

			foreach ($mod_emails AS $toemail)
			{
				if ($toemail != $email)
				{
					eval(fetch_email_phrases('moderator', iif(isset($newpost_lang["$toemail"]), $newpost_lang["$toemail"], 0)));
					vbmail($toemail, $subject, $message);
				}
			}

			// back to normal
			$this->registry->userinfo['username'] = htmlspecialchars_uni($browsing_user);
		}
	}

	/**
	* This is a pre_save method that only applies to the subclasses that have post
	* fields as their members (ie, not _Thread). Likely only called in those class's
	* pre_save methods.
	*
	* @return	bool	True on success, false on failure
	*/
	function pre_save_post($doquery = true)
	{
		if (!$this->condition)
		{
			if ($this->fetch_field('userid', 'post') == 0 AND $this->fetch_field('username', 'post') == '')
			{
				$this->error('nousername');
				return false;
			}

			if ($this->registry->options['floodchecktime'] > 0 AND !$this->info['preview'] AND $this->fetch_field('userid', 'post'))
			{
				if (!$this->info['user'])
				{
					$this->info['user'] = fetch_userinfo($this->fetch_field('userid', 'post'));
				}
				$user =& $this->info['user'];

				if ($user['lastpost'] <= TIMENOW AND
					($timepassed = TIMENOW - $user['lastpost']) < $this->registry->options['floodchecktime'] AND
					!can_moderate($this->info['forum']['forumid'], '', $user['userid'], $user['usergroupid'] . (trim($user['membergroupids']) ? ",$user[membergroupids]" : '')))
				{
					$this->error('postfloodcheck', $this->registry->options['floodchecktime'], ($this->registry->options['floodchecktime'] - $timepassed));
					return false;
				}
			}

			if ($this->fetch_field('dateline', 'post') === null)
			{
				$this->set('dateline', TIMENOW);
			}

			if ($this->fetch_field('ipaddress', 'post') === null)
			{
				$this->set('ipaddress', ($this->registry->options['logip'] ? IPADDRESS : ''));
			}
		}

		$null = null;
		if (!$this->verify_image_count($null, ''))
		{
			return false;
		}

		if ($this->info['posthash'])
		{
			$this->info['newattach'] = $this->fetch_attachment_count($this->info['posthash'], $this->fetch_field('userid', 'post'));
			$this->set('attach',
				intval($this->fetch_field('attach')) +
				$this->info['newattach']
			);
		}

		return true;
	}

	/**
	* Post save function run on each record. Applies only if there was a post submitted.
	*/
	function post_save_each_post($doquery = true)
	{
		$postid = intval($this->fetch_field($this->table == 'post' ? 'postid' : 'firstpostid'));

		if (!$this->info['user'] AND $this->registry->userinfo['userid'] AND $this->fetch_field('userid', 'post') == $this->registry->userinfo['userid'])
		{
			$this->set_info('user', $this->registry->userinfo);
		}

		if ($this->info['posthash'] AND $this->fetch_field('attach') AND $postid)
		{
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "attachment SET
					postid = $postid,
					posthash = ''
				WHERE posthash = '" . $this->dbobject->escape_string($this->info['posthash']) . "'
					AND userid = " . intval($this->fetch_field('userid', 'post')) . "
			");
		}

		if ($this->condition AND $this->post['pagetext'] AND $postid)
		{
			$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "post_parsed WHERE postid = " . intval($postid));

			if ($this->info['forum'])
			{
				require_once(DIR . '/includes/functions_databuild.php');
				delete_post_index($postid, $this->existing['title'], $this->existing['pagetext']);
			}
		}

		if ($this->post['pagetext'] AND $this->info['forum'] AND $postid)
		{
			// ### UPDATE SEARCH INDEX ###
			require_once(DIR . '/includes/functions_databuild.php');
			build_post_index($postid, $this->info['forum'], 0);
		}

		if (!$this->condition AND $this->fetch_field('visible') == 1)
		{
			if ($this->info['forum'] AND $this->fetch_field('dateline') == TIMENOW)
			{
				if (in_coventry($this->fetch_field('userid', 'post'), true))
				{
					// posted by someone in coventry, so don't update the forum last post time
					// just put it in this person's tachy last post table

					$replaceval = intval($this->fetch_field('userid', 'post')) . ",
						" . intval($this->info['forum']['forumid']) . ",
						" . intval($this->fetch_field('dateline')) . ",
						'" . $this->dbobject->escape_string($this->fetch_field('username', 'post')) . "',
					";

					if ($this->table == 'thread')
					{
						$replaceval .= "'" . $this->dbobject->escape_string($this->fetch_field('title')) . "',
							" . intval($this->fetch_field('threadid')) . ",
							" . ($this->fetch_field('pollid') ? -1 : intval($this->fetch_field('iconid')));
					}
					else if ($this->info['thread'])
					{
						$replaceval .= "'" . $this->dbobject->escape_string($this->info['thread']['title']) . "',
							" . intval($this->info['thread']['threadid']) . ",
							" . ($this->info['thread']['pollid'] ? -1 : intval($this->info['thread']['iconid']));
					}

					$this->dbobject->query_write("
						REPLACE INTO " . TABLE_PREFIX . "tachyforumpost
							(userid, forumid, lastpost, lastposter, lastthread, lastthreadid, lasticonid)
						VALUES
							($replaceval)
					");
				}
				else
				{
					$forumdata =& datamanager_init('Forum', $this->registry, ERRTYPE_SILENT);
					$forumdata->set_existing($this->info['forum']);
					$forumdata->set_info('disable_cache_rebuild', true);

					$forumdata->set('replycount', 'replycount + 1', false);
					if ($this->table == 'thread')
					{
						// we're inserting a new thread
						$forumdata->set('threadcount', 'threadcount + 1', false);
					}
					$forumdata->set('lastpost', $this->fetch_field('dateline'));
					$forumdata->set('lastposter', $this->fetch_field('username', 'post'));
					if ($this->table == 'thread')
					{
						$forumdata->set('lastthread', $this->fetch_field('title'));
						$forumdata->set('lastthreadid', $this->fetch_field('threadid'));
						$forumdata->set('lasticonid', ($this->fetch_field('pollid') ? -1 : $this->fetch_field('iconid')));
					}
					else if ($this->info['thread'])
					{
						$forumdata->set('lastthread', $this->info['thread']['title']);
						$forumdata->set('lastthreadid', $this->info['thread']['threadid']);
						$forumdata->set('lasticonid', ($this->info['thread']['pollid'] ? -1 : $this->info['thread']['iconid']));
					}

					$forumdata->save();

					// empty out the tachy posts for this forum
					$this->dbobject->query_write("
						DELETE FROM " . TABLE_PREFIX . "tachyforumpost
						WHERE forumid = " . intval($this->info['forum']['forumid'])
					);
				}
			}

			if ($this->info['user'])
			{
				$user =& datamanager_init('User', $this->registry, ERRTYPE_SILENT);
				$user->set_existing($this->info['user']);

				if ($this->info['forum']['countposts'])
				{
					$user->set('posts', 'posts + 1', false);

					if (!$this->info['user']['customtitle'])
					{
						$getusergroupid = iif($this->info['user']['displaygroupid'], $this->info['user']['displaygroupid'], $this->info['user']['usergroupid']);
						$usergroup = $this->registry->usergroupcache["$getusergroupid"];

						if (!$usergroup['usertitle'])
						{
							$gettitle = $this->dbobject->query_first("
								SELECT title
								FROM " . TABLE_PREFIX . "usertitle
								WHERE minposts <= " . ($this->info['user']['posts'] + 1) . "
								ORDER BY minposts DESC
							");
							$usertitle = $gettitle['title'];
						}
						else
						{
							$usertitle = $usergroup['usertitle'];
						}
						$user->set('usertitle', $usertitle);
					}
				}

				$dateline = $this->fetch_field('dateline');
				if ($dateline == TIMENOW OR (isset($this->info['user']['lastpost']) AND $dateline > $this->info['user']['lastpost']))
				{
					$user->set('lastpost', $dateline);
				}
				$user->save();
			}
		}
	}
}

/**
* Class to do data save/delete operations for POSTS
*
* @package	vBulletin
* @version	$Revision: 14266 $
* @date		$Date: 2006-02-10 08:51:31 -0600 (Fri, 10 Feb 2006) $
*/
class vB_DataManager_Post extends vB_DataManager_ThreadPost
{
	/**
	* Array of recognised and required fields for posts, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'postid'        => array(TYPE_UINT, REQ_INCR,  'return ($data > 0);'),
		'threadid'      => array(TYPE_UINT, REQ_YES),
		'parentid'      => array(TYPE_UINT, REQ_AUTO),
		'username'      => array(TYPE_STR,  REQ_NO,    VF_METHOD),
		'userid'        => array(TYPE_UINT, REQ_NO,    VF_METHOD),
		'title'         => array(TYPE_STR,  REQ_NO,    VF_METHOD),
		'dateline'      => array(TYPE_UINT, REQ_AUTO),
		'pagetext'      => array(TYPE_STR,  REQ_YES,   VF_METHOD),
		'allowsmilie'   => array(TYPE_UINT, REQ_YES), // this is required as we must know whether smilies count as images
		'showsignature' => array(TYPE_BOOL, REQ_NO),
		'ipaddress'     => array(TYPE_STR,  REQ_AUTO),
		'iconid'        => array(TYPE_UINT, REQ_NO,    VF_METHOD),
		'visible'       => array(TYPE_UINT, REQ_NO),
		'attach'        => array(TYPE_UINT, REQ_NO)
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'post';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('postid = %1$d', 'postid');

	/**
	* Array to store stuff to save to post table
	*
	* @var	array
	*/
	var $post = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Post(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_ThreadPost($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('postdata_start')) ? eval($hook) : false;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->pre_save_post($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		if (!$this->condition AND $this->fetch_field('parentid') === null AND ($this->info['thread'] OR $this->post['threadid']))
		{
			// we're not posting a new thread, so make this post a child of the first post in the thread
			if ($this->info['thread']['firstpostid'])
			{
				$this->set('parentid', $this->info['thread']['firstpostid']);
			}
			else
			{
				$getfirstpost = $this->dbobject->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE threadid = " . $this->post['threadid'] . " ORDER BY dateline LIMIT 1");
				$this->set('parentid', $getfirstpost['postid']);
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('postdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		$postid = $this->fetch_field('postid');

		$this->post_save_each_post($doquery);

		if ($this->info['thread'] AND ($attach = intval($this->info['newattach']) OR !$this->condition))
		{
			$thread =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
			$thread->set_existing($this->info['thread']);

			if ($attach)
			{
				$thread->set('attach', "attach + $attach", false);
			}
		}

		if (!$this->condition)
		{
			if ($this->fetch_field('dateline') == TIMENOW)
			{
				$this->insert_dupehash($this->fetch_field('threadid'));
			}

			if ($this->fetch_field('visible') == 1 AND $this->info['thread'])
			{
				// update last post info for this thread
				if ($this->info['thread']['replycount'] % 10 == 0)
				{
					$replies = $this->registry->db->query_first("
						SELECT COUNT(*)-1 AS replies
						FROM " . TABLE_PREFIX . "post AS post
						WHERE threadid = " . intval($this->info['thread']['threadid']) . " AND
							post.visible = 1
					");

					$thread->set('replycount', $replies['replies']);
				}
				else
				{
					$thread->set('replycount', 'replycount + 1', false);
				}

				if (in_coventry($this->fetch_field('userid'), true))
				{
					// posted by someone in coventry, so don't update the thread last post time
					// just put it in this person's tachy last post table

					$replaceval = intval($this->fetch_field('userid')) . ",
						" . intval($this->info['thread']['threadid']) . ",
						" . intval(TIMENOW) . ",
						'" . $this->dbobject->escape_string($this->fetch_field('username')) . "'
					";

					$this->dbobject->query_write("
						REPLACE INTO " . TABLE_PREFIX . "tachythreadpost
							(userid, threadid, lastpost, lastposter)
						VALUES
							($replaceval)
					");
				}
				else
				{
					$thread->set('lastpost', TIMENOW);
					$thread->set('lastposter', $this->fetch_field('username'));

					// empty out the tachy posts for this thread
					$this->dbobject->query_write("
						DELETE FROM " . TABLE_PREFIX . "tachythreadpost
						WHERE threadid = " . intval($this->info['thread']['threadid'])
					);
				}
			}
			else if ($this->fetch_field('visible') == 0 AND $this->info['thread'])
			{
				$thread->set('hiddencount', 'hiddencount + 1', false);
			}

			/*if ($this->fetch_field('visible') == 1 AND !in_coventry($this->registry->userinfo['userid'], true))
			{
				// Send out subscription emails
				exec_send_notification($this->fetch_field('threadid'), $this->registry->userinfo['userid'], $this->fetch_field('postid'));
			}*/
		}

		if (is_object($thread))
		{
			$thread->save();
		}

		if ($this->post['visible'] === 0)
		{
			$threadid = intval($this->fetch_field('threadid'));
			$postid = intval($this->fetch_field('postid'));

			/*insert query*/
			$this->dbobject->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "moderation (threadid, postid, type) VALUES ($threadid, $postid, 'reply')");
		}

		if (!$this->condition)
		{
			$this->email_moderators('newpostemail');
		}

		($hook = vBulletinHook::fetch_hook('postdata_postsave')) ? eval($hook) : false;
	}


	/**
	* Deletes a post
	*
	* @param	boolean	Whether to consider updating post counts, regardless of forum's settings
	* @param	integer Thread that this post belongs to
	* @param	boolean	Whether to physically remove the thread from the database
	* @param	array	Array of information for a soft delete
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($countposts = true, $threadid = 0, $physicaldel = true, $delinfo = NULL, $dolog = true)
	{
		if ($postid = $this->existing['postid'])
		{
			require_once(DIR . '/includes/functions_databuild.php');
			// note: the skip_moderator_log is the inverse of the $dolog argument

			($hook = vBulletinHook::fetch_hook('postdata_delete')) ? eval($hook) : false;

			return delete_post($postid, $countposts, $threadid, $physicaldel, $delinfo, $dolog);
		}

		return false;
	}
}

/**
* Class to do data save/delete operations for THREADS. Primarily useful when
* updating a thread's settings and you don't want to bring the first post into
* the picture.
*
* @package	vBulletin
* @version	$Revision: 14266 $
* @date		$Date: 2006-02-10 08:51:31 -0600 (Fri, 10 Feb 2006) $
*/
class vB_DataManager_Thread extends vB_DataManager_ThreadPost
{
	/**
	* Array of recognised and required fields for threads, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'threadid'      => array(TYPE_UINT, REQ_INCR),
		'title'         => array(TYPE_STR,  REQ_YES,   VF_METHOD),
		'firstpostid'   => array(TYPE_UINT, REQ_NO),
		'lastpost'      => array(TYPE_UINT, REQ_NO),
		'forumid'       => array(TYPE_UINT, REQ_YES),
		'pollid'        => array(TYPE_UINT, REQ_NO),
		'open'          => array(TYPE_UINT, REQ_AUTO,  VF_METHOD),
		'replycount'    => array(TYPE_UINT, REQ_NO),
		'hiddencount'   => array(TYPE_UINT, REQ_NO),
		'postusername'  => array(TYPE_STR,  REQ_NO,    VF_METHOD, 'verify_username'),
		'postuserid'    => array(TYPE_UINT, REQ_NO,    VF_METHOD, 'verify_userid'),
		'lastposter'    => array(TYPE_STR,  REQ_NO),
		'dateline'      => array(TYPE_UINT, REQ_AUTO),
		'views'         => array(TYPE_UINT, REQ_NO),
		'iconid'        => array(TYPE_UINT, REQ_NO,    VF_METHOD),
		'notes'         => array(TYPE_STR,  REQ_NO),
		'visible'       => array(TYPE_UINT, REQ_NO),
		'sticky'        => array(TYPE_UINT, REQ_NO,    VF_METHOD),
		'votenum'       => array(TYPE_UINT, REQ_NO),
		'votetotal'     => array(TYPE_UINT, REQ_NO),
		'attach'        => array(TYPE_UINT, REQ_NO),
		'similar'       => array(TYPE_STR,  REQ_AUTO),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'thread';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('threadid = %1$d', 'threadid');

	/**
	* Array to store stuff to save to thread/post tables
	*
	* @var	array
	*/
	var $thread = array();

	/**
	* Array holding moderator log details to insert
	*
	* @var	array
	*/
	var $modlog = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Thread(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_ThreadPost($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('threaddata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies the title. Does the same processing as the general title verifier,
	* but also requires there be a title.
	*
	* @param	string	Title text
	*
	* @return	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		if (!parent::verify_title($title))
		{
			return false;
		}

		if ($title == '')
		{
			$this->error('nosubject');
			return false;
		}

		if ($this->condition AND !$this->info['skip_moderator_log'] AND $title != $this->existing['title'])
		{
			require_once(DIR . '/includes/functions_log_error.php');
			$logtype = fetch_modlogtypes('thread_title_x_changed');
			$this->modlog[] = array('userid' => intval($this->registry->userinfo['userid']), 'type' => intval($logtype), 'action' => $this->existing['title']);
		}

		return true;
	}

	function verify_open(&$open)
	{
		if (!in_array($open, array(0, 1, 10)))
		{
			$open = 1;
		}

		if ($this->condition AND !$this->info['skip_moderator_log'])
		{
			require_once(DIR . '/includes/functions_log_error.php');
			if ($this->fetch_field('open'))
			{
				$logtype = fetch_modlogtypes('closed_thread');
			}
			else
			{
				$logtype = fetch_modlogtypes('opened_thread');
			}
			$this->modlog[] = array('userid' => intval($this->registry->userinfo['userid']), 'type' => intval($logtype));
		}

		return true;
	}

	function verify_sticky(&$sticky)
	{
		if ($sticky != 1)
		{
			$sticky = 0;
		}

		if ($this->condition AND !$this->info['skip_moderator_log'])
		{
			require_once(DIR . '/includes/functions_log_error.php');
			if ($this->fetch_field('sticky'))
			{
				$logtype = fetch_modlogtypes('unstuck_thread');
			}
			else
			{
				$logtype = fetch_modlogtypes('stuck_thread');
			}
			$this->modlog[] = array('userid' => intval($this->registry->userinfo['userid']), 'type' => intval($logtype));
		}

		return true;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if ($this->thread['username'])
		{
			$this->do_set('postusername', $this->thread['username']);
		}
		if ($this->thread['userid'])
		{
			$this->do_set('postuserid', $this->thread['userid']);
		}

		if (!$this->condition AND $this->registry->options['similarthreadsearch'])
		{
			require_once(DIR . '/includes/functions_search.php');
			$this->set('similar', fetch_similar_threads($this->fetch_field('title'), 0));
		}

		if (!$this->condition)
		{
			if (!$this->fetch_field('dateline'))
			{
				$this->set('dateline', TIMENOW);
			}

			if ($this->fetch_field('open') === null)
			{
				$oldvalue = $this->info['skip_moderator_log'];
				$this->set_info('skip_moderator_log', true);
				$this->set('open', 1);
				$this->set_info('skip_moderator_log', $oldvalue);
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('threaddata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function insert_moderator_log()
	{
		if ($this->modlog)
		{
			$threadid = intval(($tid = $this->fetch_field('threadid')) ? $tid : $this->info['thread']['threadid']);
			$forumid = intval(($fid = $this->fetch_field('forumid')) ? $fid : $this->info['forum']['forumid']);

			$modlogsql = array();
			foreach ($this->modlog AS $entry)
			{
				$modlogsql[] = "
					($entry[userid], " . TIMENOW . ", $forumid, $threadid, $entry[type],
					'" . $this->dbobject->escape_string($entry['action']) . "', '" . $this->dbobject->escape_string(IPADDRESS) . "')
				";
			}

			/*insert query*/
			$this->dbobject->query_write("
				INSERT INTO " . TABLE_PREFIX . "moderatorlog
					(userid, dateline, forumid, threadid, type, action, ipaddress)
					VALUES
						" . implode(', ', $modlogsql)
			);

			$this->modlog = array();
		}
	}

	function post_save_each($doquery = true)
	{
		$this->insert_moderator_log();

		if (!$this->condition AND $this->fetch_field('visible') == 1 AND $this->info['forum'])
		{
			$forumdata =& datamanager_init('Forum', $this->registry, ERRTYPE_SILENT);
			$forumdata->set_existing($this->info['forum']);
			$forumdata->set_info('disable_cache_rebuild', true);

			$forumdata->set('threadcount', 'threadcount + 1', false);

			/*$forumdata->set('lastpost', $this->fetch_field('dateline'));
			$forumdata->set('lastposter', $this->fetch_field('username', 'post'));
			$forumdata->set('lastthread', $this->fetch_field('title'));
			$forumdata->set('lastthreadid', $this->fetch_field('threadid'));
			$forumdata->set('lasticonid', $this->fetch_field('iconid'));*/

			$forumdata->save();
		}

		if ($this->condition AND $fpid = $this->fetch_field('firstpostid') AND !$this->info['skip_first_post_update'])
		{
			// if we're updating the title/iconid of an existing thread, update the first post
			if ((isset($this->thread['title']) OR isset($this->thread['iconid'])) AND $fp = fetch_postinfo($fpid))
			{
				$postdata =& datamanager_init('Post', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$postdata->set_existing($fp);

				if (isset($this->thread['title']))
				{
					$postdata->set('title', $this->thread['title'], true, false); // don't clean it -- already been cleaned
				}
				if (isset($this->thread['iconid']))
				{
					$postdata->set('iconid', $this->thread['iconid'], true, false);
				}

				$postdata->save();
			}
		}

		if ($this->fetch_field('open') == 10 AND $this->thread['title'])
		{
			// we're editing the title of a redirect, so update the original
			$realinfo = fetch_threadinfo($this->fetch_field('pollid'));
			if ($realinfo)
			{
				$threaddata =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$threaddata->set_existing($realinfo);
				$threaddata->set_info('skip_moderator_log', true);
				$threaddata->set_info('skip_first_post_update', true);

				$threaddata->set('title', $this->thread['title'], true, false);

				$threaddata->save();
			}
		}

		if ($this->condition AND $this->thread['title'])
		{
			// we're updating the title of a thread, so update redirect titles as well
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					title = '" . $this->dbobject->escape_string($this->thread['title']) . "'
				WHERE open = 10 AND pollid = " . intval($this->fetch_field('threadid'))
			);
		}

		($hook = vBulletinHook::fetch_hook('threaddata_postsave')) ? eval($hook) : false;
	}

	/**
	* Deletes a thread
	*
	* @param	boolean	Whether to consider updating post counts, regardless of forum's settings
	* @param	boolean	Whether to physically remove the thread from the database
	* @param	array	Array of information for a soft delete
	* @param	boolean	Whether to add an entry to the moderator log
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($countposts = true, $physicaldel = true, $delinfo = NULL, $dolog = true)
	{
		if ($threadid = $this->existing['threadid'])
		{
			require_once(DIR . '/includes/functions_databuild.php');

			($hook = vBulletinHook::fetch_hook('threaddata_delete')) ? eval($hook) : false;

			// note: the skip_moderator_log is the inverse of the $dolog argument
			return delete_thread($threadid, $countposts, $physicaldel, $delinfo, $dolog);
		}

		return false;
	}
}

/**
* Class to do data save/delete operations for a THREAD and its FIRST POST.
* This is an important distinction!
*
* @package	vBulletin
* @version	$Revision: 14266 $
* @date		$Date: 2006-02-10 08:51:31 -0600 (Fri, 10 Feb 2006) $
*/
class vB_DataManager_Thread_FirstPost extends vB_DataManager_Thread
{
	/**
	* Array of recognised and required fields for threads, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'firstpostid'   => array(TYPE_UINT, REQ_AUTO),
		'lastpost'      => array(TYPE_UINT, REQ_AUTO),
		'forumid'       => array(TYPE_UINT, REQ_YES),
		'pollid'        => array(TYPE_UINT, REQ_NO),
		'open'          => array(TYPE_UINT, REQ_AUTO,   VF_METHOD),
		'replycount'    => array(TYPE_UINT, REQ_AUTO),
		'hiddencount'   => array(TYPE_UINT, REQ_AUTO),
		'lastposter'    => array(TYPE_STR,  REQ_AUTO),
		'views'         => array(TYPE_UINT, REQ_NO),
		'notes'         => array(TYPE_STR,  REQ_NO),
		'sticky'        => array(TYPE_UINT, REQ_NO,     VF_METHOD),
		'votenum'       => array(TYPE_UINT, REQ_NO),
		'votetotal'     => array(TYPE_UINT, REQ_NO),
		'similar'       => array(TYPE_STR,  REQ_AUTO),

		// shared fields
		'threadid'      => array(TYPE_UINT, REQ_INCR),
		'title'         => array(TYPE_STR,  REQ_YES,    VF_METHOD),
		'username'      => array(TYPE_STR,  REQ_NO,     VF_METHOD), // maps to thread.postusername
		'userid'        => array(TYPE_UINT, REQ_NO,     VF_METHOD), // maps to thread.postuserid
		'dateline'      => array(TYPE_UINT, REQ_AUTO),
		'iconid'        => array(TYPE_UINT, REQ_NO,     VF_METHOD),
		'visible'       => array(TYPE_BOOL, REQ_NO), // note: post.visible will always be 1 with this object!
		'attach'        => array(TYPE_UINT, REQ_NO),

		// post only fields
		'pagetext'      => array(TYPE_STR,  REQ_YES,    VF_METHOD),
		'allowsmilie'   => array(TYPE_UINT, REQ_YES), // this is required as we must know whether smilies count as images
		'showsignature' => array(TYPE_BOOL, REQ_NO),
		'ipaddress'     => array(TYPE_STR,  REQ_AUTO),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'thread';

	/**
	* Condition template for update query
	* This is for use with sprintf(). First key is the where clause, further keys are the field names of the data to be used.
	*
	* @var	array
	*/
	var $condition_construct = array('threadid = %1$d', 'threadid');

	/**
	* Array to store stuff to save to thread table
	*
	* @var	array
	*/
	var $thread = array();

	/**
	* Array to store stuff to save to post table
	*
	* @var	array
	*/
	var $post = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Thread_FirstPost(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('threadfpdata_start')) ? eval($hook) : false;
	}

	/**
	* Takes valid data and sets it as part of the data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	*/
	function do_set($fieldname, &$value)
	{
		$this->setfields["$fieldname"] = true;

		$tables = array();

		switch ($fieldname)
		{
			case 'threadid':
			case 'title':
			case 'dateline' :
			case 'iconid':
			case 'attach':
			{
				$tables = array('thread', 'post');
			}
			break;

			// post.visible will always be 1
			case 'visible':
			{
				$this->post['visible'] = 1;
				$this->thread['visible'] =& $value;
			}
			break;

			// exist in post table as is, but in the thread table as post<name>
			case 'username':
			case 'userid':
			{
				$this->post["$fieldname"] =& $value;
				$this->thread["post$fieldname"] =& $value;
				return;
			}
			break;

			case 'pagetext':
			case 'allowsmilie':
			case 'showsignature':
			case 'ipaddress':
			{
				$tables = array('post');
			}
			break;

			default:
			{
				$tables = array('thread');
			}
		}

		($hook = vBulletinHook::fetch_hook('threadfpdata_doset')) ? eval($hook) : false;

		foreach ($tables AS $table)
		{
			$this->{$table}["$fieldname"] =& $value;
		}
	}

	/**
	* Saves thread data to the database
	*
	* @return	mixed
	*/
	function save($doquery = true)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return 0;
		}

		if ($this->condition)
		{
			// update query
			$return = $this->db_update(TABLE_PREFIX, 'thread', $this->condition, $doquery);
			if ($return)
			{
				$this->db_update(TABLE_PREFIX, 'post', 'postid = ' . $this->fetch_field('firstpostid'), $doquery);
			}
		}
		else
		{
			// insert query
			$return = $this->thread['threadid'] = $this->db_insert(TABLE_PREFIX, 'thread', $doquery);

			if ($return)
			{
				$this->do_set('threadid', $return);

				$firstpostid = $this->thread['firstpostid'] = $this->db_insert(TABLE_PREFIX, 'post', $doquery);
				if ($doquery)
				{
					$this->dbobject->query_write("UPDATE " . TABLE_PREFIX . "thread SET firstpostid = $firstpostid WHERE threadid = $return");
				}
			}
		}

		if ($return)
		{
			$this->post_save_each($doquery);
			$this->post_save_once($doquery);
		}

		return $return;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!parent::pre_save($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		if (!$this->pre_save_post($doquery))
		{
			$this->presave_called = false;
			return false;
		}

		if (!$this->condition)
		{
			$this->set('lastpost', $this->fetch_field('dateline'));
			$this->set('lastposter', $this->fetch_field('username', 'post'));
			$this->set('replycount', 0);
			$this->set('hiddencount', 0);
		}
		else
		{
			if (!$this->fetch_field('firstpostid'))
			{
				$getfirstpost = $this->dbobject->query_first("SELECT postid FROM " . TABLE_PREFIX . "post WHERE threadid = " . $this->fetch_field('threadid') . " ORDER BY dateline LIMIT 1");
				$this->set('firstpostid', $getfirstpost['postid']);
			}
		}

		if (!$this->condition AND $this->fetch_field('open') === null)
		{
			$oldvalue = $this->info['skip_moderator_log'];
			$this->set_info('skip_moderator_log', true);
			$this->set('open', 1);
			$this->set_info('skip_moderator_log', $oldvalue);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('threadfpdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		$this->post_save_each_post($doquery);

		if (!$this->condition AND $this->fetch_field('dateline') == TIMENOW)
		{
			$this->insert_dupehash(0);
		}

		if ($this->info['forum'] AND $this->fetch_field('firstpostid'))
		{
			// ### UPDATE SEARCH INDEX ###
			require_once(DIR . '/includes/functions_databuild.php');
			build_post_index($this->fetch_field('firstpostid'), $this->info['forum'], 1);
		}

		if ($this->thread['visible'] === 0)
		{
			$threadid = intval($this->fetch_field('threadid'));
			$postid = intval($this->fetch_field('firstpostid'));

			/*insert query*/
			$this->dbobject->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "moderation (threadid, postid, type) VALUES ($threadid, $postid, 'thread')");
		}

		$this->insert_moderator_log();

		if (!$this->condition)
		{
			$this->email_moderators(array('newthreademail', 'newpostemail'));
		}

		($hook = vBulletinHook::fetch_hook('threadfpdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Deletes a thread with the first post
	*
	* @param	boolean	Whether to consider updating post counts, regardless of forum's settings
	* @param	boolean	Whether to physically remove the thread from the database
	* @param	array	Array of information for a soft delete
	* @param	boolean	Whether to add an entry to the moderator log
	*
	* @return	mixed	The number of affected rows
	*/
	function delete($countposts = true, $physicaldel = true, $delinfo = NULL, $dolog = true)
	{
		($hook = vBulletinHook::fetch_hook('threadfpdata_delete')) ? eval($hook) : false;

		return parent::delete($countposts, $physicaldel, $delinfo, $dolog);
	}
}

/**
* Class to do data update operations for multiple POSTS simultaneously
*
* @package	vBulletin
* @version	$Revision: 14266 $
* @date		$Date: 2006-02-10 08:51:31 -0600 (Fri, 10 Feb 2006) $
*/
class vB_DataManager_Post_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_Post';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'postid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT * FROM " . TABLE_PREFIX . "post AS post";

		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}
}

/**
* Class to do data update operations for multiple THREADS simultaneously
*
* @package	vBulletin
* @version	$Revision: 14266 $
* @date		$Date: 2006-02-10 08:51:31 -0600 (Fri, 10 Feb 2006) $
*/
class vB_DataManager_Thread_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_Thread';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'threadid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT * FROM " . TABLE_PREFIX . "thread AS thread";
		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14266 $
|| ####################################################################
\*======================================================================*/
?>