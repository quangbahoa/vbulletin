<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.5.8 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2026 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 14697 $');
define('FORCE_HOOKS', true);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('plugins');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_hook.php');
require_once(DIR . '/includes/adminfunctions_template.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
// don't allow demo version or admin with no permission to administer plugins
if (is_demo_mode() OR !can_administer('canadminplugins'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array('pluginid' => TYPE_UINT));

// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['pluginid'] != 0, 'plugin id = ' . $vbulletin->GPC['pluginid']));

// #############################################################################
// ########################### START MAIN SCRIPT ###############################
// #############################################################################

function version_sort($a, $b)
{
	if ($a == $b)
	{
		return 0;
	}

	return (is_newer_version($a, $b) ? 1 : -1);
}

if ($_REQUEST['do'] != 'download' AND $_REQUEST['do'] != 'productexport')
{
	print_cp_header($vbphrase['plugin_system']);
}

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

if (in_array($_REQUEST['do'], array('modify', 'files', 'edit', 'add', 'product', 'productadd', 'productedit')))
{
	if (!$vbulletin->options['enablehooks'] OR defined('DISABLE_HOOKS'))
	{
		print_table_start();
		if (!$vbulletin->options['enablehooks'])
		{
			print_description_row($vbphrase['plugins_disabled_options']);
		}
		else
		{
			print_description_row($vbphrase['plugins_disable_config']);
		}
		print_table_footer(2, '', '', false);
	}
}

// ###################### Start import plugin XML #######################
if ($_REQUEST['do'] == 'files')
{
	$products = fetch_product_list();

	// download form
	print_form_header('plugin', 'download', 0, 1, 'downloadform" target="download');
	print_table_header($vbphrase['download']);
	print_input_row($vbphrase['filename'], 'filename', 'vbulletin-plugins.xml');

	$plugins = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "plugin ORDER BY hookname, title");
	$prevhook = '';
	while ($plugin = $db->fetch_array($plugins))
	{
		if ($plugin['hookname'] != $prevhook)
		{
			$prevhook = $plugin['hookname'];
			print_description_row("$vbphrase[hook_location] : " . $plugin['hookname'], 0, 2, 'tfoot');
		}

		$title = htmlspecialchars_uni($plugin['title']);
		$title = $plugin['active'] ? $title : "<strike>$title</strike>";

		$product = $products[($plugin['product'] ? $plugin['product'] : 'vbulletin')];
		if (!$product)
		{
			$product = "<em>$plugin[product]</em>";
		}

		print_label_row("
			<label for=\"cb$plugin[pluginid]\">
			<input type=\"checkbox\" id=\"cb$plugin[pluginid]\" name=\"download[]\" value=\"$plugin[pluginid]\" />$title</label>
		", $product);
	}

	print_submit_row($vbphrase['download']);

	?>
	<script type="text/javascript">
	<!--
	function js_confirm_upload(tform, filefield)
	{
		if (filefield.value == "")
		{
			return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
		}
		return true;
	}
	//-->
	</script>
	<?php

	print_form_header('plugin', 'doimport', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.pluginfile);');
	print_table_header($vbphrase['import_plugin_xml_file']);
	print_upload_row($vbphrase['upload_xml_file'], 'pluginfile', 999999999);
	print_input_row($vbphrase['import_xml_file'], 'serverfile', './includes/xml/plugins.xml');
	print_submit_row($vbphrase['import'], 0);
}

// #############################################################################
if ($_POST['do'] == 'doimport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'serverfile' => TYPE_STR,
	));

	$vbulletin->input->clean_array_gpc('f', array(
		'pluginfile' => TYPE_FILE
	));

	// got an uploaded file?
	if (file_exists($vbulletin->GPC['pluginfile']['tmp_name']))
	{
		$xml = file_read($vbulletin->GPC['pluginfile']['tmp_name']);
	}
	// no uploaded file - got a local file?
	else if (file_exists($vbulletin->GPC['serverfile']))
	{
		$xml = file_read($vbulletin->GPC['serverfile']);
	}
	// no uploaded file and no local file - ERROR
	else
	{
		print_stop_message('no_file_uploaded_and_no_local_file_found');
	}

	print_dots_start('<b>' . $vbphrase['importing_plugins'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	require_once(DIR . '/includes/class_xml.php');

	$xmlobj = new XMLparser($xml);
	if ($xmlobj->error_no == 1)
	{
			print_dots_stop();
			print_stop_message('no_xml_and_no_path');
	}

	if(!$arr = $xmlobj->parse())
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	if (!$arr['plugin'])
	{
		print_dots_stop();
		if (!empty($arr['productid']))
		{
			print_stop_message('this_file_appears_to_be_a_product');
		}
		else
		{
			print_stop_message('invalid_file_specified');
		}
	}

	if (!is_array($arr['plugin'][0]))
	{
		$arr['plugin'] = array($arr['plugin']);
	}

	$maxid = $db->query_first("SELECT MAX(pluginid) AS max FROM " . TABLE_PREFIX . "plugin");

	foreach ($arr['plugin'] AS $plugin)
	{
		unset($plugin['devkey']); // make sure we don't try to set this as it's no longer used

		$db->query_write(fetch_query_sql($plugin, 'plugin'));
	}

	// rebuild the $vboptions array
	vBulletinHook::build_datastore($db);

	// stop the 'dots' counter feedback
	print_dots_stop();

	print_cp_redirect("plugin.php?" . $vbulletin->session->vars['sessionurl'], 0);
}

// #############################################################################
if ($_POST['do'] == 'download')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'filename' => TYPE_STR,
		'download' => TYPE_ARRAY_UINT,
	));

	if (empty($vbulletin->GPC['download']) OR empty($vbulletin->GPC['filename']))
	{
		print_stop_message('please_complete_required_fields');
	}

	require_once(DIR . '/includes/class_xml.php');
	$xml = new XMLexporter();
	$xml->add_group('plugins');

	$plugins = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "plugin WHERE pluginid IN (" . implode(', ', $vbulletin->GPC['download']) . ")");
	while ($plugin = $db->fetch_array($plugins))
	{
		$params = array('active' => $plugin['active']);
		if ($plugin['product'])
		{
			$params['product'] = $plugin['product'];
		}

		$xml->add_group('plugin', $params);

		$xml->add_tag('title', $plugin['title']);
		$xml->add_tag('hookname', $plugin['hookname']);
		$xml->add_tag('phpcode', $plugin['phpcode']);

		$xml->close_group();
	}

	$xml->close_group();

	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n";

	$doc .= $xml->output();
	$xml = null;

	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, $vbulletin->GPC['filename'], 'text/xml');
}

