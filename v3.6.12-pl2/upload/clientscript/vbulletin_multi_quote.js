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
* Initializes the MQ images, so they are clickable. Additionally, it redoes
* the coloring of the image based on the current value of the cookie. This
* is helpful if a user uses the back button.
*
* @param	object	The object to search within for MQ images.
*/
function mq_init(obj)
{
	var cookie_ids = fetch_cookie('vbulletin_multiquote');
	if (cookie_ids != null && cookie_ids != '')
	{
		cookie_ids = cookie_ids.split(',');
	}
	else
	{
		cookie_ids = new Array();
	}

	var postid;

	var images = fetch_tags(obj, 'img');
	for (var i = 0; i < images.length; i++)
	{
		if (images[i].id && images[i].id.substr(0, 3) == 'mq_')
		{
			postid = images[i].id.substr(3);
			images[i].onclick = function(e) { return mq_click(this.id.substr(3)); };
			change_mq_image(postid, (PHP.in_array(postid, cookie_ids) > -1 ? true : false));
		}
	}
}

/**
* Callback function to when an MQ image is clicked. Modifies the cookie and
* updates the look of the image to suit.
*
* @param	integer	Post ID of the image clicked.
*
* @return	false	Always returns false to ensure any href event does not run
*/
function mq_click(postid)
{
	var cookie_ids = fetch_cookie('vbulletin_multiquote');

	var cookie_text = new Array();
	var is_selected = false;

	if (cookie_ids != null && cookie_ids != '')
	{
		cookie_ids = cookie_ids.split(',');

		for (i in cookie_ids)
		{
			if (cookie_ids[i] == postid)
			{
				is_selected = true;
			}
			else if (cookie_ids[i])
			{
				cookie_text.push(cookie_ids[i]);
			}
		}
	}

	// flip the image to the other option
	change_mq_image(postid, (is_selected ? false : true));

	// if we don't have the postid in the cookie, add it
	if (!is_selected)
	{
		cookie_text.push(postid);
		if (typeof mqlimit != 'undefined' && mqlimit > 0)
		{
			for (var i = 0; i < (cookie_text.length - mqlimit); i++)
			{
				var removal = cookie_text.shift();
				change_mq_image(removal, false);
			}
		}
	}

	set_cookie('vbulletin_multiquote', cookie_text.join(','));

	return false;
}

/**
* Changes the MQ image to show as being selected or unselected
*
* @param	integer	ID of the post whose MQ button is changing
* @param	boolean	Whether to make the image selected or not
*/
function change_mq_image(postid, to_selected)
{
	var mq_obj = fetch_object('mq_' + postid);
	if (mq_obj)
	{
		if (to_selected == true)
		{
			mq_obj.src = mq_obj.src.replace(/\/multiquote_off\.([a-zA-Z0-9]+)$/, '/multiquote_on.$1');
		}
		else
		{
			mq_obj.src = mq_obj.src.replace(/\/multiquote_on\.([a-zA-Z0-9]+)$/, '/multiquote_off.$1');
		}
	}
}

mq_init(fetch_object('posts'));

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15175 $
|| ####################################################################
\*======================================================================*/