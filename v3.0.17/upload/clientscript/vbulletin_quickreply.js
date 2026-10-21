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

var active = true;

// #############################################################################
// disable quote reply checkbox until a click is made if we are not using true
// threaded mode, REGARDLESS of require_click setting!
if (threadedmode != 1)
{
	fetch_object("qr_quickreply").disabled = true;
}

// disable controls for now if a click is required to activate QR
if (require_click)
{
	if (WYSIWYG == 2)
	{
		set_default_text(must_click_message, true);
	}
	
	if (typeof(must_click_message) == "string")
	{
		sig = fetch_object("cb_signature");
		if (sig)
		{
			sig.disabled = true; // signature checkbox
		}
		
		if (WYSIWYG != 2)
		{
			fetch_object("qr_message").disabled = true; // message textarea
			fetch_object("qr_message").value = must_click_message; // message textbox
		}
	}
	
	active = false;
}
else if (WYSIWYG >= 1)
{
	editInit();
}

// #############################################################################
// initialize quick reply
function qr(postid)
{
	qrt = fetch_object("collapseobj_quickreply");
	if (qrt && qrt.style.display == "none")
	{
		toggle_collapse("quickreply");
	}

	fetch_object("qr_postid").value = postid;
	fetch_object("qr_preview").select();
	fetch_object("qr_quickreply").disabled = false;

	sig = fetch_object("cb_signature");
	if (sig)
	{
		sig.disabled = false;
	}
	fetch_object("qr_message").disabled = false;
	fetch_object("qr_message").value = "";

	switch (WYSIWYG)
	{
		case 2: // full wysiwyg
		{
			if (!active)
			{
				wysiwyg_init();
			}
		}
		break;
		
		case 1: // standard editor
		{
			if (!active)
			{
				vbcode_init();
			}
			htmlwindow = fetch_object("qr_message");
		}
		break;
		
		default:
		{
			htmlwindow = fetch_object("qr_message");
		}
	}

	if (WYSIWYG == 2 && !is_ie)
	{ // do a special scroll thingy for Mozilla
		fetch_object("qr_scroll").scrollIntoView(false);
	}

	active = true;
	htmlwindow.focus();
	return false;
}

// #############################################################################
// check quick reply is initialized
function checkQR(tform)
{	
	if (fetch_object("qr_postid").value == 0)
	{
		alert(must_click_message);
		return false;
	}
	else
	{
		if (fetch_object("qr_postid").value == "who cares" && typeof tform.quickreply != "undefined")
		{
			tform.quickreply.checked = false
		}
		
		// if we are using 'Go Advanced', bypass the minimum characters test
		if (tform.clickedelm.value == tform.preview.value)
		{
			temp_minchars = 0;
		}
		else
		{
			temp_minchars = minchars;
		}
		
		if (WYSIWYG < 2)
		{
			// this function is from vbulletin_global.js
			return validatemessage(fetch_object("qr_message").value, 0, temp_minchars, maxchars, false, tform);
		}
		else
		{
			// this function is in vbulletin_wysiwyg.js
			return validatePost(tform, 0, temp_minchars, maxchars);
		}
	}
}

// #############################################################################
// get the postid
function infoQR()
{
	alert('PostID: ' + fetch_object("qr_postid").value);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 8893 $
|| ####################################################################
\*======================================================================*/