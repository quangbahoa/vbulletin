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

// #############################################################################
// vB_Text_Editor

/**
* vBulletin Editor Class
*
* Activates any HTML controls for an editor
*
* @param	string	Unique key for this editor
* @param	boolean	Initialise to WYSIWYG mode?
* @param	string	Forumid / Calendar etc.
* @param	boolean	Parse smilies?
* @param	string	(Optional) Initial text for the editor
* @param	string	(Optional) Extra arguments to pass when switching editor modes
*/
function vB_Text_Editor(editorid, mode, parsetype, parsesmilies, initial_text, ajax_extra)
{
	/**
	* Miscellaneous variables
	*
	* @var	string	Unique Editor ID
	* @var	boolean	WYSIWYG mode
	* @var	boolean	Have we initialized the editor?
	* @var	mixed	Passed parsetype (corresponds to bbcodeparse forumid)
	* @var	boolean	Passed parsesmilies option
	* @var	boolean	Can we use vBmenu popups?
	* @var	object	The element containing controls
	* @var	object	The textarea object containing the initial text
	* @var	array	Array containing all button objects
	* @var	array	Array containing all popup objects
	* @var	object	Current prompt() emulation popup
	* @var	string	State of the font context control
	* @var	string	State of the size context control
	* @var	string	State of the color context control
	* @var	string	String to contain the fake 'clipboard'
	* @var	boolean	Is the editor 'disabled'? (quick reply use)
	* @var	vB_History	History manager for undo/redo systems
	* @var  integer Is the editor mode trying to be changed?
	*/
	this.editorid = editorid;
	this.wysiwyg_mode = parseInt(mode, 10) ? 1 : 0;
	this.initialized = false;
	this.parsetype = (typeof parsetype == 'undefined' ? 'nonforum' : parsetype);
	this.ajax_extra = (typeof parsetype == 'undefined' ? '' : ajax_extra);
	this.parsesmilies = (typeof parsesmilies == 'undefined' ? 1 : parsesmilies);
	this.popupmode = (typeof vBmenu == 'undefined' ? false : true);
	this.controlbar = fetch_object(this.editorid + '_controls');
	this.textobj = fetch_object(this.editorid + '_textarea');
	this.buttons = new Array();
	this.popups = new Array();
	this.prompt_popup = null;
	this.fontstate = null;
	this.sizestate = null;
	this.colorstate = null;
	this.clipboard = '';
	this.disabled = false;
	this.history = new vB_History();
	this.influx = 0;

	// =============================================================================
	// vB_Text_Editor methods

	/**
	* Editor initialization wrapper
	*/
	this.init = function()
	{
		if (this.initialized)
		{
			return;
		}

		this.textobj.disabled = false;

		if (this.tempiframe)
		{
			this.tempiframe.parentNode.removeChild(this.tempiframe);
		}

		this.set_editor_contents(initial_text);

		this.set_editor_functions();

		this.init_controls();

		this.init_smilies(fetch_object(this.editorid + '_smiliebox'));

		if (typeof smilie_window != 'undefined' && !smilie_window.closed)
		{
			this.init_smilies(smilie_window.document.getElementById('smilietable'));
		}

		this.captcha = document.getElementById("imagestamp");
		if (this.captcha != null)
		{
			this.captcha.setAttribute("tabIndex", 1);
		}

		this.initialized = true;
	};

	/**
	* Check if we need to refocus the editor window
	*/
	this.check_focus = function()
	{
		if (!this.editwin.hasfocus)
		{
			this.editwin.focus();
			if (is_opera)
			{
				// see http://www.vbulletin.com/forum/bugs35.php?do=view&bugid=687
				this.editwin.focus();
			}
		}
	}

	/**
	* Init button controls for the editor
	*/
	this.init_controls = function()
	{
		var controls = new Array();

		if (this.controlbar == null)
		{
			return;
		}

		var buttons = fetch_tags(this.controlbar, 'div');
		for (var i = 0; i < buttons.length; i++)
		{
			if (buttons[i].className == 'imagebutton' && buttons[i].id)
			{
				controls[controls.length] = buttons[i].id;
			}
		}
		for (var i = 0; i < controls.length; i++)
		{
			var control = fetch_object(controls[i]);

			if (control.id.indexOf(this.editorid + '_cmd_') != -1)
			{
				this.init_command_button(control);
			}
			else if (control.id.indexOf(this.editorid + '_popup_') != -1)
			{
				this.init_popup_button(control);
			}
		}

		set_unselectable(this.controlbar);
	};

	/**
	* Init Smilies
	*/
	this.init_smilies = function(smilie_container)
	{
		if (smilie_container != null)
		{
			var smilies = fetch_tags(smilie_container, 'img');
			for (var i = 0; i < smilies.length; i++)
			{
				if (smilies[i].id && smilies[i].id.indexOf('_smilie_') != false)
				{
					smilies[i].style.cursor = pointer_cursor;
					smilies[i].editorid = this.editorid;
					smilies[i].onclick = vB_Text_Editor_Events.prototype.smilie_onclick;
					smilies[i].unselectable = 'on';
				}
			}
		}
	}

	/**
	* Init command button (b, i, u etc.)
	*
	* @param	object	Current HTML button node
	*/
	this.init_command_button = function(obj)
	{
		obj.cmd = obj.id.substr(obj.id.indexOf('_cmd_') + 5);
		obj.editorid = this.editorid;
		this.buttons[obj.cmd] = obj;

		if (obj.cmd == 'switchmode')
		{
			if (AJAX_Compatible)
			{
				obj.state = this.wysiwyg_mode ? true : false;
				this.set_control_style(obj, 'button', this.wysiwyg_mode ? 'selected' : 'normal');
			}
			else
			{
				obj.parentNode.removeChild(obj);
			}
		}
		else
		{
			obj.state = false;
			obj.mode = 'normal';
		}

		// event handlers
		obj.onclick = obj.onmousedown = obj.onmouseover = obj.onmouseout = vB_Text_Editor_Events.prototype.command_button_onmouseevent;
	}

	/**
	* Init popup button (forecolor, fontname etc.)
	*
	* @param	object	Current HTML button node
	*/
	this.init_popup_button = function(obj)
	{
		obj.cmd = obj.id.substr(obj.id.indexOf('_popup_') + 7);

		if (this.popupmode)
		{
			// register popup menu control
			vBmenu.register(obj.id, true);
			vBmenu.menus[obj.id].open_steps = 5;

			obj.editorid = this.editorid;
			obj.state = false;
			this.buttons[obj.cmd] = obj;

			if (obj.cmd == 'fontname')
			{
				this.fontout = fetch_object(this.editorid + '_font_out');
				this.fontout.innerHTML = obj.title;
				this.fontoptions = {'' : this.fontout};

				for (var option in fontoptions)
				{
					var div = document.createElement('div');
					div.id = this.editorid + '_fontoption_' + fontoptions[option];
					div.style.width = this.fontout.style.width;
					div.style.display = 'none';
					div.innerHTML = fontoptions[option];
					this.fontoptions[fontoptions[option]] = this.fontout.parentNode.appendChild(div);
				}
			}
			else if (obj.cmd == 'fontsize')
			{
				this.sizeout = fetch_object(this.editorid + '_size_out');
				this.sizeout.innerHTML = obj.title;
				this.sizeoptions = {'' : this.sizeout};

				for (var option in sizeoptions)
				{
					var div = document.createElement('div');
					div.id = this.editorid + '_sizeoption_' + sizeoptions[option];
					div.style.width = this.sizeout.style.width;
					div.style.display = 'none';
					div.innerHTML = sizeoptions[option];
					this.sizeoptions[sizeoptions[option]] = this.sizeout.parentNode.appendChild(div);
				}
			}

			// extend onmouseover
			obj._onmouseover = obj.onmouseover;
			// extend onclick
			obj._onclick = obj.onclick;

			// event handlers
			obj.onmouseover = obj.onmouseout = obj.onclick = vB_Text_Editor_Events.prototype.popup_button_onmouseevent;

			// extend menu show
			vBmenu.menus[obj.id]._show = vBmenu.menus[obj.id].show;
			vBmenu.menus[obj.id].show = vB_Text_Editor_Events.prototype.popup_button_show;
		}
		else
		{
			this.build_select(obj);
		}
	}

	/**
	* Replace the popup controls with <select> menus for rubbish browsers
	*
	* @param	object	The popup control element
	*/
	this.build_select = function(obj)
	{
		var sel = document.createElement('select');
		sel.id = this.editorid + '_select_' + obj.cmd;
		sel.editorid = this.editorid;
		sel.cmd = obj.cmd;

		var opt = document.createElement('option');
		opt.value = '';
		opt.text = obj.title;
		sel.add(opt, is_ie ? sel.options.length : null);

		var opt = document.createElement('option');
		opt.value = '';
		opt.text = ' ';
		sel.add(opt, is_ie ? sel.options.length : null);

		switch (obj.cmd)
		{
			case 'fontname':
			{
				for (var i = 0; i < fontoptions.length; i++)
				{
					var opt = document.createElement('option');
					opt.value = fontoptions[i];
					opt.text = (fontoptions[i].length > 10 ? (fontoptions[i].substr(0, 10) + '...') : fontoptions[i]);
					sel.add(opt, is_ie ? sel.options.length : null);
				}

				sel.onchange = vB_Text_Editor_Events.prototype.formatting_select_onchange;
				break;
			}

			case 'fontsize':
			{
				for (var i = 0; i < sizeoptions.length; i++)
				{
					var opt = document.createElement('option');
					opt.value = sizeoptions[i];
					opt.text = sizeoptions[i];
					sel.add(opt, is_ie ? sel.options.length : null);
				}

				sel.onchange = vB_Text_Editor_Events.prototype.formatting_select_onchange;
				break;
			}

			case 'forecolor':
			{
				for (var i in coloroptions)
				{
					var opt = document.createElement('option');
					opt.value = coloroptions[i];
					opt.text = PHP.trim((coloroptions[i].length > 5 ? (coloroptions[i].substr(0, 5) + '...') : coloroptions[i]).replace(new RegExp('([A-Z])', 'g'), ' $1'));
					opt.style.backgroundColor = i;
					sel.add(opt, is_ie ? sel.options.length : null);
				}

				sel.onchange = vB_Text_Editor_Events.prototype.formatting_select_onchange;
				break;
			}

			case 'smilie':
			{
				for (var cat in smilieoptions)
				{
					for (var smilieid in smilieoptions[cat])
					{
						if (smilieid != 'more')
						{
							var opt = document.createElement('option');
							opt.value = smilieoptions[cat][smilieid][1];
							opt.text = smilieoptions[cat][smilieid][1];
							opt.smilieid = smilieid;
							opt.smiliepath = smilieoptions[cat][smilieid][0];
							opt.smilietitle = smilieoptions[cat][smilieid][2];
							sel.add(opt, is_ie ? sel.options.length : null);
						}
					}
				}

				sel.onchange = vB_Text_Editor_Events.prototype.smilieselect_onchange;
				break;
			}

			case 'attach':
			{
				sel.onmouseover = vB_Text_Editor_Events.prototype.attachselect_onmouseover;
				sel.onchange = vB_Text_Editor_Events.prototype.attachselect_onchange;
				break;
			}
		}

		while (obj.hasChildNodes())
		{
			obj.removeChild(obj.firstChild);
		}

		this.buttons[obj.cmd] = obj.appendChild(sel);
	}

	/**
	* Init menu controls for the editor
	*
	* @param	object	HTML menu node
	*/
	this.init_popup_menu = function(obj)
	{
		if (this.disabled)
		{
			return;
		}

		switch (obj.cmd)
		{
			case 'fontname':
			{
				var menu = this.init_menu_container('fontname', '200px', '250px', 'auto');
				this.build_fontname_popup(obj, menu);
				break;
			}
			case 'fontsize':
			{
				var menu = this.init_menu_container('fontsize', 'auto', 'auto', 'visible');
				this.build_fontsize_popup(obj, menu);
				break;
			}
			case 'forecolor':
			{
				var menu = this.init_menu_container('forecolor', 'auto', 'auto', 'visible');
				this.build_forecolor_popup(obj, menu);
				break;
			}
			case 'smilie':
			{
				var menu = this.init_menu_container('smilie', '175px', '250px', 'auto');
				this.build_smilie_popup(obj, menu);
				break;
			}
			case 'attach':
			{
				if (typeof vB_Attachments != 'undefined' && vB_Attachments.has_attachments())
				{
					var menu = this.init_menu_container('attach', 'auto', 'auto', 'visible');
					this.build_attachments_popup(menu, obj);
				}
				else
				{
					return fetch_object('manage_attachments_button').onclick();
				}
			}
		}

		this.popups[obj.cmd] = this.controlbar.appendChild(menu);

		set_unselectable(menu);
	};

	/**
	* Init Menu Container DIV
	*
	* @param	string	Command string (forecolor, fontname etc.)
	* @param	string	CSS width for the menu
	* @param	string	CSS height for the menu
	* @param	string	CSS overflow for the menu
	*
	* @return	object	Newly created menu element
	*/
	this.init_menu_container = function(cmd, width, height, overflow)
	{
		var menu = document.createElement('div');

		menu.id = this.editorid + '_popup_' + cmd + '_menu';
		menu.className = 'vbmenu_popup';
		menu.style.display = 'none';
		menu.style.cursor = 'default';
		menu.style.padding = '3px';
		menu.style.width = width;
		menu.style.height = height;
		menu.style.overflow = overflow;

		return menu;
	}

	/**
	* Build Font Name Popup Contents
	*
	* @param	object	The control object for the menu
	* @param	object	The menu container object
	*/
	this.build_fontname_popup = function(obj, menu)
	{
		for (var n in fontoptions)
		{
			var option = document.createElement('div');
			option.innerHTML = '<font face="' + fontoptions[n] + '">' + fontoptions[n] + '</font>';
			option.className = 'ofont';
			option.style.textAlign = 'left';
			option.title = fontoptions[n];
			option.cmd = obj.cmd;
			option.controlkey = obj.id;
			option.editorid = this.editorid;
			option.onmouseover = option.onmouseout = option.onmouseup = option.onmousedown = vB_Text_Editor_Events.prototype.menuoption_onmouseevent;
			option.onclick = vB_Text_Editor_Events.prototype.formatting_option_onclick;
			menu.appendChild(option);
		}
	}

	/**
	* Build Font Size Popup Contents
	*
	* @param	object	The control object for the menu
	* @param	object	The menu container object
	*/
	this.build_fontsize_popup = function(obj, menu)
	{
		for (var n in sizeoptions)
		{
			var option = document.createElement('div');
			option.innerHTML = '<font size="' + sizeoptions[n] + '">' + sizeoptions[n] + '</font>';
			option.className = 'osize';
			option.style.textAlign = 'center';
			option.title = sizeoptions[n];
			option.cmd = obj.cmd;
			option.controlkey = obj.id;
			option.editorid = this.editorid;
			option.onmouseover = option.onmouseout = option.onmouseup = option.onmousedown = vB_Text_Editor_Events.prototype.menuoption_onmouseevent;
			option.onclick = vB_Text_Editor_Events.prototype.formatting_option_onclick;
			menu.appendChild(option);
		}
	}

	/**
	* Build ForeColor Popup Contents
	*
	* @param	object	The control object for the menu
	* @param	object	The menu container object
	*/
	this.build_forecolor_popup = function(obj, menu)
	{
		var colorout = fetch_object(this.editorid + '_color_out');
		colorout.editorid = this.editorid;
		colorout.onclick = vB_Text_Editor_Events.prototype.colorout_onclick;

		var table = document.createElement('table');
		table.cellPadding = 0;
		table.cellSpacing = 0;
		table.border = 0;

		var i = 0;
		for (var hex in coloroptions)
		{
			if (i % 8 == 0)
			{
				var tr = table.insertRow(-1);
			}
			i++;

			var div = document.createElement('div');
			div.style.backgroundColor = coloroptions[hex];

			var option = tr.insertCell(-1);
			option.style.textAlign = 'center';
			option.className = 'ocolor';
			option.appendChild(div);
			option.cmd = obj.cmd;
			option.editorid = this.editorid;
			option.controlkey = obj.id;
			option.colorname = coloroptions[hex];
			option.id = this.editorid + '_color_' + coloroptions[hex];
			option.onmouseover = option.onmouseout = option.onmouseup = option.onmousedown = vB_Text_Editor_Events.prototype.menuoption_onmouseevent;
			option.onclick = vB_Text_Editor_Events.prototype.coloroption_onclick;
		}

		menu.appendChild(table);
	}

	/**
	* Build Smilie Popup Contents
	*
	* @param	object	The control object for the menu
	* @param	object	The menu container object
	*/
	this.build_smilie_popup = function(obj, menu)
	{
		for (var cat in smilieoptions)
		{
			var category = document.createElement('div');
			category.className = 'thead';
			category.innerHTML = cat;
			menu.appendChild(category);

			for (var smilieid in smilieoptions[cat])
			{
				if (smilieid == 'more')
				{
					var option = document.createElement('div');
					option.className = 'thead';
					option.innerHTML = smilieoptions[cat][smilieid];
					option.style.cursor = pointer_cursor;
					option.editorid = this.editorid;
					option.controlkey = obj.id;
					option.onclick = vB_Text_Editor_Events.prototype.smiliemore_onclick;
				}
				else
				{
					var option = document.createElement('div');
					option.editorid = this.editorid;
					option.controlkey = obj.id;
					option.smilieid = smilieid;
					option.smilietext = smilieoptions[cat][smilieid][1];
					option.smilietitle = smilieoptions[cat][smilieid][2];

					option.className = 'osmilie';
					option.innerHTML = '<img src="' + smilieoptions[cat][smilieid][0] + '" alt="' + smilieoptions[cat][smilieid][2] + '" /> ' + smilieoptions[cat][smilieid][2];

					option.onmouseover = option.onmouseout = option.onmousedown = option.onmouseup = vB_Text_Editor_Events.prototype.menuoption_onmouseevent;

					option.onclick = vB_Text_Editor_Events.prototype.smilieoption_onclick;
				}

				menu.appendChild(option);
			}
		}
	}

	/**
	* Build Attachments Popup
	*
	* @param	object	The control object for the menu
	* @param	object	The menu container object
	*/
	this.build_attachments_popup = function(menu, obj)
	{
		if (this.popupmode)
		{
			while (menu.hasChildNodes())
			{
				menu.removeChild(menu.firstChild);
			}

			var div = document.createElement('div');
			div.editorid = this.editorid;
			div.controlkey = obj.id;
			div.className = 'thead';
			div.style.cursor = pointer_cursor;
			div.innerHTML = fetch_object('manage_attachments_button').value;
			div.title = fetch_object('manage_attachments_button').title;
			div.onclick = vB_Text_Editor_Events.prototype.attachmanage_onclick;

			menu.appendChild(div);

			var attach_count = 0;
			for (var id in vB_Attachments.attachments)
			{
				var div = document.createElement('div');
				div.editorid = this.editorid;
				div.controlkey = obj.id;
				div.className = 'osmilie';
				div.attachmentid = id;
				div.innerHTML = '<img src="' + vB_Attachments.attachments[id]['imgpath'] + '" alt="" /> ' + vB_Attachments.attachments[id]['filename'];
				div.onmouseover = div.onmouseout = div.onmousedown = div.onmouseup = vB_Text_Editor_Events.prototype.menuoption_onmouseevent;
				div.onclick = vB_Text_Editor_Events.prototype.attachoption_onclick;

				menu.appendChild(div);
				attach_count++;
			}
			if (attach_count > 1)
			{
				var div = document.createElement('div');
				div.editorid = this.editorid
				div.controlkey = obj.id;
				div.className = 'osmilie';
				div.style.fontWeight = 'bold';
				div.style.paddingLeft = '25px';
				div.innerHTML = vbphrase['insert_all'];
				div.onmouseover = div.onmouseout = div.onmousedown = div.onmouseup = vB_Text_Editor_Events.prototype.menuoption_onmouseevent;
				div.onclick = vB_Text_Editor_Events.prototype.attachinsertall_onclick;

				menu.appendChild(div);
			}
		}
		else
		{
			while (menu.options.length > 2)
			{
				menu.remove(menu.options.length - 1);
			}

			for (var id in vB_Attachments.attachments)
			{
				var opt = document.createElement('option');
				opt.value = id;
				opt.text = vB_Attachments.attachments[id]['filename'];
				menu.add(opt, is_ie ? menu.options.length : null);
			}
		}

		set_unselectable(menu);
	}

	/**
	* Menu Context
	*
	* @param	object	The menu container object
	* @param	string	The state of the control
	*/
	this.menu_context = function(obj, state)
	{
		if (this.disabled)
		{
			return;
		}

		switch (obj.state)
		{
			case true: // selected menu is open
			{
				this.set_control_style(obj, 'button', 'down');
				break;
			}

			default:
			{
				switch (state)
				{
					case 'mouseout':
					{
						this.set_control_style(obj, 'button', 'normal');
						break;
					}
					case 'mousedown':
					{
						this.set_control_style(obj, 'popup', 'down');
						break;
					}
					case 'mouseup':
					case 'mouseover':
					{
						this.set_control_style(obj, 'button', 'hover');
						break;
					}
				}
			}
		}
	};

	/**
	* Button Context
	*
	* @param	object	The button object
	* @param	string	Incoming event type
	* @param	string	Control type - 'button' or 'menu'
	*/
	this.button_context = function(obj, state, controltype)
	{
		if (this.disabled)
		{
			return;
		}

		if (typeof controltype == 'undefined')
		{
			controltype = 'button';
		}

		switch (obj.state)
		{
			case true: // selected button
			{
				switch (state)
				{
					case 'mouseover':
					case 'mousedown':
					case 'mouseup':
					{
						this.set_control_style(obj, controltype, 'down');
						break;
					}
					case 'mouseout':
					{
						this.set_control_style(obj, 'button', 'selected');
						break;
					}
				}
				break;
			}

			default: // not selected
			{
				switch (state)
				{
					case 'mouseover':
					case 'mouseup':
					{
						this.set_control_style(obj, controltype, 'hover');
						break;
					}
					case 'mousedown':
					{
						this.set_control_style(obj, controltype, 'down');
						break;
					}
					case 'mouseout':
					{
						this.set_control_style(obj, controltype, 'normal');
						break;
					}
				}
				break;
			}
		}
	};

	/**
	* Set Control Style
	*
	* @param	object	The object to be styled
	* @param	string	Control type - 'button' or 'menu'
	* @param	string	The mode to use, corresponding to the istyles array
	*/
	this.set_control_style = function(obj, controltype, mode)
	{
		if (obj.mode != mode)
		{
			obj.mode = mode;

			// construct the name of the appropriate array key from the istyles array
			istyle = 'pi_' + controltype + '_' + obj.mode;

			// set element background, color, padding and border
			if (typeof istyles != 'undefined' && typeof istyles[istyle] != 'undefined')
			{
				obj.style.background = istyles[istyle][0];
				obj.style.color = istyles[istyle][1];
				if (controltype != 'menu')
				{
					obj.style.padding = istyles[istyle][2];
				}
				obj.style.border = istyles[istyle][3];

				var tds = fetch_tags(obj, 'td');
				for (var i = 0; i < tds.length; i++)
				{
					switch (tds[i].className)
					{
						// set the right-border for popup_feedback class elements
						case 'popup_feedback':
						{
							tds[i].style.borderRight = (mode == 'normal' ? istyles['pi_menu_normal'][3] : istyles[istyle][3]);
						}
						break;

						// set the border colour for popup_pickbutton class elements
						case 'popup_pickbutton':
						{
							tds[i].style.borderColor = (mode == 'normal' ? istyles['pi_menu_normal'][0] : istyles[istyle][0]);
						}
						break;

						// set the left-padding and left-border for alt_pickbutton elements
						case 'alt_pickbutton':
						{
							if (obj.state)
							{
								tds[i].style.paddingLeft = istyles['pi_button_normal'][2];
								tds[i].style.borderLeft = istyles['pi_button_normal'][3];
							}
							else
							{
								tds[i].style.paddingLeft = istyles[istyle][2];
								tds[i].style.borderLeft = istyles[istyle][3];
							}
						}
					}
				}
			}
		}
	};

	/**
	* Format text
	*
	* @param	event	Event object
	* @param	string	Formatting command
	* @param	string	Optional argument to the formatting command
	*
	* @return	boolean
	*/
	this.format = function(e, cmd, arg)
	{
		e = do_an_e(e);

		if (this.disabled)
		{
			return false;
		}

		if (cmd != 'redo')
		{
			this.history.add_snapshot(this.get_editor_contents());
		}

		if (cmd == 'switchmode')
		{
			switch_editor_mode(this.editorid);
			return;
		}
		else if (cmd.substr(0, 6) == 'resize')
		{
			this.resize_editor(parseInt(cmd.substr(9), 10) * (parseInt(cmd.substr(7, 1), 10) == '1' ? 1 : -1));
			return;
		}

		this.check_focus();

		if (cmd.substr(0, 4) == 'wrap')
		{
			var ret = this.wrap_tags(cmd.substr(6), (cmd.substr(4, 1) == '1' ? true : false));
		}
		else if (this[cmd])
		{
			var ret = this[cmd](e);
		}
		else
		{
			try
			{
				var ret = this.apply_format(cmd, false, (typeof arg == 'undefined' ? true : arg));
			}
			catch(e)
			{
				this.handle_error(cmd, e);
				var ret = false;
			}
		}

		if (cmd != 'undo')
		{
			this.history.add_snapshot(this.get_editor_contents());
		}

		this.set_context(cmd);

		this.check_focus();

		return ret;
	};

	/**
	* Insert Image
	*
	* @param	event	Event object
	* @param	string	(Optional) Image URL
	*
	* @return	boolean
	*/
	this.insertimage = function(e, img)
	{
		if (typeof img == 'undefined')
		{
			img = this.show_prompt(vbphrase['enter_image_url'], 'http://', true);
		}
		if (img = this.verify_prompt(img))
		{
			return this.apply_format('insertimage', false, img);
		}
		else
		{
			return false;
		}
	};

	/**
	* Wrap Tags
	*
	* @param	string	Tag to wrap
	* @param	boolean	Use option?
	* @param	string	(Optional) selected text
	*
	* @return	boolean
	*/
	this.wrap_tags = function(tagname, useoption, selection)
	{
		tagname = tagname.toUpperCase();

		switch (tagname)
		{
			case 'CODE':
			case 'HTML':
			case 'PHP':
			{
				this.apply_format('removeformat');
			}
			break;
		}

		if (typeof selection == 'undefined')
		{
			selection = this.get_selection();
			if (selection === false)
			{
				selection = '';
			}
			else
			{
				selection = new String(selection);
			}
		}

		if (useoption === true)
		{
			var option = this.show_prompt(construct_phrase(vbphrase['enter_tag_option'], ('[' + tagname + ']')), '', false);
			if (option = this.verify_prompt(option))
			{
				var opentag = '[' + tagname + '="' + option + '"' + ']';
			}
			else
			{
				return false;
			}
		}
		else if (useoption !== false)
		{
			var opentag = '[' + tagname + '="' + useoption + '"' + ']';
		}
		else
		{
			var opentag = '[' + tagname + ']';
		}

		var closetag = '[/' + tagname + ']';
		var text = opentag + selection + closetag;

		this.insert_text(text, opentag.vBlength(), closetag.vBlength());

		return false;
	};

	/**
	* Check Spelling (uses ieSpell from www.iespell.com)
	*
	* Eventually we hope to integrate SpellBound (http://spellbound.sourceforge.net) for Gecko.
	*/
	this.spelling = function()
	{
		if (is_ie)
		{
			try
			{
				// attempt to instantiate ieSpell
				eval("new A" + "ctiv" + "eX" + "Ob" + "ject('ieSpell." + "ieSpellExt" + "ension').CheckD" + "ocumentNode(this.spellobj);");
			}
			catch(e)
			{
				// ask if user wants to download ieSpell
				if (e.number == -2146827859 && confirm(vbphrase['iespell_not_installed']))
				{
					// ooh they do...
					window.open('http://www.iespell.com/download.ph' + 'p');
				}
			}
		}
		else if (is_moz)
		{
			// attempt to instantiate SpellBound... when it supports this behaviour
		}
	};

	/**
	* Handle Error
	*
	* @param	string	Command name
	* @param	event	Event object
	*/
	this.handle_error = function(cmd, e)
	{
	};

	/**
	* Show JS Prompt and filter result
	*
	* @param	string	Text for the dialog
	* @param	string	Default value for the dialog
	* @param	string	Whether to force LTR (for URLs, etc)
	*
	* @return	string
	*/
	this.show_prompt = function(dialogtxt, defaultval, forceltr)
	{
		if (is_ie7)
		{
			// respect a <base> tag (see #23097)
			var base_tag = fetch_tags(document, 'base');
			if (base_tag && base_tag[0] && base_tag[0].href)
			{
				var modal_prefix = base_tag[0].href;
			}
			else
			{
				var modal_prefix = '';
			}

			var returnvalue = window.showModalDialog(modal_prefix + "clientscript/ieprompt.html?", { value: defaultval, label: dialogtxt, dir: document.dir, title: document.title, forceltr: (typeof(forceltr) != "undefined" ? forceltr : false) }, "dialogWidth:320px; dialogHeight:150px; dialogTop:" + (parseInt(window.screenTop) + parseInt(window.event.clientY) + parseInt(document.body.scrollTop) - 100) + "px; dialogLeft:" + (parseInt(window.screenLeft) + parseInt(window.event.clientX) + parseInt(document.body.scrollLeft) - 160) + "px; resizable: No;");
		}
		else
		{
			var returnvalue = prompt(dialogtxt, defaultval);
		}

		// deal with unexpected return value
		if (typeof(returnvalue) == "undefined")
		{
			return false;
		}
		else if (returnvalue == false || returnvalue == null)
		{
			return returnvalue;
		}
		else
		{
			return PHP.trim(new String(returnvalue));
		}
	};

	/**
	* Closes an open prompt (emulator) window opened by show_prompt() under IE7+
	*/
	this.close_prompt = function()
	{
		if (this.prompt_popup)
		{
			document.getElementById("vB_Editor_001").removeChild(this.prompt_popup);
			this.prompt_popup = null;
		}

		this.check_focus();
	}

	/**
	* Verify the return value of a javascript prompt
	*
	* @param	string	String to be checked
	*
	* @return	mixed	False on fail, string on success
	*/
	this.verify_prompt = function(str)
	{
		switch(str)
		{
			case 'http://':
			case 'null':
			case 'undefined':
			case 'false':
			case '':
			case null:
			case false:
				return false;

			default:
				return str;
		}
	};

	/**
	* Open Smilie Window
	*
	* @param	integer	Window width
	* @param	integer	Window height
	*/
	this.open_smilie_window = function(width, height)
	{
		smilie_window = openWindow('misc.php?' + SESSIONURL + 'do=getsmilies&editorid=' + this.editorid, width, height, 'smilie_window');

		window.onunload = vB_Text_Editor_Events.prototype.smiliewindow_onunload;
	}

	/**
	* Resize Editor
	*
	* @param	integer	Number of pixels by which to resize the editor
	*/
	this.resize_editor = function(change)
	{
		var newheight = parseInt(this.editbox.style.height, 10) + change;

		if (newheight >= 100)
		{
			this.editbox.style.height = newheight + 'px';

			// remember the setting for next time
			if (change % 99 != 0)
			{
				set_cookie('editor_height', newheight);
			}
		}
	};

	/**
	* Destroy Popup
	*/
	this.destroy_popup = function(popupname)
	{
		this.popups[popupname].parentNode.removeChild(this.popups[popupname]);
		this.popups[popupname] = null;
	}

	/**
	* Destroy Editor
	*/
	this.destroy = function()
	{
		// reset all buttons to default state
		for (var i in this.buttons)
		{
			this.set_control_style(this.buttons[i], 'button', 'normal');
		}

		// destroy popups
		for (var menu in this.popups)
		{
			this.destroy_popup(menu);
		}

		if (this.fontoptions)
		{
			for (var i in this.fontoptions)
			{
				if (i != '')
				{
					this.fontoptions[i].parentNode.removeChild(this.fontoptions[i]);
				}
			}
			this.fontoptions[''].style.display = '';
		}

		if (this.sizeoptions)
		{
			for (var i in this.sizeoptions)
			{
				if (i != '')
				{
					this.sizeoptions[i].parentNode.removeChild(this.sizeoptions[i]);
				}
			}
			this.sizeoptions[''].style.display = '';
		}
	};

	/**
	* Collapse the current selection and place the cursor where the end of the
	* selection was.
	*/
	this.collapse_selection_end = function()
	{
		if (this.editdoc.selection)
		{
			var range = this.editdoc.selection.createRange();
			// fix for horribly confusing IE bug where it randomly makes text white for funsies
			// see 3.5 bug #279
			eval("range." + "move" + "('character', -1);");
			range.collapse(false);
			range.select();
		}
		else if (document.selection && document.selection.createRange)
		{
			var range = document.selection.createRange();
			range.collapse(false);
			range.select();
		}
		else if (typeof(this.editdoc.selectionStart) != 'undefined')
		{
			var opn = this.editdoc.selectionStart + 0;
			var sel_text = this.editdoc.value.substr(this.editdoc.selectionStart, this.editdoc.selectionEnd - this.editdoc.selectionStart);

			this.editdoc.selectionStart = this.editdoc.selectionStart + sel_text.vBlength();
		}
		else if (window.getSelection)
		{
			// don't think I can do anything for this
		}
	}

	// =============================================================================
	// WYSIWYG editor
	// =============================================================================
	if (this.wysiwyg_mode)
	{
		/**
		* Disables the editor
		*
		* @param	string	Initial text for the editor
		*/
		this.disable_editor = function(text)
		{
			if (!this.disabled)
			{
				this.disabled = true;

				var hider = fetch_object(this.editorid + '_hider');
				if (hider)
				{
					hider.parentNode.removeChild(hider);
				}

				var div = document.createElement('div');
				div.id = this.editorid + '_hider';
				div.className = 'wysiwyg';
				div.style.border = '2px inset';
				div.style.width = this.editbox.style.width;
				div.style.height = this.editbox.style.height;
				var childdiv = document.createElement('div');
				childdiv.style.margin = '8px';
				childdiv.innerHTML = text;
				div.appendChild(childdiv);
				this.editbox.parentNode.appendChild(div);

				// and hide the real editor
				this.editbox.style.width = '0px';
				this.editbox.style.height = '0px';
				this.editbox.style.border = 'none';
			}
		};

		/**
		* Enables the editor
		*
		* @param	string	Initial text for the editor
		*/
		this.enable_editor = function(text)
		{
			if (typeof text != 'undefined')
			{
				this.set_editor_contents(text);
			}

			var hider = fetch_object(this.editorid + '_hider');
			if (hider)
			{
				hider.parentNode.removeChild(hider);
			}

			this.disabled = false;
		};

		/**
		* Puts the text into the editor
		*
		* @param	string	Text to write
		*/
		this.write_editor_contents = function(text, doinit)
		{
			if (text == '')
			{
				if (is_ie)
				{
					text = '<p></p>';
				}
				else if (is_moz)
				{
					text = '<br />';
				}
			}
			if (this.editdoc && this.editdoc.initialized)
			{
				this.editdoc.body.innerHTML = text;
			}
			else
			{
				// Force Firefox 1.0 to run it here and Firefox 1.5+ later on, see Bug #22661
				if (doinit && typeof Array.prototype.map == 'undefined') { this.editdoc.designMode = 'on'; }
				this.editdoc = this.editwin.document; // See: http://msdn.microsoft.com/workshop/author/dhtml/overview/XpSp2Compat.asp#caching
				this.editdoc.open('text/html', 'replace');
				this.editdoc.write(text);
				this.editdoc.close();
				if (doinit)
				{
					this.editdoc.body.contentEditable = true;
					if (typeof Array.prototype.map != 'undefined')
					{
						this.editdoc.designMode = 'on';
					}
				}
				this.editdoc.body.spellcheck = true;

				this.editdoc.initialized = true;

				this.set_editor_style();
			}

			this.set_direction();
		}

		/**
		* Put the text into the editor
		*/
		this.set_editor_contents = function(initial_text)
		{
			if (fetch_object(this.editorid + '_iframe'))
			{
				this.editbox = fetch_object(this.editorid + '_iframe');
			}
			else
			{
				var iframe = document.createElement('iframe');
				if (is_ie && window.location.protocol == 'https:')
				{
					// workaround for IE throwing insecure page warnings
					iframe.src = 'clientscript/index.html';
				}
				this.editbox = this.textobj.parentNode.appendChild(iframe);
				this.editbox.id = this.editorid + '_iframe';
				this.editbox.tabIndex = 1;
			}

			if (!is_ie)
			{
				this.editbox.style.border = '2px inset';
			}
			this.editbox.style.width = this.textobj.style.width;
			this.editbox.style.height = this.textobj.style.height;
			this.textobj.style.display = 'none';

			this.editwin = this.editbox.contentWindow;
			this.editdoc = this.editwin.document;

			this.write_editor_contents((typeof initial_text == 'undefined' ?  this.textobj.value : initial_text), true);

			if (this.editdoc.dir == 'rtl')
			{
			//	this.editdoc.execCommand('justifyright', false, null);
			}
			this.spellobj = this.editdoc.body;

			this.editdoc.editorid = this.editorid;
			this.editwin.editorid = this.editorid;
		};

		/**
		* Sets the text direction for the editor
		*/
		this.set_direction = function()
		{
			this.editdoc.dir = this.textobj.dir;
		};

		/**
		* Set the CSS style of the editor
		*/
		this.set_editor_style = function()
		{
			if (document.styleSheets['vbulletin_css'])
			{
				var rules = document.styleSheets['vbulletin_css'].rules;
				var newcss = '';

				for (var i = 0; i < rules.length; i++)
				{
					if (rules[i].selectorText.indexOf('.wysiwyg') >= 0 || rules[i].selectorText == 'BODY')
					{
						newcss += ' ' + rules[i].selectorText + ' { ' + rules[i].style.cssText + ' } ';
					}
				}

				if (!newcss)
				{
					// fall back in case the new method doesn't work
					newcss = document.styleSheets['vbulletin_css'].cssText;
				}

				this.editdoc.createStyleSheet().cssText = newcss + ' p { margin: 0px; }';
				this.editdoc.body.className = 'wysiwyg';
			}
		};

		/**
		* Init Editor Functions
		*/
		this.set_editor_functions = function()
		{
			this.editdoc.onmouseup = vB_Text_Editor_Events.prototype.editdoc_onmouseup;
			this.editdoc.onkeyup = vB_Text_Editor_Events.prototype.editdoc_onkeyup;

			if (this.editdoc.attachEvent)
			{
				this.editdoc.body.attachEvent("onresizestart", vB_Text_Editor_Events.prototype.editdoc_onresizestart);
			}

			this.editwin.onfocus = vB_Text_Editor_Events.prototype.editwin_onfocus;
			this.editwin.onblur = vB_Text_Editor_Events.prototype.editwin_onblur;
		};

		/**
		* Set Context
		*/
		this.set_context = function(cmd)
		{
			for (var i in contextcontrols)
			{
				var obj = fetch_object(this.editorid + '_cmd_' + contextcontrols[i]);
				if (obj != null)
				{
					var state = this.editdoc.queryCommandState(contextcontrols[i]);
					//alert (contextcontrols[i] + ' ' + state + ' ' + obj.state);
					if (obj.state != state)
					{
						obj.state = state;
						this.button_context(obj, (obj.cmd == cmd ? 'mouseover' : 'mouseout'));
					}
				}
			}

			this.set_font_context();

			this.set_size_context();

			this.set_color_context();
		};

		/**
		* Set Font Context
		*/
		this.set_font_context = function(fontstate)
		{
			if (this.buttons['fontname'])
			{
				if (typeof fontstate == 'undefined')
				{
					fontstate = this.editdoc.queryCommandValue('fontname');
				}
				switch (fontstate)
				{
					case '':
					{
						if (!is_ie && window.getComputedStyle)
						{
							fontstate = this.editdoc.body.style.fontFamily;
						}
					}
					break;

					case null:
					{
						fontstate = '';
					}
					break;
				}

				if (fontstate != this.fontstate)
				{
					this.fontstate = fontstate;
					var thingy = PHP.ucfirst(this.fontstate, ',');

					if (this.popupmode)
					{
						for (var i in this.fontoptions)
						{
							this.fontoptions[i].style.display = (i == thingy ? '' : 'none');
						}
					}
					else
					{
						for (var i = 0; i < this.buttons['fontname'].options.length; i++)
						{
							if (this.buttons['fontname'].options[i].value == thingy)
							{
								this.buttons['fontname'].selectedIndex = i;
								break;
							}
						}
					}
				}
			}
		};

		/**
		* Set Size Context
		*/
		this.set_size_context = function(sizestate)
		{
			if (this.buttons['fontsize'])
			{
				if (typeof sizestate == 'undefined')
				{
					sizestate = this.editdoc.queryCommandValue('fontsize');
				}
				switch (sizestate)
				{
					case null:
					case '':
					{
						if (is_moz)
						{
							sizestate = this.translate_fontsize(this.editdoc.body.style.fontSize);
						}
					}
					break;
				}
				if (sizestate != this.sizestate)
				{
					this.sizestate = sizestate;

					if (this.popupmode)
					{
						for (var i in this.sizeoptions)
						{
							this.sizeoptions[i].style.display = (i == this.sizestate ? '' : 'none');
						}
					}
					else
					{
						for (var i = 0; i < this.buttons['fontsize'].options.length; i++)
						{
							if (this.buttons['fontsize'].options[i].value == this.sizestate)
							{
								this.buttons['fontsize'].selectedIndex = i;
								break;
							}
						}
					}
				}
			}
		};

		/**
		* Set Color Context
		*/
		this.set_color_context = function(colorstate)
		{
			if (this.buttons['forecolor'])
			{
				if (typeof colorstate == 'undefined')
				{
					colorstate = this.editdoc.queryCommandValue('forecolor');
				}
				if (colorstate != this.colorstate)
				{
					if (this.popupmode)
					{
						var obj = fetch_object(this.editorid + '_color_' + this.translate_color_commandvalue(this.colorstate));
						if (obj != null)
						{
							obj.state = false;
							this.button_context(obj, 'mouseout', 'menu');
						}

						this.colorstate = colorstate;

						elmid = this.editorid + '_color_' + this.translate_color_commandvalue(colorstate);
						var obj = fetch_object(elmid);
						if (obj != null)
						{
							obj.state = true;
							this.button_context(obj, 'mouseout', 'menu');
						}
					}
					else
					{
						this.colorstate = colorstate;

						colorstate = this.translate_color_commandvalue(this.colorstate);

						for (var i = 0; i < this.buttons['forecolor'].options.length; i++)
						{
							if (this.buttons['forecolor'].options[i].value == colorstate)
							{
								this.buttons['forecolor'].selectedIndex = i;
								break;
							}
						}
					}
				}
			}
		};

		/**
		* Function to translate the output from queryCommandState('forecolor') into something useful
		*
		* @param	string	Output from queryCommandState('forecolor')
		*
		* @return	string
		*/
		this.translate_color_commandvalue = function(forecolor)
		{
			return this.translate_silly_hex((forecolor & 0xFF).toString(16), ((forecolor >> 8) & 0xFF).toString(16), ((forecolor >> 16) & 0xFF).toString(16));
		};

		/**
		* Function to translate a hex like F AB 9 to #0FAB09 and then to coloroptions['#0FAB09']
		*
		* @param	string	Red value
		* @param	string	Green value
		* @param	string	Blue value
		*
		* @return	string	Option from coloroptions array
		*/
		this.translate_silly_hex = function(r, g, b)
		{
			return coloroptions['#' + (PHP.str_pad(r, 2, 0) + PHP.str_pad(g, 2, 0) + PHP.str_pad(b, 2, 0)).toUpperCase()];
		};

		/**
		* Apply Formatting
		*/
		this.apply_format = function(cmd, dialog, argument)
		{
			this.editdoc.execCommand(cmd, (typeof dialog == 'undefined' ? false : dialog), (typeof argument == 'undefined' ? true : argument));
			return false;
		};

		/**
		* Insert Link
		*/
		this.createlink = function(e, url)
		{
			return this.apply_format('createlink', is_ie, (typeof url == 'undefined' ? true : url));
		};

		/**
		* Insert Email Link
		*/
		this.email = function(e, email)
		{
			if (typeof email == 'undefined')
			{
				email = this.show_prompt(vbphrase['enter_email_link'], '', true);
			}

			email = this.verify_prompt(email);

			if (email === false)
			{
				return this.apply_format('unlink');
			}
			else
			{
				var selection = this.get_selection();
				return this.insert_text('<a href="mailto:' + email + '">' + (selection ? selection : email) + '</a>', (selection ? true : false));
			}
		};

		/**
		* Insert Smilie
		*/
		this.insert_smilie = function(e, smilietext, smiliepath, smilieid)
		{
			this.check_focus();

			return this.insert_text('<img src="' + smiliepath + '" border="0" alt="0" smilieid="' + smilieid + '" />', false);
		};

		/**
		* Get Editor Contents
		*/
		this.get_editor_contents = function()
		{
			return this.editdoc.body.innerHTML;
		};

		/**
		* Get Selected Text
		*/
		this.get_selection = function()
		{
			var range = this.editdoc.selection.createRange();
			if (range.htmlText && range.text)
			{
				return range.htmlText;
			}
			else
			{
				var do_not_steal_this_code_html = '';
				for (var i = 0; i < range.length; i++)
				{
					do_not_steal_this_code_html += range.item(i).outerHTML;
				}
				return do_not_steal_this_code_html;
			}
		};

		/**
		* Paste HTML
		*/
		this.insert_text = function(text, movestart, moveend)
		{
			this.check_focus();

			if (typeof(this.editdoc.selection) != 'undefined' && this.editdoc.selection.type != 'Text' && this.editdoc.selection.type != 'None')
			{
				movestart = false;
				this.editdoc.selection.clear();
			}

			var sel = this.editdoc.selection.createRange();
			sel.pasteHTML(text);

			if (text.indexOf('\n') == -1)
			{
				if (movestart === false)
				{
					// do nothing
				}
				else if (typeof movestart != 'undefined')
				{
					sel.moveStart('character', -text.vBlength() +movestart);
					sel.moveEnd('character', -moveend);
				}
				else
				{
					sel.moveStart('character', -text.vBlength());
				}
				sel.select();
			}
		};

		/**
		* Prepare Form For Submit
		*/
		this.prepare_submit = function(subjecttext, minchars)
		{
			this.textobj.value = this.get_editor_contents();

			var returnvalue = validatemessage(stripcode(this.textobj.value, true), subjecttext, minchars);

			if (returnvalue)
			{
				return returnvalue;
			}
			else if (this.captcha != null && this.captcha.failed)
			{
				return false;
			}
			else
			{
				this.check_focus();
				return false;
			}
		}

		// =============================================================================
		// Mozilla WYSIWYG only
		// =============================================================================
		if (is_moz)
		{
			/**
			* Set editor contents
			*/
			this._set_editor_contents = this.set_editor_contents;
			this.set_editor_contents = function(initial_text)
			{
				this._set_editor_contents(initial_text);

				this.editdoc.addEventListener('keypress', vB_Text_Editor_Events.prototype.editdoc_onkeypress, true);
			};

			/**
			* Set editor style
			*/
			this.set_editor_style = function()
			{
				for (var ss = 0; ss < document.styleSheets.length; ss++)
				{
					try
					{
						if (document.styleSheets[ss].cssRules.length <= 0)
						{
							continue;
						}
					}
					catch(e)
					{ // trying to access a stylesheet outside the allowed domain
						continue;
					}
					for (var i = 0; i < document.styleSheets[ss].cssRules.length; i++)
					{
						if (document.styleSheets[ss].cssRules[i].selectorText == '.wysiwyg')
						{
							newss = this.editdoc.createElement('style');
							newss.type = 'text/css';
							newss.innerHTML = document.styleSheets[ss].cssRules[i].cssText + ' p { margin: 0px; }';
							this.editdoc.documentElement.childNodes[0].appendChild(newss);
							this.editdoc.body.className = 'wysiwyg';
							this.editdoc.body.style.fontFamily = document.styleSheets[ss].cssRules[i].style.fontFamily;
							this.editdoc.body.style.fontSize = document.styleSheets[ss].cssRules[i].style.fontSize;
							return false;
						}
					}
				}
			};

			/**
			* Translate Color Command Value
			*/
			this.translate_color_commandvalue = function(forecolor)
			{
				if (forecolor == '' || forecolor == null)
				{
					forecolor = window.getComputedStyle(this.editdoc.body, null).getPropertyValue('color');
				}

				if (forecolor.toLowerCase().indexOf('rgb') == 0)
				{
					var matches = forecolor.match(/^rgb\s*\(([0-9]+),\s*([0-9]+),\s*([0-9]+)\)$/);
					if (matches)
					{
						return this.translate_silly_hex((matches[1] & 0xFF).toString(16), (matches[2] & 0xFF).toString(16), (matches[3] & 0xFF).toString(16));
					}
					else
					{
						return this.translate_color_commandvalue(null);
					}
				}
				else
				{
					return forecolor;
				}
			};

			/**
			* Translate CSS fontSize to HTML Font Size
			*/
			this.translate_fontsize = function(csssize)
			{
				switch (csssize)
				{
					case '7.5pt':
					case '10px': return 1;
					case '10pt': return 2;
					case '12pt': return 3;
					case '14pt': return 4;
					case '18pt': return 5;
					case '24pt': return 6;
					case '36pt': return 7;
					default:     return '';
				}
			}

			/**
			* Apply Format
			*/
			this._apply_format = this.apply_format;
			this.apply_format = function(cmd, dialog, arg)
			{
				this.editdoc.execCommand('useCSS', false, true);
				return this._apply_format(cmd, dialog, arg);
			};

			/**
			* Insert Link
			*/
			this._createlink = this.createlink;
			this.createlink = function(e, url)
			{
				if (typeof url == 'undefined')
				{
					url = this.show_prompt(vbphrase['enter_link_url'], 'http://', true);
				}
				if ((url = this.verify_prompt(url)) !== false)
				{
					if (this.get_selection())
					{
						this.apply_format('unlink');
						this._createlink(e, url);
					}
					else
					{
						this.insert_text('<a href="' + url + '">' + url + '</a>');
					}
				}
				return true;
			};

			/**
			* Insert Smilie
			*/
			this.insert_smilie = function(e, smilietext, smiliepath, smilieid)
			{
				this.check_focus();

				try
				{
					this.apply_format('InsertImage', false, smiliepath);
					var smilies = fetch_tags(this.editdoc.body, 'img');
					for (var i = 0; i < smilies.length; i++)
					{
						if (smilies[i].src == smiliepath)
						{
							if (smilies[i].getAttribute('smilieid') < 1)
							{
								smilies[i].setAttribute('smilieid', smilieid);
								smilies[i].setAttribute('border', "0");
							}
						}
					}
				}
				catch(e)
				{
					// failed... probably due to inserting a smilie over a smilie in mozilla
				}
			};

			/**
			* Get Selection
			*/
			this.get_selection = function()
			{
				selection = this.editwin.getSelection();
				this.check_focus();
				range = selection ? selection.getRangeAt(0) : this.editdoc.createRange();
				return this.read_nodes(range.cloneContents(), false);
			};

			/**
			* Paste Text
			*/
			this.insert_text = function(str)
			{
				this.editdoc.execCommand('insertHTML', false, str);
				/*fragment = this.editdoc.createDocumentFragment();
				holder = this.editdoc.createElement('span');
				holder.innerHTML = str;

				while (holder.firstChild)
				{
					fragment.appendChild(holder.firstChild);
				}

				this.insert_node_at_selection(fragment);*/
			};

			/**
			* Set editor functions
			*/
			this.set_editor_functions = function()
			{
				this.editdoc.addEventListener('mouseup', vB_Text_Editor_Events.prototype.editdoc_onmouseup, true);
				this.editdoc.addEventListener('keyup', vB_Text_Editor_Events.prototype.editdoc_onkeyup, true);
				this.editwin.addEventListener('focus', vB_Text_Editor_Events.prototype.editwin_onfocus, true);
				this.editwin.addEventListener('blur', vB_Text_Editor_Events.prototype.editwin_onblur, true);
			};


			/**
			* Add Range (Mozilla)
			*/
			this.add_range = function(node)
			{
				this.check_focus();
				var sel = this.editwin.getSelection();
				var range = this.editdoc.createRange();
				range.selectNodeContents(node);
				sel.removeAllRanges();
				sel.addRange(range);
			};

			/**
			* Read Nodes (Mozilla)
			*/
			this.read_nodes = function(root, toptag)
			{
				var html = "";
				var moz_check = /_moz/i;

				switch (root.nodeType)
				{
					case Node.ELEMENT_NODE:
					case Node.DOCUMENT_FRAGMENT_NODE:
					{
						var closed;
						if (toptag)
						{
							closed = !root.hasChildNodes();
							html = '<' + root.tagName.toLowerCase();
							var attr = root.attributes;
							for (var i = 0; i < attr.length; ++i)
							{
								var a = attr.item(i);
								if (!a.specified || a.name.match(moz_check) || a.value.match(moz_check))
								{
									continue;
								}

								html += " " + a.name.toLowerCase() + '="' + a.value + '"';
							}
							html += closed ? " />" : ">";
						}
						for (var i = root.firstChild; i; i = i.nextSibling)
						{
							html += this.read_nodes(i, true);
						}
						if (toptag && !closed)
						{
							html += "</" + root.tagName.toLowerCase() + ">";
						}
					}
					break;

					case Node.TEXT_NODE:
					{
						//html = htmlspecialchars(root.data);
						html = PHP.htmlspecialchars(root.data);
					}
					break;
				}

				return html;
			};

			/**
			* Insert Node at Selection (Mozilla)
			*/
			this.insert_node_at_selection = function(text)
			{
				this.check_focus();

				var sel = this.editwin.getSelection();
				var range = sel ? sel.getRangeAt(0) : this.editdoc.createRange();
				sel.removeAllRanges();
				range.deleteContents();

				var node = range.startContainer;
				var pos = range.startOffset;

				switch (node.nodeType)
				{
					case Node.ELEMENT_NODE:
					{
						if (text.nodeType == Node.DOCUMENT_FRAGMENT_NODE)
						{
							selNode = text.firstChild;
						}
						else
						{
							selNode = text;
						}
						node.insertBefore(text, node.childNodes[pos]);
						this.add_range(selNode);
					}
					break;

					case Node.TEXT_NODE:
					{
						if (text.nodeType == Node.TEXT_NODE)
						{
							var text_length = pos + text.length;
							node.insertData(pos, text.data);
							range = this.editdoc.createRange();
							range.setEnd(node, text_length);
							range.setStart(node, text_length);
							sel.addRange(range);
						}
						else
						{
							node = node.splitText(pos);
							var selNode;
							if (text.nodeType == Node.DOCUMENT_FRAGMENT_NODE)
							{
								selNode = text.firstChild;
							}
							else
							{
								selNode = text;
							}
							node.parentNode.insertBefore(text, node);
							this.add_range(selNode);
						}
					}
					break;
				}
			};
		}
		// =============================================================================
		// Opera WYSIWYG only
		// =============================================================================
		else if (is_opera)
		{
			/**
				There are several issues with Opera which is why this code is disabled:
					1. Focus issues, when a button is pressed the editor loses focus
					2. Styling is lost when we make any adjustments to the editor
					3. Adding a URL is ever lasting even if you push enter
			*/
			/**
			* Set editor style
			*/
			this.set_editor_style = function()
			{
				for (var ss = 0; ss < document.styleSheets.length; ss++)
				{
					try
					{
						if (document.styleSheets[ss].cssRules.length <= 0)
						{
							continue;
						}
					}
					catch(e)
					{ // trying to access a stylesheet outside the allowed domain
						continue;
					}
					for (var i = 0; i < document.styleSheets[ss].cssRules.length; i++)
					{
						if (document.styleSheets[ss].cssRules[i].selectorText == '.wysiwyg')
						{
							newss = this.editdoc.createElement('style');
							newss.type = 'text/css';
							newss.innerHTML = document.styleSheets[ss].cssRules[i].cssText + ' p { margin: 0px; }';
							this.editdoc.documentElement.childNodes[0].appendChild(newss);
							this.editdoc.body.className = 'wysiwyg';
							this.editdoc.body.style.fontFamily = document.styleSheets[ss].cssRules[i].style.fontFamily;
							this.editdoc.body.style.fontSize = document.styleSheets[ss].cssRules[i].style.fontSize;
							return false;
						}
					}
				}
			};

			/**
			* Insert Link
			*/
			this._createlink = this.createlink;
			this.createlink = function(e, url)
			{
				if (typeof url == 'undefined')
				{
					url = this.show_prompt(vbphrase['enter_link_url'], 'http://', true);
				}
				if ((url = this.verify_prompt(url)) !== false)
				{
					if (this.get_selection())
					{
						this.apply_format('unlink');
						this._createlink(e, url);
					}
					else
					{
						this.insert_text('<a href="' + url + '">' + url + '</a>');
					}
				}
				return true;
			};

			/**
			* Insert Smilie
			*/
			this.insert_smilie = function(e, smilietext, smiliepath, smilieid)
			{
				this.check_focus();

				try
				{
					this.apply_format('InsertImage', false, smiliepath);
					var smilies = fetch_tags(this.editdoc.body, 'img');
					for (var i = 0; i < smilies.length; i++)
					{
						if (smilies[i].src == smiliepath)
						{
							if (smilies[i].getAttribute('smilieid') < 1)
							{
								smilies[i].setAttribute('smilieid', smilieid);
								smilies[i].setAttribute('border', "0");
							}
						}
					}
				}
				catch(e)
				{
					// failed... probably due to inserting a smilie over a smilie in mozilla
				}
			};

			/**
			* Get Selection
			*/
			this.get_selection = function()
			{
				selection = this.editwin.getSelection();
				this.check_focus();
				range = selection ? selection.getRangeAt(0) : this.editdoc.createRange();
				var lsserializer = document.implementation.createLSSerializer();
				return lsserializer.writeToString(range.cloneContents());
			};

			/**
			* Paste Text
			*/
			this.insert_text = function(str)
			{
				this.editdoc.execCommand('insertHTML', false, str);
			};
		}
	}
	// =============================================================================
	// Standard editor
	// =============================================================================
	else
	{
		this.disable_editor = function(text)
		{
			if (!this.disabled)
			{
				this.disabled = true;

				if (typeof text != 'undefined')
				{
					this.editbox.value = text;
				}
				this.editbox.disabled = true;
			}
		};

		this.enable_editor = function(text)
		{
			if (typeof text != 'undefined')
			{
				this.editbox.value = text;
			}
			this.editbox.disabled = false;

			this.disabled = false;
		};

		/**
		* Writes contents to the <textarea>
		*
		* @param	object	<textarea>
		* @param	string	Initial text
		*/
		this.write_editor_contents = function(text)
		{
			this.textobj.value = text;
		}

		/**
		* Put the text into the editor
		*/
		this.set_editor_contents = function(initial_text)
		{
			var iframe = this.textobj.parentNode.getElementsByTagName('iframe')[0];
			if (iframe)
			{
				this.textobj.style.display = '';
				this.textobj.style.width = iframe.style.width;
				this.textobj.style.height = iframe.style.height;

				iframe.style.width = '0px';
				iframe.style.height = '0px';
				iframe.style.border = 'none';
			}

			this.editwin = this.textobj;
			this.editdoc = this.textobj;
			this.editbox = this.textobj;
			this.spellobj = this.textobj;

			if (typeof initial_text != 'undefined')
			{
				this.write_editor_contents(initial_text);
			}

			this.editdoc.editorid = this.editorid;
			this.editwin.editorid = this.editorid;

			this.history.add_snapshot(this.get_editor_contents());
		};

		/**
		* Set the CSS style of the editor
		*/
		this.set_editor_style = function()
		{
		};

		/**
		* Init Editor Functions
		*/
		this.set_editor_functions = function()
		{
			if (this.editdoc.addEventListener)
			{
				this.editdoc.addEventListener('keypress', vB_Text_Editor_Events.prototype.editdoc_onkeypress, false);
			}
			else if (is_ie)
			{
				this.editdoc.onkeydown = vB_Text_Editor_Events.prototype.editdoc_onkeypress;
			}

			this.editwin.onfocus = vB_Text_Editor_Events.prototype.editwin_onfocus;
			this.editwin.onblur = vB_Text_Editor_Events.prototype.editwin_onblur;
		};

		/**
		* Set Context
		*/
		this.set_context = function()
		{
		};

		/**
		* Apply formatting
		*/
		this.apply_format = function(cmd, dialog, argument)
		{
			// undo & redo array pops

			switch (cmd)
			{
				case 'bold':
				case 'italic':
				case 'underline':
				{
					this.wrap_tags(cmd.substr(0, 1), false);
					return;
				}

				case 'justifyleft':
				case 'justifycenter':
				case 'justifyright':
				{
					this.wrap_tags(cmd.substr(7), false);
					return;
				}

				case 'indent':
				{
					this.wrap_tags(cmd, false);
					return;
				}

				case 'fontname':
				{
					this.wrap_tags('font', argument);
					return;
				}

				case 'fontsize':
				{
					this.wrap_tags('size', argument);
					return;
				}

				case 'forecolor':
				{
					this.wrap_tags('color', argument);
					return;
				}

				case 'createlink':
				{
					var sel = this.get_selection();
					if (sel)
					{
						this.wrap_tags('url', argument);
					}
					else
					{
						this.wrap_tags('url', argument, argument);
					}
					return;
				}

				case 'insertimage':
				{
					this.wrap_tags('img', false, argument);
					return;
				}

				case 'removeformat':
				return;
			}

			//alert('cmd :: ' + cmd + '\ndialog :: ' + dialog + '\nargument :: ' + argument);
		};

		this.undo = function()
		{
			this.history.add_snapshot(this.get_editor_contents());
			this.history.move_cursor(-1);
			if ((str = this.history.get_snapshot()) !== false)
			{
				this.editdoc.value = str;
			}
		};

		this.redo = function()
		{
			this.history.move_cursor(1);
			if ((str = this.history.get_snapshot()) !== false)
			{
				this.editdoc.value = str;
			}
		};

		/**
		* Strip a simple tag...
		*/
		this.strip_simple = function(tag, str, iterations)
		{
			var opentag = '[' + tag + ']';
			var closetag = '[/' + tag + ']';

			if (typeof iterations == 'undefined')
			{
				iterations = -1;
			}

			while ((startindex = PHP.stripos(str, opentag)) !== false && iterations != 0)
			{
				iterations --;
				if ((stopindex = PHP.stripos(str, closetag)) !== false)
				{
					var text = str.substr(startindex + opentag.length, stopindex - startindex - opentag.length);
					str = str.substr(0, startindex) + text + str.substr(stopindex + closetag.length);
				}
				else
				{
					break;
				}
			}

			return str;
		};

		/**
		* Strip a tag with an option
		*/
		this.strip_complex = function(tag, str, iterations)
		{
			var opentag = '[' + tag + '=';
			var closetag = '[/' + tag + ']';

			if (typeof iterations == 'undefined')
			{
				iterations = -1;
			}

			while ((startindex = PHP.stripos(str, opentag)) !== false && iterations != 0)
			{
				iterations --;
				if ((stopindex = PHP.stripos(str, closetag)) !== false)
				{
					var openend = PHP.stripos(str, ']', startindex);
					if (openend !== false && openend > startindex && openend < stopindex)
					{
						var text = str.substr(openend + 1, stopindex - openend - 1);
						str = str.substr(0, startindex) + text + str.substr(stopindex + closetag.length);
					}
					else
					{
						break;
					}
				}
				else
				{
					break;
				}
			}

			return str;
		};

		/**
		* Remove Formatting
		*/
		this.removeformat = function(e)
		{
			var simplestrip = new Array('b', 'i', 'u');
			var complexstrip = new Array('font', 'color', 'size');

			var str = this.get_selection();
			if (str === false)
			{
				return;
			}

			// simple stripper
			for (var tag in simplestrip)
			{
				str = this.strip_simple(simplestrip[tag], str);
			}

			// complex stripper
			for (var tag in complexstrip)
			{
				str = this.strip_complex(complexstrip[tag], str);
			}

			this.insert_text(str);
		};

		/**
		* Insert Link
		*/
		this.createlink = function(e, url)
		{
			this.prompt_link('url', url, vbphrase['enter_link_url'], 'http://');
		};

		/**
		* Remove Link
		*/
		this.unlink = function(e)
		{
			var sel = this.get_selection();
			sel = this.strip_simple('url', sel);
			sel = this.strip_complex('url', sel);
			this.insert_text(sel);
		};

		/**
		* Insert Email Link
		*/
		this.email = function(e, email)
		{
			this.prompt_link('email', email, vbphrase['enter_email_link'], '');
		};

		/**
		* Insert Smilie
		*/
		this.insert_smilie = function(e, smilietext)
		{
			this.check_focus();

			return this.insert_text(smilietext, smilietext.length, 0);
		};

		/**
		* Wrapper for Link / Email Link insert
		*/
		this.prompt_link = function(tagname, value, phrase, iprompt)
		{
			if (typeof value == 'undefined')
			{
				value = this.show_prompt(phrase, iprompt, true);
			}
			if ((value = this.verify_prompt(value)) !== false)
			{
				if (this.get_selection())
				{
					this.apply_format('unlink');
					this.wrap_tags(tagname, value);
				}
				else
				{
					this.wrap_tags(tagname, value, value);
				}
			}
			return true;
		};

		/**
		* Insert Ordered List
		*/
		this.insertorderedlist = function(e)
		{
			this.insertlist(vbphrase['insert_ordered_list'], '1');
		};

		/**
		* Insert Unordered List
		*/
		this.insertunorderedlist = function(e)
		{
			this.insertlist(vbphrase['insert_unordered_list'], '');
		};

		/**
		* Insert List
		*/
		this.insertlist = function(phrase, listtype)
		{
			var opentag = '[LIST' + (listtype ? ('=' + listtype) : '') + ']\n';
			var closetag = '[/LIST]';

			if (txt = this.get_selection())
			{
				var regex = new RegExp('([\r\n]+|^[\r\n]*)(?!\\[\\*\\]|\\[\\/?list)(?=[^\r\n])', 'gi');
				txt = opentag + PHP.trim(txt).replace(regex, '$1[*]') + '\n' + closetag;
				this.insert_text(txt, txt.vBlength(), 0);
			}
			else
			{
				this.insert_text(opentag + closetag, opentag.length, closetag.length);

				if (is_ie7)
				{
					// respect a <base> tag (see #23097)
					var base_tag = fetch_tags(document, 'base');
					var modal_prefix;
					if (base_tag && base_tag[0] && base_tag[0].href)
					{
						modal_prefix = base_tag[0].href;
					}
					else
					{
						modal_prefix = '';
					}
					var listvalue = window.showModalDialog(modal_prefix + "clientscript/ieprompt.html?", { 'value': '', 'label': vbphrase['enter_list_item'], 'dir': document.dir, 'title': document.title, 'listtype': listtype }, "dialogWidth:320px; dialogHeight:232px; dialogTop:" + (parseInt(window.screenTop) + parseInt(window.event.clientY) + parseInt(document.body.scrollTop) - 100) + "px; dialogLeft:" + (parseInt(window.screenLeft) + parseInt(window.event.clientX) + parseInt(document.body.scrollLeft) - 160) + "px; resizable: No;");
					if (this.verify_prompt(listvalue))
					{
						this.insert_text(listvalue, listvalue.vBlength(), 0);
					}
				}
				else
				{
					while (listvalue = this.show_prompt(vbphrase['enter_list_item'], '', false))
					{
						listvalue = '[*]' + listvalue + '\n';
						this.insert_text(listvalue, listvalue.vBlength(), 0);
					}
				}
			}
		};

		/**
		* Outdent
		*/
		this.outdent = function(e)
		{
			var sel = this.get_selection();
			sel = this.strip_simple('indent', sel, 1);
			this.insert_text(sel);
		};

		/**
		* Get Editor Contents
		*/
		this.get_editor_contents = function()
		{
			return this.editdoc.value;
		};

		/**
		* Get Selected Text
		*/
		this.get_selection = function()
		{
			if (typeof(this.editdoc.selectionStart) != 'undefined')
			{
				return this.editdoc.value.substr(this.editdoc.selectionStart, this.editdoc.selectionEnd - this.editdoc.selectionStart);
			}
			else if (document.selection && document.selection.createRange)
			{
				return document.selection.createRange().text;
			}
			else if (window.getSelection)
			{
				return window.getSelection() + '';
			}
			else
			{
				return false;
			}
		};

		/**
		* Paste HTML
		*/
		this.insert_text = function(text, movestart, moveend)
		{
			var selection_changed = false;

			this.check_focus();
			
			if (typeof(this.editdoc.selectionStart) != 'undefined')
			{
				var opn = this.editdoc.selectionStart + 0;
				var scrollpos = this.editdoc.scrollTop;

				this.editdoc.value = this.editdoc.value.substr(0, this.editdoc.selectionStart) + text + this.editdoc.value.substr(this.editdoc.selectionEnd);

				if (movestart === false)
				{
					// do nothing
				}
				else if (typeof movestart != 'undefined')
				{
					this.editdoc.selectionStart = opn + movestart;
					this.editdoc.selectionEnd = opn + text.vBlength() - moveend;
				}
				else
				{
					this.editdoc.selectionStart = opn;
					this.editdoc.selectionEnd = opn + text.vBlength();
				}
				this.editdoc.scrollTop = scrollpos;
			}
			else if (document.selection && document.selection.createRange)
			{
				var sel = document.selection.createRange();
				sel.text = text.replace(/\r?\n/g, '\r\n');
												
				if (movestart === false)
				{
					// do nothing
				}
				else if (typeof movestart != 'undefined')
				{
					if ((movestart - text.vBlength()) != 0)
					{
						sel.moveStart('character', movestart - text.vBlength());
						selection_changed = true;
					}
					if (moveend != 0)
					{
						sel.moveEnd('character', -moveend);
						selection_changed = true;
					}
				}
				else
				{
					sel.moveStart('character', -text.vBlength());
					selection_changed = true;
				}

				if (selection_changed)
				{
					sel.select();
				}
			}
			else
			{
				// failed - just stuff it at the end of the message
				this.editdoc.value += text;
			}
		};

		/**
		* Prepare Form For Submit
		*/
		this.prepare_submit = function(subjecttext, minchars)
		{
			var returnvalue = validatemessage(this.textobj.value, subjecttext, minchars);

			if (returnvalue)
			{
				return returnvalue;
			}
			else if (this.captcha != null && this.captcha.failed)
			{
				return returnvalue;
			}
			else
			{
				this.check_focus();
				return false;
			}
		}

		// =============================================================================
		// Safari / Old Opera Standard Editor Only
		// =============================================================================
		if (is_saf || (is_opera && (!opera.version || opera.version() < 8)))
		{
			/**
			* Insert List for Safari and older Opera
			*/
			this.insertlist = function(phrase, listtype)
			{
				var opentag = '[LIST' + (listtype ? ('=' + listtype) : '') + ']\n';
				var closetag = '[/LIST]';
				if (txt = this.get_selection())
				{
					var regex = new RegExp('([\r\n]+|^[\r\n]*)(?!\\[\\*\\]|\\[\\/?list)(?=[^\r\n])', 'gi');
					txt = opentag + PHP.trim(txt).replace(regex, '$1[*]') + '\n' + closetag;
					this.insert_text(txt, txt.vBlength(), 0);
				}
				else
				{
					this.insert_text(opentag, opentag.length, 0);

					while (listvalue = prompt(vbphrase['enter_list_item'], ''))
					{
						listvalue = '[*]' + listvalue + '\n';
						this.insert_text(listvalue, listvalue.vBlength(), 0);
					}

					this.insert_text(closetag, closetag.length, 0);
				}
			};
		}
	}

	// gentlemen, start your engines...
	this.init();
}

