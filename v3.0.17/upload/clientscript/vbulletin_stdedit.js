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

// array which holds the currently opened tags
var tags = new Array();

// currently selected text
var text = "";

// text which is to be added to the post
var AddTxt = "";

// form object that has to be used
var theform = "";

// #############################################################################
// ############################ START FUNCTIONS ################################
// #############################################################################

// #############################################################################
function validatePost(tform, subjectVal, minLength, maxLength)
{
	return validatemessage(tform.message.value, subjectVal, minLength, maxLength, false, tform);
}

// #############################################################################
function editInit()
{
	vbcode_init();
}

// #############################################################################
// function to initialize the editor
function vbcode_init()
{
	if (editor_loaded)
	{
		// editor is already loaded, don't try to load it again, as that would be bad m'kay.
		return;
	}

	if (!is_ie4 && !is_ns4)
	{
		// make all toolbar elements unselectable
		if (fetch_object("controlbar"))
		{
			set_unselectable(fetch_object("controlbar"));
		}
		if (fetch_object("smilieboxx"))
		{
			set_unselectable(fetch_object("smiliebox"));
		}

		// init buttons
		if (fetch_object("controlbar"))
		{
			var divs = fetch_object("controlbar").getElementsByTagName("div");
			for (var i  = 0; i < divs.length; i++)
			{
				var elm = divs[i];
				switch (elm.className)
				{
					case "imagebutton":
					{
						elm.onmouseover = elm.onmouseout = elm.onmouseup = elm.onmousedown = button_eventhandler;
					}
					break;
				}
			}
		}
	}

	theform = document.forms.vbform;
}

// #############################################################################
// function to handle incoming events from buttons (part 1)
function button_eventhandler(e, elm)
{
	if (is_v4)
	{ //its ie4 and it doens't support try / catch or even changing style of a button
		//return false;
	}

	e = do_an_e(e);

	switch (e.type)
	{
		case "mousedown":
		{
			format_control(elm ? elm : this, "button", "down");
		}
		break;

		case "mouseover":
		case "mouseup":
		{
			format_control(elm ? elm : this, "button", "hover");
		}
		break;

		default:
		{
			format_control(elm ? elm : this, "button", "normal");
		}
	}
}

// #############################################################################
// function used to check that the value in the array we're using is valid
function thearrayisgood(thearray, i)
{
	if (typeof(thearray[i]) == "undefined" || (thearray[i] == "") || (thearray[i] == null))
	{
		return false;
	}
	else
	{
		return true;
	}
}

// #############################################################################
// emulation of PHP's sizeof function
function sizeof(thearray)
{
	for (var i = 0; i < thearray.length; i++)
	{
		if (!thearrayisgood(thearray, i))
		{
			return i;
		}
	}
	return thearray.length;
}

// #############################################################################
// emulation of PHP's array_push function
function array_push(thearray, value)
{
	var thearraysize = sizeof(thearray);
	thearray[thearraysize] = value;
	return thearray[thearraysize];
}

// #############################################################################
// emulation of PHP's array_pop function
function array_pop(thearray)
{
	var thearraysize = sizeof(thearray);
	var retval = thearray[thearraysize - 1];
	delete thearray[thearraysize - 1];
	return retval;
}

// #############################################################################
function setmode(modevalue)
{
	closeall(theform);
	if (modevalue == 1)
	{
		normalmode = false;
	}
	else
	{
		normalmode = true;
	}
	document.cookie = "vbcodemode=" + modevalue + "; path=/; expires=Wed, 1 Jan 2020 00:00:00 GMT;";
}

// #############################################################################
function getActiveText()
{
	setfocus();
	if ((is_ie && !document.selection) || !document.selection)
	{
		return false;
	}

	var sel = document.selection;
	var rng = sel.createRange();

	if (rng != null && (sel.type == "Text" || sel.type == "None"))
	{
		text = rng.text;
	}
	if (rng != null && theform.message.createTextRange)
	{
		theform.message.caretPos = rng.duplicate();
	}
	return true;
}

// #############################################################################
function AddText(NewCode)
{
	if (typeof(theform.message.createTextRange) != "undefined" && theform.message.caretPos)
	{
		var caretPos = theform.message.caretPos;
		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? NewCode + ' ' : NewCode;
		caretPos.select();
	}
	else if (theform.message.selectionStart || theform.message.selectionStart == '0')
	{ // its mozilla and we'll need to re-write entire text
		var start_selection = theform.message.selectionStart;
		var end_selection = theform.message.selectionEnd;

		// fetch everything from start of text area to selection start
		var start = (theform.message.value).substring(0, start_selection);
		// fetch everything from start of selection to end of selection
		var middle = NewCode;
		// fetch everything from end of selection to end of text area
		var end = (theform.message.value).substring(end_selection, theform.message.textLength);

		theform.message.value = start + middle + end;
		setfocus();
		theform.message.selectionStart = end_selection + middle.length;
		theform.message.selectionEnd = start_selection + middle.length;
		getActiveText();
		AddTxt = "";
		return;
	}
	else
	{
		theform.message.value += NewCode;
	}
	setfocus();
	getActiveText();
	AddTxt = "";
}

