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
* Initialize AJAX post editing
*
* @param	mixed	ID of element (or actual element) containing postbits
*/
function vB_AJAX_QuickEdit_Init(postobj)
{
	if (AJAX_Compatible)
	{
		if (typeof postobj == 'string')
		{
			postobj = fetch_object(postobj);
		}
		var anchors = fetch_tags(postobj, 'a');
		var postid = 0;
		for (var i = 0; i < anchors.length; i++)
		{
			if (anchors[i].name && anchors[i].name.indexOf('vB::QuickEdit::') != -1)
			{
				anchors[i].onclick = vB_AJAX_QuickEditor_Events.prototype.editbutton_click;
			}
		}
	}
}

// #############################################################################
// vB_AJAX_QuickEditor
// #############################################################################

/**
* Class to allow quick editing of posts within postbit via AJAX
*/
function vB_AJAX_QuickEditor()
{
	this.postid = null;
	this.messageobj = null;
	this.container = null;
	this.originalhtml = null;
	this.ajax = null;
	this.editstate = false;
	this.editorcounter = 0;
	this.pending = false;
}

// =============================================================================
// vB_AJAX_QuickEditor methods

/**
* Check if the AJAX system is ready for us to proceed
*
* @return	boolean
*/
vB_AJAX_QuickEditor.prototype.ready = function()
{
	if (this.editstate || this.pending)
	{
		return false;
	}
	else
	{
		return true;
	}
};

/**
* Prepare to edit a single post
*
* @param	string	Name attribute of clicked link - takes the form of 'vB::QuickEdit::$postid'
*
* @return	boolean	false
*/
vB_AJAX_QuickEditor.prototype.edit = function(anchor_name)
{
	var test_ajax = new vB_AJAX_Handler(true);
	if (!test_ajax.init() || (typeof vb_disable_ajax != 'undefined' && vb_disable_ajax > 0))
	{
		// couldn't initialize, return true to allow click to go through
		return true;
	}

	var tmppostid = anchor_name.substr(anchor_name.lastIndexOf('::') + 2);

	if (this.pending)
	{
		// something is waiting to complete
		return false;
	}
	else if (!this.ready())
	{
		if (this.postid == tmppostid)
		{
			this.full_edit();
			return false;
		}
		this.abort();
	}

	this.editorcounter++;
	this.editorid = 'vB_Editor_QE_' + this.editorcounter;

	this.postid = tmppostid;

	this.messageobj = fetch_object('post_message_' + this.postid);
	this.originalhtml = this.messageobj.innerHTML;

	this.unchanged = null;
	this.unchanged_reason = null;

	this.fetch_editor();

	this.editstate = true;

	return false;
};

/**
* Send an AJAX request to fetch the editor HTML
*/
vB_AJAX_QuickEditor.prototype.fetch_editor = function()
{
	if (fetch_object('progress_' + this.postid))
	{
		fetch_object('progress_' + this.postid).style.display = '';
	}
	document.body.style.cursor = 'wait';
	this.ajax = new vB_AJAX_Handler(true);
	this.ajax.onreadystatechange(this.display_editor);
	this.ajax.send('ajax.php?do=quickedit&p=' + this.postid, 'do=quickedit&p=' + this.postid + '&editorid=' + PHP.urlencode(this.editorid));
	this.pending = true;
};

