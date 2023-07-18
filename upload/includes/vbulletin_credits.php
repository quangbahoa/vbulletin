<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11 - Licence Number VBS4AAFB47
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2023 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

print_form_header('index', 'home');
print_table_header($vbphrase['vbulletin_developers_and_contributors']);
print_column_style_code(array('white-space: nowrap', ''));
print_label_row('<b>' . $vbphrase['software_developed_by'] . '</b>', '
	<a href="https://www.vbulletin.com/" target="vbulletin">vBulletin Solutions Inc.</a>, 
	<a href="https://www.internetbrands.com/" target="vbulletin">Internet Brands, Inc.</a>
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['product_manager'] . '</b>', '
	Paul Marsden
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['business_development'] . '</b>', '
	John McGanty,
	Marjo Mercado
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['software_development'] . '</b>', '
	Paul Marsden
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['support'] . '</b>', '
	Aakif Nazir,
	Christine Tran,
	Dominic Schlatter,
	Joe DiBiasi,
	Joshua Gonzales,
	Lynne Sands,
	Mark Bowland,
	Trevor Hannant,
	Wayne Luke
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['other_contributions_from'] . '</b>', '
	Chen Avinadav,
	Darren Gordon,
	Doron Rosenberg,
	D\'Marco Brown,
	Floris Fiedeldij Dop,
	Freddie Bingham,
	George Liu,
	Jake Bunce,
	Jerry Hutchings,
	Kevin Schumacher,
	Kier Darby,
	Mark James,
	Martin Meredith,
	Michael \'Mystics\' K&ouml;nig,
	Mike Sullivan,
	Overgrow,
	Scott MacVicar,
	Stephan \'pogo\' Pogodalla,
	Torstein H&oslash;nsi,
	Zachery Woods
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['copyright_enforcement_by'] . '</b>', '
	<a href="https://www.vbulletin.com/" target="vbulletin">vBulletin Solutions Inc.</a>
', '', 'top', NULL, false);
print_table_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