// #############################################################################

if ($_POST['do'] == 'updateactive')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'active' => TYPE_ARRAY_UINT,
	));

	$cond = '';

	$plugins = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "plugin");
	while ($plugin = $db->fetch_array($plugins))
	{
		$cond .= "WHEN $plugin[pluginid] THEN " . (isset($vbulletin->GPC['active']["$plugin[pluginid]"]) ? 1 : 0) . "\n";
	}

	if (!empty($cond))
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "plugin SET active = CASE pluginid
			$cond
			ELSE active END
		");
	}

	// update the datastore
	vBulletinHook::build_datastore($db);

	$_REQUEST['do'] = 'modify';
}

// #############################################################################

if ($_POST['do'] == 'kill')
{
	$db->query_write("DELETE FROM " . TABLE_PREFIX . "plugin WHERE pluginid = " . $vbulletin->GPC['pluginid']);

	vBulletinHook::build_datastore($db);

	define('CP_REDIRECT', 'plugin.php');
	print_stop_message('deleted_plugin_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pluginid' => TYPE_UINT,
		'name'    => TYPE_STR
	));

	print_delete_confirmation('plugin', $vbulletin->GPC['pluginid'], 'plugin', 'kill');
}

// #############################################################################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'hookname' => TYPE_STR,
		'title'    => TYPE_STR,
		'phpcode'  => TYPE_STR,
		'active'   => TYPE_BOOL,
		'product'  => TYPE_STR,
	));

	if (!$vbulletin->GPC['hookname'] OR !$vbulletin->GPC['title'] OR !$vbulletin->GPC['phpcode'])
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($vbulletin->GPC['pluginid'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "plugin
			SET
				hookname = '" . $db->escape_string($vbulletin->GPC['hookname']) . "',
				title = '" . $db->escape_string($vbulletin->GPC['title']) . "',
				phpcode = '" . $db->escape_string($vbulletin->GPC['phpcode']) . "',
				product = '" . $db->escape_string($vbulletin->GPC['product']) . "',
				active = " . intval($vbulletin->GPC['active']) . "
			WHERE pluginid = " . $vbulletin->GPC['pluginid'] . "
		");
	}
	else
	{
		/*insert query*/
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "plugin
				(hookname, title, phpcode, product, active)
			VALUES
				('" . $db->escape_string($vbulletin->GPC['hookname']) . "',
				'" . $db->escape_string($vbulletin->GPC['title']) . "',
				'" . $db->escape_string($vbulletin->GPC['phpcode']) . "',
				'" . $db->escape_string($vbulletin->GPC['product']) . "',
				" . intval($vbulletin->GPC['active']) . ")
		");
		$vbulletin->GPC['pluginid'] = $db->insert_id();
	}

	// update the datastore
	vBulletinHook::build_datastore($db);

	// stuff to handle the redirect
	define('CP_REDIRECT', 'plugin.php');
	print_stop_message('saved_plugin_successfully');
}

// #############################################################################

if ($_REQUEST['do'] == 'edit' OR $_REQUEST['do'] == 'add')
{

	$products = fetch_product_list();

	$hooklocations = array();
	require_once(DIR . '/includes/class_xml.php');
	$handle = opendir(DIR . '/includes/xml/');
	while (($file = readdir($handle)) !== false)
	{
		if (!preg_match('#^hooks_(.*).xml$#i', $file, $matches))
		{
			continue;
		}
		$product = $matches[1];

		$phrased_product = $products[($product ? $product : 'vbulletin')];
		if (!$phrased_product)
		{
			$phrased_product = $product;
		}

		$xmlobj = new XMLparser(false, DIR . "/includes/xml/$file");
		$xml = $xmlobj->parse();

		if (!is_array($xml['hooktype'][0]))
		{
			// ugly kludge but it works...
			$xml['hooktype'] = array($xml['hooktype']);
		}

		foreach ($xml['hooktype'] AS $key => $hooks)
		{
			if (!is_numeric($key))
			{
				continue;
			}
			$phrased_type = isset($vbphrase["hooktype_$hooks[type]"]) ? $vbphrase["hooktype_$hooks[type]"] : $hooks['type'];

			$hooktype = $phrased_product . ' : ' . $phrased_type;

			$hooklocations["$hooktype"] = array();

			if (!is_array($hooks['hook']))
			{
				$hooks['hook'] = array($hooks['hook']);
			}

			foreach ($hooks['hook'] AS $hook)
			{
				$hookid = (is_string($hook) ? $hook : $hook['value']);
				$hooklocations["$hooktype"]["$hookid"] = $hookid;
			}
		}
	}
	ksort($hooklocations);

	$plugin = $db->query_first("
		SELECT plugin.*,
			IF(product.productid IS NULL, 0, 1) AS foundproduct,
			IF(plugin.product = 'vbulletin', 1, product.active) AS productactive
		FROM " . TABLE_PREFIX . "plugin AS plugin
		LEFT JOIN " . TABLE_PREFIX . "product AS product ON(product.productid = plugin.product)
		WHERE pluginid = " . $vbulletin->GPC['pluginid']
	);
	if (!$plugin)
	{
		$plugin = array();
	}

	print_form_header('plugin', 'update');
	construct_hidden_code('pluginid', $plugin['pluginid']);

	if ($_REQUEST['do'] == 'add')
	{
		$heading = $vbphrase['add_new_plugin'];
	}
	else
	{
		$heading = construct_phrase($vbphrase['edit_plugin_x'], $plugin['title']);
	}

	print_table_header($heading);

	print_select_row($vbphrase['product'], 'product', fetch_product_list(), $plugin['product'] ? $plugin['product'] : 'vbulletin');
	print_select_row("$vbphrase[hook_location] <dfn>$vbphrase[hook_location_desc]</dfn>", 'hookname', $hooklocations, $plugin['hookname']);
	print_input_row("$vbphrase[title] <dfn>$vbphrase[plugin_title_desc]</dfn>", 'title', $plugin['title'], 1, 60);
	print_textarea_row(
		"$vbphrase[plugin_php_code] <dfn>$vbphrase[plugin_code_desc]</dfn>",
		'phpcode',
		htmlspecialchars($plugin['phpcode']),
		10, 45,
		false,
		true,
		'ltr',
		'code'
	);
		if ($plugin['foundproduct'] AND !$plugin['productactive'])
	{
		print_description_row(construct_phrase($vbphrase['plugin_inactive_due_to_product_disabled'], $products["$plugin[product]"]));
	}
	print_yes_no_row("$vbphrase[plugin_is_active] <dfn>$vbphrase[plugin_active_desc]</dfn>", 'active', $plugin['active']);
	print_submit_row($vbphrase['save'], $vbphrase['reset']);

	if ($plugin['phpcode'] != '')
	{
		// highlight the string
		$code = $plugin['phpcode'];

		// do we have an opening <? tag?
		if (!preg_match('#^\s*<\?#si', $code))
		{
			// if not, replace leading newlines and stuff in a <?php tag and a closing tag at the end
			$code = "<?php BEGIN__VBULLETIN__CODE__SNIPPET $code \r\nEND__VBULLETIN__CODE__SNIPPET ?>";
			$addedtags = true;
		}
		else
		{
			$addedtags = false;
		}

		// highlight the string
		$oldlevel = error_reporting(0);
		if (PHP_VERSION  >= '4.2.0')
		{
			$code = highlight_string($code, true);
		}
		else
		{
			@ob_start();
			highlight_string($code);
			$code = @ob_get_contents();
			@ob_end_clean();
		}
		error_reporting($oldlevel);

		// if we added tags above, now get rid of them from the resulting string
		if ($addedtags)
		{
			$search = array(
				'#(<|&lt;)\?php( |&nbsp;)BEGIN__VBULLETIN__CODE__SNIPPET( |&nbsp;)#siU',
				'#(<(span|font).*>)(<|&lt;)\?(</\\2>(<\\2.*>))php( |&nbsp;)BEGIN__VBULLETIN__CODE__SNIPPET( |&nbsp;)#siU',
				'#END__VBULLETIN__CODE__SNIPPET( |&nbsp;)\?(>|&gt;)#siU'
			);
			$replace = array(
				'',
				'\\5',
				''
			);
			$code = preg_replace($search, $replace, $code);
		}

		print_form_header('', '');
		print_table_header($vbphrase['plugin_php_code']);
		print_description_row("<div dir=\"ltr\">$code</div>");
		print_table_footer();
	}
}

