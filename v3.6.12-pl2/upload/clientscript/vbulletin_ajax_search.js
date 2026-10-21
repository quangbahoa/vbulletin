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

/**
* Adds onclick event to the save search prefs buttons
*
* @param	string	The ID of the button that fires the search prefs
*/
function vB_AJAX_SearchPrefs_Init(buttonid)
{
	if (AJAX_Compatible && (typeof vb_disable_ajax == 'undefined' || vb_disable_ajax < 2) && fetch_object(buttonid))
	{
		// prevent the form from submitting when clicking the submit button
		var sbutton = fetch_object(buttonid);
		sbutton.onclick = vB_AJAX_SearchPrefs.prototype.form_click;
	}
};

/**
* Class to handle saveing search prefs
*
* @param	object	The form object containing the search options
*/
function vB_AJAX_SearchPrefs(formobj)
{
	// AJAX handler
	this.xml_sender = null;

	// vB_Hidden_Form object to handle form variables
	this.pseudoform = new vB_Hidden_Form('search.php');
	this.pseudoform.add_variable('ajax', 1);
	this.pseudoform.add_variable('doprefs', 1);
	this.pseudoform.add_variables_from_object(formobj);

	// Closure
	var me = this;

	/**
	* OnReadyStateChange callback. Uses a closure to keep state.
	* Remember to use me instead of this inside this function!
	*/
	this.handle_ajax_response = function()
	{
		if (me.xml_sender.handler.readyState == 4 && me.xml_sender.handler.status == 200)
		{
			if (me.xml_sender.handler.responseXML)
			{
				var obj = fetch_object(me.objid);

				// check for error first
				var error = me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'error')[0]);
				if (error)
				{
					alert(error);
				}
				else
				{
					var message = me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'message')[0]);
					if (message)
					{
						alert(message);
					}
				}
			}

			if (is_ie)
			{
				me.xml_sender.handler.abort();
			}
		}
	}
};

/**
* Submits the form via Ajax
*/
vB_AJAX_SearchPrefs.prototype.submit = function()
{
	this.xml_sender = new vB_AJAX_Handler(true);
	this.xml_sender.onreadystatechange(this.handle_ajax_response);
	this.xml_sender.send(
		'search.php?',
		this.pseudoform.build_query_string()
	);
};

/**
* Handles the form 'submit' action
*/
vB_AJAX_SearchPrefs.prototype.form_click = function()
{
	var AJAX_SearchPrefs = new vB_AJAX_SearchPrefs(this.form);
	AJAX_SearchPrefs.submit();
	return false;
};

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14851 $
|| ####################################################################
\*======================================================================*/