// =============================================================================
// Editor event handler functions

/**
* Class containing editor event handlers
*/
function vB_Text_Editor_Events()
{
}

/**
* Handles a click on a smilie in the smiliebox
*/
vB_Text_Editor_Events.prototype.smilie_onclick = function(e)
{
	vB_Editor[this.editorid].insert_smilie(e,
		this.alt,
		this.src,
		this.id.substr(this.id.lastIndexOf('_') + 1)
	);

	if (typeof smilie_window != 'undefined' && !smilie_window.closed)
	{
		smilie_window.focus();
	}

	return false;
};

/**
* Handles a mouse event on a command button
*/
vB_Text_Editor_Events.prototype.command_button_onmouseevent = function(e)
{
	e = do_an_e(e);

	if (e.type == 'click')
	{
		vB_Editor[this.editorid].format(e, this.cmd, false, true);
	}

	vB_Editor[this.editorid].button_context(this, e.type);
};

/**
* Handles a mouse event on a popup controller button
*/
vB_Text_Editor_Events.prototype.popup_button_onmouseevent = function(e)
{
	e = do_an_e(e);

	if (e.type == 'click')
	{
		this._onclick(e);
		vB_Editor[this.editorid].menu_context(this, 'mouseover');
	}
	else
	{
		vB_Editor[this.editorid].menu_context(this, e.type);
	}
};

