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

/*	Definitions for the colors for the editor popup (based on colours from MS Word)
	you may edit these colors if you wish, but there MUST be exactly 40 colors
	defined. If the color you wish to use does not have an HTML name, you
	must enter the hexadecimal version on both sides of the colon, for example:
	"#FFFF00" : "Yellow",
	"#FEFEFE" : "#FEFEFE",
*/
var coloroptions = new Array();
coloroptions = {
	"#000000" : "Black",
	"#A0522D" : "Sienna",
	"#556B2F" : "DarkOliveGreen",
	"#006400" : "DarkGreen",
	"#483D8B" : "DarkSlateBlue",
	"#000080" : "Navy",
	"#4B0082" : "Indigo",
	"#2F4F4F" : "DarkSlateGray",
	"#8B0000" : "DarkRed",
	"#FF8C00" : "DarkOrange",
	"#808000" : "Olive",
	"#008000" : "Green",
	"#008080" : "Teal",
	"#0000FF" : "Blue",
	"#708090" : "SlateGray",
	"#696969" : "DimGray",
	"#FF0000" : "Red",
	"#F4A460" : "SandyBrown",
	"#9ACD32" : "YellowGreen",
	"#2E8B57" : "SeaGreen",
	"#48D1CC" : "MediumTurquoise",
	"#4169E1" : "RoyalBlue",
	"#800080" : "Purple",
	"#808080" : "Gray",
	"#FF00FF" : "Magenta",
	"#FFA500" : "Orange",
	"#FFFF00" : "Yellow",
	"#00FF00" : "Lime",
	"#00FFFF" : "Cyan",
	"#00BFFF" : "DeepSkyBlue",
	"#9932CC" : "DarkOrchid",
	"#C0C0C0" : "Silver",
	"#FFC0CB" : "Pink",
	"#F5DEB3" : "Wheat",
	"#FFFACD" : "LemonChiffon",
	"#98FB98" : "PaleGreen",
	"#AFEEEE" : "PaleTurquoise",
	"#ADD8E6" : "LightBlue",
	"#DDA0DD" : "Plum",
	"#FFFFFF" : "White"
};

// variable to prevent init being called twice
var editor_loaded = false;

// initialize some arrays
var popupcontrols = new Array();
var colorindex = new Array();
var fontoptions = new Array();
var sizeoptions = new Array();
var buttonstatus = new Array();

// default values for URL / Image prompts
var imgurl = "http://";
var linkurl = "http://";
var is_v4 = false;
if ((navigator.appVersion.indexOf("MSIE 4.") != "-1" && is_ie) || (parseInt(navigator.appVersion) == 4 && is_ns))
{ // the v4 browsers dont support try / catch
	is_v4 = true;
}