// #############################################################################

if ($_REQUEST['do'] == 'modify')
{
	$products = fetch_product_list(true);

	print_form_header('plugin', 'updateactive');
	print_table_header($vbphrase['plugin_system'], 4);
	print_cells_row(array($vbphrase['title'], $vbphrase['product'], $vbphrase['active'], $vbphrase['controls']), 1);

	$plugins = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "plugin ORDER BY hookname, title");
	$prevhook = '';
	while ($plugin = $db->fetch_array($plugins))
	{
		if ($plugin['hookname'] != $prevhook)
		{
			$prevhook = $plugin['hookname'];
			print_description_row("$vbphrase[hook_location] : " . $plugin['hookname'], 0, 4, 'tfoot');
		}

		$product = $products[($plugin['product'] ? $plugin['product'] : 'vbulletin')];
		if (!$product)
		{
			$product = array('title' => "<em>$plugin[product]</em>", 'active' => 1);
		}
		if (!$product['active'])
		{
			$product['title'] = "<strike>$product[title]</strike>";
		}

		$title = htmlspecialchars_uni($plugin['title']);
		$title = ($plugin['active'] AND $product['active']) ? $title : "<strike>$title</strike>";

		print_cells_row(array(
			"<a href=\"plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;pluginid=$plugin[pluginid]\">$title</a>",
			$product['title'],
			"<input type=\"checkbox\" name=\"active[$plugin[pluginid]]\" value=\"1\"" . ($plugin['active'] ? ' checked="checked"' : '') . " />",
			construct_link_code($vbphrase['edit'], "plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;pluginid=$plugin[pluginid]") .
			construct_link_code($vbphrase['delete'], "plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=delete&amp;pluginid=$plugin[pluginid]")
		));
	}

	print_submit_row($vbphrase['save_active_status'], false, 4);

	echo '<p align="center">' . construct_link_code($vbphrase['add_new_plugin'], "plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=add") . '</p>';
}

// #############################################################################
// #############################################################################
// #############################################################################

if ($_REQUEST['do'] == 'product')
{
	?>
	<script type="text/javascript">
	function js_page_jump(i, sid)
	{
		var sel = fetch_object("prodsel" + i);
		var act = sel.options[sel.selectedIndex].value;
		if (act != '')
		{
			switch (act)
			{
				case 'productdisable': page = "plugin.php?do=productdisable&productid="; break;
				case 'productenable': page = "plugin.php?do=productenable&productid="; break;
				case 'productedit': page = "plugin.php?do=productedit&productid="; break;
				case 'productexport':
					document.cpform.productid.value = sid;
					document.cpform.submit();
					return;
				case 'productdelete': page = "plugin.php?do=productdelete&productid="; break;
				default: return;
			}
			document.cpform.reset();
			jumptopage = page + sid + "&s=<?php echo $vbulletin->session->vars['sessionhash']; ?>";
			window.location = jumptopage;
		}
		else
		{
			alert("<?php echo $vbphrase['invalid_action_specified']; ?>");
		}
	}
	</script>
	<?php

	print_form_header('plugin', 'productexport', false, true, 'cpform', '90%', 'download');
	construct_hidden_code('productid', '');

	print_table_header($vbphrase['installed_products'], 4); # Phrase me
	print_cells_row(array($vbphrase['title'], $vbphrase['version'], $vbphrase['description'], $vbphrase['controls']), 1);

	print_cells_row(array('<strong>vBulletin</strong>', $vbulletin->options['templateversion'], '', ''), false, '', -2);

	// used for <select> id attribute
	$i = 0;

	$products = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "product ORDER BY title");

	while ($product = $db->fetch_array($products))
	{
		$title = htmlspecialchars_uni($product['title']);
		$title = $product['active'] ? $title : "<strike>$title</strike>";

		$options= array('productedit' => $vbphrase['edit']);
		if ($product['active'])
		{
			$options['productdisable'] = $vbphrase['disable'];
		}
		else
		{
			$options['productenable'] = $vbphrase['enable'];
		}
		$options['productexport'] = $vbphrase['export'];
		$options['productdelete'] = $vbphrase['uninstall'];

		$i++;
		print_cells_row(array(
			$title,
			$product['version'],
			$product['description'],
			"\n\t<select name=\"s$product[productid]\" id=\"prodsel$i\" onchange=\"js_page_jump($i, '$product[productid]')\" class=\"bginput\">\n" . construct_select_options($options) . "\t</select>&nbsp;<input type=\"button\" class=\"button\" value=\"" . $vbphrase['go'] . "\" onclick=\"js_page_jump($i, '$product[productid]');\" />"
		), false, '', -2);
	}

	print_table_footer();
	echo '<p align="center">' . construct_link_code($vbphrase['add_import_product'], "plugin.php?" . $vbulletin->session->vars['sessionurl'] . "do=productadd") . '</p>';
}