/**
* Display the editor HTML when AJAX says fetch_editor() is ready
*/
vB_AJAX_QuickEditor.prototype.display_editor = function()
{
	var AJAX = vB_QuickEditor.ajax.handler;

	if (AJAX.readyState == 4 && AJAX.status == 200)
	{
		if (fetch_object('progress_' + vB_QuickEditor.postid))
		{
			fetch_object('progress_' + vB_QuickEditor.postid).style.display = 'none';
		}
		document.body.style.cursor = 'auto';
		vB_QuickEditor.pending = false;

		if (fetch_tag_count(AJAX.responseXML, 'disabled'))
		{
			// this will fire if quick edit has been disabled after the showthread page is loaded
			window.location = 'editpost.php?' + SESSIONURL + 'do=editpost&postid=' + vB_QuickEditor.postid;
		}
		else if (fetch_tag_count(AJAX.responseXML, 'error'))
		{
			// do nothing
		}
		else
		{
			var editor = fetch_tags(AJAX.responseXML, 'editor')[0];
			if (typeof editor == 'undefined')
			{
				window.location = "editpost.php?" + SESSIONURL + "do=editpost&postid=" + this.postid;
				return false;
			}
			var reason = editor.getAttribute('reason');

			// display the editor
			vB_QuickEditor.messageobj.innerHTML = vB_QuickEditor.ajax.fetch_data(editor);

			// display the reason
			if (fetch_object(vB_QuickEditor.editorid + '_edit_reason'))
			{
				vB_QuickEditor.unchanged_reason = PHP.unhtmlspecialchars(reason);
				fetch_object(vB_QuickEditor.editorid + '_edit_reason').value = vB_QuickEditor.unchanged_reason;
				fetch_object(vB_QuickEditor.editorid + '_edit_reason').onkeypress = vB_AJAX_QuickEditor_Events.prototype.reason_key_trap;
			}

			// initialize the editor
			vB_Editor[vB_QuickEditor.editorid] = new vB_Text_Editor(
				vB_QuickEditor.editorid,
				editor.getAttribute('mode'),
				editor.getAttribute('parsetype'),
				editor.getAttribute('parsesmilies')
			);

			if (fetch_object(vB_QuickEditor.editorid + '_editor') && fetch_object(vB_QuickEditor.editorid + '_editor').scrollIntoView)
			{
				fetch_object(vB_QuickEditor.editorid + '_editor').scrollIntoView(true);
			}

			vB_Editor[vB_QuickEditor.editorid].editbox.style.width = '100%';
			vB_Editor[vB_QuickEditor.editorid].check_focus();

			vB_QuickEditor.unchanged = vB_Editor[vB_QuickEditor.editorid].get_editor_contents();

			fetch_object(vB_QuickEditor.editorid + '_save').onclick = vB_QuickEditor.save;
			fetch_object(vB_QuickEditor.editorid + '_abort').onclick = vB_QuickEditor.abort;
			fetch_object(vB_QuickEditor.editorid + '_adv').onclick = vB_QuickEditor.full_edit;

			var delbutton = fetch_object(vB_QuickEditor.editorid + '_delete');
			if (delbutton)
			{
				delbutton.onclick = vB_QuickEditor.show_delete;
			}
		}

		if (is_ie)
		{
			AJAX.abort();
		}
	}
};

/**
* Destroy the editor, and use the specified text as the post contents
*
* @param	string	Text of post
*/
vB_AJAX_QuickEditor.prototype.restore = function(post_html, type)
{
	this.hide_errors(true);
	if (this.editorid && vB_Editor[this.editorid] && vB_Editor[this.editorid].initialized)
	{
		vB_Editor[this.editorid].destroy();
	}
	if (type == 'tableobj')
	{
		// usually called when message is saved
		fetch_object('edit' + this.postid).innerHTML = post_html;
	}
	else
	{
		// usually called when message edit is cancelled
		this.messageobj.innerHTML = post_html;
	}

	this.editstate = false;
};

/**
* Cancel the post edit and restore everything to how it started
*
* @param	event	Event object
*/
vB_AJAX_QuickEditor.prototype.abort = function(e)
{
	if (fetch_object('progress_' + vB_QuickEditor.postid))
	{
		fetch_object('progress_' + vB_QuickEditor.postid).style.display = 'none';
	}
	document.body.style.cursor = 'auto';
	vB_QuickEditor.restore(vB_QuickEditor.originalhtml, 'messageobj');
};

/**
* Pass the edits along to the full editpost.php interface
*
* @param	event	Event object
*/
vB_AJAX_QuickEditor.prototype.full_edit = function(e)
{
	var form = new vB_Hidden_Form('editpost.php?do=editpost&postid=' + vB_QuickEditor.postid);

	form.add_variable('do', 'updatepost');
	form.add_variable('s', fetch_sessionhash());
	form.add_variable('securitytoken', SECURITYTOKEN);
	form.add_variable('advanced', 1);
	// Don't preview - see editpost.php if you want to know why
	//form.add_variable('preview', 'Yes');
	form.add_variable('postid', vB_QuickEditor.postid);
	form.add_variable('wysiwyg', vB_Editor[vB_QuickEditor.editorid].wysiwyg_mode);
	form.add_variable('message', vB_Editor[vB_QuickEditor.editorid].get_editor_contents());
	form.add_variable('reason', fetch_object(vB_QuickEditor.editorid + '_edit_reason').value);

	form.submit_form();
}

