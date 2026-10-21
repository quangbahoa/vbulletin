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
* Adds onclick events to appropriate elements for submitting the form
* Each form to be activated should be specified as a separate argument, eg: vB_AJAX_Userlist_Init('form1_id', 'form2_id', 'form3_id');
*
* @param	string	form elements to attach vB_AJAX_Userlist to.
*/
function vB_AJAX_Userlist_Init(forms)
{
	// this can count as a "problematic" AJAX function, as usernames won't be found without iconv
	if (AJAX_Compatible && (typeof vb_disable_ajax == 'undefined' || vb_disable_ajax == 0))
	{
		for (var i = 0; i < arguments.length; i++)
		{
			var form = document.getElementById(arguments[i]);
			if (form)
			{
				form.onsubmit = vB_AJAX_Userlist.prototype.form_click;
			}
		}
	}
};

/**
* Class to handle userlist modifications
*
* @param	object	The form object containing the list elements
*/
function vB_AJAX_Userlist(formobj)
{
	// AJAX handler
	this.xml_sender = null;

	// vB_Hidden_Form object to handle form variables
	this.pseudoform = new vB_Hidden_Form(''); // overridden when necessary
	this.pseudoform.add_variable('ajax', 1); // this must be first!
	this.pseudoform.add_variables_from_object(formobj);

	this.list = formobj.id.replace(/userlist_(\w+)form/, '$1');

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
			// by default, resubmit the form without AJAX, unless we got good data
			var resubmit_form = true;

			if (fetch_object('userfield_' + me.list + '_progress'))
			{
				fetch_object('userfield_' + me.list + '_progress').style.display = 'none';
			}
			if (me.xml_sender.handler.responseXML)
			{
				// check for error first
				var error = me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'error')[0]);
				if (error)
				{
					// show error - disabled - resubmitting the form now
					/*fetch_object('userfield_' + me.list + '_errortext').innerHTML = error;
					fetch_object('userfield_' + me.list + '_error').style.display = '';*/
				}
				else
				{
					// hide error
					fetch_object('userfield_' + me.list + '_error').style.display = 'none';

					fetch_object('userfield_' + me.list + '_txt').value = '';
					fetch_object(me.list + 'list1').innerHTML = me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'listbit1')[0]);
					fetch_object(me.list + 'list2').innerHTML = me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'listbit2')[0]);

					resubmit_form = false;
				}
			}

			if (is_ie)
			{
				me.xml_sender.handler.abort();
			}

			// we got an error, so resubmit the form without AJAX - elimnates charset issues
			if (resubmit_form)
			{
				// the ajax entry should be first; disable it if possible
				if (typeof(me.pseudoform.variables[0]) != "undefined" && me.pseudoform.variables[0][0] == "ajax" && me.pseudoform.variables[0][1] == 1)
				{
					me.pseudoform.variables[0][1] = 0;
				}
				me.pseudoform.submit_form();
			}
		}
	}
};

/**
* Submit the form
*/
vB_AJAX_Userlist.prototype.submit_form = function(action)
{
	if (fetch_object('userfield_' + this.list + '_progress'))
	{
		fetch_object('userfield_' + this.list + '_progress').style.display = '';
	}

	this.pseudoform.action = action;

	this.xml_sender = new vB_AJAX_Handler(true);
	this.xml_sender.onreadystatechange(this.handle_ajax_response);
	this.xml_sender.send(
		action,
		this.pseudoform.build_query_string()
	);
};

/**
* Handles the form 'submit' action
*/
vB_AJAX_Userlist.prototype.form_click = function()
{
	var AJAX_Userlist = new vB_AJAX_Userlist(this);
	AJAX_Userlist.submit_form(this.action);
	return false;
};

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 17182 $
|| ####################################################################
\*======================================================================*/