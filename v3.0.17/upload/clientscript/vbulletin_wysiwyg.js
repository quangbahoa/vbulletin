/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// catch sneaky Safari browsers identifying as Internet Explorer
if (is_saf)
{
	alert(vbphrase["browser_is_safari_no_wysiwyg"]);
}

// popup open state
var popupopen = false;

// select-all state
var controla = false;

// mozilla css off
var css_off = false;

// initial opacity filter state of popup menus
var opacity = 0;

// number between 1-100 (larger = faster fade) or 0 to disable fader
var ie_use_fader = 0;

// current status of the font/size feedback boxes
var currentfont = "";
var currentsize = "";

// initialize some objects and arrays
var elm = new Object();
var htmlwindow = new Object();
var htmlbox = new Object();

// define which buttons open popups
var popupcontrols = new Array(
	"forecolor",
	"fontname",
	"fontsize",
	"smilie"
);

// define which buttons are context-controlled
var contextcontrols = new Array(
	"bold",
	"italic",
	"underline",
	"justifyleft",
	"justifycenter",
	"justifyright",
	"insertorderedlist",
	"insertunorderedlist"
);

// define which controls exist
var controlexists = new Array();
controlexists = {
	"fontname"            : false,
	"fontsize"            : false,
	"forecolor"           : false,
	"smilie"              : false,
	"bold"                : false,
	"italic"              : false,
	"underline"           : false,
	"justifyright"        : false,
	"justifycenter"       : false,
	"justifyleft"         : false,
	"insertorderedlist"   : false,
	"insertunorderedlist" : false
}

// define which keycodes do NOT insert a character
var nofirekeys = new Array(
	9,                       // Tab
	16,17,18,19,20,          // Shift, Ctrl, alt, Pause/Break, Capslock
	27,                      // Escape
	33,34,35,36,37,38,39,40, // Page Up, Page Down, Home, End, Left, Up, Right, Down
	45,                      // Insert
	91, 93,                  // Windows / Context
	112,113,114,115,116,117, // F1 - F6
	118,119,120,121,122,123, // F7 - F12
	144,145                  // NumLock, ScrollLock
);

// variable to say whether or not the smiliebox is ready
var smiliebox_loaded = false;

// #############################################################################
// ############################ START FUNCTIONS ################################
// #############################################################################

// #############################################################################
function validatePost(tForm, subjectVal, minLength, maxLength)
{
	if (is_ie)
	{
		if (typeof(htmlwindow.innerHTML) == "undefined")
		{
			alert(vbphrase["wysiwyg_please_wait"]);
			return false;
		}
		tForm.WYSIWYG_HTML.value = htmlwindow.innerHTML;
	}
	else
	{
		tForm.WYSIWYG_HTML.value = htmlbox.body.innerHTML;
	}

	return validatemessage(tForm.WYSIWYG_HTML.value, subjectVal, minLength, maxLength, true, tForm);
}

// #############################################################################
function editInit()
{
	wysiwyg_init();
}

