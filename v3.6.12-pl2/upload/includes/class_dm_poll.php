<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2 - Licence Number VBS4AAFB47
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
* Class to do data save/delete operations for POLLS
*
* Example usage (inserts a poll):
*
* $poll =& datamanager_init('Poll', $vbulletin, ERRTYPE_STANDARD);
* $options = array ("first", "second", "third", "fourth", "fifth", "sixth");
*
* foreach ($options AS $option)
* {
* 	$poll->set_option($option);
* }
* $poll->set_vote(0, 4);
* $poll->set_vote(0);
* $poll->set_vote(1, 7);
* $poll->set_vote(2, 8);
* $poll->set_vote(3, 5);
* $poll->set_vote(4, 9);
* $poll->set_vote(5, 15);
*
* $poll->save();
*
* @package	vBulletin
* @version	$Revision: 24181 $
* @date		$Date: 2007-10-15 04:46:02 -0500 (Mon, 15 Oct 2007) $
*/
class vB_DataManager_Poll extends vB_DataManager
{
	/**
	* Array of recognised and required fields for poll, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'pollid'		=> array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'question'		=> array(TYPE_NOHTMLCOND, REQ_YES),
		'dateline'      => array(TYPE_UINT,       REQ_AUTO),
		'options'		=> array(TYPE_STR,        REQ_YES,  VF_METHOD, 'verify_poll_options'),
		'votes'			=> array(TYPE_STR,        REQ_YES,  VF_METHOD, 'verify_poll_votes'),
		'active'		=> array(TYPE_BOOL,       REQ_NO),   # Default 1
		'numberoptions'	=> array(TYPE_UINT,       REQ_NO),   # Built in pre_save
		'timeout'		=> array(TYPE_UINT,       REQ_NO),   # Default 0
		'multiple'		=> array(TYPE_BOOL,       REQ_NO),   # Default 0
		'voters'		=> array(TYPE_UINT,       REQ_NO),   # Default 0
		'public'		=> array(TYPE_UINT,       REQ_NO),   # Default 0
		'lastvote'      => array(TYPE_UINT,       REQ_AUTO), # A date line ?
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('pollid = %1$d', 'pollid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'poll';

	/**
	* Array to store stuff to save to poll table
	*
	* @var	array
	*/
	var $poll = array();

	/**
	* Array to store poll options
	*
	* @var	array
	*/
	var $poll_options = array();

	/**
	* Array to store poll votes
	*
	* @var	array
	*/
	var $poll_votes = array();

