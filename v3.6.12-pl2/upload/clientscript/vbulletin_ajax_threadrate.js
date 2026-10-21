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
* Adds onclick events to appropriate elements for thread rating
*
* @param	string	The ID of the form that contains the rating options
*/
function vB_AJAX_ThreadRate_Init(formid)
{
	var formobj = fetch_object(formid);

	if (AJAX_Compatible && (typeof vb_disable_ajax == 'undefined' || vb_disable_ajax < 2) && formobj)
	{
		for (var i = 0; i < formobj.elements.length; i++)
		{
			//alert(1);
			if (formobj.elements[i].type == 'submit')
			{
				// prevent the form from submitting when clicking the submit button
				var sbutton = formobj.elements[i];
				var button = document.createElement('input');
				button.type = 'button';
				button.className = sbutton.className;
				button.value     = sbutton.value;
				button.onclick   = vB_AJAX_ThreadRate.prototype.form_click;
				sbutton.parentNode.insertBefore(button, sbutton);
				sbutton.parentNode.removeChild(sbutton);
			}
		}
	}
};

/**
* Class to handle thread rating
*
* @param	object	The form object containing the vote options
*/
function vB_AJAX_ThreadRate(formobj)
{
	// AJAX handler
	this.xml_sender = null;

	// vB_Hidden_Form object to handle form variables
	this.pseudoform = new vB_Hidden_Form('threadrate.php');
	this.pseudoform.add_variable('ajax', 1);
	this.pseudoform.add_variables_from_object(formobj);

	// Output object
	this.output_element_id = 'threadrating_current';

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
					// Hide thread rating popup menu now
					if (vBmenu.activemenu == 'threadrating')
					{
						vBmenu.hide();
					}
					alert(error);
				}
				else
				{
					var newrating = me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'voteavg')[0]);
					if (newrating != '')
					{
						fetch_object(me.output_element_id).innerHTML = newrating;
					}
					// Hide thread rating popup menu now
					if (vBmenu.activemenu == 'threadrating')
					{
						vBmenu.hide();
					}

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
* Places the vote
*/
vB_AJAX_ThreadRate.prototype.rate = function()
{
	if (this.pseudoform.fetch_variable('vote') != null)
	{
		this.xml_sender = new vB_AJAX_Handler(true);
		this.xml_sender.onreadystatechange(this.handle_ajax_response);
		this.xml_sender.send(
			'threadrate.php?t=' + threadid + '&vote=' + PHP.urlencode(this.pseudoform.fetch_variable('vote')),
			this.pseudoform.build_query_string()
		);
	}
};

/**
* Handles the form 'submit' action
*/
vB_AJAX_ThreadRate.prototype.form_click = function()
{
	var AJAX_ThreadRate = new vB_AJAX_ThreadRate(this.form);
	AJAX_ThreadRate.rate();
	return false;
};

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 17649 $
|| ####################################################################
\*======================================================================*/