// #############################################################################
// function to initialize the editor
function wysiwyg_init()
{
	// attempt to init the smiliebox
	wysiwyg_smiliebox_init();

	if (editor_loaded)
	{
		// editor is already loaded, don't try to load it again, as that would be bad m'kay.
		return;
	}

	// say that this function has been run / is running
	editor_loaded = true;

	var starttime = new Date();

	// set up the exists array
	for (key in controlexists)
	{
		controlexists[key] = object_exists("cmd_" + key);
	}

	// init color controls
	var i = 0;
	for (colorkey in coloroptions)
	{
		colorindex[i++] = coloroptions[colorkey];
	}

	// make all toolbar elements unselectable
	set_unselectable(fetch_object("controlbar"));

	// init buttons
	var divs = fetch_object("controlbar").getElementsByTagName("div");
	for (i  = 0; i < divs.length; i++)
	{
		elm = divs[i];
		switch (elm.className)
		{
			case "imagebutton":
			{
				elm.cmd = elm.id.substr(4);
				elm.controlstate = "normal";
				if (typeof(elm.firstChild.alt) != "undefined")
				{
					elm.firstChild.title = elm.firstChild.alt;
				}
				elm.onmouseover = elm.onmouseout = elm.onmouseup = elm.onmousedown = button_eventhandler;
				elm.onclick = button_click;
			}
			break;
		}
	}

	// init menu elements
	var color = 0;
	var font = 0;
	var size = 0;

	var tds = fetch_object("controlbar").getElementsByTagName("td");
	for (i = 0; i < tds.length; i++)
	{
		elm = tds[i];
		switch (elm.className)
		{
			case "ocolor":
			{
				elm.formatoption = elm.firstChild.style.background = colorindex[color];
				elm.id = "color_" + colorindex[color++].toLowerCase();
				elm.title = elm.formatoption.replace(/([a-z]{1})([A-Z]{1})/g, "$1 $2");
				elm.onmouseover = elm.onmouseout = elm.onmousedown = elm.onmouseup = select_eventhandler;
				elm.onclick = select_click;
			}
			break;

			case "ofont":
			{
				elm.formatoption = elm.style.fontFamily = fontoptions[font++];
				elm.title = elm.formatoption;
				elm.onmouseover = elm.onmouseout = elm.onmousedown = elm.onmouseup = select_eventhandler;
				elm.onclick = select_click;
			}
			break;

			case "osize":
			{
				elm.formatoption = sizeoptions[size++];
				elm.title = elm.formatoption;
				elm.style.textAlign = "center";
				elm.onmouseover = elm.onmouseout = elm.onmousedown = elm.onmouseup = select_eventhandler;
				elm.onclick = select_click;
			}
			break;

			case "osmilie":
			case "otextlink":
			{
				elm.onmouseover = elm.onmouseout = elm.onmousedown = elm.onmouseup = select_eventhandler;
				elm.onclick = select_click;
			}
		}
	}

	// init color controls 2
	if (controlexists['forecolor'])
	{
		var instantcolor = fetch_object("instantcolor");
		var colorbar = fetch_object("colorbar");
		colorbar.onmouseover = colorbar.onmouseout = colorbar.onmousedown = colorbar.onmouseup = colorbar_intercept;
		colorbar.onclick = instantcolor.onclick = set_instant_color;
	}

	// init html area and set up event handlers
	if (is_ie)
	{
		// init edit window
		htmlwindow = fetch_object("htmlbox");
		htmlbox = document;
		htmlwindow.contentEditable = true;
		htmlbox.execCommand("liveresize", false, null);

		// paste contents into window
		try
		{
			hf = fetch_object("html_hidden_field");
			itxt = hf.value;
		}
		catch(e)
		{
			itxt = "<p></p>";
		}
		set_default_text(itxt, true);

		// init context events
		htmlwindow.onmouseup = set_context;
		htmlbox.onkeyup = set_context;

		// init special event trapping
		htmlwindow.onkeypress = check_return;
		htmlwindow.onkeydown = check_select_all;
		htmlwindow.onclick = function () { controla = false; };
	}
	else
	{
		// init edit window
		htmlwindow = fetch_object("htmlbox").contentWindow;
		htmlbox = htmlwindow.document;

		// set up design mode
		htmlbox.designMode = "on";

		// a bug in mozilla will cause this to be ignored.
		//htmlbox.execCommand("useCSS", false, false);

		var itxt;
		// paste contents into window
		try
		{
			var hf = fetch_object("html_hidden_field");
			itxt = hf.value;
		}
		catch(e)
		{
			// okay, so don't then...
			itxt = "";
		}
		set_default_text(itxt, true);

		// init context events
		htmlbox.addEventListener("mouseup", set_context, true);
		htmlbox.addEventListener("keyup", set_context, true);

		// init special event trapping
		htmlbox.addEventListener("keypress", moz_intercept_keys, true);
	}

	if (controlexists['forecolor'])
	{
		fetch_object("colorbar").style.display = "";
	}

	window.onresize = close_popups;

	if (typeof(preview_focus) == "undefined" && typeof(require_click) == "undefined")
	{
		htmlwindow.focus(); // not doing this for now as it moves the page down on preview and the QR area might be closed
	}

	var endtime = new Date();
	window.status = construct_phrase(vbphrase["wysiwyg_initialized"], (is_ie ? "Internet Explorer" : "Mozilla"), (endtime - starttime) / 1000);
}

