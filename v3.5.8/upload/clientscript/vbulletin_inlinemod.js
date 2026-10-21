/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// #############################################################################
// vB_Inline_Mod
// #############################################################################

/**
* Inline Moderation Class
*
* @param	string	Name of the instance of this class
* @param	string	Type of system (thread/post)
* @param	string	ID of the form containing all checkboxes
* @param	string	Phrase for use on Go button
*/
function vB_Inline_Mod(varname, type, formobjid, go_phrase)
{
	/**
	* Variables created from arguments
	*
	* @var	string
	* @var	string
	* @var	object
	* @var	string
	*/
	this.varname = varname;
	this.type = (type.toLowerCase() == 'thread' ? 'thread' : 'post');
	this.formobj = fetch_object(formobjid);
	this.go_phrase = go_phrase;
	
	/**
	* Other variables
	*
	* @var	string	Prefix for all checkbox IDs
	* @var	integer	Number of items checked on this page
	* @var	array	Array of IDs fetched from the vbulletin_inline cookie
	* @var	array	Array of IDs ready to be saved into the vbulletin_inline cookie
	*/
	this.list = (this.type == 'thread' ? 'tlist_' : 'plist_');
	this.cookie_ids = null;
	this.cookie_array = new Array();
	
	// =============================================================================
	// vB_Inline_Mod methods
	
	/**
	* Initialization action to run on page load
	*/
	this.init = function()
	{
		// attach clickfunc to all checkboxes
		for (i = 0; i < this.formobj.elements.length; i++)
		{
			if (this.is_in_list(this.formobj.elements[i]))
			{
				this.formobj.elements[i].inlineModID = this.varname;
				this.formobj.elements[i].onclick = inlinemod_checkbox_onclick;
			}
		}
		
		if (this.fetch_ids())
		{
			for (i in this.cookie_ids)
			{
				if (this.cookie_ids[i] != '')
				{
					if (checkbox = fetch_object(this.list + this.cookie_ids[i]))
					{
						checkbox.checked = true;
						
						if (this.type == 'thread')
						{
							this.highlight_thread(checkbox);
						}
						else
						{
							this.highlight_post(checkbox);
						}
					}
					this.cookie_array[this.cookie_array.length] = this.cookie_ids[i];
				}
			}
		}

		this.set_output_counters();
	}
	
	/**
	* Returns an array of IDs from the inlinemod cookie
	*
	* @return	boolean	True if array created
	*/
	this.fetch_ids = function()
	{
		this.cookie_ids = fetch_cookie('vbulletin_inline' + this.type);
		
		if (this.cookie_ids != null && this.cookie_ids != '')
		{
			this.cookie_ids = this.cookie_ids.split('-');
			if (this.cookie_ids.length > 0)
			{
				return true;
			}
		}
		
		return false;	
	}
	
	/**
	* Toggles the selected state of an inline moderation item, updates the cookie
	*
	* @param	string	ID of the checkbox
	*
	* @return	boolean
	*/
	this.toggle = function(checkbox)
	{	
		if (this.type == 'thread')
		{
			this.highlight_thread(checkbox);
		}
		else
		{
			this.highlight_post(checkbox);
		}
		
		this.save(checkbox.id.substr(6), checkbox.checked);
	}
	
	/**
	* Saves the inline moderation cookie
	*
	* @param	string	Item ID
	* @param	boolean	Add id to array?
	*
	* @return	boolean
	*/
	this.save = function(checkboxid, checked)
	{
		this.cookie_array = new Array();
	
		if (this.fetch_ids())
		{
			for (i in this.cookie_ids)
			{
				if (this.cookie_ids[i] != checkboxid && this.cookie_ids[i] != '')
				{
					this.cookie_array[this.cookie_array.length] = this.cookie_ids[i];
				}
			}
		}
	
		if (checked)
		{
			this.cookie_array[this.cookie_array.length] = checkboxid;
		}
	
		this.set_output_counters();
	
		this.set_cookie();
		
		return true;
	}
	
	/**
	* Saves the inline moderation cookie
	*/
	this.set_cookie = function()
	{
		expires = new Date();
		expires.setTime(expires.getTime() + 3600000);
		set_cookie('vbulletin_inline' + this.type, this.cookie_array.join('-'), expires);
	}
	
	/**
	* Check / Uncheck All Inline Moderation Checkboxes
	*/
	this.check_all = function(checked, itemtype, caller)
	{
		if (typeof checked == 'undefined')
		{
			checked = this.formobj.allbox.checked;
		}
		
		this.cookie_array = new Array();
		
		// Remove all items on this page from the cookie list
		if (this.fetch_ids())
		{
			for (i in this.cookie_ids)
			{
				if (!fetch_object(this.list + this.cookie_ids[i]))
				{
					// this item is not on this page so put back in the cookie
					this.cookie_array[this.cookie_array.length] = this.cookie_ids[i]
				}
			}
		}
		
		counter = 0;
	
		// check/uncheck all boxes
		for (var i = 0; i < this.formobj.elements.length; i++)
		{				
			if (this.is_in_list(this.formobj.elements[i]))
			{				
				elm = this.formobj.elements[i];				
				
				if (typeof itemtype != 'undefined')
				{
					if (elm.value & itemtype)
					{
						elm.checked = checked;
					}
					else
					{
						elm.checked = !checked;
					}
				}
				else if (checked == 'invert')
				{
					elm.checked = !elm.checked;
				}
				else
				{
					elm.checked = checked;
				}
				
				if (this.type == 'thread')
				{
					this.highlight_thread(elm);
				}
				else
				{
					this.highlight_post(elm);
				}
				
				if (elm.checked)
				{
					// add item to cookie if we are 'checking' it
					this.cookie_array[this.cookie_array.length] = elm.id.substring(6);
				}
			}
		}
	
		this.set_output_counters();
	
		this.set_cookie();
		
		return true;
	}
	
	this.is_in_list = function(obj)
	{
		return (obj.type == 'checkbox' && obj.id.indexOf(this.list) == 0 && (obj.disabled == false || obj.disabled == 'undefined'));
	}
	
	/**
	* Sets the value of the inline go button and the menu feedback
	*/
	this.set_output_counters = function()
	{
		if (obj = fetch_object('inlinego'))
		{
			obj.value = construct_phrase(this.go_phrase, this.cookie_array.length);
		}
	}
	
	/**
	* Toggles an element's classname between original and 'inlinemod'
	*
	* @param	object	The element on which to work
	* @param	object	The checkbox corresponding to the cell element
	*/
	this.toggle_highlight = function(cell, checkbox)
	{
		if (cell.className == 'alt1' || cell.className == 'alt2' || cell.className == 'inlinemod')
		{
			if (checkbox.checked)
			{
				if (!cell.oclassName)
				{
					cell.oclassName = cell.className;
				}
				cell.className = 'inlinemod';
			}
			else if (cell.oclassName)
			{
				cell.className = cell.oclassName;
			}
		}
	}
	
	/**
	* Highlights a thread <tr> in a thread listing
	*
	* @param	object	The checkbox for the thread
	*/
	this.highlight_thread = function(checkbox)
	{
		tobj = checkbox;
		while (tobj.tagName != 'TR')
		{
			if (tobj.parentNode.tagName == 'HTML')
			{
				break;
			}
			else
			{			
				tobj = tobj.parentNode;
			}
		}
		if (tobj.tagName == 'TR')
		{
			tds = tobj.childNodes;
			for (var i = 0; i < tds.length; i++)
			{
				this.toggle_highlight(tds[i], checkbox);
			}
		}
	}
	
	/**
	* Highlights a post <table> on showthread
	*
	* @param	object	The checkbox for the post
	*/
	this.highlight_post = function(checkbox)
	{
		if (table = fetch_object('post' + checkbox.id.substr(6)))
		{
			tds = fetch_tags(table, 'td');
			for (var i = 0; i < tds.length; i++)
			{
				this.toggle_highlight(tds[i], checkbox);
			}
		}
	}
	
	// get everything running
	this.init();
}

/**
* Function to handle checkboxes being clicked
*
* @param	event	Event object
*/
function inlinemod_checkbox_onclick(e)
{
	var inlineModObj = eval(this.inlineModID);
	inlineModObj.toggle(this);
};

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12698 $
|| ####################################################################
\*======================================================================*/