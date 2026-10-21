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

/**
* vBulletin popup menu example usage:
*
* To create a new popup menu:
* 	<element id="x">Click me <script type="text/javascript"> vBmenu.register('x'); </script></element>
*
* To create a dynamic popup menu with a title and two options:
* 	<element id="x">Click me</element>
* 	<script type="text/javascript">
* 	menu = new vB_Menu_Builder('x');
* 	menu.set_title('My Menu');
* 	menu.add_option('Option 1', 'script.php?opt=1');
* 	menu.add_option('Option 2', 'script.php?opt=2');
* 	menu.build();
* 	</script>
*/

// #############################################################################
// vB_Popup_Handler
// #############################################################################

/**
* vBulletin popup menu registry
*/
function vB_Popup_Handler()
{
	/**
	* Options:
	*
	* @var	integer	Number of steps to use in sliding menus open
	* @var	boolean	Use opacity face in menu open?
	*/
	this.open_steps = 10;
	this.open_fade = false;

	this.active = false;

	this.menus = new Array();
	this.activemenu = null;

	this.hidden_selects = new Array();
}

// =============================================================================
// vB_Popup_Handler methods

/**
* Activate / Deactivate the menu system
*
* @param	boolean	Active state for menus
*/
vB_Popup_Handler.prototype.activate = function(active)
{
	this.active = active;
}

/**
* Register a control object as a menu control
*
* @param	string	ID of the control object
* @param	boolean	Do not add an image (true)
*
* @return	vB_Popup_Menu
*/
vB_Popup_Handler.prototype.register = function(controlkey, noimage)
{
	this.menus[controlkey] = new vB_Popup_Menu(controlkey, noimage);

	return this.menus[controlkey];
}

/**
* Hide active menu
*/
vB_Popup_Handler.prototype.hide = function()
{
	if (this.activemenu != null)
	{
		//this.activemenu.hide();
		this.menus[this.activemenu].hide();
	}
}


// #############################################################################
// initialize menu registry

vBmenu = new vB_Popup_Handler();

/**
* Function to allow anything to hide all menus
*
* @param	event	Event object
*
* @return	mixed
*/
function vbmenu_hide(e)
{
	if (e && e.button && e.button != 1 && e.type == 'click')
	{
		return true;
	}
	else
	{
		vBmenu.hide();
	}
}

// #############################################################################
// vB_Popup_Menu
// #############################################################################

/**
* vBulletin popup menu class constructor
*
* Manages a single menu and control object
* Initializes control object
*
* @param	string	ID of the control object
*/
function vB_Popup_Menu(controlkey, noimage)
{
	this.controlkey = controlkey;
	this.menuname = this.controlkey.split('.')[0] + '_menu';

	this.init_control(noimage);

	if (fetch_object(this.menuname))
	{
		this.init_menu();
	}

	this.slide_open = (is_opera ? false : true);
	this.open_steps = vBmenu.open_steps;
}

// =============================================================================
// vB_Popup_Menu methods

/**
* Initialize the control object
*/
vB_Popup_Menu.prototype.init_control = function(noimage)
{
	this.controlobj = fetch_object(this.controlkey);
	this.controlobj.state = false;

	if (this.controlobj.firstChild && (this.controlobj.firstChild.tagName == 'TEXTAREA' || this.controlobj.firstChild.tagName == 'INPUT'))
	{
		// do nothing
	}
	else
	{
		if (!noimage && !(is_mac && is_ie))
		{
			var space = document.createTextNode(' ');
			this.controlobj.appendChild(space);

			var img = document.createElement('img');
			img.src = IMGDIR_MISC + '/menu_open.gif';
			img.border = 0;
			img.title = '';
			img.alt = '';
			this.controlobj.appendChild(img);
		}

		this.controlobj.unselectable = true;
		if (!noimage)
		{
			this.controlobj.style.cursor = pointer_cursor;
		}
		this.controlobj.onclick = vB_Popup_Events.prototype.controlobj_onclick;
		this.controlobj.onmouseover = vB_Popup_Events.prototype.controlobj_onmouseover;
	}
}