// #############################################################################
// function to initialize the smiliebox
function wysiwyg_smiliebox_init()
{
	if (smiliebox_loaded)
	{
		return;
	}
	else
	{
		// fetch the smiliebox object, forcing the system to look for it
		var smilieboxobj = fetch_object("smiliebox", true);
		if (smilieboxobj)
		{
			set_unselectable(smilieboxobj);
			var divs = smilieboxobj.getElementsByTagName("div");
			for (var i = 0; i < divs.length; i++)
			{
				if (divs[i].className == "sbsmilie")
				{
					divs[i].onclick = prepare_insert_smilie;
					if (is_ie)
					{
						divs[i].style.cursor = "hand";
					}
					else
					{
						divs[i].style.cursor = "pointer";
					}
				}
			}
			smiliebox_loaded = true;
		}
	}
}

// #############################################################################
// returns true / false if an object is found
function object_exists(objname)
{
	if (fetch_object(objname))
	{
		return true;
	}
	else
	{
		return false;
	}
}

// #############################################################################
// function to intercept the return / enter key for Internet Explorer
function check_return()
{
	// if key is return/enter, find the enclosing <p> and set its style to margin:0px
	if (window.event.keyCode == 13)
	{
		range = htmlbox.selection.createRange();
		parentelm = range.parentElement();

		while (parentelm.tagName != "P" && parentelm.tagName != "DIV")
		{
			parentelm = parentelm.parentNode;
		}

		if (parentelm.tagName == "P")
		{
			parentelm.style.margin = "0px";
		}
	}
}

// #############################################################################
// function to intercept control-a presses for Internet Explorer
function check_select_all()
{
	if (window.event.ctrlKey)
	{
		if (window.event.keyCode == 65)
		{
			// if pressed control+a, set 'controla' to true
			controla = true;
		}
	}
	else if (controla && !window.event.shiftKey && !window.event.altKey)
	{
		if (in_array(window.event.keyCode, nofirekeys) == -1)
		{
			// if controla is true, paste in '<p></p>' to prevent double-spacing
			document.selection.createRange().pasteHTML("<p></p>");
		}
		// set controla to false again
		controla = false;
	}
}

