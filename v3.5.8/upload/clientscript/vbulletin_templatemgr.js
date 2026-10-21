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

// set up some variables for the search functions
var win = window;
var n = 0;

// ***************************************************************************************
// functions for use on the template listing page
// ***************************************************************************************

// #############################################################################
// function to jump to various style-related pages
function Sdo(phpdo, styleid)
{
	// get the variables we need
	phpdo = phpdo.split("_");

	if (phpdo[0] == "template")
	{
		switch (phpdo[1])
		{
			case "templates": // expand templates list
			{
				goURL = "modify&expandset=";
			}
			break;

			case "addtemplate": // add template
			{
				goURL = "add&dostyleid=";
			}
			break;

			case "editstyle": // edit style
			{
				goURL = "editstyle&dostyleid=";
			}
			break;

			case "addstyle": // add child style
			{
				goURL = "addstyle&parentid=";
			}
			break;

			case "delete": // delete style
			{
				goURL = "deletestyle&dostyleid=";
			}
			break;

			case "download": // go to style file manager
			{
				goURL = "files&dostyleid=";
			}
			break;

			case "revertall": // revert all templates
			{
				goURL = "revertall&dostyleid=";
			}
			break;
		}
		if (goURL)
		{
			window.location = "template.php?s=" + SESSIONHASH + "&group=" + document.forms.tform.group.value + "&do=" + goURL + styleid;
		}
	}
	else if (phpdo[0] == "css")
	{
		window.location = "css.php?s=" + SESSIONHASH + "&do=edit&dowhat=" + phpdo[1] + "&group=" + document.forms.tform.group.value + "&dostyleid=" + styleid;
	}
}

// #############################################################################
// function to jump to a url within template.php
function Tjump(gourl)
{
	var gotourl = "template.php?s=" + SESSIONHASH + "&do=" + gourl + "&searchstring=" + SEARCHSTRING + "&expandset=" + EXPANDSET;

	if (is_ie && event.shiftKey)
	{
		window.open(gotourl)
	}
	else
	{
		window.location = gotourl;
	}
}

// #############################################################################
// function to expand a template group
function Texpand(dogroup,doexpandset)
{
	window.location="template.php?s=" + SESSIONHASH + "&do=modify&expandset=" + doexpandset + "&group=" + dogroup + "#" + dogroup;
}

// ***************************************************************************************
// these functions are used only by the STANDARD editor (not IE)
// ***************************************************************************************

// #############################################################################
// function to edit a template
function Tedit(templateid)
{
	Tjump("edit&templateid=" + templateid + "&dostyleid=" + EXPANDSET + "&group=" + GROUP);
}

// #############################################################################
// function to customize a default template
function Tcustom1(styleid,title)
{
	Tjump("add&dostyleid=" + styleid + "&title=" + title + "&group=" + GROUP);
}

// #############################################################################
// function to further customize a customized template
function Tcustom2(styleid,templateid)
{
	Tjump("add&dostyleid=" + styleid + "&templateid=" + templateid + "&group=" + GROUP);
}

// #############################################################################
// function to delete a template
function Tdelete(styleid,templateid)
{
	Tjump("delete&dostyleid=" + styleid + "&templateid=" + templateid + "&group=" + GROUP);
}

// #############################################################################
// function to view an original template
function Toriginal(title)
{
	window.open("template.php?s=" + SESSIONHASH + "&do=view&title=" + title);
}

// ***************************************************************************************
// these functions are used by the ENHANCED editor (IE only)
// ***************************************************************************************

