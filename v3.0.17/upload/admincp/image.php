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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 15854 $');
define('NO_REGISTER_GLOBALS', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('attachment_image', 'cppermission');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminimages'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
// <!-- Missing -->

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

/*
NOTE:
	for use in imagecategory table:
	imagetype = 1 => avatar
	imagetype = 2 => icon
	imagetype = 3 => smilie
*/

// make sure we are dealing with avatars,smilies or icons
globalize($_REQUEST, array('table' => STR));
switch($table)
{
	case 'avatar':
		$itemtype = 'avatar';
		$itemtypeplural = 'avatars';
		$catid = 1;
		break;
	case 'icon':
		$itemtype = 'post_icon';
		$itemtypeplural = 'post_icons';
		$catid = 2;
		break;
	case 'smilie':
		$itemtype = 'smilie';
		$itemtypeplural = 'smilies';
		$catid = 3;
		break;
	default:
		print_cp_header($vbphrase['error']);
		print_stop_message('invalid_table_specified');
		break;
}

print_cp_header($vbphrase["$itemtypeplural"]);
log_admin_action($vbphrase["$itemtypeplural"] . iif($_REQUEST['id'] != 0, " id = $_REQUEST[id]"));

$tables = array('avatar' => $vbphrase['avatar'], 'icon' => $vbphrase['post_icon'], 'smilie' => $vbphrase['smilie']);

$itemid = $table . 'id';
$itempath = $table . 'path';

// ************************************************************
// start functions

$img_per_row = 5;

// ###################### Start showimage #######################
function construct_img_html($imagepath)
{
	// returns an image based on imagepath
	return '<img src="' . iif(substr($imagepath, 0, 7) != 'http://' AND substr($imagepath, 0, 1) != '/', '../', '') . "$imagepath\" alt=\"$imagepath\" align=\"middle\" />";
}

// ###################### Start makeitemrow #######################
function print_image_item_row(&$cell)
{
// returns a row of five cells for use in $do==viewimages
	global $img_per_row;
	$cells = $img_per_row - sizeof($cell);
	for ($i=0; $i < $cells; $i++)
	{
		$cell[] = '';
	}
	print_cells_row($cell, 0, 0, 1, 'bottom');
	$cell = array();
}

// ###################### Start displayitem #######################
function print_image_item($item, $massmove = false)
{
	// displays an item together with links to edit/remove
	global $session, $table, $itemid, $itempath, $page, $perpage, $vbphrase, $catid;
	static $categories;

	if (!$massmove)
	{
		$out = "<b>$item[title]</b><br /><br />" . construct_img_html($item["$itempath"]) . '<br />' . iif($table == 'smilie', " <span class=\"smallfont\">$item[smilietext]</span>") . '<br />';
		$out .= construct_link_code($vbphrase['edit'], "image.php?$session[sessionurl]do=edit&table=$table&id=$item[$itemid]&perpage=$perpage&page=$page");
		$out .= construct_link_code($vbphrase['delete'], "image.php?$session[sessionurl]do=remove&table=$table&id=$item[$itemid]&perpage=$perpage&page=$page");
		$out .= " <input type=\"text\" class=\"bginput\" name=\"order[" . $item["$itemid"] . "]\" tabindex=\"1\" value=\"$item[displayorder]\" size=\"2\" title=\"" . $vbphrase['display_order'] . "\" class=\"smallfont\" /> ";
	}
	else
	{

		if (!$categories)
		{
			$categories = '<option value="0"></option>';
			$categories .= construct_select_options(fetch_image_categories_array($catid));
		}
		$title = iif($item['title'], "<a href=\"image.php?$session[sessionurl]do=edit&amp;table=$table&amp;id=$item[$itemid]&amp;perpage=$perpage&amp;page=$page&amp;massmove=$massmove\">$item[title]</a>", construct_link_code($vbphrase['edit'], "image.php?$session[sessionurl]do=edit&amp;table=$table&amp;id=$item[$itemid]&amp;perpage=$perpage&amp;page=$page&amp;massmove=$massmove"));
		$out = "<b>" . $title . "</b><br /><br />" . construct_img_html($item["$itempath"]) . '<br />' . iif($table == 'smilie', " <span class=\"smallfont\">$item[smilietext]</span>") . '<br />';
		$out .= '<select name="category[' . $item["$itemid"] . ']" class="bginput">' . $categories . '</select>';
	}

	return $out;
}

// ###################### Start getimagecategories #######################
function fetch_image_categories_array($catid)
{
// returns an array of imagecategoryid => title for use in <select> lists
	global $DB_site, $cats;
	if (!is_array($cats))
	{
		$categories = $DB_site->query("
			SELECT imagecategoryid,title
			FROM " . TABLE_PREFIX . "imagecategory
			WHERE imagetype = $catid ORDER BY displayorder
		");
		$cats = array();
		while ($category = $DB_site->fetch_array($categories))
		{
			$cats[$category['imagecategoryid']] = $category['title'];
		}
		$DB_site->free_result($categories);
	}
	return $cats;
}

// end functions
// ************************************************************

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

// ###################### Start Update Permissions #######################
if ($_POST['do'] == 'updatepermissions')
{
	globalize($_POST, array(
		'imagecategoryid' => INT,
		'iperm',
	));

	$categoryinfo = verify_id('imagecategory', $imagecategoryid, 0, 1);

	if ($categoryinfo['imagetype'] == 3)
	{
		print_stop_message('smilie_categories_dont_support_permissions');
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "imagecategorypermission WHERE imagecategoryid=$imagecategoryid");

	foreach($iperm AS $usergroupid => $canuse)
	{
		if ($canuse == 0)
		{
			$DB_site->query("INSERT INTO " . TABLE_PREFIX . "imagecategorypermission (imagecategoryid,usergroupid) VALUES ($imagecategoryid,$usergroupid)");
		}
	}

	define('CP_REDIRECT', 'image.php?do=modify&amp;table=' . $table);
	print_stop_message('saved_permissions_successfully');
}

// ###################### Start Edit Permissions #######################
if ($_REQUEST['do'] == 'editpermissions')
{
	globalize($_REQUEST, array(
		'imagecategoryid' => INT
	));

	$categoryinfo = verify_id('imagecategory', $imagecategoryid, 0, 1);
	if ($categoryinfo['imagetype'] == 3)
	{
		print_stop_message('smilie_categories_dont_support_permissions');
	}

	$usergroups = $DB_site->query("
		SELECT usergroup.*, imagecategoryid AS nopermission FROM " . TABLE_PREFIX . "usergroup AS usergroup
		LEFT JOIN " . TABLE_PREFIX . "imagecategorypermission AS imgperm ON
		(imgperm.usergroupid = usergroup.usergroupid AND imgperm.imagecategoryid = $imagecategoryid)
		ORDER BY title
	");

	print_form_header('image', 'updatepermissions');
	construct_hidden_code('table', $table);
	construct_hidden_code('imagecategoryid', $imagecategoryid);
	print_table_header(construct_phrase($vbphrase["permissions_for_{$itemtype}_category_x"], $categoryinfo['title']));
	print_label_row('<span class="smallfont"><b>' . $vbphrase['usergroup'] . '</b></span>', '<span class="smallfont"><b>' . $vbphrase["can_use_this_{$itemtype}_category"] . '</b></span>');
	while ($usergroup = $DB_site->fetch_array($usergroups))
	{
		$usergroupid = $usergroup['usergroupid'];
		$canuse = iif($usergroup['nopermission'], 0, 1);
		print_yes_no_row($usergroup['title'], "iperm[$usergroupid]", $canuse);
	}
	print_submit_row($vbphrase['save']);

}

// ###################### Start Kill Category #######################
if ($_POST['do'] == 'killcategory')
{
	globalize($_POST, array(
		'imagecategoryid' => INT,
		'destinationid' => INT,
		'deleteitems' => INT
	));

	if ($deleteitems == 1)
	{
		$DB_site->query("DELETE FROM " . TABLE_PREFIX . "$table WHERE imagecategoryid = $imagecategoryid");
		$extra = $vbphrase["{$itemtypeplural}_deleted"];
	}
	else
	{
		$dest = $DB_site->query_first("
			SELECT title
			FROM " . TABLE_PREFIX . "imagecategory
			WHERE imagecategoryid = $imagecategoryid
		");
		$DB_site->query("
			UPDATE " . TABLE_PREFIX . "$table
			SET imagecategoryid = $destinationid
			WHERE imagecategoryid = $imagecategoryid
		");
		$extra = $vbphrase["{$itemtypeplural}_deleted"];
	}

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "imagecategory WHERE imagecategoryid = $imagecategoryid");

	build_image_cache($table);

	### KIER LOOK HERE ###
	define('CP_REDIRECT', "image.php?do=modify&table=$table");
	print_stop_message('deleted_category_successfully');
	### END LOOK HERE ###
}

// ###################### Start Remove Category #######################
if ($_REQUEST['do'] == 'removecategory')
{
	globalize($_REQUEST, array(
		'imagecategoryid' => INT
	));

	$categories = $DB_site->query("
		SELECT * FROM " . TABLE_PREFIX . "imagecategory
		WHERE imagetype = $catid ORDER BY displayorder
	");
	if ($DB_site->num_rows($categories) < 2)
	{
		print_stop_message("cant_remove_last_{$itemtype}_category");
	}
	else
	{
		$category = array();
		$destcats = array();
		while ($tmp = $DB_site->fetch_array($categories))
		{
			if ($tmp['imagecategoryid'] == $imagecategoryid)
			{
				$category = $tmp;
			}
			else
			{
				$destcats[$tmp['imagecategoryid']] = $tmp['title'];
			}
		}
		unset($tmp);
		$DB_site->free_result($categories);

		echo "<p>&nbsp;</p><p>&nbsp;</p>\n";

		print_form_header('image', 'killcategory');
		construct_hidden_code('imagecategoryid', $category['imagecategoryid']);
		construct_hidden_code('table', $table);
		print_table_header(construct_phrase($vbphrase["confirm_deletion_of_{$itemtype}_category_x"], $category['title']));
		print_description_row('<blockquote>' . construct_phrase($vbphrase["are_you_sure_you_want_to_delete_the_{$itemtype}_category_called_x"], $category['title'], construct_select_options($destcats)) . '</blockquote>');
		print_submit_row($vbphrase['delete'], '', 2, $vbphrase['go_back']);
	}

}

// ###################### Start Update Category #######################
if ($_POST['do'] == 'insertcategory')
{
	globalize($_REQUEST, array(
		'title' => STR_NOHTML,
		'displayorder' => INT
	));

	$DB_site->query("INSERT INTO " . TABLE_PREFIX . "imagecategory (
		imagecategoryid,title,imagetype,displayorder
	) VALUES (
		NULL, '" . addslashes($title) . "', $catid, $displayorder
	)");

	build_image_cache($table);

	define('CP_REDIRECT', "image.php?do=modify&table=$table");
	print_stop_message('saved_category_x_successfully', $title);
}

// ###################### Start Add Category #######################
if ($_REQUEST['do'] == 'addcategory')
{
	print_form_header('image', 'insertcategory');
	construct_hidden_code('table', $table);
	print_table_header($vbphrase["add_new_{$itemtype}_category"]);
	print_input_row($vbphrase['title'], 'title');
	print_input_row($vbphrase['display_order'], 'displayorder');
	print_submit_row($vbphrase['save']);
}

// ###################### Start Update Category #######################
if ($_POST['do'] == 'updatecategory')
{
	globalize($_POST, array(
		'imagecategoryid' => INT,
		'title' => STR_NOHTML,
		'displayorder' => INT,
	));

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "imagecategory SET
		title = '" . addslashes($title) . "',
		displayorder = $displayorder
		WHERE imagecategoryid = $imagecategoryid
	");

	build_image_cache($table);

	define('CP_REDIRECT', "image.php?do=modify&table=$table");
	print_stop_message('saved_category_x_successfully', $title);
}

// ###################### Start Edit Category #######################
if ($_REQUEST['do'] == 'editcategory')
{
	globalize($_REQUEST, array(
		'imagecategoryid' => INT
	));

	$category = $DB_site->query_first("
		SELECT * FROM " . TABLE_PREFIX . "imagecategory
		WHERE imagecategoryid = $imagecategoryid
	");

	print_form_header('image', 'updatecategory');
	construct_hidden_code('table', $table);
	construct_hidden_code('imagecategoryid', $category['imagecategoryid']);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase["{$itemtype}_category"], $category['title'], $category['imagecategoryid']));
	print_input_row($vbphrase['title'], 'title', $category['title'], 0);
	print_input_row($vbphrase['display_order'], 'displayorder', $category['displayorder']);
	print_submit_row();

}

// ###################### Start Update Smiley Category Display Order #######################
if ($_REQUEST['do'] == 'docategorydisplayorder')
{
	globalize($_REQUEST, array(
		'order'
	));

	if (is_array($order))
	{
		$categories = $DB_site->query("
			SELECT imagecategoryid,displayorder
			FROM " . TABLE_PREFIX . "imagecategory
			WHERE imagetype = $catid
		");
		while ($category = $DB_site->fetch_array($categories))
		{
			$displayorder = intval($order["$category[imagecategoryid]"]);
			if ($category['displayorder'] != $displayorder)
			{
				$DB_site->query("
					UPDATE " . TABLE_PREFIX . "imagecategory
					SET displayorder = $displayorder
					WHERE imagecategoryid = $category[imagecategoryid] AND
					imagetype = $catid
				");
			}
		}
	}

	define('CP_REDIRECT', "image.php?do=modify&amp;table=$table");
	print_stop_message('saved_display_order_successfully');
}

// ###################### Start Do Upload #######################
if ($_POST['do'] == 'doupload')
{
	globalize($_POST, array(
		'imagefile' => FILE,
		'imagespath' => STR,
		'title' => STR,
		'smilietext' => STR
	));

	if (is_uploaded_file($imagefile['tmp_name']))
	{
		if (empty($title) OR empty($imagespath) OR ($table == 'smilie' AND empty($smilietext)))
		{
			print_stop_message('please_complete_required_fields');
	 	}
		if (file_exists("./$imagespath/" . $imagefile['name']))
		{
			print_stop_message('file_x_already_exists', htmlspecialchars_uni($imagefile['name']));
		}

		$validfile = false;
		// Verify that file is playing nice
		$fp = fopen($attachment, 'rb');
		if ($fp)
		{
			$header = fread($fp, 200);
			fclose($fp);
			if (!preg_match('#<html|<head|<body|<script#si', $header))
			{
				$validfile = true;
			}
		}

		if (!$validfile OR !getimagesize($imagefile['tmp_name']))
		{
			print_stop_message('invalid_file_specified');
		}

		$result = copy($imagefile['tmp_name'], "./$imagespath/" . $imagefile['name']);
		if (!$result)
		{
			$error = ob_get_contents();
			print_stop_message('error_writing_x', htmlspecialchars_uni($imagefile['name']));
		}
		$imagespath .= '/' . $imagefile['name'];

		define('IMAGE_UPLOADED', true);
		$_POST['do'] = 'insert';
	}
	else
	{
		print_stop_message('invalid_file_specified');
	}

}

// ###################### Start Upload #######################
if ($_REQUEST['do'] == 'upload')
{
	print_form_header('image', 'doupload', 1);
	construct_hidden_code('table', $table);
	print_table_header($vbphrase["upload_{$itemtype}"]);
	print_upload_row($vbphrase['filename'], 'imagefile');
	print_input_row($vbphrase['title'], 'title');
	switch($table)
	{
		case 'avatar':
			print_input_row($vbphrase['minimum_posts'], 'minimumposts', 0);
			break;
		case 'smilie':
			print_input_row($vbphrase['text_to_replace'], 'smilietext');
			break;
	}
	print_input_row($vbphrase["{$itemtype}_file_path_dfn"], 'imagespath', 'images/' . $table . 's');
	print_label_row($vbphrase["{$itemtype}_category"], "<select name=\"imagecategoryid\" tabindex=\"1\" class=\"bginput\">" . construct_select_options(fetch_image_categories_array($catid), $item['imagecategoryid']) . '</select>', '', 'top', 'imagecategoryid');
	print_input_row($vbphrase['display_order'], 'displayorder', 1);
	print_submit_row($vbphrase['upload']);

}

// ###################### Start Kill #######################
if ($_POST['do'] == 'kill')
{
	globalize($_POST, array(
		'avatarid' => INT,
		'iconid' => INT,
		'smilieid' => INT,
		'page' => INT,
		'perpage' => INT,
	));

	if ($avatarid)
	{
		$id = $avatarid;
	}
	else if ($iconid)
	{
		$id = $iconid;
	}
	else if ($smilieid)
	{
		$id = $smilieid;
	}

	$image = $DB_site->query_first("SELECT imagecategoryid FROM " . TABLE_PREFIX . "$table WHERE $itemid = $id");
	$imagecategoryid = $image['imagecategoryid'];

	$DB_site->query("DELETE FROM " . TABLE_PREFIX . "$table WHERE $itemid = $id");

	define('CP_REDIRECT', "image.php?$session[sessionurl]do=viewimages&table=$table&amp;imagecategoryid=$imagecategoryid&amp;page=$page&amp;perpage=$perpage");
	print_stop_message("deleted_{$itemtype}_successfully");
}

// ###################### Start Remove #######################
if ($_REQUEST['do'] == 'remove')
{
	globalize($_REQUEST, array(
		'id' => INT,
		'page' => INT,
		'perpage' => INT,
	));

	$hidden = array(
		'table' => $table,
		'page' => $page,
		'perpage' => $perpage
	);

	print_delete_confirmation($table, $id, 'image', 'kill', $itemtype, $hidden);
}

// ###################### Start Do Insert Multiple #######################
if ($_POST['do'] == 'doinsertmultiple')
{
	globalize($_POST, array(
		'page' => INT,
		'perpage' => INT,
		'imagespath' => STR,
		'doinsertmultiple' => STR,
		'ititle',
		'icat',
		'ismilietext',
		'iminimumposts',
		'doimage'
	));

	if (empty($doinsertmultiple))
	{
		// just go back to the interface if a page button was pressed, rather than the actual submit button
		$_REQUEST['do'] = 'insertmultiple';
	}
	else if (!is_array($doimage))
	{
		// return error if no images checked for insertion
		print_stop_message("no_{$itemtypeplural}_selected");
	}
	else
	{
		echo "<ul>\n";
		foreach($doimage AS $path => $yes)
		{
			if ($yes)
			{
				$title = $ititle["$path"];
				$minimumposts = $iminimumposts["$path"];
				$smilietext = $ismilietext["$path"];
				$category = $icat["$path"];
				$path = $imagespath . '/' . urldecode($path);
				echo "\t<li>" . $vbphrase["processing_{$itemtype}"] . " ";
				$DB_site->query("
					INSERT INTO " . TABLE_PREFIX . "$table (
						$itemid,title," . iif($table == 'avatar','minimumposts,','') . iif($table == 'smilie','smilietext,','') . "$itempath,imagecategoryid,displayorder
					) VALUES (
						NULL,'" . addslashes($title) . "'," . iif($table == 'avatar', intval($minimumposts).',','') . iif($table == 'smilie',"'" . addslashes($smilietext) . "',",'')."'" . addslashes($path) . "'," . intval($category) . ",1
					)
				");
				echo $vbphrase['okay'] . ".</li>\n";
			}
		}
		echo "</ul>\n";

	}
	build_image_cache($table);
	$doneinsert = 1;
	$_REQUEST['do'] = 'insertmultiple';
}

// ###################### Start Insert Multiple #######################
if ($_REQUEST['do'] == 'insertmultiple')
{
	globalize($_REQUEST, array(
		'imagespath' => STR,
		'perpage' => INT,
		'imagecategoryid' => INT
	));

	$imagespath = preg_replace('/\/$/s', '', $imagespath);

	// try to open the specified file path to images
	if (!$handle = @opendir("./$imagespath"))
	{
		print_stop_message('invalid_file_path_specified');
	}
	else
	{
		// make a $pathcache array containing the filepaths of the existing images in the db
		$pathcache = array();
		$items = $DB_site->query("SELECT $itempath AS path FROM " . TABLE_PREFIX . "$table");
		while ($item = $DB_site->fetch_array($items))
		{
			$pathcache["$item[path]"] = 1;
		}
		unset($item);
		$DB_site->free_result($items);

		// populate the $filearray with paths of images that are not contained in the $pathcache
		$path = $imagespath;
		$filearray = array();
		readdir($handle);
		readdir($handle); // Get rid of '..' and '.'

		$imagelist = array('.jpg', '.gif', '.jpeg', '.jpe', '.png', '.bmp');

		while($file = readdir($handle))
		{
			$ext = strtolower(strrchr($file, '.'));
			if (in_array($ext, $imagelist) AND !$pathcache["$path/$file"] AND !$pathcache["$vboptions[bburl]/$path/$file"])
			{
				$filearray[] = $file;
			}
		}
		// free the $pathcache
		unset($pathcache);
		// close the directory handler
		closedir($handle);

		// now display the returned items

		// get some variables defining what parts of the $filearray to show
		$page = intval($page);
		if ($page < 1)
		{
			$page = 1;
		}
		$perpage = intval($perpage);
		if ($perpage < 1)
		{
			$perpage = 10;
		}
		$startat = ($page - 1) * $perpage;
		$endat = $startat + $perpage;
		$totalitems = sizeof($filearray);
		$totalpages = ceil($totalitems / $perpage);

		// if $endat is greater than $totalitems truncate it so we don't get empty rows in the table
		if ($endat > $totalitems)
		{
			$endat = $totalitems;
		}

		// check to see that the file array actually has some contents
		if ($totalitems == 0)
		{
			// check to see if we are coming from an insert operation...
			if ($doneinsert == 1)
			{
				define('CP_REDIRECT', "image.php?$session[sessionurl]table=$table");
				print_stop_message("all_{$itemtypeplural}_added");
			}
			else
			{
				print_stop_message("no_new_{$itemtypeplural}");
			}
		}
		else
		{
			print_form_header('image', 'doinsertmultiple');
			construct_hidden_code('table', $table);
			construct_hidden_code('imagespath', $imagespath);
			construct_hidden_code('perpage', $perpage);
			construct_hidden_code('imagecategoryid', $imagecategoryid);

			// make the headings for the table
			$header = array();
			$header[] = $vbphrase['image'];
			$header[] = $vbphrase['title'];
			switch ($table)
			{
				case 'avatar':
					$header[] = $vbphrase['minimum_posts'];
					break;
				case 'smilie':
					$header[] = $vbphrase['text_to_replace'];
					break;
			}
			$header[] = '<input type="checkbox" name="allbox" title="' . $vbphrase['check_all'] . '" onclick="js_check_all(this.form);" /><input type="hidden" name="page" value="' . $page . '" />';

			// get $colspan based on the number of headings and use it for the print_table_header() call
			print_table_header(construct_phrase($vbphrase["adding_multiple_{$itemtypeplural}_reading_from_x"], "$vboptions[bburl]/$path"), sizeof($header));
			// display the column headings
			print_cells_row($header, 1, 0, 1);

			// now run through the appropriate bits of $filearray and display
			for ($i = $startat; $i < $endat; $i++)
			{

				// make a nice title from the filename
				$titlefield = substr($filearray[$i], 0, strrpos($filearray[$i], '.'));

				$cell = array();
				$cell[] = construct_img_html("$path/". $filearray[$i]) . "<br /><span class=\"smallfont\">" . $filearray[$i] . '</span>';
				$cell[] = "<input type=\"text\" class=\"bginput\" name=\"ititle[" . urlencode($filearray["$i"]) . "]\" tabindex=\"1\" value=\"" . ucwords(preg_replace('/(_|-)/siU', ' ', $titlefield)) . "\" size=\"25\" />\n\t<select name=\"icat[" . urlencode($filearray["$i"]) . "]\" tabindex=\"1\" class=\"bginput\">\n" . construct_select_options(fetch_image_categories_array($catid), $imagecategoryid) . "\t</select>\n\t";

				// add extra cells if needed
				switch ($table)
				{
					case 'avatar':
						$cell[] = "<input type=\"text\" class=\"bginput\" name=\"iminimumposts[" . urlencode($filearray["$i"]) . "]\" tabindex=\"1\" value=\"0\" size=\"5\" />";
						break;
					case 'smilie':
						$cell[] = "<input type=\"text\" class=\"bginput\" name=\"ismilietext[" . urlencode($filearray["$i"]) . "]\" tabindex=\"1\" value=\":$titlefield:\" size=\"15\" />";
						break;
				}

				$cell[] = "<input type=\"checkbox\" name=\"doimage[" . urlencode($filearray["$i"]) . "]\" value=\"1\" tabindex=\"1\" />";

				print_cells_row($cell, 0, 0, 1);
			}

			// make a page navigator if $totalitems is greater than $perpage
			if ($perpage < $totalitems)
			{
				$pagenav = "<span class=\"smallfont\">" . $vbphrase['pages'] . " ($totalpages)</span> &nbsp; &nbsp; ";
				for ($i = 1; $i <= $totalpages; $i++)
				{
					$pagenav .= " <input type=\"submit\" class=\"button\" name=\"page\" tabindex=\"1\" value=\" $i \"" . iif($i == $page, ' disabled="disabled"') . ' /> ';
				}
				print_description_row('<center><input type="submit" class="button" name="doinsertmultiple" value="' . $vbphrase["add_{$itemtypeplural}"] . '" style="font-weight:bold" tabindex="1" /> <input type="reset" tabindex="2" value="' . $vbphrase['reset'] . '" style="font-weight:bold" /></center>', 0, $colspan);
				print_table_footer($colspan, $pagenav);
			}
			else
			{
				print_table_footer($colspan, '<input type="submit" class="button" name="doinsertmultiple" value="' . $vbphrase["add_{$itemtypeplural}"] . '" tabindex="1" /> <input type="reset" class="button" value="' . $vbphrase['reset'] . '" tabindex="1" />');
			}


		} // end if($totalitems)
	} // end if(opendir())
}

// ###################### Start Insert #######################
if ($_POST['do'] == 'insert')
{
	globalize($_POST, array(
		'title' => STR,
		'minimumposts' => INT,
		'smilietext' => STR,
		'imagespath' => STR,
		'imagecategoryid' => INT,
		'displayorder' => INT
	));

	if (!$imagespath OR ($table == 'smilie' AND !$smilietext))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($table == 'smilie' AND $DB_site->query_first("SELECT smilieid FROM " . TABLE_PREFIX . "$table WHERE BINARY smilietext = '" . addslashes($smilietext) . "'"))
	{
		if (IMAGE_UPLOADED)
		{ // if the image is being uploaded zap it
			unlink($imagespath);
		}
		// this smilie already exists
		print_stop_message('smilie_replace_text_x_exists', $smilietext);
	}

	$DB_site->query("
		INSERT INTO " . TABLE_PREFIX . "$table (
			$itemid,title," . iif($table == 'avatar', 'minimumposts,', '') . iif($table == 'smilie', 'smilietext,', '') . "$itempath,imagecategoryid,displayorder
		) VALUES (
			NULL,'" . addslashes($title) . "'," . iif($table == 'avatar', intval($minimumposts) . ',', '') . iif($table == 'smilie', "'" . addslashes($smilietext) . "',", '') . "'" . addslashes($imagespath) . "'," . intval($imagecategoryid) . ',' . intval($displayorder) . "
		)
	");

	build_image_cache($table);

	define('CP_REDIRECT', "image.php?$session[sessionurl]do=viewimages&table=$table&amp;imagecategoryid=$imagecategoryid");
	print_stop_message("saved_{$itemtype}_successfully");
}

// ###################### Start Add #######################
if ($_REQUEST['do'] == 'add')
{
	print_form_header('image', 'insert');
	construct_hidden_code('table', $table);
	print_table_header($vbphrase["add_a_single_{$itemtype}"]);
	print_input_row($vbphrase['title'], 'title');
	switch($table)
	{
		case 'avatar':
			print_input_row($vbphrase['minimum_posts'], 'minimumposts', 0);
			break;
		case 'smilie':
			print_input_row($vbphrase['text_to_replace'], 'smilietext');
			break;
	}
	print_input_row($vbphrase["{$itemtype}_file_path"], 'imagespath');
	print_select_row($vbphrase["{$itemtype}_category"], 'imagecategoryid', fetch_image_categories_array($catid),  $item['imagecategoryid']);
	print_input_row($vbphrase['display_order'],'displayorder',1);
	print_submit_row($vbphrase["add_{$itemtype}"]);

	print_form_header('image', 'insertmultiple');
	construct_hidden_code('table', $table);
	print_table_header($vbphrase["add_multiple_{$itemtypeplural}"]);
	print_select_row($vbphrase["{$itemtype}_category"], 'imagecategoryid', fetch_image_categories_array($catid),  $item['imagecategoryid']);
	print_input_row($vbphrase["{$itemtypeplural}_file_path"], 'imagespath', "images/$table" . 's');
	print_input_row($vbphrase["{$itemtypeplural}_to_show_per_page"], 'perpage', 10);
	print_submit_row($vbphrase["add_{$itemtypeplural}"]);

}

// ###################### Start Update #######################
if ($_POST['do'] == 'update')
{
	globalize($_POST, array(
		'id' => INT,
		'title' => STR,
		'minimumposts' => INT,
		'imagespath' => STR,
		'imagecategoryid' => INT,
		'displayorder' => INT,
		'smilietext' => STR,
		'page' => INT,
		'perpage' => INT,
		'massmove' => INT,
	));

	if (!$imagespath OR ($table == 'smilie' AND !$smilietext))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($table == 'smilie')
	{
		$oldtext = $DB_site->query_first("SELECT smilietext FROM " . TABLE_PREFIX . "$table WHERE $itemid = " . intval($id));
		if ($oldtext['smilietext'] != $smilietext AND $DB_site->query_first("SELECT smilieid FROM " . TABLE_PREFIX . "$table WHERE BINARY smilietext = '" . addslashes($smilietext) . "'"))
		{
			// this smilie already exists
			print_stop_message('smilie_replace_text_x_exists', $smilietext);
		}
	}

	$DB_site->query("
		UPDATE " . TABLE_PREFIX . "$table SET
		title='" . addslashes($title) . "',
		" . iif($table == 'avatar', "minimumposts = $minimumposts,", '').
		iif($table == 'smilie', "smilietext = '" . addslashes($smilietext) . "',", '')."
		$itempath = '" . addslashes($imagespath) . "',
		imagecategoryid = " . intval($imagecategoryid) . ",
		displayorder = " . intval($displayorder) . "
		WHERE $itemid = " . intval($id)
	);

	build_image_cache($table);

	define('CP_REDIRECT', "image.php?$session[sessionurl]do=viewimages&amp;table=$table&amp;imagecategoryid=$imagecategoryid&amp;perpage=$perpage&amp;page=$page&amp;massmove=$massmove");
	print_stop_message("saved_{$itemtype}_successfully");
}

// ###################### Start Edit #######################
if ($_REQUEST['do'] == 'edit')
{
	globalize($_REQUEST, array(
		'id' => INT,
		'page' => INT,
		'perpage' => INT,
		'massmove' => INT,
	));

	$item = $DB_site->query_first("SELECT * FROM " . TABLE_PREFIX . "$table WHERE $itemid = $id");

	print_form_header('image', 'update');
	construct_hidden_code('id', $id);
	construct_hidden_code('table', $table);
	construct_hidden_code('page', $page);
	construct_hidden_code('perpage', $perpage);
	construct_hidden_code('massmove', $massmove);
	print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase["$itemtype"], $item['title'], $item["$itemid"]));
	print_label_row($vbphrase['image'], construct_img_html($item["$itempath"]));
	print_input_row($vbphrase['title'], 'title', $item['title']);
	switch($table)
	{
		case 'avatar':
			print_input_row($vbphrase['minimum_posts'], 'minimumposts', $item['minimumposts']);
			break;
		case 'smilie':
			print_input_row($vbphrase['text_to_replace'], 'smilietext', $item['smilietext']);
			break;
	}
	print_input_row($vbphrase["{$itemtype}_file_path"], 'imagespath', $item["$itempath"]);
	print_select_row($vbphrase["{$itemtype}_category"], 'imagecategoryid', fetch_image_categories_array($catid), $item['imagecategoryid']);
	print_input_row($vbphrase['display_order'], 'displayorder', $item['displayorder']);
	print_submit_row();

}

// ###################### Start Update Display Order #######################
if ($_POST['do'] == 'displayorder')
{
	globalize($_POST, array(
		'order',
		'category',
		'doorder' => STR,
		'perpage' => INT,
		'page' => INT,
		'imagecategoryid' => INT,
		'massmove' => INT
	));

	// check that the correct submit button was pressed...
	if ($doorder)
	{
		if (!$massmove AND !is_array($order))
		{
			print_stop_message('please_complete_required_fields');
		}
		else if ($massmove)
		{
			foreach($category AS $id => $imagecategoryid)
			{
				if ($imagecategoryid)
				{
					$DB_site->query("UPDATE " . TABLE_PREFIX . "$table SET imagecategoryid = $imagecategoryid WHERE $itemid = $id");
				}
			}
		}
		else
		{
			$items = $DB_site->query("SELECT $itemid,displayorder FROM " . TABLE_PREFIX . "$table");
			$ordercache = array();
			while ($item = $DB_site->fetch_array($items))
			{
				$ordercache["$item[$itemid]"] = $item['displayorder'];
			}
			unset($item);
			$DB_site->free_result($items);

			foreach($order AS $id => $displayorder)
			{
				$displayorder = intval($displayorder);
				if ($displayorder != $ordercache["$id"])
				{
					$DB_site->query("UPDATE " . TABLE_PREFIX . "$table SET displayorder = $displayorder WHERE $itemid = $id");
				}
			}
		}
	}
	build_image_cache($table);
	$_REQUEST['do'] = 'viewimages';

}

// ###################### Start View Images #######################
if ($_REQUEST['do'] == 'viewimages')
{
	globalize($_REQUEST, array(
		'pagesub' => INT,
		'page' => INT,
		'perpage' => INT,
		'imagecategoryid' => INT,
		'massmove' => INT
	));

	if ($pagesub)
	{
		$page = $pagesub;
	}

	if ($page < 1)
	{
		$page = 1;
	}

	if ($perpage < 1)
	{
		$perpage = 20;
	}
	$startat = ($page - 1) * $perpage;

	// check to see if we should be displaying a single image category
	if ($imagecategoryid)
	{
		$categoryinfo = verify_id('imagecategory', $imagecategoryid, 0, 1);
		// check to ensure that the returned category is of the appropriate type
		if ($categoryinfo['imagetype'] != $catid)
		{
			unset($categoryinfo);
			$imagecategoryid = 0;
		}
	}

	$count = $DB_site->query_first("SELECT COUNT(*) AS total FROM " . TABLE_PREFIX . "$table" . iif($imagecategoryid, " WHERE imagecategoryid=$imagecategoryid", ''));
	$totalitems = $count['total'];
	$totalpages = ceil($totalitems / $perpage);

	if ($startat > $totalitems)
	{
		$page = 1;
		$startat = 0;
	}

	if ($categoryinfo)
	{
	// we already have the category details - don't include it in the query
		$items = $DB_site->query("
			SELECT * FROM " . TABLE_PREFIX . "$table
			WHERE imagecategoryid = $categoryinfo[imagecategoryid]
			ORDER BY " . iif($table == 'avatar', 'minimumposts,', '') . "displayorder
			LIMIT $startat,$perpage
		");
	}
	else
	{
	// getting all items regardless of category... better get the category titles too
		$items = $DB_site->query("
			SELECT $table.*, imagecategory.title AS category
			FROM " . TABLE_PREFIX . "$table AS $table
			LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
			" . iif($imagecategoryid, "WHERE $table.imagecategoryid = $imagecategoryid", '') . "
			ORDER BY " . iif($table == 'avatar','minimumposts,') . "imagecategory.displayorder,$table.displayorder
			LIMIT $startat, $perpage
		");
	}
	$itemcache = array();
	while ($item = $DB_site->fetch_array($items))
	{
		if ($table != 'avatar')
		{
			$item['minimumposts'] = 0;
		}
		$itemcache["$item[minimumposts]"][] = $item;
	}
	$j = 0;

	print_form_header('image', 'displayorder');
	construct_hidden_code('table', $table);
	construct_hidden_code('imagecategoryid', $imagecategoryid);
	construct_hidden_code('massmove', $massmove);
	print_table_header(
		$vbphrase["{$itemtype}_manager"]
		. ' <span class="normal">'
		. iif($categoryinfo, "$categoryinfo[title] - ")
		. construct_phrase($vbphrase['page_x_of_y'], $page, $totalpages)
		. '</span>',
		$img_per_row
	);

	foreach ($itemcache AS $minimumposts => $val)
	{
		if ($table == 'avatar')
		{
			print_description_row($vbphrase['minimum_posts'] . ': ' . $minimumposts, 0, $img_per_row, 'thead', 'center');
			$lastcategory = 0;
		}
		$cell = array();
		$i = 0;
		foreach($val AS $item)
		{
			if ($item['imagecategoryid'] != $lastcategory AND !$categoryinfo)
			{
				$i = 0;
				print_image_item_row($cell);
				print_description_row('- - ' . iif(empty($item['category']), '(' . $vbphrase['uncategorized'] . ')', $item['category']) . ' - -', 0, $img_per_row, 'thead', 'center');

			}
			if ($i < $img_per_row)
			{
				$cell[] = print_image_item($item, $massmove);
			}
			else
			{
				$i = 0;
				print_image_item_row($cell);
				$cell[] = print_image_item($item, $massmove);
			}
			$lastcategory = $item['imagecategoryid'];
			$j++;
			$i++;
		}
		print_image_item_row($cell);
	}

	construct_hidden_code('page', $page);
	if ($totalitems > $perpage)
	{
		$pagebuttons = "\n\t" . $vbphrase['pages'] . ": ($totalpages)\n";
		for ($i = 1; $i <= $totalpages; $i++)
		{
			$pagebuttons .= "\t<input type=\"submit\" class=\"button\" name=\"pagesub\" value=\" $i \"" . iif($i == $page, ' disabled="disabled"') . " tabindex=\"1\" />\n";
		}
		$pagebuttons .= "\t&nbsp; &nbsp; &nbsp; &nbsp;";
	}
	else
	{
		$pagebuttons = '';
	}
	if ($massmove)
	{
		$categories = '<option value="0"></option>';
		$categories .= construct_select_options(fetch_image_categories_array($catid));
		$categories = '<select name="selectall" class="bginput" onchange="js_select_all(this.form);">' . $categories . '</select>';

		$buttontext = $vbphrase['mass_move'];
	}
	else
	{
		$buttontext = $vbphrase['save_display_order'];
	}
	print_table_footer($img_per_row, "\n\t$categories <input type=\"submit\" class=\"button\" name=\"doorder\" value=\"" . $buttontext . "\" tabindex=\"1\" />\n\t&nbsp; &nbsp; &nbsp; &nbsp;$pagebuttons
	" . $vbphrase['per_page'] . "
	<input type=\"text\" name=\"perpage\" value=\"$perpage\" size=\"3\" tabindex=\"1\" />
	<input type=\"submit\" class=\"button\" value=\"" . $vbphrase['go'] . "\" tabindex=\"1\" />\n\t");

	echo "<p align=\"center\">" .
		construct_link_code($vbphrase["add_{$itemtype}"], "image.php?$session[sessionurl]do=add&table=$table") .
		construct_link_code($vbphrase["edit_{$itemtype}_categories"], "image.php?$session[sessionurl]do=modify&table=$table") .
	"</p>";

}

// ###################### Start Modify Categories #######################
if ($_REQUEST['do'] == 'modify')
{
	$categories = $DB_site->query("
		SELECT imagecategory.*, COUNT($table.$itemid) AS items
		FROM " . TABLE_PREFIX . "imagecategory AS imagecategory
		LEFT JOIN " . TABLE_PREFIX . "$table AS $table USING(imagecategoryid)
		WHERE imagetype = $catid
		GROUP BY imagecategoryid
		ORDER BY displayorder
	");

	if ($DB_site->num_rows($categories))
	{
		print_form_header('image', 'docategorydisplayorder');
		construct_hidden_code('table', $table);
		print_table_header($vbphrase["edit_{$itemtype}_categories"], 4);
		print_cells_row(array($vbphrase['title'], $vbphrase['contains'], $vbphrase['display_order'], $vbphrase['controls']), 1);
		while ($category = $DB_site->fetch_array($categories))
		{
			$cell = array();
			$cell[] = "<a href=\"image.php?$session[sessionurl]do=viewimages&table=$table&imagecategoryid=$category[imagecategoryid]\">$category[title]</a>";
			$cell[] = vb_number_format($category['items']) . ' ' . $vbphrase["$itemtypeplural"];
			$cell[] = "<input type=\"text\" class=\"bginput\" name=\"order[$category[imagecategoryid]]\" value=\"$category[displayorder]\" tabindex=\"1\" size=\"3\" />";
			$cell[] =
				construct_link_code($vbphrase['mass_move'], "image.php?$session[sessionurl]do=viewimages&amp;massmove=1&amp;table=$table&amp;imagecategoryid=$category[imagecategoryid]") .
				construct_link_code($vbphrase['view'], "image.php?$session[sessionurl]do=viewimages&amp;table=$table&amp;imagecategoryid=$category[imagecategoryid]") .
				construct_link_code($vbphrase['edit'], "image.php?$session[sessionurl]do=editcategory&amp;table=$table&amp;imagecategoryid=$category[imagecategoryid]").
				construct_link_code($vbphrase['delete'], "image.php?$session[sessionurl]do=removecategory&amp;table=$table&amp;imagecategoryid=$category[imagecategoryid]").
				iif($category['imagetype'] != 3, construct_link_code($vbphrase["{$itemtype}_permissions"], "image.php?$session[sessionurl]do=editpermissions&amp;table=$table&amp;imagecategoryid=$category[imagecategoryid]"), '');
			print_cells_row($cell);
		}
		print_submit_row($vbphrase['save_display_order'], NULL, 4);
		echo "<p align=\"center\">" . construct_link_code($vbphrase["add_new_{$itemtype}_category"], "image.php?$session[sessionurl]do=addcategory&table=$table") . construct_link_code($vbphrase["show_all_{$itemtypeplural}"], "image.php?$session[sessionurl]do=viewimages&amp;table=$table")."</p>";

	}
	else
	{
		print_stop_message("no_{$itemtype}_categories_found", "image.php?$session[sessionurl]do=addcategory&amp;table=$table");
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 15854 $
|| ####################################################################
\*======================================================================*/
?>