// #############################################################################
// function to update the state of all context-sensitive controls
function set_context(clickedelm)
{

	if (!is_ie && !css_off)
	{ // its mozilla and the user is attempting to do some editing so we'll presume its loaded
		try
		{
			htmlbox.execCommand("useCSS", false, true);
			css_off = true;
		}
		catch(e)
		{
			// its probably not ready yet
			css_off = false;
		}
	}

	// do context buttons
	var qcs;
	for (var i in contextcontrols)
	{
		if (controlexists[contextcontrols[i]])
		{
			qcs = htmlbox.queryCommandState(contextcontrols[i]);
			// only update the formatting if the state of a control actually needs to change
			if (typeof(buttonstatus[contextcontrols[i]]) != "undefined" && buttonstatus[contextcontrols[i]] != qcs)
			{
				buttonstatus[contextcontrols[i]] = qcs;
				button_context(fetch_object("cmd_" + contextcontrols[i]), contextcontrols[i] == clickedelm ? "mouseover" : "mouseout");
			}
		}
	}

	// set left-align button to highlight if no align button is highlighted in IE
		// disabled this for the time being
	/*if (is_ie && !buttonstatus['justifycenter'] && !buttonstatus['justifyright'])
	{
		buttonstatus['justifyleft'] = true;
		button_context(fetch_object("cmd_justifyleft"), "mouseout");
	}*/

	// do font context
	if (controlexists['fontname'])
	{
		var fontcontext = htmlbox.queryCommandValue("fontname");
		switch (fontcontext)
		{
			case "":
			{
				if (!is_ie)
				{
					fontcontext = htmlbox.body.style.fontFamily;
				}
			}
			break;

			case null:
			{
				fontcontext = "";
			}
			break;
		}
		if (fontcontext != currentfont)
		{
			if (is_ie)
			{
				fontdivs = fetch_object("fontFeedback").childNodes;
				for (i = 0; i < fontdivs.length; i++)
				{
					fontdivs[i].style.display = fontdivs[i].innerHTML == fontcontext ? "" : "none";
				}
			}
			else
			{
				var fontword = fontcontext;
				var commapos = fontword.indexOf(",");
				if (commapos != -1)
				{
					fontword = fontword.substr(0, commapos);
					fontword = fontword.substr(0, 1).toUpperCase() + fontword.substr(1);
				}
				fetch_object("fontOut").value = fontword;
			}
			currentfont = fontcontext;
			fetch_object("fontFeedback").title = fontcontext;
		}
	}

	// do size context
	if (controlexists['fontsize'])
	{
		var sizecontext = htmlbox.queryCommandValue("fontsize");
		switch (sizecontext)
		{
			case "":
			{
				if (!is_ie)
				{
					sizecontext = moz_get_font_size(htmlbox.body.style.fontSize);
				}
			}
			break;

			case null:
			{
				sizecontext = "";
			}
			break;
		}
		if (sizecontext != currentsize)
		{
			if (is_ie)
			{
				sizedivs = fetch_object("sizeFeedback").childNodes;
				for (i = 0; i < sizedivs.length; i++)
				{
					sizedivs[i].style.display = sizedivs[i].innerHTML == sizecontext ? "" : "none";
				}
			}
			else
			{
				fetch_object("sizeOut").value = sizecontext;
			}
			currentsize = sizecontext;
			fetch_object("sizeFeedback").title = sizecontext;
		}
	}

	close_popups();
}

// #############################################################################
// function to handle incoming events from buttons (part 1)
function button_eventhandler(e, elm)
{
	e = do_an_e(e);
	button_context(elm ? elm : this, e.type);
}

// #############################################################################
// function to handle incoming events from buttons (part 2)
function button_context(elm, state)
{
	if (elm == null)
	{
		return;
	}
	if (typeof(buttonstatus[elm.cmd]) == "undefined")
	{
		buttonstatus[elm.cmd] = null;
	}

	switch (buttonstatus[elm.cmd])
	{
		case "down":
		{
			format_control(elm, (elm.cmd == "forecolor" || elm.cmd == "smilie") ? "popup" : "button", "down");
		}
		break;

		case true:
		{
			switch (state)
			{
				case "click":
				case "mouseout":
				{
					format_control(elm, "button", "selected");
				}
				break;

				case "mouseover":
				case "mousedown":
				{
					format_control(elm, "button", "down");
				}
				break;

				case "mouseup":
				{
					format_control(elm, "button", "hover");
				}
				break;
			}
		}
		break;

		default:
		{
			switch (state)
			{
				case "click":
				case "mouseout":
				{
					format_control(elm, "button", "normal");
				}
				break;

				case "mouseup":
				case "mouseover":
				{
					if (popupopen && in_array(elm.cmd, popupcontrols) == -1)
					{
						return;
					}
					else
					{
						format_control(elm, "button", "hover");
					}
				}
				break;

				case "mousedown":
				{
					format_control(elm, "button", "down");
				}
				break;
			}
		}
	}
}