/**
* Overrides the show() function from the vBmenu system
*
* @param	object	Control object
* @param	boolean	Show instantly?
*/
vB_Text_Editor_Events.prototype.popup_button_show = function(obj, instant)
{
	if (typeof vB_Editor[obj.editorid].popups[obj.cmd] == 'undefined' || vB_Editor[obj.editorid].popups[obj.cmd] == null)
	{
		vB_Editor[obj.editorid].init_popup_menu(obj);
	}
	else if (obj.cmd == 'attach' && (typeof vB_Attachments == 'undefined' || !vB_Attachments.has_attachments()))
	{
		return fetch_object('manage_attachments_button').onclick();
	}
	this._show(obj, instant);
};

/**
* Handles a selection from a formatting <select> menu
*/
vB_Text_Editor_Events.prototype.formatting_select_onchange = function(e)
{
	var arg = this.options[this.selectedIndex].value;
	if (arg != '')
	{
		vB_Editor[this.editorid].format(e, this.cmd, arg);
	}
	this.selectedIndex = 0;
};

/**
* Handles a selection from the smilies <select> menu
*/
vB_Text_Editor_Events.prototype.smilieselect_onchange = function(e)
{
	if (this.options[this.selectedIndex].value != '')
	{
		vB_Editor[this.editorid].insert_smilie(e,
			this.options[this.selectedIndex].value,
			this.options[this.selectedIndex].smiliepath,
			this.options[this.selectedIndex].smilieid
		);
	}
	this.selectedIndex = 0;
};

