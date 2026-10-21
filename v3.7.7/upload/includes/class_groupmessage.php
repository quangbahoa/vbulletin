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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/functions_user.php');

/**
* Group Message factory.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_MessageFactory
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* BB code parser object (if necessary)
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Information about the group that this message belongs to
	*
	* @var	array
	*/
	var $group = array();

	/**
	* Permission cache for various users.
	*
	* @var	array
	*/
	var $perm_cache = array();

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	array	Userinfo
	*/
	function vB_Group_MessageFactory(&$registry, &$bbcode, &$group)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Database::Registry object is not an object", E_USER_ERROR);
		}

		$this->bbcode =& $bbcode;
		$this->group =& $group;

	}

	/**
	* Create a message object for the specified message
	*
	* @param	array	message information
	*
	* @return	vB_Group_Message
	*/
	function &create($message, $type = '')
	{
		$class_name = 'vB_Group_Message_';

		if ($type)
		{
			$class_name .= $type . '_';
		}

		switch ($message['state'])
		{
			case 'deleted':
				$class_name .= 'Deleted';
				break;

			case 'moderation':
			case 'visible':
			default:
				if (!empty($message['ignored']))
				{
					$class_name .= 'Ignored';
				}
				else
				{
					$class_name .= 'Message';
				}
		}

		($hook = vBulletinHook::fetch_hook('group_messagebit_factory')) ? eval($hook) : false;

		if (class_exists($class_name))
		{
			return new $class_name($this->registry, $this, $this->bbcode, $this->group, $message);
		}
		else
		{
			trigger_error('vB_Group_MessageFactory::create(): Invalid type ' . htmlspecialchars_uni($class_name) . '.', E_USER_ERROR);
		}
	}
}