// #############################################################################
function setfocus()
{
	theform.message.focus();
}

// #############################################################################
function vbcode(vbcode, prompttext, displayoption)
{
	if (typeof theform != "object" && typeof must_click_message != "undefined")
	{ // QR check
		alert(must_click_message);
		return false;
	}

	var optioncompiled = "";
	if (displayoption)
	{
		optionvalue = prompt(construct_phrase(vbphrase["enter_option_x_tag"], vbcode), "");
		if (optionvalue != null)
		{
			optioncompiled = "=\"" + optionvalue + "\"";
		}
	}

	// lets call this when they try and use vbcode rather than on change
	getActiveText();

	if (text)
	{ // its IE to the rescue
		if (text.substring(0, vbcode.length + 2 ) == "[" + vbcode + "]" && text.substring(text.length - vbcode.length - 3, text.length) == "[/" + vbcode + "]")
		{
			AddTxt = text.substring(vbcode.length + 2, text.length - vbcode.length - 3);
		}
		else
		{
			AddTxt = "[" + vbcode + optioncompiled + "]" + text + "[/" + vbcode + "]";
		}
		AddText(AddTxt);
	}
	else if ((theform.message.selectionStart || theform.message.selectionStart == '0') && theform.message.selectionStart != theform.message.selectionEnd)
	{ // its mozilla and we'll need to re-write entire text
		var start_selection = theform.message.selectionStart;
		var end_selection = theform.message.selectionEnd;

		// fetch everything from start of text area to selection start
		var start = (theform.message.value).substring(0, start_selection);
		// fetch everything from start of selection to end of selection
		var middle = (theform.message.value).substring(start_selection, end_selection);
		// fetch everything from end of selection to end of text area
		var end = (theform.message.value).substring(end_selection, theform.message.textLength);

		if (middle.substring(0, vbcode.length + 2 ) == "[" + vbcode + "]" && middle.substring(middle.length - vbcode.length - 3, middle.length) == "[/" + vbcode + "]")
		{
			middle = middle.substring(vbcode.length + 2, middle.length - vbcode.length - 3);
		}
		else
		{
			middle = "[" + vbcode + optioncompiled + "]" + middle + "[/" + vbcode + "]";
		}

		theform.message.value = start + middle + end;
		setfocus();
		theform.message.selectionStart = end_selection + middle.length;
		theform.message.selectionEnd = start_selection + middle.length;
		return false;
	}
	else
	{

		if (!normalmode)
		{
			var donotinsert = false;
			var thetag = 0;
			for (var i = 0; i < tags.length; i++)
			{
				if (typeof(tags[i]) != "undefined" && tags[i] == vbcode)
				{
					donotinsert = true;
					thetag = i;
				}
			}

			if (!donotinsert)
			{
				array_push(tags, vbcode);
				AddTxt = "[" + vbcode + optioncompiled + "]";
				AddText(AddTxt);
			}
			else
			{ // its already open
				var closedtag = "";
				while (typeof(tags[thetag]) != "undefined")
				{
					closedtag = array_pop(tags);
					AddTxt = "[/" + closedtag + "]";
					AddText(AddTxt);
				}
			}
		}
		else
		{
			var inserttext = prompt(vbphrase["enter_text_to_be_formatted"] + "\n[" + vbcode + "]xxx[/" + vbcode + "]", "");
			if ((inserttext != null) && (inserttext != ""))
			{
				AddTxt = "[" + vbcode + optioncompiled + "]" + inserttext + "[/" + vbcode + "] ";
			}
			AddText(AddTxt);
		}
	}
	setfocus();
	return false;
}

// #############################################################################
function closetag()
{
	getActiveText();
	if (!normalmode)
	{
		if (typeof(tags[0]) != "undefined")
		{
			var Tag = array_pop(tags)
			AddTxt = "[/"+ Tag +"]";
			AddText(AddTxt);
		}
	}
	setfocus();
}

// #############################################################################
function closeall()
{
	getActiveText();
	if (!normalmode)
	{
		var g = sizeof(tags);
		if (thearrayisgood(tags, g-1))
		{
			var Addtxt = "";
			var newtag = "";
			for (var h = 0; h < g; h++)
			{
				newtag = array_pop(tags);
				Addtxt += "[/" + newtag + "]";
			}
			AddText(Addtxt);
		}
	}
	setfocus();
}