// #############################################################################
// function to change the appearence of a control element
function format_control(elm, elmtype, controlstate)
{
	// if we do not *need* to change the control state, then don't
	if (typeof(elm.controlstate) != "undefined" && controlstate == elm.controlstate)
	{
		return;
	}

	// construct the name of the appropriate array key from the istyles array
	var istyle = "pi_" + elmtype + "_" + controlstate;

	// set element background, color, padding and border
	elm.style.background = istyles[istyle][0];
	elm.style.color = istyles[istyle][1];
	if (elmtype != "menu")
	{
		elm.style.padding = istyles[istyle][2];
	}
	elm.style.border = istyles[istyle][3];

	// set the element controlstate variable
	elm.controlstate = controlstate;

	// handle some special cases for popup elements
	if (typeof(elm.cmd) != "undefined" && in_array(elm.cmd, popupcontrols) != -1)
	{
		var tds = elm.getElementsByTagName("td");
		for (var i = 0; i < tds.length; i++)
		{
			switch (tds[i].className)
			{
				// set the right-border for popup_feedback class elements
				case "popup_feedback":
				{
					tds[i].style.borderRight = iif(controlstate == "normal", istyles["pi_menu_normal"][3], istyles[istyle][3]);
				}
				break;

				// set the border colour for popup_pickbutton class elements
				case "popup_pickbutton":
				{
					tds[i].style.borderColor = iif(controlstate == "normal", istyles["pi_menu_normal"][0], istyles[istyle][0]);
				}
				break;

				// set the left-padding and left-border for alt_pickbutton elements
				case "alt_pickbutton":
				{
					if (buttonstatus[elm.cmd])
					{
						tds[i].style.paddingLeft = istyles["pi_button_normal"][2];
						tds[i].style.borderLeft = istyles["pi_button_normal"][3];
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

// #############################################################################
// function to set 'unselectable' for an element and all its child nodes
function set_unselectable(elm)
{
	if (is_ie4)
	{
		return;
	}
	else if (typeof(elm.tagName) != "undefined")
	{
		if (elm.hasChildNodes())
		{
			for (var i = 0; i < elm.childNodes.length; i++)
			{
				set_unselectable(elm.childNodes[i]);
			}
		}
		elm.unselectable = true;
	}
}

// #############################################################################
function build_fontoptions(is_wysiwyg)
{
	if (is_wysiwyg)
	{
		for (key in fontoptions)
		{
			document.writeln('<tr><td class="ofont">' + fontoptions[key] + '</td></tr>');
		}
	}
	else
	{
		for (key in fontoptions)
		{
			document.writeln('<option value="' + fontoptions[key] + '">' + fontoptions[key] + '</option>');
		}
	}
}

// #############################################################################
function build_sizeoptions(is_wysiwyg)
{
	if (is_wysiwyg)
	{
		for (key in sizeoptions)
		{
			document.writeln('<tr><td class="osize"><font size="' + sizeoptions[key] + '">' + sizeoptions[key] + '</font></td></tr>');
		}
	}
	else
	{
		for (key in sizeoptions)
		{
			document.writeln('<option value="' + sizeoptions[key] + '">' + sizeoptions[key] + '</option>');
		}
	}
}

// #############################################################################
function build_coloroptions(is_wysiwyg)
{
	if (is_wysiwyg)
	{
		for (var y = 0; y < 5; y++)
		{
			document.write('<tr align="center">');
			for (var x = 0; x < 8; x++)
			{
				document.write('<td class="ocolor"><div></div></td>');
			}
			document.write('</tr>');
		}
	}
	else
	{
		for (key in coloroptions)
		{
			document.writeln('<option value="' + coloroptions[key] + '" style="background-color:' + coloroptions[key] + ';">' + coloroptions[key].replace(/([a-z]{1})([A-Z]{1})/g, "$1 $2") + '</option>');
		}
	}
}

// #############################################################################
function set_default_text(textvalue, is_wysiwyg, non_wysiwyg_obj)
{
	if (is_wysiwyg)
	{
		if (is_ie)
		{
			if (textvalue == "")
			{
				textvalue = "<p style=\"margin:0px\"></p>";
			}
			fetch_object("htmlbox").innerHTML = textvalue;
			fetch_object("htmlbox").className = "wysiwyg";
		}
		else
		{
			var htb = fetch_object("htmlbox").contentWindow.document;
			htb.open();
			htb.write("<html><head><title>Mozilla WYSIWYG</title></head><body>" + textvalue + "</body></html>");
			htb.close();
			htb.body.style.cursor = "text";

			var bgstyle = fetch_mozilla_css_class(".wysiwyg");
			if (bgstyle != false)
			{
				// got bgstyle
			}
			else
			{
				// just set up a default style
				bgstyle = {
					"backgroundColor" : "white",
					"color"           : "black",
					"fontFamily"      : "verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif",
					"fontSize"        : "10pt"
				};
			}

			htb.body.style.backgroundColor = bgstyle.backgroundColor;
			htb.body.style.color = bgstyle.color;
			htb.body.style.fontFamily = bgstyle.fontFamily;
			htb.body.style.fontSize = bgstyle.fontSize;
		}
	}
	else
	{
		non_wysiwyg_obj.value = textvalue;
	}
}

// #############################################################################
function fetch_mozilla_css_class(selector)
{
	for (var s = 0; s < document.styleSheets.length; s++)
	{
		for (var r = 0; r < document.styleSheets[s].cssRules.length; r++)
		{
			if (document.styleSheets[s].cssRules[r].selectorText == selector)
			{
				return document.styleSheets[s].cssRules[r].style;
			}
		}
	}

	return false;
}

// #############################################################################
function open_smilie_window(x_width, y_width, wysiwyg, forumid)
{
	if (typeof(forumid) == "undefined")
	{
		forumid = 0;
	}
	window.open("misc.php?" + SESSIONURL + "do=getsmilies&wysiwyg=" + wysiwyg + "&forumid=" + forumid, "smilies", "statusbar=no,menubar=no,toolbar=no,scrollbars=yes,resizable=yes,width=" + x_width + ",height=" + y_width);
}

// #############################################################################
function alter_box_height(boxid, pixelvalue)
{
	var box = fetch_object(boxid);
	var boxheight = parseInt(box.style.height);
	var newheight = boxheight + pixelvalue;
	if (newheight > 0)
	{
		box.style.height = newheight + "px";
	}
	return false;
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 10971 $
|| ####################################################################
\*======================================================================*/