/**
* Handles a selection from the attachment <select> menu
*/
vB_Text_Editor_Events.prototype.attachselect_onchange = function(e)
{
	var arg = this.options[this.selectedIndex].value;
	if (arg != '')
	{
		vB_Editor[this.editorid].wrap_tags('attach', false, arg);
	}
	this.selectedIndex = 0;
};

/**
* Handles a mouse over event for the attachment <select> menu
*/
vB_Text_Editor_Events.prototype.attachselect_onmouseover = function(e)
{
	if (this.options.length <= 2)
	{
		vB_Editor[this.editorid].build_attachments_popup(this);
		return true;
	}
};

/**
* Handles a mouse event on a menu option
*/
vB_Text_Editor_Events.prototype.menuoption_onmouseevent = function(e)
{
	e = do_an_e(e);
	vB_Editor[this.editorid].button_context(this, e.type, 'menu');
};

/**
* Handles a click on a formatting option in the font/size menus
*/
vB_Text_Editor_Events.prototype.formatting_option_onclick = function(e)
{
	vB_Editor[this.editorid].format(e, this.cmd, this.firstChild.innerHTML);
	vBmenu.hide();
};

/**
* Handles a click on a color option in the color menu
*/
vB_Text_Editor_Events.prototype.coloroption_onclick = function(e)
{
	fetch_object(this.editorid + '_color_bar').style.backgroundColor = this.colorname;
	vB_Editor[this.editorid].format(e, this.cmd, this.colorname);
	vBmenu.hide();
};