	var $deleted_options = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Poll(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('polldata_start')) ? eval($hook) : false;
	}

	/**
	* Setting the text for a poll option
	*
	* @param	string	The option text for a poll
	* @param	integer	(optional) The position of the option, if you need to over write a option, a blank text will delete will also delete the votes
	* @param	integer	An optional number of votes to set this option to
	*
	* @return 	boolean	Returns true if user exists
	*/
	function set_option($option_text, $option_number = NULL, $votes = null)
	{
		if ($option_number < 0)
		{
			return false;
		}

		end($this->poll_options);
		$max_option = key($this->poll_options);

		if ($option_number === NULL OR $option_number > $max_option AND $option_text)
		{
			if ($option_number === NULL)
			{
				$option_number = $max_option + 1;
			}

			// Simple add OR adding past the end so default to end
			$this->poll_options["$option_number"] = $option_text;

			// Set up a matchings 0 vote value in the poll_votes
			$this->set_vote($option_number, ($votes === null ? 0 : intval($votes)));
		}
		else if ($option_number <= $max_option)
		{
			if ($option_text) // A positional overwrite
			{
				$this->poll_options["$option_number"] = $option_text;
				if ($votes !== null)
				{
					$this->set_vote($option_number, intval($votes));
				}
			}
			else if (isset($this->poll_options["$option_number"])) // A positional delete
			{
				// Remove the vote first as it checks that an option exsits before
				// doing anything.
				$this->set_vote($option_number, 'remove');
				unset($this->poll_options["$option_number"]);

				$this->deleted_options[] = intval($option_number) + 1; // vote choices stored 1-based
			}
		}

		ksort($this->poll_options);
	}

	/**
	* Returns the text at a given position or the position of a given option
	*
	* @param	mixed	The option text to find or the position to return
	* @param	bool 	Default true, if true then $lookup is an int and the position, else the option text
	*
	* @return 	boolean	Returns true if user exists
	*/
	function get_option($lookup, $position = true)
	{
		if ($position)
		{
			// Is it NULL or 0 that its looking for ?
			if ($lookup === NULL)
			{

				// No such option
				return false;
			}
			else
			{
				// If its not there its going to be null
				return $this->poll_options["$lookup"];
			}
		}

		if ($found = array_search($lookup, $this->poll_options))
		{
			return $found;
		}
		else
		{
			return NULL;
		}
	}

	/**
	* Resets the options and votes.
	*/
	function clear_options()
	{
		$this->poll_options = array();
		$this->poll_votes = array();
	}

	/**
	* Setting the vote for an option
	*
	* @param	int		The position that the vote should be set for
	* @param	int 	(optional) The position of the option, if you need to over write a option, a blank text will delete will also delete the votes
	*
	* @return 	boolean	Returns true if user exists
	*/
	function set_vote($position, $increment = true)
	{
		// No such option OR there is no option to match
		if ($position === NULL OR $this->poll_options["$position"] === NULL)
		{
			return false;
		}
		// Going to be the default
		else if ($increment === true)
		{
			$this->poll_votes[$position]++;
		}
		// Setting it straight to 5 for instance
		else if (is_int($increment))
		{
			$this->poll_votes["$position"] = $increment;
		}
		// Removing the vote information, probally because the option has been removed
		else if ($increment === 'remove')
		{
			unset($this->poll_votes["$position"]);
		}

		ksort($this->poll_votes);

		return true;
	}

	/**
	* Get a vote for an option
	*
	* @param	mixed	The numeric position or text of the option
	* @param	bool 	Indicates if it is a position or a option text to look up
	*
	* @return 	boolean	Returns true if user exists
	*/
	function get_vote($lookup, $position = true)
	{
		if ($position)
		{
			// Is it NULL or 0 that its looking for ?
			if ($lookup === NULL)
			{
				// No such option
				return false;
			}
			else
			{
				// If its not there its going to be null
				return $this->poll_votes[$lookup];
			}
		}
		else if ($found = array_search($lookup, $this->poll_options))
		{
			return $this->poll_votes[$found];
		}
		else
		{
			return NULL;
		}
	}

	/**
	* Format the data for saving
	*
	* @param	bool
	*
	* @return 	boolean	Function result
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$pollcount = count($this->poll_options);
		if ($this->registry->options['maxpolloptions'] > 0 AND $pollcount > $this->registry->options['maxpolloptions'])
		{
			// slice out the options/votes that are out of range
			$pollcount = $this->registry->options['maxpolloptions'];
			$this->poll_options = array_slice($this->poll_options, 0, $pollcount);
			$this->poll_votes = array_slice($this->poll_votes, 0, $pollcount);
		}

		$this->do_set('numberoptions', $pollcount);

		$polloptions = implode('|||', $this->poll_options);
		$this->do_set('options', $polloptions);

		$pollvotes = implode('|||', $this->poll_votes);
		$this->do_set('votes', $pollvotes);

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('polldata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		if ($this->deleted_options AND $this->existing['pollid'])
		{
			// we deleted some options, so we need to:
			// 1. remove votes for those options
			// 2. adjust later votes to point to the new position for their options

			// remember votes are stored 1-based, so this has already been offset
			$deleted = implode(',', $this->deleted_options);

			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "pollvote
				WHERE pollid = " . $this->existing['pollid'] . "
					AND voteoption IN ($deleted)
			");

			rsort($this->deleted_options, SORT_NUMERIC);

			// move any options later than a deleted option down by 1
			// this works back to front, so higher options will be decremented more if necessary
			foreach ($this->deleted_options AS $deloption)
			{
				$this->dbobject->query_write("
					UPDATE " . TABLE_PREFIX . "pollvote SET
						voteoption = voteoption - 1
					WHERE pollid = " . $this->existing['pollid'] . "
						AND voteoption > $deloption
				");
			}
		}

		($hook = vBulletinHook::fetch_hook('polldata_postsave')) ? eval($hook) : false;
	}

	/**
	* Removing the votes from the
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{

		if ($this->existing['pollid'])
		{
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "pollvote
				WHERE pollid = " . $this->existing['pollid']
			);
		}

		($hook = vBulletinHook::fetch_hook('polldata_delete')) ? eval($hook) : false;
	}

	/**
	* Verifies that there at at least 2 options
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_poll_options(&$poll_options)
	{
		return (count($this->poll_options) >= 2 ? true : false);
	}

	/**
	* Checks that the vote is between 0 and 5
	*
	* @param	integer	The vote
	*
	* @return	boolean	Returns true on success
	*/
	function verify_poll_votes(&$vote)
	{
		// Check there is at least one seperator
		if(!substr($vote, '|||')) { return false; }

		$votes = explode('|||', $vote);

		//Check that there is at least 2 votes
		if (count($votes) < 2) { return false; }

		return true;
	}

	function set_existing(&$existing)
	{
		parent::set_existing($existing);

		if (isset($existing['votes']))
		{
			$this->poll_votes = explode('|||', $existing['votes']);
		}

		if (isset($existing['options']))
		{
			$this->poll_options = explode('|||', $existing['options']);
		}
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 24181 $
|| ####################################################################
\*======================================================================*/
?>