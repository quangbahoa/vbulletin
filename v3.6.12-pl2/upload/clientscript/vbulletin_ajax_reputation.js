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
* Register a post for ajax reputation
*
* @param	string	Postid
*
* @return	vB_Reputation_Object
*/
function vbrep_register(postid)
{
	if (typeof vBrep == 'object' && typeof postid != 'undefined')
	{
		return vBrep.register(postid);
	}
}

// #############################################################################
// vB_Reputation_Handler
// #############################################################################

/**
* vBulletin reputation registry
*/
function vB_Reputation_Handler()
{
	this.reps = new Array();
	this.ajax = new Array();
};

// =============================================================================
// vB_Reputation_Handler methods

/**
* Register a control object as a reputation control
*
* @param	string	ID of the control object
*
* @return	vB_Reputation_Object
*/
vB_Reputation_Handler.prototype.register = function(postid)
{
	if (AJAX_Compatible && (typeof vb_disable_ajax == 'undefined' || vb_disable_ajax < 2))
	{
		this.reps[postid] = new vB_Reputation_Object(postid);
		if (obj = fetch_object('reputation_' + postid))
		{
			obj.onclick = vB_Reputation_Object.prototype.reputation_click;
			return this.reps[postid];
		}
	}
};

// #############################################################################
// initialize reputation registry

vBrep = new vB_Reputation_Handler();

// #############################################################################
// vB_Reputation_Object
// #############################################################################

/**
* vBulletin Reputation class constructor
*
* Manages a single reputation and control object
* Initializes control object
*
* @param	string	postid
*/
function vB_Reputation_Object(postid)
{
	this.postid = postid;
	this.divname = 'reputationmenu_' + postid + '_menu';
	this.divobj = null;
	this.postobj = fetch_object('post' + postid);

	this.vbmenuname = 'reputationmenu_' + postid;
	this.vbmenu = null;

	this.xml_sender_populate = null;
	this.xml_sender_submit = null;

	var me = this;

	/**
	* Populate OnReadyStateChange callback. Uses a closure to keep state.
	* Remember to use me instead of "this" inside this function!
	*/
	this.onreadystatechange_populate = function()
	{
		if (me.xml_sender_populate.handler.readyState == 4 && me.xml_sender_populate.handler.status == 200)
		{
			if (me.xml_sender_populate.handler.responseXML)
			{
				// check for error first
				var error = me.xml_sender_populate.fetch_data(fetch_tags(me.xml_sender_populate.handler.responseXML, 'error')[0]);
				if (error)
				{
					alert(error);
				}
				else
				{
					if (!me.divobj)
					{
						// Create new div to hold reputation menu html
						me.divobj = document.createElement('div');
						me.divobj.id = me.divname;
						me.divobj.style.display = 'none';
						me.divobj.onkeypress = vB_Reputation_Object.prototype.repinput_onkeypress;
						me.postobj.parentNode.appendChild(me.divobj);

						me.vbmenu = vbmenu_register(me.vbmenuname, true);
						// Remove menu's mouseover event
						fetch_object(me.vbmenu.controlkey).onmouseover = '';
						fetch_object(me.vbmenu.controlkey).onclick = '';
					}

					me.divobj.innerHTML = me.xml_sender_populate.fetch_data(fetch_tags(me.xml_sender_populate.handler.responseXML, 'reputationbit')[0]);

					var inputs = fetch_tags(me.divobj, 'input');
					for (var i = 0; i < inputs.length; i++)
					{
						if (inputs[i].type == 'submit')
						{
							var sbutton = inputs[i];
							var button = document.createElement('input');
							button.type = 'button';
							button.className = sbutton.className;
							button.value = sbutton.value;
							button.onclick = vB_Reputation_Object.prototype.submit_onclick;
							sbutton.parentNode.insertBefore(button, sbutton);
							sbutton.parentNode.removeChild(sbutton);
							button.name = sbutton.name;
							button.id = sbutton.name + '_' + me.postid
						}
					}

					me.vbmenu.show(fetch_object(me.vbmenuname));
				}
			}

			if (is_ie)
			{
				me.xml_sender_populate.handler.abort();
			}
		}
	}

	/**
	* Submit OnReadyStateChange callback. Uses a closure to keep state.
	* Remember to use me instead of "this" inside this function!
	*/
	this.onreadystatechange_submit = function()
	{
		if (me.xml_sender_submit.handler.readyState == 4 && me.xml_sender_submit.handler.status == 200)
		{
			if (me.xml_sender_submit.handler.responseXML)
			{
				// Register new menu item for this reputation icon
				if (!me.vbmenu)
				{
					me.vbmenu = vbmenu_register(me.vbmenuname, true);
					// Remove menu's mouseover event
					fetch_object(me.vbmenu.controlkey).onmouseover = '';
					fetch_object(me.vbmenu.controlkey).onclick = '';
				}

				// check for error first
				var error = me.xml_sender_submit.fetch_data(fetch_tags(me.xml_sender_submit.handler.responseXML, 'error')[0]);
				if (error)
				{
					me.vbmenu.hide(fetch_object(me.vbmenuname));
					alert(error);
				}
				else
				{
					me.vbmenu.hide(fetch_object(me.vbmenuname));
					var repinfo =  fetch_tags(me.xml_sender_submit.handler.responseXML, 'reputation')[0];
					var repdisplay = repinfo.getAttribute('repdisplay');
					var reppower = repinfo.getAttribute('reppower');
					var userid = repinfo.getAttribute('userid');

					var spans = fetch_tags(document, 'span');
					var postid = null;
					var match = null;

					for (var i = 0; i < spans.length; i++)
					{
						if (match = spans[i].id.match(/^reppower_(\d+)_(\d+)$/))
						{
							if (match[2] == userid)
							{
								spans[i].innerHTML = reppower;
							}
						}
						else if (match = spans[i].id.match(/^repdisplay_(\d+)_(\d+)$/))
						{
							if (match[2] == userid)
							{
								spans[i].innerHTML = repdisplay;
							}
						}
					}
					alert(me.xml_sender_submit.fetch_data(repinfo));
				}
			}

			if (is_ie)
			{
				me.xml_sender_submit.handler.abort();
			}
		}
	}
}