/**
* Handles a click on the color instant-select button
*/
vB_Text_Editor_Events.prototype.colorout_onclick = function(e)
{
	e = do_an_e(e);
	vB_Editor[this.editorid].format(e, 'forecolor', fetch_object(this.editorid + '_color_bar').style.backgroundColor);
	return false;
};

/**
* Handles a click on a smilie option in the smilie menu
*/
vB_Text_Editor_Events.prototype.smilieoption_onclick = function(e)
{
	vB_Editor[this.editorid].button_context(this, 'mouseout', 'menu');
	vB_Editor[this.editorid].insert_smilie(e, this.smilietext, fetch_tags(this, 'img')[0].src, this.smilieid);
	vBmenu.hide();
};

/**
* Handles a click on the 'More' link in the smilie menu
*/
vB_Text_Editor_Events.prototype.smiliemore_onclick = function(e)
{
	vB_Editor[this.editorid].open_smilie_window(smiliewindow_x, smiliewindow_y);
	vBmenu.hide();
};

/**
* Handles a click on the 'Manage' link in the attachments menu
*/
vB_Text_Editor_Events.prototype.attachmanage_onclick = function(e)
{
	vBmenu.hide();
	fetch_object('manage_attachments_button').onclick();
};

/**
* Handles a click on an attachment option in the attachments menu
*/
vB_Text_Editor_Events.prototype.attachoption_onclick = function(e)
{
	vB_Editor[this.editorid].button_context(this, 'mouseout', 'menu');
	vB_Editor[this.editorid].wrap_tags('attach', false, this.attachmentid);
	vBmenu.hide();
};