/**
* Init the popup menu object
*/
vB_Popup_Menu.prototype.init_menu = function()
{
	this.menuobj = fetch_object(this.menuname);

	if (this.menuobj && !this.menuobj.initialized)
	{
		this.menuobj.initialized = true;
		this.menuobj.onclick = e_by_gum;
		this.menuobj.style.position = 'absolute';
		this.menuobj.style.zIndex = 50;

		// init popup filters (ie only)
		if (is_ie && !is_mac)
		{
			this.menuobj.style.filter += "progid:DXImageTransform.Microsoft.alpha(enabled=1,opacity=100)";
			this.menuobj.style.filter += "progid:DXImageTransform.Microsoft.shadow(direction=135,color=#8E8E8E,strength=3)";
		}

		this.init_menu_contents();
	}
}

/**
* Init the popup menu contents
*/
vB_Popup_Menu.prototype.init_menu_contents = function()
{
	var tds = fetch_tags(this.menuobj, 'td');
	for (var i = 0; i < tds.length; i++)
	{
		if (tds[i].className == 'vbmenu_option')
		{
			if (tds[i].title && tds[i].title == 'nohilite')
			{
				// not an active cell
				tds[i].title = '';
			}
			else
			{
				// create a reference back to the menu class
				tds[i].controlkey = this.controlkey;

				// handle mouseover / mouseout highlighting events
				tds[i].onmouseover = vB_Popup_Events.prototype.menuoption_onmouseover;
				tds[i].onmouseout = vB_Popup_Events.prototype.menuoption_onmouseout;

				if (typeof tds[i].onclick == 'function')
				{
					// allow onclick events from <td> elements to override <a> elements inside
					tds[i].ofunc = tds[i].onclick;
					tds[i].onclick = vB_Popup_Events.prototype.menuoption_onclick_function;
				}
				else
				{
					// attempt to emulate a click on internal <a> elements
					tds[i].onclick = vB_Popup_Events.prototype.menuoption_onclick_link;
				}

				// wondering what this was supposed to do actually...
				if (!is_saf && !is_kon)
				{
					try
					{
						links = fetch_tags(tds[i], 'a');
						for (var j = 0; j < links.length; j++)
						{
							if (typeof links[j].onclick  == 'undefined')
							{
								links[j].onclick = e_by_gum;
							}
						}
					}
					catch(e)
					{
						// hmmm...
					}
				}
			}
		}
	}
}

/**
* Show the menu
*
* @param	object	The control object calling the menu
* @param	boolean	Use slide (false) or open instantly? (true)
*/
vB_Popup_Menu.prototype.show = function(obj, instant)
{
	if (!vBmenu.active)
	{
		return false;
	}
	else if (!this.menuobj)
	{
		this.init_menu();
	}

	if (!this.menuobj)
	{
		return false;
	}

	if (vBmenu.activemenu != null)
	{
		vBmenu.menus[vBmenu.activemenu].hide();
	}

	vBmenu.activemenu = this.controlkey;

	this.menuobj.style.display = '';
	if (vBmenu.slide_open)
	{
		this.menuobj.style.clip = 'rect(auto, auto, auto, auto)';
	}
	this.pos = this.fetch_offset(obj);
	this.leftpx = this.pos['left'];
	this.toppx = this.pos['top'] + obj.offsetHeight;

	if ((this.leftpx + this.menuobj.offsetWidth) >= document.body.clientWidth && (this.leftpx + obj.offsetWidth - this.menuobj.offsetWidth) > 0)
	{
		this.leftpx = this.leftpx + obj.offsetWidth - this.menuobj.offsetWidth;
		this.direction = 'right';
	}
	else
	{
		this.direction = 'left'
	}

	this.menuobj.style.left = this.leftpx + 'px';
	this.menuobj.style.top  = this.toppx + 'px';

	if (!instant && this.slide_open)
	{
		this.intervalX = Math.ceil(this.menuobj.offsetWidth / this.open_steps);
		this.intervalY = Math.ceil(this.menuobj.offsetHeight / this.open_steps);
		this.slide((this.direction == 'left' ? 0 : this.menuobj.offsetWidth), 0, 0);
	}
	else if (this.menuobj.style.clip && vBmenu.slide_open)
	{
		this.menuobj.style.clip = 'rect(auto, auto, auto, auto)';
	}

	// deal with IE putting <select> elements on top of everything
	this.handle_overlaps(true);

	if (this.controlobj.editorid)
	{
		this.controlobj.state = true;
		//this.controlobj.editor.menu_context(this.controlobj, 'mousedown');
		vB_Editor[this.controlobj.editorid].menu_context(this.controlobj, 'mousedown');
	}
}