// #############################################################################

if ($_REQUEST['do'] == 'productdisable' OR $_REQUEST['do'] == 'productenable')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'productid' => TYPE_STR
	));

	$newstate = ($_REQUEST['do'] == 'productdisable' ? 0 : 1);

	// Do the product table
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "product
		SET active = $newstate
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	vBulletinHook::build_datastore($db);
	build_product_datastore();

	// build bitfields to remove/add this products bitfields
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	define('CP_REDIRECT', 'plugin.php?do=product');

	if ($_REQUEST['do'] == 'productdisable')
	{
		print_stop_message('product_disabled_successfully');
	}
	else
	{
		print_stop_message('product_enabled_successfully');
	}
}

// #############################################################################

if ($_REQUEST['do'] == 'productadd' OR $_REQUEST['do'] == 'productedit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'productid'		=> TYPE_STR
	));

	if ($vbulletin->GPC['productid'])
	{
		$product = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "product
			WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		");
	}
	else
	{
		$product = array();
	}

	if (!$product)
	{
		?>
		<script type="text/javascript">
		<!--
		function js_confirm_upload(tform, filefield)
		{
			if (filefield.value == "")
			{
				return confirm("<?php echo construct_phrase($vbphrase['you_did_not_specify_a_file_to_upload'], '" + tform.serverfile.value + "'); ?>");
			}
			return true;
		}
		//-->
		</script>
		<?php

		print_form_header('plugin', 'productimport', 1, 1, 'uploadform" onsubmit="return js_confirm_upload(this, this.productfile);');
		print_table_header($vbphrase['import_product']);
		print_upload_row($vbphrase['upload_xml_file'], 'productfile', 999999999);
		print_input_row($vbphrase['import_xml_file'], 'serverfile', './includes/xml/product.xml');
		print_yes_no_row($vbphrase['allow_overwrite_upgrade_product'], 'allowoverwrite', 0);
		print_submit_row($vbphrase['import']);
	}

	print_form_header('plugin', 'productsave');

	if ($product)
	{
		print_table_header(construct_phrase($vbphrase['edit_product_x'], $product['productid']));
		print_label_row($vbphrase['product_id'], $product['productid']);

		construct_hidden_code('productid', $product['productid']);
		construct_hidden_code('editing', 1);
	}
	else
	{
		print_table_header($vbphrase['add_new_product']);
		print_input_row($vbphrase['product_id'], 'productid', '', true, 35, 25); // max length = 25
	}

	print_input_row($vbphrase['title'], 'title', $product['title']);
	print_input_row($vbphrase['version'], 'version', $product['version']);
	print_input_row($vbphrase['description'], 'description', $product['description']);

	print_submit_row();

	// if we're editing a product, show the install/uninstall code options
	if ($product)
	{
		echo '<br />';

		print_form_header('plugin', 'productcode');
		construct_hidden_code('productid', $vbulletin->GPC['productid']);

		$productcodes = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "productcode
			WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			ORDER BY version
		");

		if ($db->num_rows($productcodes))
		{
			print_table_header($vbphrase['existing_install_uninstall_code'], 4);
			print_cells_row(array(
				$vbphrase['version'],
				$vbphrase['install_code'],
				$vbphrase['uninstall_code'],
				$vbphrase['delete']
			), 1);

			$productcodes_grouped = array();
			$productcodes_versions = array();

			while ($productcode = $db->fetch_array($productcodes))
			{
				// have to be careful here, as version numbers are not necessarily unique
				$productcodes_versions["$productcode[version]"] = 1;
				$productcodes_grouped["$productcode[version]"][] = $productcode;
			}

			$productcodes_versions = array_keys($productcodes_versions);
			usort($productcodes_versions, 'version_sort');

			foreach ($productcodes_versions AS $version)
			{
				foreach ($productcodes_grouped["$version"] AS $productcode)
				{
					print_cells_row(array(
						"<input type=\"text\" name=\"productcode[$productcode[productcodeid]][version]\" value=\"" . htmlspecialchars_uni($productcode['version']) . "\" size=\"10\" />",
						"<textarea name=\"productcode[$productcode[productcodeid]][installcode]\" rows=\"5\" cols=\"40\" wrap=\"virtual\" tabindex=\"1\">" . htmlspecialchars($productcode['installcode']) . "</textarea>",
						"<textarea name=\"productcode[$productcode[productcodeid]][uninstallcode]\" rows=\"5\" cols=\"40\" wrap=\"virtual\" tabindex=\"1\">" . htmlspecialchars($productcode['uninstallcode']) . "</textarea>",
						"<input type=\"checkbox\" name=\"productcode[$productcode[productcodeid]][delete]\" value=\"1\" />"
					));
				}
			}

			print_table_break();
		}

		print_table_header($vbphrase['add_new_install_uninstall_code']);

		print_input_row($vbphrase['version'], 'version');
		print_textarea_row($vbphrase['install_code'], 'installcode');
		print_textarea_row($vbphrase['uninstall_code'], 'uninstallcode');

		print_submit_row();
	}
}

// #############################################################################

