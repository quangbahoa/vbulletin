// #############################################################################
// work around to fetch the contents of the selection
function moz_get_html()
{
	var sel = htmlwindow.getSelection();
	var range = null;
	htmlwindow.focus();
	range = sel ? sel.getRangeAt(0) : htmlbox.createRange();
	var root = range.cloneContents();
	return moz_read_nodes(root, false);
}

// #############################################################################
// emulation of PHP htmlspecialchars function
function htmlspecialchars(html)
{
	html = html.replace(/"/, "&quot;");
	html = html.replace(/</, "&lt;");
	html = html.replace(/>/, "&gt;");
	return html;
}

// #############################################################################
// read through the nodes obtaining the content
function moz_read_nodes(root, toptag)
{
	var html = "";
	var moz_check = /_moz/i;

	switch (root.nodeType)
	{
		case Node.ELEMENT_NODE:
		case Node.DOCUMENT_FRAGMENT_NODE:
		{
			var closed;
			if (toptag)
			{
				closed = !root.hasChildNodes();
				html = '<' + root.tagName.toLowerCase();
				var attr = root.attributes;
				for (i = 0; i < attr.length; ++i)
				{
					var a = attr.item(i);
					if (!a.specified || a.name.match(moz_check) || a.value.match(moz_check))
					{
						continue;
					}
	
					html += " " + a.name.toLowerCase() + '="' + a.value + '"';
				}
				html += closed ? " />" : ">";
			}
			for (var i = root.firstChild; i; i = i.nextSibling)
			{
				html += moz_read_nodes(i, true);
			}
			if (toptag && !closed)
			{
				html += "</" + root.tagName.toLowerCase() + ">";
			}
		}
		break;

		case Node.TEXT_NODE:
		{
			html = htmlspecialchars(root.data);
		}
		break;
	}
	return html;
}

// #############################################################################
// insert node at a point, based on example provided with the Midas demo
function moz_insert_node_at_selection(text)
{
	var sel = htmlwindow.getSelection();
	var range = null;
	htmlwindow.focus();
	range = sel ? sel.getRangeAt(0) : htmlbox.createRange();
	sel.removeAllRanges();
	range.deleteContents();

	var node = range.startContainer;
	var pos = range.startOffset;

	switch (node.nodeType)
	{
		case Node.ELEMENT_NODE:
		{
			if (text.nodeType == Node.DOCUMENT_FRAGMENT_NODE)
			{
				selNode = text.firstChild;
			}
			else
			{
				selNode = text
			}
			node.insertBefore(text, node.childNodes[pos]);
			add_range(selNode);
		}
		break;

		case Node.TEXT_NODE:
		{
			if (text.nodeType == Node.TEXT_NODE)
			{
				var text_length = pos + text.length;
				node.insertData(pos, text.data);
				range = htmlbox.createRange();
				range.setEnd(node, text_length);
				range.setStart(node, text_length);
				sel.addRange(range);
			}
			else
			{
				node = node.splitText(pos);
				var selNode;
				if (text.nodeType == Node.DOCUMENT_FRAGMENT_NODE)
				{
					selNode = text.firstChild;
				}
				else
				{
					selNode = text;
				}
				node.parentNode.insertBefore(text, node);
				add_range(selNode);
			}
		}
		break;
	}
}

// #############################################################################
// correctly add the range of inserted text
function add_range(node)
{
	htmlwindow.focus();
	var sel = htmlwindow.getSelection();
	var range = null;
	range = htmlbox.createRange();
	range.selectNodeContents(node);
	sel.removeAllRanges();
	sel.addRange(range);
}

// #############################################################################
// add a smilie since this has to be inserted as a node
function moz_insert_smilie(smilieHTML)
{
	var frag = htmlbox.createDocumentFragment();
	var span = htmlbox.createElement("span");
	span.innerHTML = smilieHTML;
	
	while (span.firstChild)
	{
		frag.appendChild(span.firstChild);
	}
	
	moz_insert_node_at_selection(frag);
}

// #############################################################################
// intercept keypress with certain functions so that it works like IE
function moz_intercept_keys(e)
{
	if (e.ctrlKey)
	{
		switch (String.fromCharCode(e.charCode).toLowerCase())
		{
		    case "b":
			{
				htmlbox.execCommand("bold", false, null);
			}
			break;
			
		    case "i":
			{
				htmlbox.execCommand("italic", false, null);
			}
			break;
			
		    case "u":
			{
				htmlbox.execCommand("underline", false, null);
			}
			break;
			
			default: return;
		}
		e.preventDefault();
	}
	else if (e.keyCode == 9)
	{
		// first lets try post icon, then submit, then just let it proceed making the tab
		if (object_exists('rb_iconid_0'))
		{
			fetch_object('rb_iconid_0').focus();
		}
		else if (sbutt = document.getElementsByName('sbutton'))
		{
			sbutt.item(0).focus();
		} 
		else
		{
			return;
		}
		e.preventDefault();
	}
}

// #############################################################################
function moz_get_font_size(pointsize)
{
	switch (pointsize)
	{
		case "7.5pt":
		case "10px": return 1;
		case "10pt": return 2;
		case "12pt": return 3;
		case "14pt": return 4;
		case "18pt": return 5;
		case "24pt": return 6;
		case "36pt": return 7;
		default: return "";
	}
}