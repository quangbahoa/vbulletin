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
* Adds onclick events to appropriate elements for AJAX IP resolving
*
* @param	string	The ID of the table that contains WOL entries with IPs to resolve
*/
function vB_AJAX_WolResolve_Init(woltableid)
{
	if (AJAX_Compatible && (typeof vb_disable_ajax == 'undefined' || vb_disable_ajax < 2))
	{
		var link_list = fetch_tags(fetch_object(woltableid), 'a');
		for (var i = 0; i < link_list.length; i++)
		{
			if (link_list[i].id && link_list[i].id.substr(0, 10) == 'resolveip_' && link_list[i].innerHTML.match(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/))
			{
				// innerHTML is the ip address
				link_list[i].onclick = resolve_ip_click;
			}
		}
	}
}

/**
* Class to handle resolving IP addresses to host names with AJAX
*
* @param	string	The IP to resolve
* @param	string	The ID of the object that the resolved IP will replace
*/
function vB_AJAX_WolResolve(ip, objid)
{
	this.ip = ip;
	this.objid = objid;
	this.xml_sender = null;

	var me = this;

	/**
	* Resolves the IP using AJAX
	*/
	this.resolve = function()
	{
		this.xml_sender = new vB_AJAX_Handler(true);
		this.xml_sender.onreadystatechange(this.onreadystatechange);
		this.xml_sender.send('online.php?do=resolveip&ipaddress=' + PHP.urlencode(this.ip), 'do=resolveip&ajax=1&ipaddress=' + PHP.urlencode(this.ip));
	}

	/**
	* OnReadyStateChange callback. Uses a closure to keep state.
	* Remember to use me instead of this inside this function!
	*/
	this.onreadystatechange = function()
	{
		if (me.xml_sender.handler.readyState == 4 && me.xml_sender.handler.status == 200)
		{
			if (me.xml_sender.handler.responseXML)
			{
				var obj = fetch_object(me.objid);
				obj.parentNode.insertBefore(document.createTextNode(me.xml_sender.fetch_data(fetch_tags(me.xml_sender.handler.responseXML, 'ipaddress')[0])), obj);

				// might need to display the IP still instead of removing it... we'll wait and see.
				obj.parentNode.removeChild(obj);
			}

			if (is_ie)
			{
				me.xml_sender.handler.abort();
			}
		}
	}
}

/**
* Handles click events on resolve IP links
*/
function resolve_ip_click(e)
{
	var resolver = new vB_AJAX_WolResolve(this.innerHTML, this.id);
	resolver.resolve();
	return false;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14539 $
|| ####################################################################
\*======================================================================*/