vB_Text_Editor_Events.prototype.attachinsertall_onclick = function(e)
{
	var insert = '';
	var breakchar = (vB_Editor[this.editorid].wysiwyg_mode ? '<br /><br />' : '\r\n\r\n');

	for (var id in vB_Attachments.attachments)
	{
		insert += insert != '' ? breakchar : '';
		insert += '[ATTACH]' + id + '[/ATTACH]';
	}
	vB_Editor[this.editorid].insert_text(insert);
	vBmenu.hide();
}

/**
* Closes the smilie window when the main page exits
*/
vB_Text_Editor_Events.prototype.smiliewindow_onunload = function(e)
{
	if (typeof smilie_window != 'undefined' && !smilie_window.closed)
	{
		smilie_window.close();
	}
};

/**
* Sets editwin.hasfocus = true on focus
*/
vB_Text_Editor_Events.prototype.editwin_onfocus = function(e)
{
	this.hasfocus = true;
};

/**
* Sets editwin.hasfocus = false on blur
*/
vB_Text_Editor_Events.prototype.editwin_onblur = function(e)
{
	this.hasfocus = false;
};

/**
* Sets context and hides menus on mouse clicks in the editor
*/
vB_Text_Editor_Events.prototype.editdoc_onmouseup = function(e)
{
	vB_Editor[this.editorid].set_context();
	if (vB_Editor[this.editorid].popupmode)
	{
		vBmenu.hide();
	}
};