// #############################################################################
// function to display help info etc in the FORMTYPE enhanced editor
function Tprep(elm, styleid, echo)
{
	// get string value
	str = elm.value;

	if (echo)
	{
		button = new Array();
		button['edit'] = eval("document.forms.tform.edit" + styleid);
		button['edit'].disabled = "disabled";
		button['cust'] = eval("document.forms.tform.cust" + styleid);
		button['cust'].disabled = "disabled";
		button['kill'] = eval("document.forms.tform.kill" + styleid);
		button['kill'].disabled = "disabled";
		button['expa'] = eval("document.forms.tform.expa" + styleid);
		button['expa'].disabled = "disabled";
		button['orig'] = eval("document.forms.tform.orig" + styleid);
		button['orig'].disabled = "disabled";
		textbox = document.getElementById('helparea' + styleid);
	}

	if (str != '')
	{
		selitem = eval("document.forms.tform.tl" + styleid);
		out = new Array();
		out['selectedtext'] = selitem.options[selitem.selectedIndex].text.replace(/^-- /,'');
		if (str == "~")
		{
			str = out['selectedtext'];
		}
		out['styleid'] = styleid;
		out['original'] = str;
		if (str.search(/^\[(\w*)\]$/) != -1)
		{
			out['value'] = str.replace(/^\[(\w*)\]$/,'$1');
			if (isNaN(out['value']) || out['value']=="")
			{
				out['action'] = "expand";
				//out['text'] = "Click the 'Expand/Collapse' button or double-click the group name to expand or collapse the " + out['selectedtext'].replace(/ Templates/,'').bold() + " group of templates.";
				out['text'] = construct_phrase(vbphrase['click_the_expand_collapse_button'], out['selectedtext'].replace(/Templates/, '').bold());
				button['expa'].disabled = "";
			}
			else
			{
				out['action'] = "editinherited";
				selecteditem = eval('document.forms.tform.tl'+styleid);
				tsid = selecteditem.options[selecteditem.selectedIndex].getAttribute('tsid');
				out['text'] = construct_phrase(vbphrase['this_template_has_been_customized_in_a_parent_style'], STYLETITLE[tsid].bold(), STYLETITLE[styleid].bold(), out['selectedtext'].bold(), "template.php?s=" + SESSIONHASH + "&amp;do=edit&amp;templateid=" + out['value'] + "&amp;group=" + GROUP);
				button['orig'].disabled = "";
				button['cust'].disabled = "";
			}
		}
		else
		{
			out['value'] = str;
			if (isNaN(out['value']))
			{
				out['action'] = "customize";
				out['text'] = vbphrase['this_template_has_not_been_customized'];
				button['cust'].disabled = "";
			}
			else
			{
				out['action'] = "edit";
				out['text'] = vbphrase['this_template_has_been_customized_in_this_style'];
				button['edit'].disabled = "";
				button['orig'].disabled = "";
				button['kill'].disabled = "";
			}
		}
		if (echo)
		{
			textbox.innerHTML = out['selectedtext'].bold() + ":<br /><br />" + out['text'];
			if (elm.getAttribute('i'))
			{
				var editinfo = elm.getAttribute('i').split(";");
				editinfo[1] = new Date(editinfo[1] * 1000);
				day = editinfo[1].getDate();
				month = editinfo[1].getMonth();
				year = editinfo[1].getFullYear();
				hours = editinfo[1].getHours();
				if (hours < 10)
				{
					hours = '0' + hours;
				}
				mins = editinfo[1].getMinutes();
				if (mins < 10)
				{
					mins = '0' + mins;
				}
				textbox.innerHTML += construct_phrase("<br /><br />" + vbphrase['template_last_edited_js'], MONTH[month], day, year, hours, mins, editinfo[0].bold());
			}
		}
		else
		{
			return out;
		}
	}
	else
	{
		textbox.innerHTML = construct_phrase("<center>" + vbphrase['x_templates'] + "</center>", STYLETITLE[styleid].bold());
	}
}

// #############################################################################
// function to jump to the correct template.php page
function Tdo(arry,request)
{
	switch(arry['action'])
	{
		case "expand":
			Tjump("modify&expandset=" + EXPANDSET + "&group=" + arry['value']);
			break;
		case "customize":
			Tjump("add&dostyleid=" + arry['styleid'] + "&title=" + arry['value'] + "&group=" + GROUP);
			break;
		case "edit":
			switch(request)
			{
				case "vieworiginal":
					window.open("template.php?s=" + SESSIONHASH + "&do=view&title=" + out['selectedtext']);
					break;
				case "killtemplate":
					Tjump("delete&templateid=" + arry['value'] + "&dostyleid=" + arry['styleid'] + "&group=" + GROUP);
					break;
				default:
					Tjump("edit&templateid=" + arry['value'] + "&group=" + GROUP);
					break;
			}
			break;
		case "editinherited":
			if (request == "vieworiginal")
			{
				window.open("template.php?s=" + SESSIONHASH + "&do=view&title=" + out['selectedtext']);
			}
			else
			{
				Tjump("add&dostyleid=" + arry['styleid'] + "&templateid=" + arry['value'] + "&group=" + GROUP);
			}
			break;
	}
}