/**
* Save the edited post via AJAX
*
* @param	event	Event object
*/
vB_AJAX_QuickEditor.prototype.save = function(e)
{
	var newtext = vB_Editor[vB_QuickEditor.editorid].get_editor_contents();
	var newreason = vB_Editor[vB_QuickEditor.editorid];

	if (newtext == vB_QuickEditor.unchanged && newreason == vB_QuickEditor.unchanged_reason)
	{
		vB_QuickEditor.abort(e);
	}
	else
	{
		fetch_object(vB_QuickEditor.editorid + '_posting_msg').style.display = '';
		document.body.style.cursor = 'wait';

		pc_obj = fetch_object('postcount' + vB_QuickEditor.postid);
		vB_QuickEditor.ajax = new vB_AJAX_Handler(true);
		vB_QuickEditor.ajax.onreadystatechange(vB_QuickEditor.update);
		vB_QuickEditor.ajax.send('editpost.php?do=updatepost&postid=' + vB_QuickEditor.postid,
			'do=updatepost&ajax=1&postid='
			+ vB_QuickEditor.postid
			+ '&wysiwyg=' + vB_Editor[vB_QuickEditor.editorid].wysiwyg_mode
			+ '&message=' + PHP.urlencode(newtext)
			+ '&reason=' + PHP.urlencode(fetch_object(vB_QuickEditor.editorid + '_edit_reason').value)
			+ (pc_obj != null ? '&postcount=' + PHP.urlencode(pc_obj.name) : '')
		);

		vB_QuickEditor.pending = true;
	}
};

/**
* Show the delete dialog
*/
vB_AJAX_QuickEditor.prototype.show_delete = function()
{
	vB_QuickEditor.deletedialog = fetch_object('quickedit_delete');
	if (vB_QuickEditor.deletedialog && vB_QuickEditor.deletedialog.style.display != '')
	{
		vB_QuickEditor.deletedialog.style.display = '';

		vB_QuickEditor.deletebutton = fetch_object('quickedit_dodelete');
		vB_QuickEditor.deletebutton.onclick = vB_QuickEditor.delete_post;

		if (fetch_object('del_reason'))
		{
			fetch_object('del_reason').onkeypress = vB_AJAX_QuickEditor_Events.prototype.delete_items_key_trap;
		}

		// don't do this stuff for browsers that don't have any defined events
		// to detect changed radio buttons with keyboard navigation
		if (!is_opera && !is_saf)
		{
			vB_QuickEditor.deletebutton.disabled = true;
			vB_QuickEditor.deleteoptions = new Array();

			vB_QuickEditor.deleteoptions['leave'] = fetch_object('rb_del_leave');
			vB_QuickEditor.deleteoptions['soft'] = fetch_object('rb_del_soft');
			vB_QuickEditor.deleteoptions['hard'] = fetch_object('rb_del_hard');

			for (var i in vB_QuickEditor.deleteoptions)
			{
				if (vB_QuickEditor.deleteoptions[i])
				{
					vB_QuickEditor.deleteoptions[i].onclick = vB_QuickEditor.deleteoptions[i].onchange = vB_AJAX_QuickEditor_Events.prototype.delete_button_handler;
					vB_QuickEditor.deleteoptions[i].onkeypress = vB_AJAX_QuickEditor_Events.prototype.delete_items_key_trap;
				}
			}
		}
	}
};

/**
* Run the delete system
*/
vB_AJAX_QuickEditor.prototype.delete_post = function()
{
	var dontdelete = fetch_object('rb_del_leave');
	if (dontdelete && dontdelete.checked)
	{
		vB_QuickEditor.abort();
		return;
	}

	var form = new vB_Hidden_Form('editpost.php');

	form.add_variable('do', 'deletepost');
	form.add_variable('s', fetch_sessionhash());
	form.add_variable('securitytoken', SECURITYTOKEN);
	form.add_variable('postid', vB_QuickEditor.postid);
	form.add_variables_from_object(vB_QuickEditor.deletedialog);

	form.submit_form();
};