// #############################################################################
// function to handle completed clicks on buttons
function button_click(e, elm)
{
	e = do_an_e(e);
	elm = elm ? elm : this;

	switch (elm.cmd)
	{
		case "forecolor":
		case "fontname":
		case "fontsize":
		case "smilie":
		{
			if (buttonstatus[elm.cmd])
			{
				close_popups();
			}
			else
			{
				open_popup(elm, "popup_" + elm.cmd, e);
			}
		}
		break;

		case "insertimage":
		{
			// get image url and parse out potentially nasty stuff
			imgurl = new String(prompt(vbphrase["enter_image_url"], imgurl)).replace(/javascript:/gi, 'java_script:').replace(/"/g, '&quot;');
			
			if (imgurl != "http://" && imgurl != "" && imgurl != "undefined" && imgurl != null)
			{
				if (imgurl.match(/^https?:\/\//))
				{
					do_format("insertimage", false, imgurl);
				}
			}
		}
		break;

		case "createlink":
		{
			if (is_ie)
			{
				do_format("createlink", true, null);
			}
			else
			{
				if (moz_get_html() == "")
				{
					alert(vbphrase["moz_must_select_text"]);
					break;
				}
				var tmplink = prompt(vbphrase["enter_link_url"], linkurl);
				if (tmplink != "http://" && tmplink != "" && tmplink != "undefined" && tmplink != null)
				{
					linkurl = tmplink;
					do_format("createlink", false, linkurl);
				}
				else
				{
					linkurl = "http://";
					do_format("unlink", false, null);
				}
			}
		}
		break;

		case "unlink":
		{
			if (is_ie)
			{
				do_format("unlink", true, null);
			}
			else
			{
				if (moz_get_html() == "")
				{
					alert(vbphrase["must_select_text_to_use"]);
					break;
				}
				do_format("unlink", false, null);
			}
		}
		break;

		case "createmail":
		{
			tmplink = prompt(vbphrase["enter_email_link"], "");
			if (tmplink != "" && tmplink != "undefined" && tmplink != null)
			{
				linkurl = "mailto:" + tmplink;
				if (is_ie)
				{
					var sText = document.selection.createRange().htmlText;
					if (!sText)
					{
						sText = tmplink;
					}
					document.selection.createRange().pasteHTML("<a href=\"" + linkurl + "\">" + sText + "</a>");
				}
				else
				{
					sText = moz_get_html();
					if (sText == "" || sText == "undefined" || sText == null)
					{
						sText = tmplink;
					}
					var frag = htmlbox.createDocumentFragment();
					var span = htmlbox.createElement("span");
					span.innerHTML = "<a href=\"" + linkurl + "\">" + sText + "</a>";

					while (span.firstChild)
					{
						frag.appendChild(span.firstChild);
					}

					moz_insert_node_at_selection(frag);

				}
			}
		}
		break;

		default:
		{
			if (elm.cmd.substr(0, 4) == "wrap")
			{
				wrap_tags(elm.cmd.substr(6).toUpperCase(), "[", "]", elm.cmd.substr(4, 1));
			}
			else
			{
				do_format(elm.cmd, false, null);
			}
		}
		break;
	}

	htmlwindow.focus();
}

// #############################################################################
// function to handle incoming events from elements inside popups (part 1)
function select_eventhandler(e, elm)
{
	e = do_an_e(e);
	select_context(elm = elm ? elm : this, e.type);
}

// #############################################################################
// function to handle incoming events from elements inside popups (part 2)
function select_context(elm, state)
{
	if (typeof(elm.formatoption) == "undefined" || typeof(buttonstatus[elm.formatoption]) == "undefined")
	{
		if (typeof(elm.formatoption) == "undefined")
		{
			elm.formatoption = elm.id;
		}
		buttonstatus[elm.formatoption] = null;
	}
	switch (buttonstatus[elm.formatoption])
	{
		case true:
		{
			switch (state)
			{
				case "click":
				case "mouseout":
				{
					format_control(elm, "button", "selected");
				}
				break;

				case "mouseover":
				case "mousedown":
				{
					format_control(elm, "menu", "down");
				}
				break;

				case "mouseup":
				{
					format_control(elm, "menu", "hover");
				}
				break;
			}
		}
		break;

		default:
		{
			switch (state)
			{
				case "click":
				case "mouseout":
				{
					format_control(elm, "menu", "normal");
					window.status = "";
				}
				break;

				case "mouseover":
				case "mouseup":
				{
					format_control(elm, "menu", "hover");
					window.status = elm.title;
				}
				break;

				case "mousedown":
				{
					format_control(elm, "menu", "down");
				}
				break;

				default: return;
			}
		}
	}
}

// #############################################################################
// function to handle completed clicks on elements inside popups
function select_click(e)
{
	e = do_an_e(e);

	switch (this.className)
	{
		case "ocolor":
		{
			set_color(this.formatoption);
			return;
		}
		break;

		case "ofont":
		{
			do_format("fontname", false, this.formatoption);
		}
		break;

		case "osize":
		{
			do_format("fontsize", false, this.formatoption);
		}
		break;

		case "osmilie":
		{
			close_popups();
			insert_smilie(this, this.id.substr(7));
		}
		break;

		case "otextlink":
		{
			switch (this.id)
			{
				case "morecolors":
				{
					if (newcolor = call_system_color())
					{
						if (newcolor != "#000000")
						{
							set_color(newcolor);
						}
					}
				}
				break;

				case "moresmilies":
				{
					open_smilie_window(smiliewindow_x, smiliewindow_y, 1);
					close_popups();
				}
				break;
			}
		}
		break;
	}

	format_control(this, "menu", "normal");
}

// #############################################################################
function open_popup(buttonelement, menuname, e)
{
	close_popups();

	if (e)
	{
		e.cancelBubble = true;
	}

	if (menuname == "popup_forecolor")
	{
		// do color context
		var colorcontext = new String(htmlbox.queryCommandValue("forecolor")).toLowerCase();

		if (is_ie)
		{
			colorcontext = "#" + translate_ie_forecolor(colorcontext);
		}

		if (colorcontext.substr(0, 1) == "#")
		{
			colorcontext = colorcontext.toUpperCase();
			if (coloroptions[colorcontext])
			{
				colorcontext = coloroptions[colorcontext].toLowerCase();
			}
		}

		// set all color swatches to be unselected
		var colortds = fetch_object("popup_forecolor").getElementsByTagName("td");
		for (var i = 0; i < colortds.length; i++)
		{
			buttonstatus[colortds[i].formatoption] = false;
			format_control(colortds[i], "menu", "normal");
		}

		// attempt to select active color swatch
		try
		{
			var selcolor = fetch_object("color_" + colorcontext);
			buttonstatus[selcolor.formatoption] = true;
			format_control(selcolor, "button", "selected");
		}
		catch(e) {}
	}

	buttonstatus[buttonelement.cmd] = "down";
	button_context(buttonelement, "mousedown");

	if (!is_ie)
	{
		var elmleft = getOffsetLeft(buttonelement);
		var elmtop = buttonelement.offsetHeight + getOffsetTop(buttonelement);

		fetch_object(menuname).style.left = elmleft + "px";
		fetch_object(menuname).style.top = elmtop + "px";
	}

	popupopen = true;

	if (is_ie && ie_use_fader)
	{
		opacity = 0;
		fetch_object(menuname).filters.item(0).opacity = 0;
		fade_popup(menuname);
	}
	else
	{
		fetch_object(menuname).style.display = "";
	}

	/*if (menuname == "popup_forecolor" || menuname == "popup_smilie")
	{
		if (!is_ie)
		{
			fetch_object("popupfix").style.left = elmleft + "px";
			fetch_object("popupfix").style.top = elmtop - 1 + "px";
		}
		else
		{
			fetch_object("popupfix").style.top = "20px";
		}
		fetch_object("popupfix").style.display = "";
	}*/
}

// #############################################################################
function fade_popup(itemid)
{
	elm = fetch_object(itemid)
	elm.style.display = "";

	if (opacity <= 100)
	{
		opacity += ie_use_fader;
		elm.filters.item(0).opacity = opacity;
		fadetimer = setTimeout("fade_popup('" + itemid + "');", 10);
	}
	else
	{
		opacity = 0;
		clearTimeout(fadetimer);
	}
}

// #############################################################################
function close_popups()
{
	if (!popupopen)
	{
		return;
	}

	popupopen = false;

	//fetch_object("popupfix").style.display = "none";
	var curbutton, curpopup;
	for (i in popupcontrols)
	{
		if (controlexists[popupcontrols[i]])
		{
			curbutton = fetch_object("cmd_" + popupcontrols[i]);
			buttonstatus[curbutton.cmd] = false;
			button_context(curbutton, "mouseout");

			curpopup = fetch_object("popup_" + popupcontrols[i]);
			if (curpopup.style.display != "none")
			{
				curpopup.style.display = "none";
				curpopup.scrollTop = 0;
			}
		}
	}

	if (is_ie && ie_use_fader && fadetimer)
	{
		clearTimeout(fadetimer);
	}
}

// #############################################################################
function translate_ie_forecolor(forecolor)
{
	if (is_ie)
	{
		var r = (forecolor & 0xFF).toString(16);
			r = r.length < 2 ? ("0" + r) : r;
		var g = ((forecolor >> 8) & 0xFF).toString(16);
			g = g.length < 2 ? ("0" + g) : g;
		var b = ((forecolor >> 16) & 0xFF).toString(16);
			b = b.length < 2 ? ("0" + b) : b;
		return (r + g + b).toUpperCase();
	}
	else
	{
		return forecolor;
	}
}

// #############################################################################
function call_system_color()
{
	curcolor = translate_ie_forecolor(htmlbox.queryCommandValue("forecolor"));

	newcolor = fetch_object("syscolorpicker").ChooseColorDlg(curcolor).toString(16);

	if (newcolor.length < 6)
	{
		tmpcolor = "000000".substring(0, 6 - newcolor.length);
		newcolor = tmpcolor.concat(newcolor);
	}

	if (newcolor == curcolor)
	{
		return false;
	}
	else
	{
		return newcolor;
	}
}

// #############################################################################
// function to apply formatting to selected text using execCommand()
function do_format(formatcommand, showinterface, extraparameters)
{
	htmlwindow.focus();

	try
	{
		// attempt to apply the specified formatting
		htmlbox.execCommand(formatcommand, showinterface, extraparameters);
		set_context(formatcommand);
	}
	catch(e)
	{
		// if that caused an error, tell user why
		switch (formatcommand.toLowerCase())
		{
			case "cut":
			case "copy":
			case "paste":
			{
				// mozilla needs to have a config file edited to allow cut/copy/paste via execCommand
				alert(vbphrase["moz_edit_config_file"]);
			}
			break;

			default:
			{
				// just show an error message
				show_command_error(formatcommand);
			}
			break;
		}
	}

	htmlwindow.focus();
}

// #############################################################################
// function to apply text color to selected text
function set_color(colorname)
{
	do_format("forecolor", false, colorname);
	fetch_object("colorbar").style.background = colorname;
}

// #############################################################################
// function to wrap tags around selected text
function wrap_tags(tagname, openbrace, closebrace, useoption)
{
	// string to represet single regex tag wrapping
	var wraptagstring = " %2$s%1$s%3$s%4$s%2$s/%1$s%3$s ";

	htmlwindow.focus();

	// get rid of formatting in certain tags
	switch (tagname.toLowerCase())
	{
		case "code":
		case "html":
		case "php":
		{
			htmlbox.execCommand("removeformat", false, null);
		}
		break;
	}

	if (useoption == 1)
	{
		var thingy = prompt(construct_phrase(vbphrase["enter_tag_option"], (openbrace + tagname + closebrace)), "");
		if (thingy == "" || thingy == null)
		{
			return;
		}
		else
		{
			wraptagstring = " %2$s%1$s=\"" + thingy + "\"%3$s%4$s%2$s/%1$s%3$s ";
		}
	}

	if (is_ie)
	{
		textselection = htmlbox.selection.createRange();
		// get rid of surrounding <p> tags if there are any
		txt = textselection.htmlText.replace(/<p([^>]*)>(.*)<\/p>/i, '$2');
		// now paste html using the parsed text from above
		textselection.pasteHTML(construct_phrase(wraptagstring, tagname, openbrace, closebrace, txt));
	}
	else
	{
		var frag = htmlbox.createDocumentFragment();
		var span = htmlbox.createElement("span");
		span.innerHTML = construct_phrase(wraptagstring, tagname, openbrace, closebrace, moz_get_html());

		while (span.firstChild)
		{
			frag.appendChild(span.firstChild);
		}

		moz_insert_node_at_selection(frag);
	}

	htmlwindow.focus();
}

// #############################################################################
// function to intercept events fired by the 'colorbar' element
function colorbar_intercept(e)
{
	e = do_an_e(e);
	button_context(fetch_object("cmd_forecolor"), e.type);
}

// #############################################################################
// function to set forecolor to 'colorbar' background, fired by color button
function set_instant_color(e)
{
	e = do_an_e(e);
	do_format("forecolor", false, fetch_object("colorbar").style.backgroundColor);
}

// #############################################################################
// function to insert a smilie part 1
function prepare_insert_smilie(e)
{
	e = do_an_e(e);
	insert_smilie(this, this.id.substr(9));
}

// #############################################################################
// function to insert a smilie part 2
function insert_smilie(elm, smilieid)
{
	// only naughty one is "Control"
	if (typeof(document.selection) != "undefined" && document.selection.type != "Text" && document.selection.type != "None")
	{
		document.selection.clear();
	}

	htmlwindow.focus();

	try
	{
		if (is_ie)
		{
			smilieHTML = '<img src="' + elm.getElementsByTagName("img")[0].src + '" border="0" alt="" smilieid="' + smilieid + '" /> ';
			htmlbox.selection.createRange().pasteHTML(smilieHTML);
		}
		else
		{
			htmlbox.execCommand('InsertImage', false, elm.getElementsByTagName("img")[0].src);
			var smilies = htmlbox.getElementsByTagName("img");
			for (var i = 0; i < smilies.length; i++)
			{
				if (smilies[i].src == elm.getElementsByTagName("img")[0].src)
				{
					if (smilies[i].getAttribute("smilieid") < 1)
					{
						smilies[i].setAttribute("smilieid", smilieid);
						smilies[i].setAttribute("border", "0");
					}
				}
			}
		}
	}
	catch(e)
	{
		// failed... probably due to inserting a smilie over a smilie in mozilla
	}
}

// #############################################################################
// function to get the absolute top position of a node relative to the window
function getOffsetTop(elm)
{
	var mOffsetParent = elm.offsetParent;
	var mOffsetTop = elm.offsetTop;

	while(mOffsetParent)
	{
		mOffsetTop += mOffsetParent.offsetTop;
		mOffsetParent = mOffsetParent.offsetParent;
	}

	return mOffsetTop + (is_ie ? (parseInt(fetch_object("controlbar").currentStyle.borderLeftWidth) + 2) : 0);
}

// #############################################################################
// function to get the absolute left position of a node relative to the window
function getOffsetLeft(elm)
{
	var mOffsetLeft = elm.offsetLeft;
	var mOffsetParent = elm.offsetParent;

	while(mOffsetParent)
	{
		mOffsetLeft += mOffsetParent.offsetLeft;
		mOffsetParent = mOffsetParent.offsetParent;
	}

	return mOffsetLeft + (is_ie ? (parseInt(fetch_object("controlbar").currentStyle.borderLeftWidth) + parseInt(fetch_object("controlbar").currentStyle.paddingLeft)) : 0);
}

// #############################################################################
function show_command_error(message)
{
	alert(message + ":\n" + vbphrase["wysiwyg_command_invalid"]);
}

// #############################################################################
function edit_enabled()
{
	if (is_ie)
	{
		return htmlwindow.contentEditable ? true : false;
	}
	else
	{
		return htmlbox.designMode == "on" ? true : false;
	}
}

// #############################################################################
function show_wysiwyg_version()
{
	alert("This editor is running with vbulletin_wysiwyg.js, CVS version " + String("$Revision: 14530 $").replace(/^\$(Revision): ([0-9\.]+) \$$/, '$2'));
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14530 $
|| ####################################################################
\*======================================================================*/