if ($_POST['do'] == 'productsave')
{
	// Check to see if it is a duplicate.
	$vbulletin->input->clean_array_gpc('p', array(
		'productid'    => TYPE_STR,
		'editing'      => TYPE_BOOL,
		'title'        => TYPE_STR,
		'version'      => TYPE_STR,
		'description'  => TYPE_STR,
		'confirm'      => TYPE_BOOL,
	));

	if (!$vbulletin->GPC['productid'] OR !$vbulletin->GPC['title'] OR !$vbulletin->GPC['version'])
	{
		print_stop_message('please_complete_required_fields');
	}

	if (strtolower($vbulletin->GPC['productid']) == 'vbulletin')
	{
		print_stop_message('product_x_installed_version_y_z', 'vBulletin', $vbulletin->options['templateversion'], $vbulletin->GPC['version']);
	}

	if (!$vbulletin->GPC['editing'] AND $existingprod = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'"
	))
	{
		print_stop_message('product_x_installed_version_y_z', $vbulletin->GPC['title'], $existingprod['version'], $vbulletin->GPC['version']);
	}

	if ($vbulletin->GPC['editing'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "product SET
				title = '" . $db->escape_string($vbulletin->GPC['title']) . "',
				description = '" . $db->escape_string($vbulletin->GPC['description']) . "',
				version = '" . $db->escape_string($vbulletin->GPC['version']) . "'
			WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		");
	}
	else
	{
		// product IDs must match #^[a-z0-9_]+$# and must be max 25 chars
		if (!preg_match('#^[a-z0-9_]+$#s', $vbulletin->GPC['productid']) OR strlen($vbulletin->GPC['productid']) > 25)
		{
			$sugg = preg_replace('#\s+#s', '_', strtolower($vbulletin->GPC['productid']));
			$sugg = preg_replace('#[^\w]#s', '', $sugg);
			$sugg = str_replace('__', '_', $sugg);
			$sugg = substr($sugg, 0, 25);
			print_stop_message('product_id_invalid', htmlspecialchars_uni($vbulletin->GPC['productid']), $sugg);
		}

		// reserve 'vb' prefix for official vBulletin products
		if (!$vbulletin->GPC['confirm'] AND strtolower(substr($vbulletin->GPC['productid'], 0, 2)) == 'vb')
		{
			print_form_header('plugin', 'productsave');
			print_table_header($vbphrase['vbulletin_message']);
			print_description_row(
				htmlspecialchars_uni($vbulletin->GPC['title']) . ' ' . htmlspecialchars_uni($vbulletin->GPC['version']) .
				'<dfn>' . htmlspecialchars_uni($vbulletin->GPC['description']) . '</dfn>'
			);
			print_input_row($vbphrase['vb_prefix_reserved'], 'productid', $vbulletin->GPC['productid'], true, 35, 25);
			construct_hidden_code('title', $vbulletin->GPC['title']);
			construct_hidden_code('description', $vbulletin->GPC['description']);
			construct_hidden_code('version', $vbulletin->GPC['version']);
			construct_hidden_code('confirm', 1);
			print_submit_row();
			print_cp_footer();

			// execution terminates here
		}

		/* insert query */
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "product
				(productid, title, description, version, active)
			VALUES
				('" . $db->escape_string($vbulletin->GPC['productid']) . "',
				'" . $db->escape_string($vbulletin->GPC['title']) . "',
				'" . $db->escape_string($vbulletin->GPC['description']) . "',
				'" . $db->escape_string($vbulletin->GPC['version']) . "',
				1)
		");
	}

	// update the products datastore
	build_product_datastore();

	define('CP_REDIRECT', 'plugin.php?do=product');
	print_stop_message('product_x_updated', $vbulletin->GPC['productid']);
}

// #############################################################################

if ($_POST['do'] == 'productcode')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'productid'     => TYPE_STR,
		'version'       => TYPE_STR,
		'installcode'   => TYPE_STR,
		'uninstallcode' => TYPE_STR,
		'productcode'   => TYPE_ARRAY
	));

	$product = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	if (!$product)
	{
		print_stop_message('invalid_product_specified');
	}

	if ($vbulletin->GPC['version'] AND ($vbulletin->GPC['installcode'] OR $vbulletin->GPC['uninstallcode']))
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "productcode
				(productid, version, installcode, uninstallcode)
			VALUES
				('" . $db->escape_string($vbulletin->GPC['productid']) . "',
				'" . $db->escape_string($vbulletin->GPC['version']) . "',
				'" . $db->escape_string($vbulletin->GPC['installcode']) . "',
				'" . $db->escape_string($vbulletin->GPC['uninstallcode']) . "')
		");
	}

	foreach ($vbulletin->GPC['productcode'] AS $productcodeid => $productcode)
	{
		$productcodeid = intval($productcodeid);

		if ($productcode['delete'])
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "productcode
				WHERE productcodeid = $productcodeid
			");
		}
		else
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "productcode SET
					version = '" . $db->escape_string($productcode['version']) . "',
					installcode = '" . $db->escape_string($productcode['installcode']) . "',
					uninstallcode = '" . $db->escape_string($productcode['uninstallcode']) . "'
				WHERE productcodeid = $productcodeid
			");
		}
	}

	define('CP_REDIRECT', 'plugin.php?do=productedit&productid=' . $vbulletin->GPC['productid']);
	print_stop_message('product_x_updated', $vbulletin->GPC['productid']);
}

// #############################################################################

if ($_POST['do'] == 'productkill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'productid' => TYPE_STR
	));

	if (strtolower($vbulletin->GPC['productid']) == 'vbulletin')
	{
		print_cp_redirect('plugin.php?do=product');
	}

	// run uninstall code first; try to undo things in the opposite order they were done
	$productcodes = $db->query_read("
		SELECT version, uninstallcode
		FROM " . TABLE_PREFIX . "productcode
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND uninstallcode <> ''
	");

	$productcodes_grouped = array();
	$productcodes_versions = array();

	while ($productcode = $db->fetch_array($productcodes))
	{
		// have to be careful here, as version numbers are not necessarily unique
		$productcodes_versions["$productcode[version]"] = 1;
		$productcodes_grouped["$productcode[version]"][] = $productcode;
	}

	$productcodes_versions = array_keys($productcodes_versions);
	usort($productcodes_versions, 'version_sort');
	$productcodes_versions = array_reverse($productcodes_versions);

	foreach ($productcodes_versions AS $version)
	{
		foreach ($productcodes_grouped["$version"] AS $productcode)
		{
			eval($productcode['uninstallcode']);
		}
	}

	// need to remove the language columns for this product as well
	require_once(DIR . '/includes/class_dbalter.php');

	$db_alter =& new vB_Database_Alter_MySQL($db);
	if ($db_alter->fetch_table_info('language'))
	{
		$phrasetypes = $db->query_read("
			SELECT fieldname
			FROM " . TABLE_PREFIX . "phrasetype
			WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		");
		while ($phrasetype = $db->fetch_array($phrasetypes))
		{
			$db_alter->drop_field("phrasegroup_$phrasetype[fieldname]");
		}
	}

	delete_product($vbulletin->GPC['productid']);

	build_all_styles();

	vBulletinHook::build_datastore($db);

	require_once(DIR . '/includes/adminfunctions_language.php');
	build_language();

	build_options();

	build_product_datastore();

	// build bitfields to remove/add this products bitfields
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	if (!defined('DISABLE_PRODUCT_REDIRECT'))
	{
		define('CP_REDIRECT', 'plugin.php?do=product');
	}
	print_stop_message('product_x_uninstalled', $vbulletin->GPC['productid']);
}

// #############################################################################

if ($_REQUEST['do'] == 'productdelete')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'productid' => TYPE_STR
	));

	if (strtolower($vbulletin->GPC['productid']) == 'vbulletin')
	{
		print_cp_redirect('plugin.php?do=product');
	}

	print_delete_confirmation('product', $vbulletin->GPC['productid'], 'plugin', 'productkill');
}