/**
* Handles click events on reputation icon
*/
vB_Reputation_Object.prototype.reputation_click = function (e)
{
	e = e ? e : window.event;

	do_an_e(e);
	var postid = this.id.substr(this.id.lastIndexOf('_') + 1);
	var repobj = vBrep.reps[postid];

	// fetch and return reputation html
	if (repobj.vbmenu == null)
	{
		repobj.populate();
	}
	else if (vBmenu.activemenu != repobj.vbmenuname)
	{
		repobj.vbmenu.show(fetch_object(repobj.vbmenuname));
	}
	else
	{
		repobj.vbmenu.hide();
	}

	return true;
}

/**
* Handles click events on reputation submit button
*/

vB_Reputation_Object.prototype.submit_onclick = function (e)
{
	e = e ? e : window.event;
	do_an_e(e);

	var postid = this.id.substr(this.id.lastIndexOf('_') + 1);
	var repobj = vBrep.reps[postid];
	repobj.submit();

	return false;
}

/**
*	Catches the keypress of the reputation controls to keep them from submitting to inlineMod
*/
vB_Reputation_Object.prototype.repinput_onkeypress = function (e)
{
	e = e ? e : window.event;

	switch (e.keyCode)
	{
		case 13:
		{
			vBrep.reps[this.id.split(/_/)[1]].submit();	
			return false;
		}
		default:
		{
			return true;
		}
	}
}

/**
* Queries for proper response to reputation, response varies
*
*/
vB_Reputation_Object.prototype.populate = function()
{
	this.xml_sender_populate = new vB_AJAX_Handler(true);
	this.xml_sender_populate.onreadystatechange(this.onreadystatechange_populate);
	this.xml_sender_populate.send('reputation.php?p=' + this.postid, 'p=' + this.postid + '&ajax=1');
}

/**
* Submits reputation
*
*/
vB_Reputation_Object.prototype.submit = function()
{
	this.psuedoform = new vB_Hidden_Form('reputation.php');
	this.psuedoform.add_variable('ajax', 1);
	this.psuedoform.add_variables_from_object(this.divobj);

	this.xml_sender_submit = new vB_AJAX_Handler(true);
	this.xml_sender_submit.onreadystatechange(this.onreadystatechange_submit)
	this.xml_sender_submit.send(
		'reputation.php?do=addreputation&p=' + this.psuedoform.fetch_variable('p') + '&reputation=' + this.psuedoform.fetch_variable('reputation') + '&reason=' + PHP.urlencode(this.psuedoform.fetch_variable('reason')),
		this.psuedoform.build_query_string()
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15754 $
|| ####################################################################
\*======================================================================*/