/**
* Generic message class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
* @abstract
*
*/
class vB_Group_Message
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Factory object that created this object. Used for permission caching.
	*
	* @var	vB_Group_MessageFactory
	*/
	var $factory = null;

	/**
	* BB code parser object (if necessary)
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Cached information from the BB code parser
	*
	* @var	array
	*/
	var $parsed_cache = array();

	/**
	* Information about the group this message belongs to
	*
	* @var	array
	*/
	var $group = array();

	/**
	* Information about this message
	*
	* @var	array
	*/
	var $message = array();

	/**
	* Variable which identifies if the data should be cached
	*
	* @var	boolean
	*/
	var $cachable = true;

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = '';

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	vB_Group_MessagFactory
	* @param	array			User info
	* @param	array			Message info
	*/
	function vB_Group_Message(&$registry, &$factory, &$bbcode, $group, $message)
	{
		if (!is_subclass_of($this, 'vB_Group_Message'))
		{
			trigger_error('Direct instantiation of vB_Group_Message class prohibited. Use the vB_Group_MessageFactory class.', E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Database::Registry object is not an object", E_USER_ERROR);
		}

		$this->registry =& $registry;
		$this->factory =& $factory;
		$this->bbcode =& $bbcode;

		$this->group = $group;
		$this->message = $message;
	}

	/**
	* Template method that does all the work to display an issue note, including processing the template
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		($hook = vBulletinHook::fetch_hook('group_messagebit_display_start')) ? eval($hook) : false;

		// preparation for display...
		$this->prepare_start();

		if ($this->message['userid'])
		{
			$this->process_registered_user();
		}
		else
		{
			$this->process_unregistered_user();
		}

		fetch_avatar_from_userinfo($this->message, true);

		$this->process_date_status();
		$this->process_display();
		$this->process_text();
		$this->prepare_end();

		// actual display...
		$group =& $this->group;
		$message =& $this->message;

		global $show, $vbphrase, $stylevar;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		($hook = vBulletinHook::fetch_hook('group_messagebit_display_complete')) ? eval($hook) : false;

		eval('$output = "' . fetch_template($this->template) . '";');

		return $output;
	}

	/**
	* Any startup work that needs to be done to a note.
	*/
	function prepare_start()
	{
		$this->message = array_merge($this->message, convert_bits_to_array($this->message['options'], $this->registry->bf_misc_useroptions));
		$this->message = array_merge($this->message, convert_bits_to_array($this->message['adminoptions'], $this->registry->bf_misc_adminoptions));

		$this->message['checkbox_value'] = 0;
		$this->message['checkbox_value'] += ($this->message['state'] == 'moderation') ? POST_FLAG_INVISIBLE : 0;
		$this->message['checkbox_value'] += ($this->message['state'] == 'deleted') ? POST_FLAG_DELETED : 0;
	}

	/**
	* Process note as if a registered user posted
	*/
	function process_registered_user()
	{
		global $show, $vbphrase;

		fetch_musername($this->message);

		$this->message['onlinestatus'] = 0;
		// now decide if we can see the user or not
		if ($this->message['lastactivity'] > (TIMENOW - $this->registry->options['cookietimeout']) AND $this->message['lastvisit'] != $this->message['lastactivity'])
		{
			if ($this->message['invisible'])
			{
				if (($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden']) OR $this->message['userid'] == $this->registry->userinfo['userid'])
				{
					// user is online and invisible BUT bbuser can see them
					$this->message['onlinestatus'] = 2;
				}
			}
			else
			{
				// user is online and visible
				$this->message['onlinestatus'] = 1;
			}
		}

		if (!isset($this->factory->perm_cache["{$this->message['userid']}"]))
		{
			$this->factory->perm_cache["{$this->message['userid']}"] = cache_permissions($this->message, false);
		}

		if ( // no avatar defined for this user
			empty($this->message['avatarurl'])
			OR // visitor doesn't want to see avatars
			($this->registry->userinfo['userid'] > 0 AND !$this->registry->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$this->message['avatarid'] AND !($this->factory->perm_cache["{$this->message['userid']}"]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$this->message['adminavatar']) //
		)
		{
			$show['avatar'] = false;
		}
		else
		{
			$show['avatar'] = true;
		}

		$show['emaillink'] = (
			$this->message['showemail'] AND $this->registry->options['displayemails'] AND (
				!$this->registry->options['secureemail'] OR (
					$this->registry->options['secureemail'] AND $this->registry->options['enableemail']
				)
			) AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']
			AND $this->registry->userinfo['userid']
		);
		$show['homepage'] = ($this->message['homepage'] != '' AND $this->message['homepage'] != 'http://');
		$show['pmlink'] = ($this->registry->options['enablepms'] AND $this->registry->userinfo['permissions']['pmquota'] AND ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
	 					OR ($this->message['receivepm'] AND $this->factory->perm_cache["{$this->userinfo['userid']}"]['pmquota'])
	 				)) ? true : false;
	}

	/**
	* Process note as if an unregistered user posted
	*/
	function process_unregistered_user()
	{
		$this->message['rank'] = '';
		$this->message['notesperday'] = 0;
		$this->message['displaygroupid'] = 1;
		$this->message['username'] = $this->message['postusername'];
		fetch_musername($this->message);
		$this->message['usertitle'] = $this->registry->usergroupcache['1']['usertitle'];
		$this->message['joindate'] = '';
		$this->message['notes'] = 'n/a';
		$this->message['avatar'] = '';
		$this->message['profile'] = '';
		$this->message['email'] = '';
		$this->message['useremail'] = '';
		$this->message['icqicon'] = '';
		$this->message['aimicon'] = '';
		$this->message['yahooicon'] = '';
		$this->message['msnicon'] = '';
		$this->message['skypeicon'] = '';
		$this->message['homepage'] = '';
		$this->message['findnotes'] = '';
		$this->message['signature'] = '';
		$this->message['reputationdisplay'] = '';
		$this->message['onlinestatus'] = '';
	}

	/**
	* Prepare the text for display
	*/
	function process_text()
	{
		$this->message['message'] = $this->bbcode->parse(
			$this->message['pagetext'],
			'socialmessage',
			$this->message['allowsmilie']
		);
		$this->parsed_cache =& $this->bbcode->cached;

		if (!empty($this->message['del_reason']))
		{
			$this->message['del_reason'] = fetch_censored_text($this->message['del_reason']);
		}
	}

	/**
	* Any closing work to be done.
	*/
	function prepare_end()
	{
		global $show;

		global $onload, $messageid;

		if (can_moderate(0, 'canviewips'))
		{
			$this->message['messageipaddress'] = ($this->message['messageipaddress'] ? htmlspecialchars_uni(long2ip($this->message['messageipaddress'])) : '');
		}
		else
		{
			$this->message['messageipaddress'] = '';
		}

		$show['reportlink'] = (
			$this->registry->userinfo['userid']
			AND ($this->registry->options['rpforumid'] OR
				($this->registry->options['enableemail'] AND $this->registry->options['rpemail']))
		);
	}

	/**
	 * Created Human readable Dates and Times
	 *
	 */
	function process_date_status()
	{
		global $vbphrase;

		$this->message['date'] = vbdate($this->registry->options['dateformat'], $this->message['dateline'], true);
		$this->message['time'] = vbdate($this->registry->options['timeformat'], $this->message['dateline']);
	}

	/**
	 * Sets up different display variables for the Group Message
	 *
	 */
	function process_display()
	{
		global $show;

		$show['moderation'] = ($this->message['state'] == 'moderation');
		$show['edit'] = can_edit_group_message($this->message, $this->group);
		$show['inlinemod'] = (
			(
				$this->message['state'] != 'deleted'
				AND (
					fetch_socialgroup_modperm('canmoderategroupmessages', $this->group)
					OR fetch_socialgroup_modperm('candeletegroupmessages', $this->group)
				)
			)
			OR
			(
				$this->message['state'] == 'deleted'
				AND (
					fetch_socialgroup_modperm('candeletegroupmessages', $this->group)
					OR fetch_socialgroup_modperm('canremovegroupmessages', $this->group)
				)
			)
		);
	}
}

/**
* Deleted message class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Message_Deleted extends vB_Group_Message
{

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = 'socialgroups_message_deleted';
}

/**
* Normal message class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Message_Message extends vB_Group_Message
{

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = 'socialgroups_message';
}

/**
* Ignored message class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Message_Ignored extends vB_Group_Message
{

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = 'socialgroups_message_ignored';
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:50, Wed Oct 21st 2026
|| # SVN: $Revision: 27795 $
|| ####################################################################
\*======================================================================*/
?>