/**
* Hide the menu
*/
vB_Popup_Menu.prototype.hide = function(e)
{

	if (e && e.button && e.button != 1)
	{
		// get around some context menu issues etc.
		return true;
	}

	this.stop_slide();

	this.menuobj.style.display = 'none';

	this.handle_overlaps(false);

	if (this.controlobj.editorid)
	{
		this.controlobj.state = false;
		//this.controlobj.editor.menu_context(this.controlobj, 'mouseout');
		vB_Editor[this.controlobj.editorid].menu_context(this.controlobj, 'mouseout');
	}

	vBmenu.activemenu = null;
}

/**
* Hover behaviour for control object
*/
vB_Popup_Menu.prototype.hover = function(obj)
{
	if (vBmenu.activemenu != null)
	{
		if (vBmenu.menus[vBmenu.activemenu].controlkey != this.id)
		{
			this.show(obj, true);
		}
	}
}

/**
* Simulate clicking an option in a menu
*
* @param	event	Event object
* @param	object	Clicked object
*/
vB_Popup_Menu.prototype.choose = function(e, obj)
{
	var links = fetch_tags(obj, 'a');
	if (links[0])
	{
		if (is_ie)
		{
			// use this in IE to send HTTP_REFERER
			links[0].click();
			window.event.cancelBubble = true;
		}
		else
		{
			// other browsers can use this
			if (e.shiftKey)
			{
				window.open(links[0].href);
				e.stopPropagation();
				e.preventDefault();
			}
			else
			{
				window.location = links[0].href;
				e.stopPropagation();
				e.preventDefault();
			}
		}

		this.hide();
	}
}

/**
* Slides menu open
*
* @param	integer	Clip X
* @param	integer	Clip Y
* @param	integer	Opacity (0-100)
*/
vB_Popup_Menu.prototype.slide = function(clipX, clipY, opacity)
{
	if (this.direction == 'left' && (clipX < this.menuobj.offsetWidth || clipY < this.menuobj.offsetHeight))
	{
		if (vBmenu.open_fade && is_ie)
		{
			opacity += 10;
			this.menuobj.filters.item('DXImageTransform.Microsoft.alpha').opacity = opacity;
		}

		clipX += this.intervalX;
		clipY += this.intervalY;

		this.menuobj.style.clip = "rect(auto, " + clipX + "px, " + clipY + "px, auto)";
		this.slidetimer = setTimeout("vBmenu.menus[vBmenu.activemenu].slide(" + clipX + ", " + clipY + ", " + opacity + ");", 0);
	}
	else if (this.direction == 'right' && (clipX > 0 || clipY < this.menuobj.offsetHeight))
	{
		if (vBmenu.open_fade && is_ie)
		{
			opacity += 10;
			menuobj.filters.item('DXImageTransform.Microsoft.alpha').opacity = opacity;
		}

		clipX -= this.intervalX;
		clipY += this.intervalY;

		this.menuobj.style.clip = "rect(auto, " + this.menuobj.offsetWidth + "px, " + clipY + "px, " + clipX + "px)";
		this.slidetimer = setTimeout("vBmenu.menus[vBmenu.activemenu].slide(" + clipX + ", " + clipY + ", " + opacity + ");", 0);
	}
	else
	{
		this.stop_slide();
	}
}

/**
* Abort menu slider
*/
vB_Popup_Menu.prototype.stop_slide = function()
{
	clearTimeout(this.slidetimer);

	this.menuobj.style.clip = 'rect(auto, auto, auto, auto)';

	if (vBmenu.open_fade && is_ie)
	{
		this.menuobj.filters.item('DXImageTransform.Microsoft.alpha').opacity = 100;
	}
}

