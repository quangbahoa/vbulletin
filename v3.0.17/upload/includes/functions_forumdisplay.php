<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.0.17 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Start getDotThreads #######################
// --> Queries a list of given ids and generates an array of ids that the user has posted in
function fetch_dot_threads_array($ids)
{
	global $DB_site, $bbuserinfo, $vboptions;

	if ($ids AND $vboptions['showdots'] AND $bbuserinfo['userid'])
	{
		$dotthreads = array();
		$mythreads = $DB_site->query("
			SELECT COUNT(*) AS count, threadid, MAX(dateline) AS lastpost
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (post.postid = deletionlog.primaryid AND type = 'post')
			WHERE post.userid = $bbuserinfo[userid] AND
			post.visible = 1 AND
			post.threadid IN (0$ids) AND
			deletionlog.primaryid IS NULL
			GROUP BY threadid
		");

		while ($mythread = $DB_site->fetch_array($mythreads))
		{
			$dotthreads["$mythread[threadid]"]['count'] = $mythread['count'];
			$dotthreads["$mythread[threadid]"]['lastpost'] = vbdate($vboptions['dateformat'], $mythread['lastpost'], true);
		}

		return $dotthreads;
	}

	return false;

}

// ###################### Start parseThreadData #######################
// translate stuff from the db into data for a template like threadbit
// note: this function requires the use of $iconcache - include it in $specialtemplates!
function process_thread_array($thread, $lastread = -1, $allowicons = -1)
{
	global $bbuserinfo, $vboptions, $vbphrase, $stylevar, $foruminfo, $forumcache, $iconcache;
	global $_FORUMOPTIONS, $newthreads, $dotthreads, $perpage, $ignore, $show;
	static $pperpage;

	if ($pperpage == 0)
	{ // lets calculate posts per page
		// the following code should be left just in case we plan to use this function in showthread at some point

		$max = intval(max(explode(',', $vboptions['usermaxposts'])));

		if ($bbuserinfo['maxposts'] != -1 AND $bbuserinfo['maxposts'] AND $max)
		{
			$bbuserinfo['maxposts'] = ($max > $bbuserinfo['maxposts']) ? $bbuserinfo['maxposts'] : $max;
		}
		else
		{
			$bbuserinfo['maxposts'] = $vboptions['maxposts'];
		}

		if (THIS_SCRIPT != 'showthread')
		{
			// the goal here is to use $bbuserinfo['maxposts'] unless it exceeds max($vboptions['usermaxposts']) and in that case use max($vboptions['usermaxposts'])
			$pperpage = $bbuserinfo['maxposts'];
		}
		else
		{
			$pperpage = $perpage;
		}

		if ($pperpage < 1)
		{
			$pperpage = $vboptions['maxposts'];
		}
	}

	if ($allowicons == -1)
	{
		$allowicons = $forumcache["$thread[forumid]"]['options'] & $_FORUMOPTIONS['allowicons'];
	}

	if ($lastread == -1)
	{
		$lastread = $bbuserinfo['lastvisit'];
	}

	$show['paperclip'] = false;
	$show['unsubscribe'] = false;

	// thread forumtitle
	if (empty($thread['forumtitle']))
	{
		$thread['forumtitle'] = $forumcache["$thread[forumid]"]['title'];
	}

	// word wrap title
	if ($vboptions['wordwrap'] != 0)
	{
		$thread['threadtitle'] = fetch_word_wrapped_string($thread['threadtitle']);
	}

	$thread['threadtitle'] = fetch_censored_text($thread['threadtitle']);

	// format thread preview if there is one
	if ($ignore["$thread[postuserid]"])
	{
		$thread['preview'] = '';
	}
	else if (isset($thread['preview']) AND $vboptions['threadpreview'] > 0)
	{
		$thread['preview'] = strip_quotes($thread['preview']);
		$thread['preview'] = htmlspecialchars_uni(fetch_trimmed_title(strip_bbcode($thread['preview'], false, true), $vboptions['threadpreview']));
	}

	// thread last reply date/time
	$thread['lastpostdate'] = vbdate($vboptions['dateformat'], $thread['lastpost'], true);
	$thread['lastposttime'] = vbdate($vboptions['timeformat'], $thread['lastpost']);

	// post reply date/time (for search results as posts mainly)
	if ($thread['postdateline'])
	{
		$thread['postdate'] = vbdate($vboptions['dateformat'], $thread['postdateline'], true);
		$thread['posttime'] = vbdate($vboptions['timeformat'], $thread['postdateline']);
	}
	else
	{
		$thread['postdate'] = '';
		$thread['posttime'] = '';
	}

	// thread not moved
	if ($thread['open'] != 10)
	{
		// allow ratings?
		if ($foruminfo['allowratings'])
		{
			// show votes?
			if ($thread['votenum'] >= $vboptions['showvotes'])
			{
				$thread['rating'] = intval(round($thread['voteavg']));
			}
			// do not show votes
			else
			{
				$thread['rating'] = 0;
			}
		}
		// do not allow ratings
		else
		{
			 $thread['rating'] = 0;
			 $thread['votenum'] = 0;
		}

		// sticky thread?
		if ($thread['sticky'])
		{
			$show['sticky'] = true;
			$thread['typeprefix'] = $vbphrase['sticky_thread_prefix'];
		}
		else
		{
			$show['sticky'] = false;
			$thread['typeprefix'] = '';
		}

		// thread contains poll?
		if ($thread['pollid'] != 0)
		{
			$thread['typeprefix'] .= $vbphrase['poll_thread_prefix'];
		}

		// multipage nav
		$thread['totalposts'] = $thread['replycount'] + 1;
		$total = &$thread['totalposts'];
		if (($vboptions['allowthreadedmode'] == 0 OR ($bbuserinfo['threadedmode'] == 0 AND empty($_COOKIE[COOKIE_PREFIX . 'threadedmode'])) OR $_COOKIE[COOKIE_PREFIX . 'threadedmode'] == 'linear') AND $thread['totalposts'] > $bbuserinfo['maxposts'] AND $vboptions['linktopages'])
		{
			$totalpages = ceil($thread['totalposts'] / $pperpage);
			$address = "showthread.php?$session[sessionurl]t=$thread[threadid]";
			$address2 = "$thread[highlight]";
			$curpage = 0;

			$thread['pagenav'] = '';
			$show['pagenavmore'] = false;

			while ($curpage++ < $totalpages)
			{
				if ($vboptions['maxmultipage'] AND $curpage > $vboptions['maxmultipage'])
				{
					$show['pagenavmore'] = true;
					break;
				}

				$pagenumbers = fetch_start_end_total_array($curpage, $pperpage, $thread['totalposts']);
				eval('$thread[pagenav] .= "' . fetch_template('threadbit_pagelink') . '";');
			}

		}
		// do not show pagenav
		else
		{
			$thread['pagenav'] = '';
		}

		// allow thread icons?
		if ($allowicons)
		{
			// get icon from icon cache
			if ($thread['threadiconid'])
			{
				$thread['threadiconpath'] = $iconcache["$thread[threadiconid]"]['iconpath'];
				$thread['threadicontitle'] = $iconcache["$thread[threadiconid]"]['title'];
			}

			// show poll icon
			if ($thread['pollid'] != 0)
			{
				$show['threadicon'] = true;
				$thread['threadiconpath'] = "$stylevar[imgdir_misc]/poll_posticon.gif";
				$thread['threadicontitle'] = $vbphrase['poll'];
			}
			// show specified icon
			else if ($thread['threadiconpath'])
			{
				$show['threadicon'] = true;
			}
			// show default icon
			else if (!empty($vboptions['showdeficon']))
			{
				$show['threadicon'] = true;
				$thread['threadiconpath'] = $vboptions['showdeficon'];
				$thread['threadicontitle'] = '';
			}
			// do not show icon
			else
			{
				$show['threadicon'] = false;
				$thread['threadiconpath'] = '';
				$thread['threadicontitle'] = '';
			}
		}
		// do not allow icons
		else
		{
			$show['threadicon'] = false;
			$thread['threadiconpath'] = '';
			$thread['threadicontitle'] = '';
		}

		// thread has attachment?
		if ($thread['attach'] > 0)
		{
			$show['paperclip'] = true;
		}

		// folder icon generation
		$thread['statusicon'] = '';

		// show dot folder?
		if ($bbuserinfo['userid'] AND $vboptions['showdots'] AND $dotthreads["$thread[threadid]"])
		{
			$thread['statusicon'] .= '_dot';
			$thread['dot_count'] = $dotthreads["$thread[threadid]"]['count'];
			$thread['dot_lastpost'] = $dotthreads["$thread[threadid]"]['lastpost'];
		}
		// show hot folder?
		if ($vboptions['usehotthreads'] AND (($thread['replycount'] >= $vboptions['hotnumberposts'] AND $vboptions['hotnumberposts'] > 0) OR ($thread['views'] >= $vboptions['hotnumberviews'] AND $vboptions['hotnumberviews'] > 0)))
		{
			$thread['statusicon'] .= '_hot';
		}
		// show locked folder?
		if (!$thread['open'])
		{
			$thread['statusicon'] .= '_lock';
		}

		// show new folder?
		if ($thread['lastpost'] > $lastread)
		{
			$threadview = intval(fetch_bbarray_cookie('thread_lastview', $thread['threadid']));

			if ($thread['lastpost'] > $threadview)
			{
				$thread['statusicon'] .= '_new';
				$show['gotonewpost'] = true;
			}
			else
			{
				$newthreads--;
				$show['gotonewpost'] = false;
			}
		}
		else
		{
			$show['gotonewpost'] = false;
		}

		// format numbers nicely
		$thread['replycount'] = vb_number_format($thread['replycount']);
		$thread['views'] = vb_number_format($thread['views']);
		$show['threadmoved'] = false;
	}
	// thread moved?
	else
	{
		// thread has been moved!
		$thread['threadid'] = $thread['pollid'];
		$thread['replycount'] = '-';
		$thread['views'] = '-';
		$show['threadicon'] = false;
		$thread['statusicon'] = '_moved' . iif($thread['lastpost'] > $lastread, '_new');
		$thread['pagenav'] = '';
		$thread['movedprefix'] = $vbphrase['moved_thread_prefix'];
		$thread['rating'] = 0;
		$thread['votenum'] = 0;
		$thread['pagenav'] = '';
		$show['gotonewpost'] = false;
		$thread['showpagenav'] = false;
		$show['sticky'] = false;
		$show['threadmoved'] = true;
	}

	$show['subscribed'] = iif ($thread['issubscribed'], true, false);
	$show['pagenav'] = iif ($thread['pagenav'] != '', true, false);
	$show['guestuser'] = iif (!$thread['postuserid'], true, false);
	$show['threadrating'] = iif ($thread['rating'] > 0, true, false);
	$show['threadcount'] = iif ($thread['dot_count'], true, false);

	return $thread;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12825 $
|| ####################################################################
\*======================================================================*/
?>