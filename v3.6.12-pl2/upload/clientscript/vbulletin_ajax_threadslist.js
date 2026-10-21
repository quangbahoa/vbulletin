/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.6.12 Patch Level 2
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

var vB_ThreadTitle_Editor = null;

/**
* Adds ondblclick events to appropriate elements for title editing
*
* @param	string	The ID of the thread list element (usually 'threadslist')
*/
function vB_AJAX_Threadlist_Init(threadlistid)
{
	if (AJAX_Compatible && (typeof vb_disable_ajax == 'undefined' || vb_disable_ajax < 2))
	{
		var tds = fetch_tags(fetch_object(threadlistid), 'td');
		for (var i = 0; i < tds.length; i++)
		{
			if (tds[i].hasChildNodes() && tds[i].id && tds[i].id.substr(0, 3) == 'td_')
			{
				var anchors = fetch_tags(tds[i], 'a');
				for (var j = 0; j < anchors.length; j++)
				{
					if (anchors[j].name && anchors[j].name.indexOf('vB::AJAX') != -1)
					{
						var details = tds[i].id.split('_');

						switch (details[1])
						{
							case 'threadtitle':
							{
								if (typeof vb_disable_ajax == 'undefined' || vb_disable_ajax == 0)
								{
									tds[i].style.cursor = 'default';
									tds[i].ondblclick = vB_AJAX_ThreadList_Events.prototype.threadtitle_doubleclick;
								}
							}
							break;

							case 'threadstatusicon':
							{
								tds[i].style.cursor = pointer_cursor;
								tds[i].ondblclick = vB_AJAX_ThreadList_Events.prototype.threadicon_doubleclick;
							}
							break;
						}

						break;
					}
				}
			}
		}
	}
}

// #############################################################################
// vB_AJAX_OpenClose
// #############################################################################

/**
* Class to handle opening and closing of threads from forumdisplay with XML-HTTP
*
* @param	object	The clickable status icon image for the thread
*/
function vB_AJAX_OpenClose(obj)
{
	this.obj = obj;
	this.threadid = this.obj.id.substr(this.obj.id.lastIndexOf('_') + 1);
	this.imgobj = fetch_object('thread_statusicon_' + this.threadid);
	this.xml_sender = null;

	// =============================================================================
	// vB_AJAX_OpenClose methods

	/**
	* Function to switch the open/closed state of a thread / thread status icon
	*/
	this.toggle = function()
	{
		this.xml_sender = new vB_AJAX_Handler(true);
		this.xml_sender.onreadystatechange(this.onreadystatechange);
		this.xml_sender.send('ajax.php?do=updatethreadopen&t=' + this.threadid, 'do=updatethreadopen&t=' + this.threadid + '&src=' + PHP.urlencode(this.imgobj.src));
	}

	var me = this;

	/**
	* OnReadyStateChange callback. Uses a closure to keep state.
	* Remember to use me instead of this inside this function!
	*/
	this.onreadystatechange = function()
	{
		if (me.xml_sender.handler.readyState == 4 && me.xml_sender.handler.status == 200)
		{
			if (me.xml_sender.handler.responseXML)
			{
				me.imgobj.src = me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'imagesrc')[0]);
				if (iobj = fetch_object("tlist_" + me.threadid))
				{
					if (me.imgobj.src.indexOf('_lock') != -1)
					{	// locked thread - add lock status to inline mod
						iobj.value = iobj.value | 1;
					}
					else
					{	// unlocked thread - remove lock status from inline mod
						iobj.value = iobj.value & ~1;
					}
				}
			}

			if (is_ie)
			{
				me.xml_sender.handler.abort();
			}
		}
	}

	// send the data
	this.toggle();
}

// #############################################################################
// vB_AJAX_TitleEdit
// #############################################################################