/**
* Sets context on key presses in the editor
*/
vB_Text_Editor_Events.prototype.editdoc_onkeyup = function(e)
{
	vB_Editor[this.editorid].set_context();
};

/**
* Handle a keypress event in the editor window
*/
vB_Text_Editor_Events.prototype.editdoc_onkeypress = function(e)
{
	if (!e)
	{
		e = window.event;
	}

	if (e.ctrlKey)
	{
		var code = e.charCode ? e.charCode : e.keyCode;
		switch (String.fromCharCode(code).toLowerCase())
		{
			case 'b': cmd = 'bold'; break;
			case 'i': cmd = 'italic'; break;
			case 'u': cmd = 'underline'; break;
			default: return;
		}

		e = do_an_e(e);
		vB_Editor[this.editorid].apply_format(cmd, false, null);
		return false;
	}
	else if (e.keyCode == 9)
	{
		// first lets try post icon, then submit, then just let it proceed making the tab
		// you can't suppress events a tab even on Opera so skip that too.
		var firsticon = fetch_object('rb_iconid_0');
		if (firsticon != null)
		{
			firsticon.focus();
		}
		else if (fetch_object(this.editorid + '_save') != null && !is_opera)
		{
			fetch_object(this.editorid + '_save').focus();
		}
		else if (fetch_object('qr_submit') != null && !is_opera)
		{
			fetch_object('qr_submit').focus();
		}
		else
		{
			return;
		}
		e = do_an_e(e);
	}
};