/**
* Fetch offset of an object
*
* @param	object	The object to be measured
*
* @return	array	The measured offsets left/top
*/
vB_Popup_Menu.prototype.fetch_offset = function(obj)
{
	var left_offset = obj.offsetLeft;
	var top_offset = obj.offsetTop;

	while ((obj = obj.offsetParent) != null)
	{
		left_offset += obj.offsetLeft;
		top_offset += obj.offsetTop;
	}

	return { 'left' : left_offset, 'top' : top_offset };
}

/**
* Detect an overlap of an object and a menu
*
* @param	object	Object to be tested for overlap
* @param	array	Array of dimensions for menu object
*
* @return	boolean	True if overlap
*/
vB_Popup_Menu.prototype.overlaps = function(obj, m)
{
	var s = new Array();
	var pos = this.fetch_offset(obj);
	s['L'] = pos['left'];
	s['T'] = pos['top'];
	s['R'] = s['L'] + obj.offsetWidth;
	s['B'] = s['T'] + obj.offsetHeight;


	if (s['L'] > m['R'] || s['R'] < m['L'] || s['T'] > m['B'] || s['B'] < m['T'])
	{
		return false;
	}
	return true;
}

/**
* Handle IE overlapping <select> elements
*
* @param	boolean	Hide (true) or show (false) overlapping <select> elements
*/
vB_Popup_Menu.prototype.handle_overlaps = function(dohide)
{
	if (is_ie)
	{
		var selects = fetch_tags(document, 'select');

		if (dohide)
		{
			var menuarea = new Array(); menuarea = {
				'L' : this.leftpx,
				'R' : this.leftpx + this.menuobj.offsetWidth,
				'T' : this.toppx,
				'B' : this.toppx + this.menuobj.offsetHeight
			};

			for (var i = 0; i < selects.length; i++)
			{
				if (this.overlaps(selects[i], menuarea))
				{
					var hide = true;
					var s = selects[i];
					while (s = s.parentNode)
					{
						if (s.className == 'vbmenu_popup')
						{
							hide = false;
							break;
						}
					}

					if (hide)
					{
						selects[i].style.visibility = 'hidden';
						array_push(vBmenu.hidden_selects, i);
					}
				}
			}
		}
		else
		{
			while (true)
			{
				var i = array_pop(vBmenu.hidden_selects);
				if (typeof i == 'undefined' || i == null)
				{
					break;
				}
				else
				{
					selects[i].style.visibility = 'visible';
				}
			}
		}
	}
}

// #############################################################################
// Menu event handler functions

/**
* Class containing menu popup event handlers
*/
function vB_Popup_Events()
{
}

/**
* Handles control object click events
*/
vB_Popup_Events.prototype.controlobj_onclick = function(e)
{
	do_an_e(e);
	if (vBmenu.activemenu == null || vBmenu.menus[vBmenu.activemenu].controlkey != this.id)
	{
		vBmenu.menus[this.id].show(this);
	}
	else
	{
		vBmenu.menus[this.id].hide();
	}
};

/**
* Handles control object mouseover events
*/
vB_Popup_Events.prototype.controlobj_onmouseover = function(e)
{
	do_an_e(e);
	vBmenu.menus[this.id].hover(this);
};

/**
* Handles menu option click events for options with onclick events
*/
vB_Popup_Events.prototype.menuoption_onclick_function = function(e)
{
	this.ofunc(e);
	vBmenu.menus[this.controlkey].hide();
};

/**
* Handles menu option click events for options containing links
*/
vB_Popup_Events.prototype.menuoption_onclick_link = function(e)
{
	vBmenu.menus[this.controlkey].choose(e, this);
};

/**
* Handles menu option mouseover events
*/
vB_Popup_Events.prototype.menuoption_onmouseover = function(e)
{
	this.className = 'vbmenu_hilite';
};

/**
* Handles menu option mouseout events
*/
vB_Popup_Events.prototype.menuoption_onmouseout = function(e)
{
	this.className = 'vbmenu_option';
};

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13550 $
|| ####################################################################
\*======================================================================*/