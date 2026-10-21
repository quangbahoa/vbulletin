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

function init_validation(formid)
{
	var formobj = fetch_object(formid);
	for (var i = 0; i < formobj.elements.length; i++)
	{
		switch (formobj.elements[i].tagName)
		{
			case 'INPUT':
			{
				switch (formobj.elements[i].type)
				{
					case 'text':
					case 'password':
					case 'file':
					{
						YAHOO.util.Event.on(formobj.elements[i], "blur", validate_setting);
					}
					break;

					case 'radio':
					case 'checkbox':
					case 'button':
					{
						if (is_opera)
						{
							YAHOO.util.Event.on(formobj.elements[i], "keypress", validate_setting);
						}
						YAHOO.util.Event.on(formobj.elements[i], "click", validate_setting);
					}
					break;

					default:
					// do nothing
				}
			}
			break;

			case 'SELECT':
			{
				YAHOO.util.Event.on(formobj.elements[i], "change", validate_setting);
			}
			break;

			case 'TEXTAREA':
			{
				YAHOO.util.Event.on(formobj.elements[i], "blur", validate_setting);
			}
			break;

			default:
			// do nothing
		}
	}

	YAHOO.util.Event.on(document, 'mousedown', capture_results);
	YAHOO.util.Event.on(document, 'mouseup', display_results);
}

var settings_validation = new Array();
var settings_validation_cache = new Array();
var settings_validation_cleanup = new Array();

var mouse_down = false;

function capture_results(e)
{
	e = e ? e : window.event;
	mouse_down = (e.type == 'mousedown');
}

function display_results(e)
{
	e = e ? e : window.event;
	mouse_down = (e.type == 'mousedown');

	for (var setting_name in settings_validation_cleanup)
	{
		fetch_object('tbody_error_' + setting_name).style.display = 'none';
		delete settings_validation_cleanup[setting_name];
	}

	for (var setting_name in settings_validation_cache)
	{
		fetch_object('tbody_error_' + setting_name).style.display = '';
		fetch_object('span_error_' + setting_name).innerHTML = settings_validation_cache[setting_name];
		delete settings_validation_cache[setting_name];
	}
}

function validate_setting(e)
{
	e = e ? e : window.event;
	if (this.id)
	{
		this.varname = this.id.replace(/^.+\[(.+)\].*$/, '$1');
	}
	else
	{
		this.varname = this.name.replace(/^.+\[(.+)\].*$/, '$1');
	}

	//fetch_object('error_output').innerHTML += '<div>' + this.varname + ' - ' + e.type.italics() + ' ' + this.tagName + ' event</div>';

	settings_validation[this.varname] = new vB_Setting_Validator(this.varname);

	return true;
}

function vB_Setting_Validator(varname)
{
	this.varname = varname;
	this.query_string = '';

	this.check_setting();
}

vB_Setting_Validator.prototype.check_setting = function()
{
	this.container = fetch_object('tbody_' + this.varname);

	this.form = new vB_Hidden_Form('options.php');
	this.form.add_variable('do', 'validate');
	try
	{
		this.form.add_variable('adminhash', fetch_object('optionsform').adminhash.value);
	}
	catch(e){}

	this.form.add_variables_from_object(this.container);
	this.query_string = this.form.build_query_string() + 'varname=' + this.varname;
	this.form = null;

	this.ajax = new vB_AJAX_Handler(true);
	this.ajax.init();
	this.ajax.varname = this.varname;
	var ajax_closure = this.ajax;

	this.ajax.handler.onreadystatechange = function(e) { handle_validation(ajax_closure); };
	this.ajax.send('options.php?do=validate&varname=' + this.varname, this.query_string);

}

function handle_validation(ajax_closure)
{
	if (ajax_closure.handler.readyState == 4 && ajax_closure.handler.status == 200 && ajax_closure.handler.responseXML)
	{
		var setting_name = ajax_closure.fetch_data(fetch_tags(ajax_closure.handler.responseXML, 'varname')[0]);
		var validity_return = ajax_closure.fetch_data(fetch_tags(ajax_closure.handler.responseXML, 'valid')[0]);

		//fetch_object('error_output').innerHTML += '<div>' + ajax_closure.varname + ' - validation: <font color="white">' + (validity_return == 1 ? 'YES' : 'NO') + '</font></div>';

		var errmsg = fetch_object('tbody_error_' + setting_name);
		if (errmsg)
		{
			if (errmsg.style.display != 'none')
			{
				if (mouse_down)
				{
					settings_validation_cleanup[setting_name] = true;
				}
				else
				{
					errmsg.style.display = 'none';
				}
			}

			if (validity_return != 1)
			{
				if (mouse_down)
				{
					settings_validation_cache[setting_name] = validity_return;
				}
				else
				{
					fetch_object('tbody_error_' + setting_name).style.display = '';
					fetch_object('span_error_' + setting_name).innerHTML = validity_return;
				}
			}
		}
		else
		{
			// couldn't find the specified tbody_error_x
		}

		if (is_ie)
		{
			ajax_closure.handler.abort();
		}
	}
}

function count_errors()
{
	var tbodies = fetch_tags(document, 'tbody');
	for (var i = 0; i < tbodies.length; i++)
	{
		if (tbodies[i].id && tbodies[i].id.substr(0, 12) == 'tbody_error_' && tbodies[i].style.display != 'none')
		{
			return confirm(error_confirmation_phrase);
		}
	}

	return true;
}

if (AJAX_Compatible)
{
	init_validation('optionsform');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 17675 $
|| ####################################################################
\*======================================================================*/