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

var quote_editorid;
var quote_xml;

/**
* Initializes the link to fetch/deselect the additional MQ'd posts not in this thread.
*
* @param	string	ID of the editor to add the text to
* @param	integer	The ID of the current thread
*/
function init_unquoted_posts(editorid, threadid)
{
	var fetch_link = fetch_object('multiquote_more');
	if (fetch_link)
	{
		fetch_link.onclick = function() { return handle_unquoted_posts(editorid, threadid, 'fetch'); };
	}

	var deselect_link = fetch_object('multiquote_deselect');
	if (deselect_link)
	{
		deselect_link.onclick = function() { return handle_unquoted_posts(editorid, threadid, 'deselect'); };
	}
}

/**
* Handles the unquoted posts for all other threads. Either fetches the contents of the posts, or deselects them
*
* @param	string	ID of the editor to insert the text into
* @param	integer	The thread ID of the current thread; posts from this thread will not be fetched
* @param	string	Type of data to retrieve: fetch (returns post text) or deselect (returns new value of cookie)
*/
function handle_unquoted_posts(editorid, threadid, type)
{
	quote_editorid = editorid;

	quote_xml = new vB_AJAX_Handler(true);
	quote_xml.onreadystatechange(handle_ajax_unquoted_response);
	quote_xml.send(
		'newreply.php?do=unquotedposts&threadid=' + threadid,
		'do=unquotedposts&threadid=' + threadid
			+ '&wysiwyg=' + (vB_Editor[quote_editorid].wysiwyg_mode ? 1 : 0)
			+ '&type=' + PHP.urlencode(type)
	);

	return false;
}

/**
* OnReadyStateChange handler for the AJAX object.
* If a fetch response, inserts the text at the cursor position.
*/
function handle_ajax_unquoted_response()
{
	if (quote_xml.handler.readyState == 4 && quote_xml.handler.status == 200)
	{
		if (quote_xml.handler.responseXML)
		{
			if (fetch_tags(quote_xml.handler.responseXML, 'quotes')[0])
			{
				// insert the text into the editor
				vB_Editor[quote_editorid].history.add_snapshot(vB_Editor[quote_editorid].get_editor_contents());
				vB_Editor[quote_editorid].insert_text(quote_xml.fetch_data(fetch_tags(quote_xml.handler.responseXML, 'quotes')[0]));
				vB_Editor[quote_editorid].collapse_selection_end();
				vB_Editor[quote_editorid].history.add_snapshot(vB_Editor[quote_editorid].get_editor_contents());

				// change the multiquote empty input to empty the cookie completely
				var multiquote_empty_input = fetch_object('multiquote_empty_input');
				if (multiquote_empty_input)
				{
					multiquote_empty_input.value = 'all';
				}
			}
			else if (fetch_tags(quote_xml.handler.responseXML, 'mqpostids')[0])
			{
				// this returns the new content of the cookie, so use it
				set_cookie('vbulletin_multiquote', quote_xml.fetch_data(fetch_tags(quote_xml.handler.responseXML, 'mqpostids')[0]));
			}

			// remove the link to insert unquoted posts
			var unquoted_posts = fetch_object('unquoted_posts');
			if (unquoted_posts)
			{
				unquoted_posts.style.display = 'none';
			}
		}

		if (is_ie)
		{
			quote_xml.handler.abort();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14997 $
|| ####################################################################
\*======================================================================*/