/**
* Stop resizing of images in IE
*/
vB_Text_Editor_Events.prototype.editdoc_onresizestart = function(e)
{
	if (e.srcElement.tagName == 'IMG')
	{
		return false;
	}
};

/**
* Save editor contents to textarea so if we hit back / forward its not lost
* Only appears to work with Firefox at the moment
*/
function save_iframe_to_textarea()
{
	for (var editorid in vB_Editor)
	{
		if (vB_Editor[editorid].wysiwyg_mode && vB_Editor[editorid].initialized)
		{
			vB_Editor[editorid].textobj.value = vB_Editor[editorid].get_editor_contents();
		}
	}
}

if (window.attachEvent)
{
	window.attachEvent('onbeforeunload', save_iframe_to_textarea);
}
else if(window.addEventListener)
{
	window.addEventListener('unload', save_iframe_to_textarea, true);
}

// #############################################################################
// Editor mode switcher system

/**
* Switch editor between standard and wysiwyg modes
*
* @param	string	EditorID (vB_Editor[x])
*/
function switch_editor_mode(editorid)
{
	if (AJAX_Compatible)
	{
		var mode = (vB_Editor[editorid].wysiwyg_mode ? 0 : 1);

		if (vB_Editor[editorid].influx == 1)
		{
			// already clicked, go away!
			return;
		}
		else
		{
			vB_Editor[editorid].influx = 1;
		}

		if (typeof vBmenu != 'undefined')
		{
			vBmenu.hide();
		}

		var ajax = new vB_AJAX_Handler(true);
		ajax.onreadystatechange(function()
		{
			if (ajax.handler.readyState == 4 && ajax.handler.status == 200)
			{
				if (ajax.handler.responseXML)
				{
					// destroyer
					var parsetype = vB_Editor[editorid].parsetype;
					var parsesmilies = vB_Editor[editorid].parsesmilies;
					var ajax_extra = vB_Editor[editorid].ajax_extra;

					vB_Editor[editorid].destroy();

					var parsed_text = ajax.fetch_data(fetch_tags(ajax.handler.responseXML, 'message')[0]);
					var matches = parsed_text.match(/&#([0-9]+);/g);
					if (matches)
					{
						for (var i = 0; typeof matches[i] != 'undefined'; i++)
						{
							if (submatch = matches[i].match(/^&#([0-9]+);$/))
							{
								parsed_text = parsed_text.replace(submatch[0], String.fromCharCode(submatch[1]));
							}
						}
					}

					// removing this line because of IE7 focus issues - on second activation of IE7 WYWIWYG, focus is lost
					//vB_Editor[editorid] = null; // collect the garbage

					vB_Editor[editorid] = new vB_Text_Editor(editorid, mode, parsetype, parsesmilies, parsed_text, ajax_extra);
					vB_Editor[editorid].check_focus();
					fetch_object(editorid + '_mode').value = mode;
				}

				if (is_ie)
				{
					ajax.handler.abort();
				}
			}
		});

		// !!attention
		ajax.send('ajax.php?do=editorswitch', 'do=editorswitch'
			+ '&towysiwyg='	+ mode
			+ '&parsetype=' + vB_Editor[editorid].parsetype
			+ '&allowsmilie=' + vB_Editor[editorid].parsesmilies
			+ '&message=' + PHP.urlencode(vB_Editor[editorid].get_editor_contents())
			+ (vB_Editor[editorid].ajax_extra ? ('&' + vB_Editor[editorid].ajax_extra) : '')
		);
	}
}


// #############################################################################
// Generic global editor variables

/**
* Define which buttons are context-controlled
*
* @var	array	Context controls
*/
var contextcontrols = new Array(
	'bold',
	'italic',
	'underline',
	'justifyleft',
	'justifycenter',
	'justifyright',
	'insertorderedlist',
	'insertunorderedlist'
);

/**
* Define available color name options - keyed with hex value
*
* @var	array	Color options
*/
var coloroptions = new Array();
coloroptions = {
	'#000000' : 'Black',
	'#A0522D' : 'Sienna',
	'#556B2F' : 'DarkOliveGreen',
	'#006400' : 'DarkGreen',
	'#483D8B' : 'DarkSlateBlue',
	'#000080' : 'Navy',
	'#4B0082' : 'Indigo',
	'#2F4F4F' : 'DarkSlateGray',
	'#8B0000' : 'DarkRed',
	'#FF8C00' : 'DarkOrange',
	'#808000' : 'Olive',
	'#008000' : 'Green',
	'#008080' : 'Teal',
	'#0000FF' : 'Blue',
	'#708090' : 'SlateGray',
	'#696969' : 'DimGray',
	'#FF0000' : 'Red',
	'#F4A460' : 'SandyBrown',
	'#9ACD32' : 'YellowGreen',
	'#2E8B57' : 'SeaGreen',
	'#48D1CC' : 'MediumTurquoise',
	'#4169E1' : 'RoyalBlue',
	'#800080' : 'Purple',
	'#808080' : 'Gray',
	'#FF00FF' : 'Magenta',
	'#FFA500' : 'Orange',
	'#FFFF00' : 'Yellow',
	'#00FF00' : 'Lime',
	'#00FFFF' : 'Cyan',
	'#00BFFF' : 'DeepSkyBlue',
	'#9932CC' : 'DarkOrchid',
	'#C0C0C0' : 'Silver',
	'#FFC0CB' : 'Pink',
	'#F5DEB3' : 'Wheat',
	'#FFFACD' : 'LemonChiffon',
	'#98FB98' : 'PaleGreen',
	'#AFEEEE' : 'PaleTurquoise',
	'#ADD8E6' : 'LightBlue',
	'#DDA0DD' : 'Plum',
	'#FFFFFF' : 'White'
};

// #############################################################################
// vB_History
// #############################################################################

function vB_History()
{
	this.cursor = -1;
	this.stack = new Array();
}

// =============================================================================
// vB_History methods

vB_History.prototype.move_cursor = function(increment)
{
	var test = this.cursor + increment;
	if (test >= 0 && this.stack[test] != null && typeof this.stack[test] != 'undefined')
	{
		this.cursor += increment;
	}
};

vB_History.prototype.add_snapshot = function(str)
{
	if (this.stack[this.cursor] == str)
	{
		return;
	}
	else
	{
		this.cursor++;
		this.stack[this.cursor] = str;

		if (typeof this.stack[this.cursor + 1] != 'undefined')
		{
			this.stack[this.cursor + 1] = null;
		}
	}
};

vB_History.prototype.get_snapshot = function()
{
	if (typeof this.stack[this.cursor] != 'undefined' && this.stack[this.cursor] != null)
	{
		return this.stack[this.cursor];
	}
	else
	{
		return false;
	}
};

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 27022 $
|| ####################################################################
\*======================================================================*/