/**
* Check for errors etc. and initialize restore when AJAX says save() is complete
*
* @return	boolean	false
*/
vB_AJAX_QuickEditor.prototype.update = function()
{
	var AJAX = vB_QuickEditor.ajax.handler;

	if (AJAX.readyState == 4 && AJAX.status == 200)
	{
		vB_QuickEditor.pending = false;
		document.body.style.cursor = 'auto';
		fetch_object(vB_QuickEditor.editorid + '_posting_msg').style.display = 'none';

		// this is the nice error handler, of which Safari makes a mess
		if (fetch_tag_count(AJAX.responseXML, 'error'))
		{
			var errors = fetch_tags(AJAX.responseXML, 'error');

			var error_html = '<ol>';
			for (var i = 0; i < errors.length; i++)
			{
				error_html += '<li>' + vB_QuickEditor.ajax.fetch_data(errors[i]) + '</li>';
			}
			error_html += '</ol>';

			vB_QuickEditor.show_errors('<ol>' + error_html + '</ol>');
		}
		else
		{
			vB_QuickEditor.restore(vB_QuickEditor.ajax.fetch_data(fetch_tags(AJAX.responseXML, 'postbit')[0]), 'tableobj');
			PostBit_Init(fetch_object('post' + vB_QuickEditor.postid), vB_QuickEditor.postid);
		}

		if (is_ie)
		{
			AJAX.abort();
		}
	}

	return false;
};

/**
* Pop up a window showing errors
*
* @param	string	Error HTML
*/
vB_AJAX_QuickEditor.prototype.show_errors = function(errortext)
{
	fetch_object('ajax_post_errors_message').innerHTML = errortext;
	var errortable = fetch_object('ajax_post_errors');
	errortable.style.width = '400px';
	errortable.style.zIndex = 500;
	var measurer = (is_saf ? 'body' : 'documentElement');
	errortable.style.left = (is_ie ? document.documentElement.clientWidth : self.innerWidth) / 2 - 200 + document[measurer].scrollLeft + 'px';
	errortable.style.top = (is_ie ? document.documentElement.clientHeight : self.innerHeight) / 2 - 150 + document[measurer].scrollTop + 'px';
	errortable.style.display = '';
};

/**
* Hide the error window
*/
vB_AJAX_QuickEditor.prototype.hide_errors = function(skip_focus_check)
{
	this.errors = false;
	fetch_object('ajax_post_errors').style.display = 'none';
	if (skip_focus_check != true)
	{
		vB_Editor[this.editorid].check_focus();
	}
};

// =============================================================================
// vB_AJAX_QuickEditor Event Handlers

/**
* Class to handle quick editor events
*/
function vB_AJAX_QuickEditor_Events()
{
}

/**
* Handles clicks on edit buttons of postbits
*/
vB_AJAX_QuickEditor_Events.prototype.editbutton_click = function(e)
{
	return vB_QuickEditor.edit(this.name);
};

/**
* Handles manipulation of form elements in the delete section
*/
vB_AJAX_QuickEditor_Events.prototype.delete_button_handler = function(e)
{
	if (this.id == 'rb_del_leave' && this.checked)
	{
		vB_QuickEditor.deletebutton.disabled = true;
	}
	else
	{
		vB_QuickEditor.deletebutton.disabled = false;
	}
}

/**
* Key trapper for reason box
*/
vB_AJAX_QuickEditor_Events.prototype.reason_key_trap = function(e)
{
	e = e ? e : window.event;

	switch (e.keyCode)
	{
		case 9: // tab
		{
			fetch_object(vB_QuickEditor.editorid + '_save').focus();
			return false;
		}
		break;

		case 13: // enter
		{
			vB_QuickEditor.save();
			return false;
		}
		break;

		default:
		{
			return true;
		}
	}
}

/**
* Key trapper for reason box
*/
vB_AJAX_QuickEditor_Events.prototype.delete_items_key_trap = function(e)
{
	e = e ? e : window.event;

	if (e.keyCode == 13) // enter
	{
		if (vB_QuickEditor.deletebutton.disabled == false)
		{
			vB_QuickEditor.delete_post();
		}
		return false;
	}

	return true;
}

// #############################################################################
// initialize the editor class

var vB_QuickEditor = new vB_AJAX_QuickEditor();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 26446 $
|| ####################################################################
\*======================================================================*/