// #############################################################################

if ($_POST['do'] == 'productimport')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'serverfile'   => TYPE_STR,
		'allowoverwrite' => TYPE_BOOL
	));

	$vbulletin->input->clean_array_gpc('f', array(
		'productfile' => TYPE_FILE
	));

	if (file_exists($vbulletin->GPC['productfile']['tmp_name']))
	{
		// got an uploaded file?
		$xml = file_read($vbulletin->GPC['productfile']['tmp_name']);
	}
	else if (file_exists($vbulletin->GPC['serverfile']))
	{
		// no uploaded file - got a local file?
		$xml = file_read($vbulletin->GPC['serverfile']);
	}
	else
	{
		print_stop_message('no_file_uploaded_and_no_local_file_found');
	}

	print_dots_start('<b>' . $vbphrase['importing_product'] . "</b>, $vbphrase[please_wait]", ':', 'dspan');

	require_once(DIR . '/includes/class_xml.php');

	$xmlobj = new XMLparser($xml);
	if ($xmlobj->error_no == 1)
	{
			print_dots_stop();
			print_stop_message('no_xml_and_no_path');
	}

	if(!$arr = $xmlobj->parse())
	{
		print_dots_stop();
		print_stop_message('xml_error_x_at_line_y', $xmlobj->error_string(), $xmlobj->error_line());
	}

	// ############## general product information
	$info = array(
		'productid'   => substr($arr['productid'], 0, 25),
		'title'       => $arr['title'],
		'description' => $arr['description'],
		'version'     => $arr['version'],
		'active'      => $arr['active']
	);

	if (!$info['productid'])
	{
		print_dots_stop();
		if (!empty($arr['plugin']))
		{
			print_stop_message('this_file_appears_to_be_a_plugin');
		}
		else
		{
			print_stop_message('invalid_file_specified');
		}
	}

	// look to see if we already have this product installed
	if ($existingprod = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($info['productid']) . "'"
	))
	{
		if (!$vbulletin->GPC['allowoverwrite'])
		{
			print_dots_stop();
			print_stop_message('product_x_installed_no_overwrite', $info['title']);
		}

		$active = $existingprod['active'];

		// clear out the old product info; settings should be retained in memory already
		delete_product($info['productid']);

		// not sure what we're deleting, so rebuild everything
		$rebuild = array(
			'templates' => true,
			'plugins' => true,
			'phrases' => true,
			'options' => true
		);

		$installed_version = $existingprod['version'];
	}
	else
	{
		$active = 1;

		$rebuild = array(
			'styles' => false,
			'plugins' => false,
			'phrases' => false,
			'options' => false
		);

		$installed_version = null;
	}

	if (strtolower($info['productid']) == 'vbulletin')
	{
		print_dots_stop();
		print_stop_message('product_x_installed_no_overwrite', 'vBulletin');
	}

	// ############## import install/uninstall code
	if (is_array($arr['codes']['code']))
	{
		$codes =& $arr['codes']['code'];
		if (!isset($codes[0]))
		{
			$codes = array($codes);
		}

		foreach ($codes AS $code)
		{
			/* insert query */
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "productcode
					(productid, version, installcode, uninstallcode)
				VALUES
					('" . $db->escape_string($info['productid']) . "',
					'" . $db->escape_string($code['version']) . "',
					'" . $db->escape_string($code['installcode']) . "',
					'" . $db->escape_string($code['uninstallcode']) . "')
			");

			// if we haven't installed this product before or the code was added
			// in a version that's newer than what we're installing, we need to run
			// this set of code
			if ($installed_version === null OR is_newer_version($code['version'], $installed_version))
			{
				eval($code['installcode']);
			}
		}
	}

	/* insert query */
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "product
			(productid, title, description, version, active)
		VALUES
			('" . $db->escape_string($info['productid']) . "',
			'" . $db->escape_string($info['title']) . "',
			'" . $db->escape_string($info['description']) . "',
			'" . $db->escape_string($info['version']) . "',
			$active)
	");


	// ############## import templates
	if (is_array($arr['templates']['template']))
	{
		$querybits = array();
		$querytemplates = 0;

		$templates =& $arr['templates']['template'];
		if (!isset($templates[0]))
		{
			$templates = array($templates);
		}

		foreach ($templates AS $template)
		{
			$title = $db->escape_string($template['name']);
			$template['template'] = $db->escape_string($template['value']);
			$template['username'] = $db->escape_string($template['username']);
			$template['templatetype'] = $db->escape_string($template['templatetype']);
			$template['date'] = intval($template['date']);

			if ($template['templatetype'] != 'template')
			{
				// template is a special template
				$querybits[] = "(-1, '$template[templatetype]', '$title', '$template[template]', '', $template[date], '$template[username]', '" . $db->escape_string($template['version']) . "', '" . $db->escape_string($info['productid']) . "')";
			}
			else
			{
				// template is a standard template
				$querybits[] = "(-1, '$template[templatetype]', '$title', '" . $db->escape_string(compile_template($template['value'])) . "', '$template[template]', $template[date], '$template[username]', '" . $db->escape_string($template['version']) . "', '" . $db->escape_string($info['productid']) . "')";
			}
			if (++$querytemplates % 20 == 0)
			{
				/*insert query*/
				$db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "template
					(styleid, templatetype, title, template, template_un, dateline, username, version, product)
					VALUES
					" . implode(',', $querybits) . "
				");
				$querybits = array();
			}
		}

		// insert any remaining templates
		if (!empty($querybits))
		{
			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "template
				(styleid, templatetype, title, template, template_un, dateline, username, version, product)
				VALUES
				" . implode(',', $querybits) . "
			");
		}
		unset($querybits);

		$rebuild['templates'] = true;
	}

	// ############## import hooks/plugins
	if (is_array($arr['plugins']['plugin']))
	{
		$plugins =& $arr['plugins']['plugin'];
		if (!isset($plugins[0]))
		{
			$plugins = array($plugins);
		}

		foreach ($plugins AS $plugin)
		{
			$plugin['product'] = $info['productid'];
			unset($plugin['devkey']);

			$db->query_write(fetch_query_sql($plugin, 'plugin'));
		}

		$rebuild['plugins'] = true;
	}

	// ############## import phrases
	if (is_array($arr['phrases']['phrasetype']))
	{
		require_once(DIR . '/includes/adminfunctions_language.php');

		$master_phrasetypes = array();
		$master_phrasefields = array();
		foreach(fetch_phrasetypes_array(false) as $phrasetype)
		{
			$master_phrasetypes["$phrasetype[title]"] = $phrasetype['phrasetypeid'];
			$master_phrasefields["$phrasetype[fieldname]"] = $phrasetype['phrasetypeid'];
		}

		$phrasetypes =& $arr['phrases']['phrasetype'];
		if (!isset($phrasetypes[0]))
		{
			$phrasetypes = array($phrasetypes);
		}

		foreach ($phrasetypes AS $phrasetype)
		{
			if (empty($phrasetype['phrase']))
			{
				continue;
			}

			$type = (empty($phrasetype['fieldname']) ? $phrasetype['name'] : $phrasetype['fieldname']);
			$typeid = intval($master_phrasefields["$type"]);
			if (!$typeid)
			{
				$typeid = intval($master_phrasetypes["$type"]);
			}

			if ($typeid == 0)
			{
				// group doesn't exist, we must create it; find max phrasetypeid that isn't a "special" type
				$maxgroup = $db->query_first("
					SELECT MAX(phrasetypeid) AS max
					FROM " . TABLE_PREFIX . "phrasetype
					WHERE phrasetypeid < 1000
				");

				$typeid = ($maxgroup['max'] + 1);

				$db->query_write("
					INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
						(phrasetypeid, fieldname, title, editrows, product)
					VALUES
						($typeid,
						'" . $db->escape_string($phrasetype['fieldname']) . "',
						'" . $db->escape_string($phrasetype['name']) . "',
						3,
						'" . $db->escape_string($info['productid']) . "')
				");

				// need to add the column to the language table as well
				require_once(DIR . '/includes/class_dbalter.php');

				$db_alter =& new vB_Database_Alter_MySQL($db);
				if ($db_alter->fetch_table_info('language'))
				{
					$db_alter->add_field(array(
						'name' => "phrasegroup_$phrasetype[fieldname]",
						'type' => 'mediumtext'
					));
				}
			}

			$phrases =& $phrasetype['phrase'];
			if (!isset($phrases[0]))
			{
				$phrases = array($phrases);
			}

			$sql = array();

			foreach ($phrases AS $phrase)
			{
				$sql[] = "(-1, $typeid, '" . $db->escape_string($phrase['name']) . "', '" . $db->escape_string($phrase['value']) . "', '" . $db->escape_string($info['productid']) . "')";
			}

			/*insert query*/
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, phrasetypeid, varname, text, product)
				VALUES
					" . implode(',', $sql)
			);
		}

		$rebuild['phrases'] = true;
	}

	// ############## import settings
	if (is_array($arr['options']['settinggroup']))
	{
		$settinggroups =& $arr['options']['settinggroup'];
		if (!isset($settinggroups[0]))
		{
			$settinggroups = array($settinggroups);
		}

		foreach ($settinggroups AS $group)
		{
			if (empty($group['setting']))
			{
				continue;
			}

			// create the setting group if it doesn't already exist
			/*insert query*/
			$db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "settinggroup
					(grouptitle, displayorder, volatile, product)
				VALUES
					('" . $vbulletin->db->escape_string($group['name']) . "',
					" . intval($group['displayorder']) . ",
					1,
					'" . $vbulletin->db->escape_string($info['productid']) . "')
			");

			$settings =& $group['setting'];
			if (!isset($settings[0]))
			{
				$settings = array($settings);
			}

			$setting_bits = array();

			foreach ($settings AS $setting)
			{
				if (isset($vbulletin->options["$setting[varname]"]))
				{
					$newvalue = $vbulletin->options["$setting[varname]"];
				}
				else
				{
					$newvalue = $setting['defaultvalue'];
				}

				$setting_bits[] = "(
					'" . $db->escape_string($setting['varname']) . "',
					'" . $db->escape_string($group['name']) . "',
					'" . $db->escape_string(trim($newvalue)) . "',
					'" . $db->escape_string(trim($setting['defaultvalue'])) . "',
					'" . $db->escape_string(trim($setting['datatype'])) . "',
					'" . $db->escape_string($setting['optioncode']) . "',
					" . intval($setting['displayorder']) . ",
					" . intval($setting['advanced']) . ",
					1,
					'" . $db->escape_string($info['productid']) . "'\n\t)";
			}

			/*insert query*/
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "setting
				(varname, grouptitle, value, defaultvalue, datatype, optioncode, displayorder, advanced, volatile, product)
				VALUES
				" . implode(",\n\t", $setting_bits)
			);
		}

		$rebuild['options'] = true;
	}

	// Check if the plugin system is disabled. If it is, enable it.
	if (!$vbulletin->options['enablehooks'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "setting
			SET value = '1'
			WHERE varname = 'enablehooks'
		");

		$rebuild['options'] = true;
	}

	// Now rebuild everything we need...
	if ($rebuild['templates'])
	{
		build_all_styles();
	}
	if ($rebuild['plugins'])
	{
		vBulletinHook::build_datastore($db);
	}
	if ($rebuild['phrases'])
	{
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();
	}
	if ($rebuild['options'])
	{
		build_options();
	}

	build_product_datastore();

	// build bitfields to remove/add this products bitfields
	require_once(DIR . '/includes/class_bitfield_builder.php');
	vB_Bitfield_Builder::save($db);

	print_dots_stop();

	if (!defined('DISABLE_PRODUCT_REDIRECT'))
	{
		define('CP_REDIRECT', 'plugin.php?do=product');
	}
	print_stop_message('product_x_imported', $info['productid']);
}

