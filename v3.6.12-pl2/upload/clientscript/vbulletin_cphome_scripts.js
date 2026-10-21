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

// #############################################################################
// newer version detector
if (typeof(vb_version) != "undefined" && isNewerVersion(current_version, vb_version))
{
	t = fetch_object('news_table');
		t_head_r = t.insertRow(0);
			t_head_c = t_head_r.insertCell(0);
			t_head_c.className = 'thead';
			t_head_c.innerHTML = newer_version_string.bold();
		t_body_r = t.insertRow(1);
			t_body_c = t_body_r.insertCell(0);
			t_body_c.className = 'alt1';
				t_body_p1 = document.createElement('p');
				t_body_p1.className = 'smallfont';
					t_body_a1 = document.createElement('a');
					t_body_a1.href = 'http://www.vbulletin.com/forum/showthread.ph' + 'p?p=' + vb_announcementid;
					t_body_a1.target = '_blank';
					t_body_a1.innerHTML = construct_phrase(latest_string, vb_version).bold();
				t_body_p1.appendChild(t_body_a1);
				t_body_p1.innerHTML += '. ' + construct_phrase(current_string, current_version.bold()) + '.';
			t_body_c.appendChild(t_body_p1);
				t_body_p2 = document.createElement('p');
				t_body_p2.className = 'smallfont';
					t_body_a2 = document.createElement('a');
					t_body_a2.href = 'http://members.vbulletin.com/';
					t_body_a2.target = '_blank';
					t_body_a2.innerHTML = construct_phrase(download_string, vb_version.bold());
				t_body_p2.appendChild(t_body_a2);
			t_body_c.appendChild(t_body_p2);
}

// #############################################################################
function create_cp_table(tableid)
{
	var t = document.createElement('table');

	t.cellPadding = 4;
	t.cellSpacing = 0;
	t.border = 0;
	t.align = 'center';
	t.width = '90%';
	t.className = 'tborder';

	if (tableid)
	{
		t.id = tableid;
	}

	return t;
}

// #############################################################################
function news_loader_onreadystatechange()
{
	if (news.handler.readyState == 4 && news.handler.status == 200 && news.handler.responseXML)
	{
		var visible_messages = done_table; // if there's a table, we're already displaying messages
		var table_visible = false;
		var news_id = '';
		var news_date = '';
		var news_title = '';
		var news_body = '';
		var news_link = '';

		var news_items = fetch_tags(news.handler.responseXML, 'item');

		if (done_table)
		{
			t = fetch_object('news_table');
			table_visible = true;
		}
		else
		{
			// table is there we've just hidden it
			t = fetch_object('news_table');

			if (news_items.length)
			{
				// no point in displaying if there's nothing to add
				fetch_object('admin_news').style.display = '';

				th = t.insertRow(0);
				cell = th.insertCell(0);
				cell.className = 'tcat';
				cell.align = 'center';
				cell.innerHTML = news_header_string.bold();

				table_visible = true;
			}
		}

		for (var i = 0; i < news_items.length; i++)
		{
			news_id = news.fetch_data(fetch_tags(news_items[i], 'guid')[0]);

			if (PHP.in_array(news_id, dismissed_news) == -1)
			{
				visible_messages = true;

				news_date = news.fetch_data(fetch_tags(news_items[i], 'pubdate')[0]);
				news_title = news.fetch_data(fetch_tags(news_items[i], 'title')[0]);
				news_body = news.fetch_data(fetch_tags(news_items[i], 'description')[0]);
				news_link = news.fetch_data(fetch_tags(news_items[i], 'link')[0]);

				var local_news_matches = news_body.match(/\[local\]((?!\[\/local\]).)*\[\/local\]/g);
				if (local_news_matches != null)
				{
					sessionurl = (SESSIONHASH == '' ? '' : 's=' + SESSIONHASH + '&');
					var local_match_number;
					for (local_match_number = 0; local_match_number < local_news_matches.length; local_match_number++)
					{
						news_body = news_body.replace(local_news_matches[local_match_number], local_news_matches[local_match_number].replace(/^\[local\](.*)\.php(\??)(.*)\[\/local\]$/, '$1' + local_extension + '?' + sessionurl + '$3'));
					}
				}

				r1 = t.insertRow(t.rows.length);
				r1.id = 'r1_' + news_id;
					c1 = r1.insertCell(0);
					c1.className = 'thead';
						s = document.createElement('input');
						s.type = 'submit';
						s.name = 'acpnews[' + news_id + ']';
						s.className = 'button';
						if (is_ie)
						{
							s.style.styleFloat = stylevar_right;
						}
						else
						{
							s.style.cssFloat = stylevar_right;
						}
						s.title = "id=" + news_id;
						s.value = dismiss_string;
					c1.appendChild(s);
						t1 = document.createTextNode(construct_phrase(vbulletin_news_string, news_title));
					c1.appendChild(t1);
				r2 = t.insertRow(t.rows.length);
				r2.id = 'r2_' + news_id;
					c2 = r2.insertCell(0);
					c2.className = 'alt2 smallfont';
					c2.innerHTML = news_body + ' ';
					if (news_link && news_link != 'http://')
					{
						link_elem = document.createElement('a');
						link_elem.href = news_link;
						link_elem.target = '_blank';
						link_elem.innerHTML = view_string.bold();
						c2.appendChild(link_elem);
					}
			}
		}

		if (is_ie)
		{
			news.handler.abort();
		}

		if (table_visible)
		{
			if (news_items.length)
			{
				// row to display all (even dismissed news)
				r3 = t.insertRow(t.rows.length);
				c3 = r3.insertCell(0);
				c3.className = (visible_messages ? 'tfoot' : 'alt1');
				c3.align = 'center';
				a = document.createElement('a');
				a.href = show_all_news_link;
				a.innerHTML = show_all_news_string;

				// workaround an issue where the link color isn't applied properly
				if (c3.currentStyle)
				{
					a.style.color = c3.currentStyle.color;
				}
				else if (window.getComputedStyle && window.getComputedStyle(c3, null))
				{
					a.style.color = window.getComputedStyle(c3, null).color;
				}

				c3.appendChild(a);
			}


			// little bit of code to redo all the inserted table rows
			var rows = fetch_tags(fetch_object('news_table'), 'td');
			var last_row = 'alt1';
			for (var i = 0; i < rows.length; i++)
			{
				if (rows[i].className == 'alt1' || rows[i].className == 'alt2')
				{
					last_row = rows[i].className;
				}
				else if (rows[i].className == 'alt2 smallfont')
				{
					if (last_row == 'alt1')
					{
						last_row = 'alt2';
					}
					else
					{
						rows[i].className = 'alt1 smallfont';
						last_row = 'alt1';
					}
				}
			}
		}

		//fetch_object('admin_news').style.height = '300px';
		//fetch_object('admin_news').style.overflowY = 'scroll';
	}
}

// #############################################################################
if (AJAX_Compatible)
{
	dismissed_news = dismissed_news.split(',');
	var news = new vB_AJAX_Handler(true);
	news.onreadystatechange(news_loader_onreadystatechange);
	news.send('./newsproxy.php', '');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 26289 $
|| ####################################################################
\*======================================================================*/