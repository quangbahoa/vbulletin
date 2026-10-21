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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Postbit optimized for announcements
*
* @package 		vBulletin
* @version		$Revision: 12111 $
* @date 		$Date: 2005-06-01 10:54:40 -0500 (Wed, 01 Jun 2005) $
*
*/
class vB_Postbit_Announcement extends vB_Postbit
{
	/**
	* Processes the date information and determines whether the post is new or old
	*/
	function process_date_status()
	{
		$this->post['dateline'] = $this->post['startdate'];

		$this->post['startdate'] = vbdate($this->registry->options['dateformat'], $this->post['startdate'], false, true, false);
		$this->post['enddate'] = vbdate($this->registry->options['dateformat'], $this->post['enddate'], false, true, false);

		parent::process_date_status();

	}

	/**
	* Processes the post's icon.
	*/
	function process_icon()
	{
		global $show;

		$show['messageicon'] = false;
	}

	/**
	* Processes miscellaneous post items at the end of the construction process.
	*/
	function prep_post_end()
	{
		global $show;

		$this->post['editlink'] = false;
		$this->post['replylink'] = false;
		$this->post['forwardlink'] = false;

		$show['postcount'] = false;
		$show['reputationlink'] = false;
		$show['reportlink'] = false;
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		$this->post['message'] = $this->bbcode_parser->parse($this->post['pagetext'], 'announcement', $this->post['allowsmilies']);
	}
}

/**
* Postbit optimized for private messages
*
* @package 		vBulletin
* @version		$Revision: 12111 $
* @date 		$Date: 2005-06-01 10:54:40 -0500 (Wed, 01 Jun 2005) $
*
*/
class vB_Postbit_Pm extends vB_Postbit
{
	/**
	* Determines whether the post should actually be displayed.
	*
	* @return	bool	True if the post should be displayed; false otherwise
	*/
	function is_displayable()
	{
		// PMs ignore tachy status
		return true;
	}

	/**
	* Processes miscellaneous post items at the beginning of the construction process.
	*/
	function prep_post_start()
	{
		$this->post = array_merge($this->post, fetch_userinfo($this->post['fromuserid'], 3));

		parent::prep_post_start();
	}

	/**
	* Processes miscellaneous post items at the end of the construction process.
	*/
	function prep_post_end()
	{
		global $show;

		$this->post['editlink'] = false;
		$this->post['replylink'] = 'private.php?' . $this->registry->session->vars['sessionurl'] . 'do=newpm&amp;pmid=' . $this->post['pmid'];
		$this->post['forwardlink'] = 'private.php?' . $this->registry->session->vars['sessionurl'] . 'do=newpm&amp;forward=1&amp;pmid=' . $this->post['pmid'];

		$show['postcount'] = false;
		$show['reputationlink'] = false;
		$show['reportlink'] = false;
		$show['spacer'] = false;
	}

	/**
	* Processes the date information and determines whether the post is new or old
	*/
	function process_date_status()
	{
		if ($this->post['messageread'])
		{
			$this->post['statusicon'] = 'old';
			$this->post['statustitle'] = $vbphrase['old'];	
		}
		else
		{
			$this->post['statusicon'] = 'new';
			$this->post['statustitle'] = $vbphrase['unread_date'];			
		}	
		
		// format date/time
		$this->post['postdate'] = vbdate($this->registry->options['dateformat'], $this->post['dateline'], true);
		$this->post['posttime'] = vbdate($this->registry->options['timeformat'], $this->post['dateline']);		
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		$this->post['message'] = parse_pm_bbcode($this->post['message'], $this->post['allowsmilie']);
	}
}

/**
* Postbit optimized for soft deleted posts
*
* @package 		vBulletin
* @version		$Revision: 12111 $
* @date 		$Date: 2005-06-01 10:54:40 -0500 (Wed, 01 Jun 2005) $
*
*/
class vB_Postbit_Post_Deleted extends vB_Postbit_Post
{
	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit_deleted';

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_attachments()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_im_icons()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function parse_bbcode()
	{
	}
}

/**
* Postbit optimized for global ignored (tachy'd) posts
*
* @package 		vBulletin
* @version		$Revision: 12111 $
* @date 		$Date: 2005-06-01 10:54:40 -0500 (Wed, 01 Jun 2005) $
*
*/
class vB_Postbit_Post_Global_Ignore extends vB_Postbit_Post
{
	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit_ignore_global';

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_attachments()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_im_icons()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function parse_bbcode()
	{
	}
}

/**
* Postbit optimized for regular (ignore list) ignored posts
*
* @package 		vBulletin
* @version		$Revision: 12111 $
* @date 		$Date: 2005-06-01 10:54:40 -0500 (Wed, 01 Jun 2005) $
*
*/
class vB_Postbit_Post_Ignore extends vB_Postbit_Post
{
	/**
	* The name of the template that will be used to display this post.
	*
	* @var	string
	*/
	var $templatename = 'postbit_ignore';

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_attachments()
	{
	}

	/**
	* Will not be displayed. No longer does anything.
	*/
	function process_im_icons()
	{
	}


	/**
	* Will not be displayed. No longer does anything.
	*/
	function parse_bbcode()
	{
	}
}

/**
* Postbit optimized for user notes
*
* @package 		vBulletin
* @version		$Revision: 12111 $
* @date 		$Date: 2005-06-01 10:54:40 -0500 (Wed, 01 Jun 2005) $
*
*/
class vB_Postbit_Usernote extends vB_Postbit
{
	/**
	* Processes miscellaneous post items at the end of the construction process.
	*/
	function prep_post_end()
	{
		global $show;

		$this->post['editlink'] = 'usernote.php?' . $this->registry->session->vars['sessionurl'] . 'do=editnote&usernoteid=' . $this->post['usernoteid'];
		$this->post['replylink'] = false;
		$this->post['forwardlink'] = false;

		$show['postcount'] = false;
		$show['reputationlink'] = false;
		$show['reportlink'] = false;
		$show['showpostlink'] = false;
	}

	/**
	* Parses the post for BB code.
	*/
	function parse_bbcode()
	{
		$this->post['message'] = parse_usernote_bbcode($this->post['message'], $this->post['allowsmilies']);
	}
}



/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12111 $
|| ####################################################################
\*======================================================================*/
?>