// #############################################################################
function fontformat(thevalue, thetype)
{
	getActiveText();

	if (text)
	{ // its IE to the rescue
		AddTxt = "[" + thetype + "=" + thevalue + "]" + text + "[/" + thetype + "]";
		AddText(AddTxt);
	}
	else if (theform.message.selectionEnd && (theform.message.selectionEnd - theform.message.selectionStart > 0))
	{ // its mozilla and we'll need to re-write entire text
		var start_selection = theform.message.selectionStart;
		var end_selection = theform.message.selectionEnd;
		if (end_selection <= 2)
		{
			end_selection = theform.message.textLength;
		}

		// fetch everything from start of text area to selection start
		var start = (theform.message.value).substring(0, start_selection);
		// fetch everything from start of selection to end of selection
		var middle = (theform.message.value).substring(start_selection, end_selection);
		// fetch everything from end of selection to end of text area
		var end = (theform.message.value).substring(end_selection, theform.message.textLength);

		middle = "[" + thetype + "=" + thevalue + "]" + middle + "[/" + thetype + "]";

		theform.message.value = start + middle + end;
		theform.message.selectionStart = end_selection + middle.length;
		theform.message.selectionEnd = start_selection + middle.length;
	}
	else
	{
		if (!normalmode)
		{
			var donotinsert = false;
			var thetag = 0;
			for (var i = 0; i < tags.length; i++)
			{
				if (typeof(tags[i]) != "undefined" && tags[i] == thetype)
				{
					donotinsert = true;
					thetag = i;
				}
			}

			if (!donotinsert)
			{
				array_push(tags, thetype);
				AddTxt = "[" + thetype + "=" + thevalue + "]";
				AddText(AddTxt);
			}
			else
			{ // its already open
				var closedtag = "";
				while (tags[thetag])
				{
					closedtag = array_pop(tags);
					AddTxt = "[/" + closedtag + "]";
					AddText(AddTxt);
				}
			}
		}
		else
		{
			var inserttext = prompt(vbphrase["enter_text_to_be_formatted"] + "\n[" + thetype + "=" + thevalue + "]xxx[/" + thetype + "]", "");
			if ((inserttext != null) && (inserttext != ""))
			{
				AddTxt = "[" + thetype + "=" + thevalue + "]" + inserttext + "[/" + thetype + "]";
				AddText(AddTxt);
			}
		}
	}

	if (typeof(theform.sizeselect) != "undefined")
	{
		theform.sizeselect.selectedIndex = 0;
	}
	if (typeof(theform.fontselect) != "undefined")
	{
		theform.fontselect.selectedIndex = 0;
	}
	if (typeof(theform.colorselect) != "undefined")
	{
		theform.colorselect.selectedIndex = 0;
	}
	setfocus();
	return false;
}

// #############################################################################
function namedlink(thetype)
{
	var extraspace = "";

	getActiveText();
	var dtext = "";
	if (text)
	{
		dtext = text;
	}
	else
	{
		extraspace = " ";
	}
	linktext = prompt(vbphrase["enter_link_text"], dtext);
	var prompttext, prompt_contents;
	if (thetype == "URL")
	{
		prompt_text = vbphrase["enter_link_url"];
		prompt_contents = "http://";
	}
	else
	{
		prompt_text = vbphrase["enter_email_link"];
		prompt_contents = "";
	}
	var linkurl = prompt(prompt_text, prompt_contents);
	if ((linkurl != null) && (linkurl != ""))
	{
		if ((linktext != null) && (linktext != ""))
		{
			AddTxt = "[" + thetype + "=" + linkurl + "]" + linktext + "[/" + thetype + "]" + extraspace;
			AddText(AddTxt);
		}
		else
		{
			AddTxt = "[" + thetype + "]" + linkurl + "[/" + thetype + "]" + extraspace;
			AddText(AddTxt);
		}
	}
}

// #############################################################################
function dolist()
{
	var listtype = prompt(vbphrase["enter_list_type"], "");

	if ((listtype == "a") || (listtype == "1") || (listtype == "i"))
	{
		thelist = "[list=" + listtype + "]\n";
	}
	else
	{
		thelist = "[list]\n";
	}
	var listentry = "initial";
	while ((listentry != "") && (listentry != null))
	{
		listentry = prompt(vbphrase["enter_list_item"], "");
		if ((listentry != "") && (listentry != null))
		{
			thelist = thelist + "[*]" + listentry + "\n";
		}
	}
	AddTxt = thelist + "[/list]";
	if (!text)
	{
		AddTxt = AddTxt + " ";
	}
	AddText(AddTxt);
}

// #############################################################################
function smilie(thesmilie)
{
	getActiveText();
	var AddSmilie = " " + thesmilie + " ";
	AddText(AddSmilie);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14531 $
|| ####################################################################
\*======================================================================*/