// #############################################################################

if ($_REQUEST['do'] == 'productexport')
{
	require_once(DIR . '/includes/class_xml.php');
	$xml = new XMLexporter();

	$vbulletin->input->clean_array_gpc('r', array(
		'productid'	=> TYPE_STR
	));

	//	Set up the parent tag
	$product_details = $db->query_first("
		SELECT productid, title, description, version, active
		FROM " . TABLE_PREFIX . "product
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	if (!$product_details)
	{
		print_stop_message('invalid_product_specified');
	}

	$xml->add_group('product', array(
		'productid' => $product_details['productid'],
		'active' => $product_details['active']
	)); // Parent for product

	$xml->add_tag('title', $product_details['title']);
	$xml->add_tag('description', $product_details['description']);
	$xml->add_tag('version', $product_details['version']);

	// START - Grab any extra code to run on install/uninstall
	$productcodes = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "productcode
		WHERE productid = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
	");

	$xml->add_group('codes');

	$productcodes_grouped = array();
	$productcodes_versions = array();

	while ($productcode = $db->fetch_array($productcodes))
	{
		// have to be careful here, as version numbers are not necessarily unique
		$productcodes_versions["$productcode[version]"] = 1;
		$productcodes_grouped["$productcode[version]"][] = $productcode;
	}

	$productcodes_versions = array_keys($productcodes_versions);
	usort($productcodes_versions, 'version_sort');

	foreach ($productcodes_versions AS $version)
	{
		foreach ($productcodes_grouped["$version"] AS $productcode)
		{
			$xml->add_group('code', array('version' => $productcode['version']));
				$xml->add_tag('installcode', $productcode['installcode']);
				$xml->add_tag('uninstallcode', $productcode['uninstallcode']);
			$xml->close_group();
		}
	}

	$xml->close_group();

	// START - Grab the templates
	$gettemplates = $db->query_read("
		SELECT title, templatetype, username, dateline, version, product,
			IF(templatetype = 'template', template_un, template) AS template
		FROM " . TABLE_PREFIX . "template
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND styleid = -1
		ORDER BY title
	");

	$xml->add_group('templates');

	while ($template = $db->fetch_array($gettemplates))
	{
		$xml->add_tag('template', $template['template'], array(
			'name' => htmlspecialchars($template['title']),
			'templatetype' => $template['templatetype'],
			'date' => $template['dateline'],
			'username' => $template['username'],
			'version' => htmlspecialchars_uni($template['version'])
		), true);
	}

	$xml->close_group();

	// START - Grab the plugins
	$xml->add_group('plugins');

	$plugins = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "plugin
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		ORDER BY hookname
	");
	while ($plugin = $db->fetch_array($plugins))
	{
		$params = array('active' => $plugin['active']);

		$xml->add_group('plugin', $params);
			$xml->add_tag('title', $plugin['title']);
			$xml->add_tag('hookname', $plugin['hookname']);
			$xml->add_tag('phpcode', $plugin['phpcode']);
		$xml->close_group();
	}

	unset($plugin);
	$db->free_result($plugins);
	$xml->close_group();

	// START - Grab the phrases
	require_once(DIR . '/includes/adminfunctions_language.php');

	$phrasetypes = fetch_phrasetypes_array(false);

	$phrases = array();
	$getphrases = $db->query_read("
		SELECT varname, text, phrasetypeid
		FROM " . TABLE_PREFIX . "phrase
		WHERE (languageid = -1 OR languageid = 0)
			AND product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
		ORDER BY languageid, phrasetypeid, varname
	");
	while ($getphrase = $db->fetch_array($getphrases))
	{
		$phrases["$getphrase[phrasetypeid]"]["$getphrase[varname]"] = $getphrase;
	}
	unset($getphrase);
	$db->free_result($getphrases);

	$xml->add_group('phrases');

	foreach ($phrases AS $_phrasetypeid => $typephrases)
	{
		// create a group for each phrase type that we have phrases for
		// then insert the phrases

		$xml->add_group('phrasetype', array('name' => $phrasetypes["$_phrasetypeid"]['title'], 'fieldname' => $phrasetypes["$_phrasetypeid"]['fieldname']));

		foreach ($typephrases AS $phrase)
		{
			$xml->add_tag('phrase', $phrase['text'], array('name' => $phrase['varname']), true);
		}

		$xml->close_group();
	}

	$xml->close_group();

	// START - Grab the options
	$setting = array();
	$settinggroup = array();

	$groups = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "settinggroup
		WHERE volatile = 1
		ORDER BY displayorder
	");
	while ($group = $db->fetch_array($groups))
	{
		$settinggroup["$group[grouptitle]"] = $group;
	}

	$options = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "setting
		WHERE product = '" . $db->escape_string($vbulletin->GPC['productid']) . "'
			AND volatile = 1
		ORDER BY displayorder
	");
	while ($set = $db->fetch_array($options))
	{
		$setting["$set[grouptitle]"][] = $set;
	}
	unset($set);
	$db->free_result($sets);

	$xml->add_group('options');

	foreach ($settinggroup AS $grouptitle => $group)
	{
		if (empty($setting["$grouptitle"]))
		{
			continue;
		}

		// add a group for each setting group we have settings for

		$xml->add_group('settinggroup', array('name' => htmlspecialchars($group['grouptitle']), 'displayorder' => $group['displayorder']));

		foreach($setting["$grouptitle"] AS $set)
		{
			$arr = array('varname' => $set['varname'], 'displayorder' => $set['displayorder']);
			if ($set['advanced'])
			{
				$arr['advanced'] = 1;
			}
			$xml->add_group('setting', $arr);

			if ($set['datatype'])
			{
				$xml->add_tag('datatype', $set['datatype']);
			}
			if ($set['optioncode'] != '')
			{
				$xml->add_tag('optioncode', $set['optioncode']);
			}
			if ($set['defaultvalue'] !== '')
			{
				$xml->add_tag('defaultvalue', $set['defaultvalue']);
			}
			$xml->close_group();
		}

		$xml->close_group();
	}

	$xml->close_group();


	// Finish up
	$xml->close_group();
	$doc = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\r\n\r\n" . $xml->output();

	unset($xml);

	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, "product-" . $vbulletin->GPC['productid'] . '.xml', 'text/xml');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 04:51, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 14697 $
|| ####################################################################
\*======================================================================*/
?>