// ***************************************************************************************
// functions for manipulating template text - formerly in PHP function print_template_javascript()
// ***************************************************************************************

// #############################################################################
// function to do a preview of a template in a new window
var popup = '';
function displayHTML()
{
	var inf = document.cpform.template.value;

	if (popup && !popup.closed)
	{
		popup.document.close();
	}
	else
	{
		popup = window.open(", ", 'popup', 'toolbar = no, status = no, scrollbars=yes');
	}
	popup.document.open();
	popup.document.write('' + inf + '');
}

// #############################################################################
// function to copy text into the clipboard
function HighlightAll()
{
	var tempval = eval('document.cpform.template')
	tempval.focus();
	tempval.select();
	if (document.all)
	{
		therange = tempval.createTextRange();
		therange.execCommand('Copy');
		setTimeout("window.status=''",1800)
	}
}

// #############################################################################
// function to find text on a page
var startpos = 0;
function findInPage(str)
{
	var txt, i, found;
	if (str == '')
	{
		return false;
	}
	if (is_moz)
	{
		txt = fetch_object(textarea_id).value;
		if (!startpos || startpos + str.length >= txt.length)
		{
			startpos = 0;
		}
		var x = 0;
		var matchfound = false;
		for (i = startpos; i < txt.length; i++)
		{
			if (txt.charAt(i) == str.charAt(x))
			{
				x++;
			}
			else
			{
				x = 0;
			}
			if (x == str.length)
			{
				i++;
				startpos = i;
				fetch_object(textarea_id).focus();
				fetch_object(textarea_id).setSelectionRange(i - str.length, i);
				// really dirty nasty thing, hide from Kier
				moz_txtarea_scroll(fetch_object(textarea_id), i);
				matchfound = true;
				break;
			}
			if (i == txt.length - 1 && startpos > 0)
			{ // argh at end
				i = 0;
				startpos = 0;
			}
		}
		if (!matchfound)
		{
			alert(vbphrase['not_found']);
		}
	}
	if (is_ie)
	{
		txt = win.fetch_object(textarea_id).createTextRange();
		for (i = 0; i <= n && (found = txt.findText(str)) != false; i++)
		{
			txt.moveStart('character', 1);
			txt.moveEnd('textedit');
		}
		if (found)
		{
			txt.moveStart('character', -1);
			txt.findText(str);
			txt.select();
			txt.scrollIntoView(true);
			n++;
		}
		else
		{
			if (n > 0)
			{
				n = 0;
				findInPage(str);
			}
			else { alert(vbphrase['not_found']); }
		}
	}
	return false;
}

// well the lame we're going to do here is create a textarea dynamically
// once we've done that we can just substring it to the length of the where the match is
// from there we can just grab the actual height of the new textarea and whats where the offset should be
function moz_txtarea_scroll(input, txtpos)
{
	var newarea = input.cloneNode(true);
	newarea.setAttribute('id', 'moo');
	newarea.value = input.value.substr(0, txtpos);
	document.body.appendChild(newarea);
	if (newarea.scrollHeight <= input.scrollTop || newarea.scrollHeight >= input.scrollTop + input.offsetHeight)
	{
		if (newarea.scrollHeight == newarea.clientHeight)
		{
			input.scrollTop = 0;
		}
		else
		{
			input.scrollTop = newarea.scrollHeight - 40;
		}
	}
	document.body.removeChild(document.getElementById('moo'));
}

// #############################################################################
// function to change the text-wrap style of an element
function set_wordwrap(idname, yesno)
{
	element = fetch_object(idname);

	if (yesno)
	{
		element.wrap = "soft";
	}
	else
	{
		element.wrap = "off";
	}
}

// #############################################################################
// function to check/uncheck userselectability boxes for child styles
function check_children(styleid, value)
{
	// check this box
	fetch_object("userselect_" + styleid).checked = value;
	//alert(STYLETITLE[styleid]);

	// check check children
	for (i in STYLEPARENTS)
	{
		if (STYLEPARENTS[i] == styleid)
		{
			check_children(i, value);
		}
	}

	return false;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 13370 $
|| ####################################################################
\*======================================================================*/