/**
* Class to handle thread title editing with XML-HTTP
*
* @param	object	The <td> containing the title element
*/
function vB_AJAX_TitleEdit(obj)
{
	this.obj = obj;
	this.threadid = this.obj.id.substr(this.obj.id.lastIndexOf('_') + 1);
	this.linkobj = fetch_object('thread_title_' + this.threadid);
	this.container = this.linkobj.parentNode;
	this.editobj = null;
	this.xml_sender = null;

	this.origtitle = '';
	this.editstate = false;

	// =============================================================================
	// vB_AJAX_TitleEdit methods

	/**
	* Function to initialize the editor for a thread title
	*/
	this.edit = function()
	{
		if (this.editstate == false)
		{
			// create the new editor input box properties...
			this.inputobj = document.createElement('input');
			this.inputobj.type = 'text';
			this.inputobj.size = 50;
			// read in value for titlemaxchars from $vbulletin->options['titlemaxchars'], specified in template or default to 85
			this.inputobj.maxLength = ((typeof(titlemaxchars) == "number" && titlemaxchars > 0) ? titlemaxchars : 85);
			this.inputobj.style.width = Math.max(this.linkobj.offsetWidth, 250) + 'px';
			this.inputobj.className = 'bginput';
			this.inputobj.value = PHP.unhtmlspecialchars(this.linkobj.innerHTML);
			this.inputobj.title = this.inputobj.value;

			// ... and event handlers
			this.inputobj.onblur = vB_AJAX_ThreadList_Events.prototype.titleinput_onblur;
			this.inputobj.onkeypress = vB_AJAX_ThreadList_Events.prototype.titleinput_onkeypress;

			// insert the editor box and select it
			this.editobj = this.container.insertBefore(this.inputobj, this.linkobj);
			this.editobj.select();

			// store the original text
			this.origtitle = this.linkobj.innerHTML;

			// hide the link object
			this.linkobj.style.display = 'none';

			// declare that we are in an editing state
			this.editstate = true;
		}
	}

	/**
	* Function to restore a thread title in the editing state
	*/
	this.restore = function()
	{
		if (this.editstate == true)
		{
			// do we actually need to save?
			if (this.editobj.value != this.origtitle)
			{
				this.linkobj.innerHTML = PHP.htmlspecialchars(this.editobj.value);
				this.save(this.editobj.value);
			}
			else
			{
				// set the new contents for the link
				this.linkobj.innerHTML = this.editobj.value;
			}

			// remove the editor box
			this.container.removeChild(this.editobj);

			// un-hide the link
			this.linkobj.style.display = '';

			// declare that we are in a normal state
			this.editstate = false;
			this.obj = null;
		}
	}

	/**
	* Function to save an edited thread title
	*
	* @param	string	Edited title text
	*
	* @return	string	Validated title text
	*/
	this.save = function(titletext)
	{
		this.xml_sender = new vB_AJAX_Handler(true);
		this.xml_sender.onreadystatechange(this.onreadystatechange);
		this.xml_sender.send('ajax.php?do=updatethreadtitle&t=' + this.threadid, 'do=updatethreadtitle&t=' + this.threadid + '&title=' + PHP.urlencode(titletext));
	}

	var me = this;

	/**
	* OnReadyStateChange callback. Uses a closure to keep state.
	* Remember to use me instead of this inside this function!
	*/
	this.onreadystatechange = function()
	{
		if (me.xml_sender.handler.readyState == 4 && me.xml_sender.handler.status == 200)
		{
			if (me.xml_sender.handler.responseXML)
			{
				me.linkobj.innerHTML = me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'linkhtml')[0]);
			}

			if (is_ie)
			{
				me.xml_sender.handler.abort();
			}

			vB_ThreadTitle_Editor.obj = null;
		}
	}

	// start the editor
	this.edit();
}

// #############################################################################
// Threadlist event handlers

/**
* Class to handle events in the threadlist
*/
function vB_AJAX_ThreadList_Events()
{
}

/**
* Handles double-clicking on thread title cells to initialize title edit
*/
vB_AJAX_ThreadList_Events.prototype.threadtitle_doubleclick = function(e)
{
	if (vB_ThreadTitle_Editor && vB_ThreadTitle_Editor.obj == this)
	{
		return false;
	}
	else
	{
		try
		{
			vB_ThreadTitle_Editor.restore();
		}
		catch(e) {}

		vB_ThreadTitle_Editor = new vB_AJAX_TitleEdit(this);
	}
};

/**
* Handles double-clicking on thread icon cells to toggle open/close state
*/
vB_AJAX_ThreadList_Events.prototype.threadicon_doubleclick = function(e)
{
	openclose = new vB_AJAX_OpenClose(this);
};

/**
* Handles blur events on thread title input boxes
*/
vB_AJAX_ThreadList_Events.prototype.titleinput_onblur = function(e)
{
	vB_ThreadTitle_Editor.restore();
};

/**
* Handles keypress events on thread title input boxes
*/
vB_AJAX_ThreadList_Events.prototype.titleinput_onkeypress = function (e)
{
	e = e ? e : window.event;
	switch (e.keyCode)
	{
		case 13: // return / enter
		{
			vB_ThreadTitle_Editor.inputobj.blur();
			return false;
		}
		case 27: // escape
		{
			vB_ThreadTitle_Editor.inputobj.value = vB_ThreadTitle_Editor.origtitle;
			vB_ThreadTitle_Editor.inputobj.blur();
			return true;
		}
	}
};

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 16232 $
|| ####################################################################
\*======================================================================*/