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

error_reporting(E_ALL & ~E_NOTICE);

define('THIS_SCRIPT', 'upgrade_300b3.php');
define('VERSION', '3.0.0 Beta 3');
define('UPGRADE_COMPAT', true);

// #############################################################################
// array of titles for each step - alter this if you change the contents of a step

// just temporary for my testing purposes - KD
define('VB3UPGRADE', 1);
// require the code that makes it all work...
require_once('./upgradecore.php');

// 3.7.0 changes mean that...
if (!empty($vbulletin->GPC['step']))
{
	echo '<div style="margin:75px">' . $upgrade_phrases['upgrade_300b3.php']['upgrade_from_vb2_not_supported'] . '</div>';
	
	print_upgrade_footer();
	
	exit;
}


if (TABLE_PREFIX != '')
{
	?>
	<blockquote>
		<p style="font-size:10pt; color: red;"><b><?php echo $upgrade_phrases['upgrade_300b3.php']['tableprefix_not_empty']; ?></b></p>
		<p><?php echo $upgrade_phrases['upgrade_300b3.php']['tableprefix_not_empty_fix']; ?></p>
	</blockquote>
	<?php
	print_upgrade_footer();
	exit;
}
// we need all the new table data
require_once(DIR . '/install/mysql-schema.php');

// #############################################################################
// introduction
if ($vbulletin->GPC['step'] === 'welcome')
{
	echo "<blockquote>\n";
	echo sprintf($upgrade_phrases['upgrade_300b3.php']['welcome'], htmlspecialchars_uni($vbulletin->config['Database']['dbname']));
	echo "</blockquote>\n";

	if (SAFEMODE)
	{
		// Use ini_set here instead?
		echo "<p><i>{$upgrade_phrases['upgrade_300b3.php']['safe_mode_warning']}</i></p>";
	}

	// turn the board off
	$db->query_write("UPDATE setting SET value = 0 WHERE varname = 'bbactive'");
	$db->query_write("UPDATE template SET template = CONCAT(template,'\n\$bbactive = 0;\n') WHERE title = 'options'");

	// create the upgradelog table - don't worry if this table has already been created
	$db->hide_errors();
	$db->query_write("
		CREATE TABLE upgradelog(
			upgradelogid int unsigned NOT NULL AUTO_INCREMENT,
			script varchar(50) NOT NULL default '',
			steptitle varchar(250) NOT NULL default '',
			step smallint unsigned NOT NULL default 0,
			startat int unsigned NOT NULL default 0,
			perpage smallint unsigned NOT NULL default 0,
			dateline int unsigned NOT NULL default 0,
			PRIMARY KEY (upgradelogid)
		)
	");
	$db->show_errors();
}

// #############################################################################
// Create New vBulletin 3 Tables
if ($vbulletin->GPC['step'] == 1)
{
	$db->hide_errors();
	$db->query_read("SELECT COUNT(*) AS count FROM calendar");
	$db->show_errors();
	$errno = $db->errno;
	if (!$errno)
	{
		$errno = 0;
	}

	if ($errno == 0)
	{
		echo "<blockquote>{$upgrade_phrases['upgrade_300b3.php']['upgrade_already_run']}</blockquote>";
		print_upgrade_footer();
	}

	$year = date('Y');

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "calendar (
	calendarid int unsigned NOT NULL auto_increment,
	title varchar(255) NOT NULL DEFAULT '',
	description varchar(100) NOT NULL DEFAULT '',
	displayorder smallint NOT NULL DEFAULT '0',
	neweventemail varchar(255) NOT NULL DEFAULT '',
	moderatenew smallint NOT NULL DEFAULT '0',
	startofweek smallint NOT NULL DEFAULT '0',
	options int unsigned NOT NULL DEFAULT '0',
	cutoff smallint unsigned NOT NULL DEFAULT '0',
	eventcount smallint unsigned NOT NULL DEFAULT '0',
	birthdaycount smallint unsigned NOT NULL DEFAULT '0',
	startyear smallint unsigned NOT NULL DEFAULT '2000',
	endyear smallint unsigned NOT NULL DEFAULT '2006',
	PRIMARY KEY (calendarid),
	KEY displayorder (displayorder)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendar");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "calendarmoderator (
 	calendarmoderatorid int unsigned NOT NULL auto_increment,
	userid int unsigned NOT NULL DEFAULT '0',
	calendarid int unsigned NOT NULL DEFAULT '0',
	neweventemail smallint NOT NULL DEFAULT '0',
	permissions int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarmoderatorid),
	KEY userid (userid, calendarid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarmoderator");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "calendarpermission (
	calendarpermissionid int unsigned NOT NULL auto_increment,
	calendarid int unsigned NOT NULL DEFAULT '0',
	usergroupid smallint unsigned NOT NULL DEFAULT '0',
	calendarpermissions int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarpermissionid),
	KEY calendarid (calendarid),
	KEY usergroupid (usergroupid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarpermission");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "calendarcustomfield (
	calendarcustomfieldid int unsigned NOT NULL auto_increment,
	calendarid int unsigned NOT NULL DEFAULT '0',
	title varchar(255) NOT NULL DEFAULT '',
	options mediumtext,
	allowentry smallint NOT NULL DEFAULT '1',
	required smallint NOT NULL DEFAULT '0',
	length smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarcustomfieldid),
	KEY calendarid (calendarid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarcustomfield");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "holiday (
	holidayid int unsigned NOT NULL auto_increment,
	varname varchar(100) NOT NULL DEFAULT '',
	recurring smallint unsigned NOT NULL DEFAULT '0',
	recuroption char(6) NOT NULL DEFAULT '',
	allowsmilies smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (holidayid),
	KEY varname (varname)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "holiday");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "subscribeevent (
	userid int unsigned NOT NULL DEFAULT '0',
	eventid int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (userid,eventid),
	KEY eventid (eventid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribeevent");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "ranks (
	rankid smallint unsigned NOT NULL auto_increment,
	minposts smallint unsigned NOT NULL DEFAULT '0',
	ranklevel smallint unsigned NOT NULL DEFAULT '0',
	rankimg varchar(255) NOT NULL DEFAULT '',
	usergroupid smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (rankid),
	KEY grouprank (usergroupid, minposts)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "ranks");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "usertextfield (
	userid int unsigned NOT NULL DEFAULT '0',
	subfolders mediumtext,
	pmfolders mediumtext,
	buddylist mediumtext,
	ignorelist mediumtext,
	signature mediumtext,
	searchprefs mediumtext,
	PRIMARY KEY (userid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usertextfield");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "imagecategory (
	imagecategoryid smallint unsigned NOT NULL auto_increment,
	title varchar(255) NOT NULL DEFAULT '',
	imagetype smallint unsigned NOT NULL DEFAULT '0',
	displayorder smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (imagecategoryid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "imagecategory");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "imagecategorypermission (
	imagecategoryid smallint unsigned NOT NULL DEFAULT '0',
	usergroupid smallint unsigned NOT NULL DEFAULT '0',
	KEY imagecategoryid (imagecategoryid, usergroupid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "imagecategorypermission");

	// needs to create the version without the underscore to prevent DB errors!
	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "postparsed (
	postid int unsigned NOT NULL DEFAULT '0',
	dateline int unsigned NOT NULL DEFAULT '0',
	hasimages smallint NOT NULL DEFAULT '0',
	pagetext_html mediumtext,
	PRIMARY KEY (postid),
	KEY dateline (dateline)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "postparsed");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "strikes (
	striketime int unsigned NOT NULL DEFAULT '0',
	strikeip char(15) NOT NULL DEFAULT '',
	username char(50) NOT NULL DEFAULT '',
	KEY striketime (striketime),
	KEY strikeip (strikeip)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "strikes");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "passwordhistory (
	userid int unsigned NOT NULL DEFAULT '0',
	password varchar(50) NOT NULL DEFAULT '',
	passworddate date NOT NULL DEFAULT '0000-00-00',
	KEY userid (userid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "passwordhistory");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "moderatorlog (
	moderatorlogid int unsigned NOT NULL auto_increment,
	dateline int unsigned NOT NULL DEFAULT '0',
	userid int unsigned NOT NULL DEFAULT '0',
	forumid smallint unsigned NOT NULL DEFAULT '0',
	threadid int unsigned NOT NULL DEFAULT '0',
	postid int unsigned NOT NULL DEFAULT '0',
	pollid int unsigned NOT NULL DEFAULT '0',
	action varchar(250) NOT NULL DEFAULT '',
	PRIMARY KEY (moderatorlogid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "moderatorlog");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "usernote (
	usernoteid int unsigned NOT NULL auto_increment,
	userid int unsigned NOT NULL DEFAULT '0',
	posterid int unsigned NOT NULL DEFAULT '0',
	username varchar(50) NOT NULL DEFAULT '',
	dateline int unsigned NOT NULL DEFAULT '0',
	message mediumtext,
	title varchar(255) NOT NULL DEFAULT '',
	allowsmilies smallint NOT NULL DEFAULT '0',
	PRIMARY KEY (usernoteid),
	KEY userid (userid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usernote");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "customprofilepic (
	userid int unsigned NOT NULL DEFAULT '0',
	profilepicdata mediumtext,
	dateline int unsigned NOT NULL DEFAULT '0',
	filename varchar(100) NOT NULL DEFAULT '',
	visible smallint NOT NULL DEFAULT '1',
	PRIMARY KEY (userid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "customprofilepic");

	$query[] = 'DROP TABLE search';
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "search");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "search (
	searchid int unsigned NOT NULL auto_increment,
	userid int unsigned NOT NULL DEFAULT '0',
	ipaddress char(15) NOT NULL DEFAULT '',
	personal smallint unsigned NOT NULL DEFAULT '0',
	query varchar(200) NOT NULL DEFAULT '',
	searchuser varchar(200) NOT NULL DEFAULT '',
	forumchoice mediumtext,
	sortby varchar(200) NOT NULL DEFAULT '',
	sortorder varchar(4) NOT NULL DEFAULT '',
	searchtime float NOT NULL DEFAULT '0',
	showposts smallint unsigned NOT NULL DEFAULT '0',
	orderedids mediumtext,
	dateline int unsigned NOT NULL DEFAULT '0',
	searchterms mediumtext,
	displayterms mediumtext,
	searchhash varchar(32) NOT NULL DEFAULT '',
	PRIMARY KEY (searchid),
	UNIQUE KEY searchunique (searchhash, sortby, sortorder)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "search");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "language (
	languageid smallint(5) unsigned NOT NULL auto_increment,
	title varchar(50) NOT NULL default '',
	userselect smallint(5) unsigned NOT NULL default '1',
	options smallint(5) unsigned NOT NULL default '1',
	languagecode varchar(12) NOT NULL default '',
	charset VARCHAR(15) NOT NULL default '',
	imagesoverride varchar(150) NOT NULL default '',
	dateoverride varchar(20) NOT NULL default '',
	timeoverride varchar(20) NOT NULL default '',
	registereddateoverride varchar(20) NOT NULL default '',
	calformat1override varchar(20) NOT NULL default '',
	calformat2override varchar(20) NOT NULL default '',
	logdateoverride varchar(20) NOT NULL default '',
	decimalsep char(1) NOT NULL default '.',
	thousandsep char(1) NOT NULL default ',',
	phrasegroup_global mediumtext,
	phrasegroup_cpglobal mediumtext,
	phrasegroup_cppermission mediumtext,
	phrasegroup_forum mediumtext,
	phrasegroup_calendar mediumtext,
	phrasegroup_attachment_image mediumtext,
	phrasegroup_style mediumtext,
	phrasegroup_logging mediumtext,
	phrasegroup_cphome mediumtext,
	phrasegroup_promotion mediumtext,
	phrasegroup_user mediumtext,
	phrasegroup_help_faq mediumtext,
	phrasegroup_sql mediumtext,
	phrasegroup_subscription mediumtext,
	phrasegroup_language mediumtext,
	phrasegroup_bbcode mediumtext,
	phrasegroup_stats mediumtext,
	phrasegroup_diagnostic mediumtext,
	phrasegroup_maintenance mediumtext,
	phrasegroup_profilefield mediumtext,
	phrasegroup_thread mediumtext,
	phrasegroup_timezone mediumtext,
	phrasegroup_banning mediumtext,
	phrasegroup_reputation mediumtext,
	phrasegroup_wol mediumtext,
	phrasegroup_threadmanage mediumtext,
	phrasegroup_pm mediumtext,
	phrasegroup_cpuser mediumtext,
	phrasegroup_accessmask MEDIUMTEXT,
	phrasegroup_cron MEDIUMTEXT,
	phrasegroup_moderator MEDIUMTEXT,
	phrasegroup_cpoption MEDIUMTEXT,
	phrasegroup_cprank MEDIUMTEXT,
	phrasegroup_cpusergroup MEDIUMTEXT,
	phrasegroup_holiday MEDIUMTEXT,
	phrasegroup_posting mediumtext,
	phrasegroup_poll mediumtext,
	phrasegroup_fronthelp mediumtext,
	phrasegroup_register mediumtext,
	phrasegroup_search mediumtext,
	phrasegroup_showthread mediumtext,
	phrasegroup_postbit mediumtext,
	phrasegroup_forumdisplay mediumtext,
	phrasegroup_messaging mediumtext,
	PRIMARY KEY  (languageid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "language");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "phrase (
	phraseid int unsigned NOT NULL auto_increment,
	languageid smallint NOT NULL DEFAULT '0',
	varname varchar(250) NOT NULL DEFAULT '',
	text mediumtext,
	phrasetypeid smallint unsigned NOT NULL DEFAULT '0',
	product varchar (15) NOT NULL DEFAULT '',
	PRIMARY KEY  (phraseid),
	UNIQUE KEY name_lang_type (varname,languageid,phrasetypeid),
	KEY languageid (languageid,phrasetypeid),
	KEY varname (varname)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "phrase");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "phrasetype (
	phrasetypeid smallint unsigned NOT NULL DEFAULT '0',
	fieldname char(20) NOT NULL default '',
	title char(50) NOT NULL DEFAULT '',
	editrows smallint unsigned NOT NULL DEFAULT '0',
	product varchar(15) NOT NULL DEFAULT '0',
	PRIMARY KEY (phrasetypeid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "phrasetype");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "mailqueue (
	mailqueueid int unsigned NOT NULL auto_increment,
	dateline int unsigned NOT NULL DEFAULT '0',
	toemail mediumtext,
	subject mediumtext,
	message mediumtext,
	header mediumtext,
	PRIMARY KEY (mailqueueid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "mailqueue");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "adminhelp (
	adminhelpid int unsigned NOT NULL auto_increment,
	script varchar(50) NOT NULL DEFAULT '',
	action varchar(25) NOT NULL DEFAULT '',
	optionname varchar(25) NOT NULL DEFAULT '',
	displayorder smallint unsigned NOT NULL DEFAULT '1',
	volatile smallint unsigned NOT NULL DEFAULT '0',
	product varchar(15) NOT NULL DEFAULT '',
	PRIMARY KEY (adminhelpid),
	UNIQUE KEY phraseunique (script, action, optionname)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminhelp");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "cron (
	cronid int unsigned NOT NULL auto_increment,
	nextrun int unsigned NOT NULL DEFAULT '0',
	weekday smallint NOT NULL DEFAULT '0',
	day smallint NOT NULL DEFAULT '0',
	hour smallint NOT NULL DEFAULT '0',
	minute smallint NOT NULL DEFAULT '0',
	filename char(50) NOT NULL DEFAULT '',
	loglevel smallint NOT NULL DEFAULT '0',
	title varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (cronid),
	KEY nextrun (nextrun)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cron");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "cronlog (
	cronlogid int unsigned NOT NULL auto_increment,
	cronid int unsigned NOT NULL DEFAULT '0',
	dateline int unsigned NOT NULL DEFAULT '0',
	description mediumtext,
	PRIMARY KEY (cronlogid),
	KEY cronid (cronid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cronlog");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "reminder (
	reminderid int unsigned NOT NULL auto_increment,
	userid int unsigned NOT NULL DEFAULT '0',
	title varchar(50) NOT NULL DEFAULT '',
	text mediumtext,
	duedate int unsigned NOT NULL DEFAULT '0',
	adminonly smallint unsigned NOT NULL DEFAULT '1',
	completedby int unsigned NOT NULL DEFAULT '0',
	completedtime int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (reminderid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reminder");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "userpromotion (
	userpromotionid int unsigned NOT NULL auto_increment,
	usergroupid int unsigned NOT NULL DEFAULT '0',
	joinusergroupid int unsigned NOT NULL DEFAULT '0',
	reputation int unsigned NOT NULL DEFAULT '0',
	date int unsigned NOT NULL DEFAULT '0',
	posts int unsigned NOT NULL DEFAULT '0',
	strategy smallint NOT NULL DEFAULT '0',
	type smallint NOT NULL DEFAULT '2',
	PRIMARY KEY (userpromotionid),
	KEY usergroupid (usergroupid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userpromotion");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "datastore (
	title char(50) NOT NULL DEFAULT '',
	data mediumtext,
	unserialize SMALLINT NOT NULL DEFAULT '2',
	PRIMARY KEY (title)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "datastore");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "threadviews (
	threadid int unsigned NOT NULL DEFAULT '0',
	KEY threadid (threadid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadviews");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "attachmentviews (
	attachmentid int unsigned NOT NULL DEFAULT '0',
	KEY postid (attachmentid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmentviews");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "deletionlog (
	primaryid int unsigned NOT NULL DEFAULT '0',
	type enum('post', 'thread') NOT NULL DEFAULT 'post',
	userid int unsigned NOT NULL DEFAULT '0',
	username varchar(50) NOT NULL DEFAULT '',
	reason varchar(125) NOT NULL DEFAULT '',
	PRIMARY KEY (primaryid, type)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "deletionlog");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "usergrouprequest (
	usergrouprequestid int unsigned NOT NULL auto_increment,
	userid int unsigned NOT NULL DEFAULT '0',
	usergroupid smallint unsigned NOT NULL DEFAULT '0',
	reason varchar(250) NOT NULL DEFAULT '',
	dateline int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (usergrouprequestid),
	KEY usergroupid (usergroupid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergrouprequest");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "usergroupleader (
	usergroupleaderid smallint unsigned NOT NULL auto_increment,
	userid int unsigned NOT NULL DEFAULT '0',
	usergroupid smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (usergroupleaderid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergroupleader");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "attachmenttype (
	extension char(5) NOT NULL DEFAULT '',
	mimetype varchar(255) NOT NULL DEFAULT '',
	size int unsigned NOT NULL DEFAULT '0',
	width smallint unsigned NOT NULL DEFAULT '0',
	height smallint unsigned NOT NULL DEFAULT '0',
	enabled smallint unsigned NOT NULL DEFAULT '1',
	display smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (extension)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmenttype");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "editlog (
	postid int unsigned NOT NULL DEFAULT '0',
	userid int unsigned NOT NULL DEFAULT '0',
	username varchar(50) NOT NULL DEFAULT '',
	dateline int unsigned NOT NULL DEFAULT '0',
	reason varchar(200) NOT NULL DEFAULT '',
	PRIMARY KEY (postid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "editlog");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "faq (
	faqname varchar(50) NOT NULL DEFAULT '',
	faqparent varchar(50) NOT NULL DEFAULT '',
	displayorder smallint unsigned NOT NULL DEFAULT '0',
	volatile smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (faqname),
	KEY faqparent (faqparent)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "faq");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "subscription (
	subscriptionid smallint unsigned NOT NULL auto_increment,
	title varchar(100) NOT NULL DEFAULT '',
	description varchar(250) NOT NULL DEFAULT '',
	cost char(10) NOT NULL DEFAULT '',
	length char(10) NOT NULL DEFAULT '',
	units char(1) NOT NULL DEFAULT '',
	forums mediumtext,
	nusergroupid smallint NOT NULL DEFAULT '0',
	membergroupids varchar(255) NOT NULL DEFAULT '',
	methods smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (subscriptionid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscription");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "subscriptionlog (
	subscriptionlogid mediumint unsigned NOT NULL auto_increment,
	subscriptionid smallint NOT NULL DEFAULT '0',
	userid int unsigned NOT NULL DEFAULT '0',
	pusergroupid smallint NOT NULL DEFAULT '0',
	status smallint NOT NULL DEFAULT '0',
	regdate int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (subscriptionlogid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscriptionlog");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "reputation (
	postid int NOT NULL DEFAULT '1',
	userid int NOT NULL DEFAULT '1',
	reputation int NOT NULL DEFAULT '0',
	whoadded int NOT NULL DEFAULT '0',
	reason varchar(250) NOT NULL DEFAULT '',
	dateline int NOT NULL DEFAULT '0',
	KEY userid (userid),
	KEY whoadded (whoadded),
	KEY multi (postid, userid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reputation");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "reputationlevel (
	reputationlevelid int unsigned NOT NULL auto_increment,
	minimumreputation int NOT NULL DEFAULT '0',
	level varchar(250) NOT NULL DEFAULT '',
	PRIMARY KEY (reputationlevelid),
	KEY reputationlevel (minimumreputation)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reputationlevel");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "pm (
	pmid int unsigned NOT NULL auto_increment,
	pmtextid int unsigned NOT NULL DEFAULT '0',
	userid int unsigned NOT NULL DEFAULT '0',
	folderid smallint NOT NULL DEFAULT '0',
	messageread smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (pmid),
	KEY pmtextid (pmtextid),
	KEY userid (userid),
	KEY folderid (folderid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pm");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "pmtext (
	pmtextid int unsigned NOT NULL auto_increment,
	fromuserid int unsigned NOT NULL DEFAULT '0',
	fromusername varchar(50) NOT NULL DEFAULT '',
	title varchar(250) NOT NULL DEFAULT '',
	message mediumtext,
	touserarray mediumtext,
	iconid smallint unsigned NOT NULL DEFAULT '0',
	dateline int unsigned NOT NULL DEFAULT '0',
	showsignature smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (pmtextid),
	KEY fromuserid (fromuserid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pmtext");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "pmreceipt (
	pmid int unsigned NOT NULL DEFAULT '0',
	userid int unsigned NOT NULL DEFAULT '0',
	touserid int unsigned NOT NULL DEFAULT '0',
	tousername varchar(50) NOT NULL DEFAULT '',
	title varchar(250) NOT NULL DEFAULT '',
	sendtime int unsigned NOT NULL DEFAULT '0',
	readtime int unsigned NOT NULL DEFAULT '0',
	denied smallint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (pmid),
	KEY userid (userid),
	KEY touserid (touserid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pmreceipt");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "stats (
	dateline int unsigned NOT NULL DEFAULT '0',
	nuser mediumint unsigned NOT NULL DEFAULT '0',
	nthread mediumint unsigned NOT NULL DEFAULT '0',
	npost mediumint unsigned NOT NULL DEFAULT '0',
	nviews mediumint unsigned NOT NULL DEFAULT '0',
	ausers mediumint unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (dateline)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "stats");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "moderation (
	threadid int unsigned NOT NULL DEFAULT '0',
	postid int unsigned NOT NULL DEFAULT '0',
	type enum('thread', 'reply') NOT NULL DEFAULT 'thread',
	PRIMARY KEY (postid, type),
	KEY type (type)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "moderation");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "userban (
	userid int unsigned NOT NULL DEFAULT '0',
	usergroupid smallint unsigned NOT NULL DEFAULT '0',
	displaygroupid smallint unsigned NOT NULL DEFAULT '0',
	adminid int unsigned NOT NULL DEFAULT '0',
	bandate int unsigned NOT NULL DEFAULT '0',
	liftdate int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (userid),
	KEY liftdate (liftdate)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userban");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "administrator (
	userid int unsigned NOT NULL DEFAULT '0',
	adminpermissions int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (userid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "administrator");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "posthash (
	userid int unsigned NOT NULL DEFAULT '0',
	threadid int unsigned NOT NULL DEFAULT '0',
	postid int unsigned NOT NULL DEFAULT '0',
	dupehash char(32) NOT NULL DEFAULT '',
	dateline int unsigned NOT NULL DEFAULT '0',
	KEY userid (userid, dupehash),
	KEY dateline (dateline)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "posthash");

	exec_queries();

	// The image activation table may already exist (from 2.3.0)
	$db->hide_errors();
	$db->query_write("
CREATE TABLE " . TABLE_PREFIX . "regimage (
	regimagehash char(32) NOT NULL DEFAULT '',
	imagestamp char(6) NOT NULL DEFAULT '',
	dateline int unsigned NOT NULL DEFAULT '0',
	KEY regimagehash (regimagehash, dateline)
)
");
	$db->show_errors();

	## Populate bitfields
	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (vB_Bitfield_Builder::build(false) !== false)
	{
		vB_Bitfield_Builder::save($db);
	}

}

// #############################################################################
// Update Templates - Do ALTER command in small steps as some servers bomb out if you specify too much
if ($vbulletin->GPC['step'] == 2)
{
	if ($maxloggedin = $db->query_first("SELECT template FROM template WHERE templatesetid = -2 AND title = 'maxloggedin'"))
	{
		$maxusers = explode(' ', $maxloggedin['template']);
		$maxtemp['maxonline'] = $maxusers[0];
		$maxtemp['maxonlinedate'] = $maxusers[1];
		$template = serialize($maxtemp);
		$query[] = "INSERT INTO datastore (title, data) VALUES ('maxloggedin', '" . $db->escape_string($template) . "')";
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['moving_maxloggedin_datastore'];
	}

	// make attachment cache based on vB2 attachment settings
	$imagetypes = array('bmp', 'gif', 'jpe', 'jpeg', 'jpg', 'png');
	$attachmentcache = array('extensions' => 'bmp doc gif jpe jpeg jpg pdf png psd txt zip');

	foreach(explode(' ', $attachmentcache['extensions']) as $extension)
	{
		if (in_array($extension, $imagetypes))
		{
			$width = $maxattachwidth;
			$height = $maxattachheight;
		}
		else
		{
			$width = 0;
			$height = 0;
		}

		$attachmentcache["$extension"] = array
		(
			'extension' => $extension,
			'size' => $maxattachsize,
			'width' => $width,
			'height' => $height,
			'enabled' => 1,
			'display' => iif($extension == 'txt', 2, 0),
			'newwindow' => iif(in_array($extension, array('bmp', 'gif', 'jpe', 'jpeg', 'png')), 1, 0),
		);
	}

	$attachmentcache = serialize($attachmentcache);

	$query[] = "
		INSERT INTO datastore
			(title, data)
		VALUES
			('birthdaycache', ''),
			('smiliecache', 'a:0:{}'),
			('iconcache', 'a:0:{}'),
			('bbcodecache', 'a:0:{}'),
			('rankphp', ''),
			('userstats', 'a:0:{}'),
			('mailqueue', 0),
			('cron', 0),
			('attachmentcache', '" . $db->escape_string($attachmentcache) . "'),
			('banemail', '" . $db->escape_string($banemail) . "'),
			('eventcache', 'a:0:{}'),
			('usergroupcache', 'a:0:{}'),
			('forumcache', 'a:0:{}'),
			('wol_spiders', 'a:0:{}')
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['new_datastore_values'];

	$query[] = "DELETE FROM template WHERE templatesetid = -2";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['removing_special_templates'];

	$query[] = "DELETE FROM privatemessage WHERE userid = 0";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['removing_orphan_pms'];

	exec_queries();
}

// #############################################################################
// Calendar Updates
if ($vbulletin->GPC['step'] == 3)
{
	$query[] = "ALTER TABLE calendar_events RENAME event";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['rename_calendar_events'];

	$query[] = "ALTER TABLE event
		ADD calendarid INT UNSIGNED NOT NULL DEFAULT '0',
		ADD recurring SMALLINT NOT NULL DEFAULT '0',
		ADD recuroption VARCHAR(6) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 1, 5);

	$query[] = "ALTER TABLE event
		ADD dateline_from INT UNSIGNED NOT NULL DEFAULT '0',
		ADD dateline_to INT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 2, 5);

	$query[] = "ALTER TABLE event
		ADD visible SMALLINT DEFAULT 1 NOT NULL DEFAULT '0',
		ADD utc SMALLINT NOT NULL DEFAULT '0'";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 3, 5);

	$query[] = "ALTER TABLE event
		ADD dateline INT UNSIGNED NOT NULL DEFAULT '0',
		ADD INDEX daterange (calendarid,visible,dateline_from,dateline_to)
		";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 4, 5);

	$query[] = "ALTER TABLE event ADD customfields MEDIUMTEXT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'event', 5, 5);

	exec_queries();

	// Convert dates to new style
	$events = $db->query_read("SELECT eventid, eventdate FROM event");
	while ($event = $db->fetch_array($events))
	{
		$temp = explode('-', $event['eventdate']);
		if (intval($temp[0]) < 1970 OR intval($temp[0]) > 2037)
		{
			$temp[0] = 2002;
		}
		// The times on these are really arbitrary sine we didn't support real UTC independence before.
		// This will cause existing events to appear on wrong dates for some users now. They will need to be edited as required
		$from_timestamp = gmmktime(12, 0, 0, $temp[1], $temp[2], $temp[0]);
		$to_timestamp = gmmktime(13, 0, 0, $temp[1], $temp[2], $temp[0]);
		$db->query_write("
			UPDATE event
			SET	dateline_from = $from_timestamp,
				dateline_to = $to_timestamp
			WHERE eventid = $event[eventid]
		");
	}

	$query[] = "ALTER TABLE event
				DROP eventdate";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['droping_event_date'];

	$query[] = "ALTER TABLE event
				CHANGE subject title VARCHAR(254) NOT NULL DEFAULT ''";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['changing_subject_to_title'];

	// Create a private and public calendar

	$query[] = "INSERT INTO calendar (title, description, displayorder, startofweek, options, cutoff, eventcount, birthdaycount)
			VALUES ('{$upgrade_phrases['upgrade_300b3.php']['public']}', '{$upgrade_phrases['upgrade_300b3.php']['public_calendar']}', 1, 1, 375, 15, 5, 2)";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['creating_pub_calendar'];

	$query[] = "INSERT INTO calendar (title, description, displayorder, startofweek, options, cutoff, eventcount, birthdaycount)
			VALUES ('{$upgrade_phrases['upgrade_300b3.php']['private']}', '{$upgrade_phrases['upgrade_300b3.php']['private_calendar']}', 2, 1, 374, 15, 5, 2)";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['creating_priv_calendar'];

	$query[] = 'UPDATE event SET calendarid = 1 WHERE public = 1';
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['moving_pub_events'];

	$query[] = 'UPDATE event SET calendarid = 2 WHERE public = 0';
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['moving_priv_events'];

	$query[] = 'ALTER TABLE event DROP public';
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['drop_public_field'];

	exec_queries();
}

// #############################################################################
// Alter Forum Table
if ($vbulletin->GPC['step'] == 4)
{
	// Try to delete some fields that vB3 needs but seem popular as hacks in vB2
	$db->hide_errors();
	$db->query_write("ALTER TABLE forum DROP lastthread");
	$db->query_write("ALTER TABLE forum DROP lastthreadid");
	$db->show_errors();

	$query[] = "ALTER TABLE forum
			ADD lastthread CHAR(250) NOT NULL DEFAULT '',
			ADD lastthreadid INT UNSIGNED NOT NULL DEFAULT '0',
			ADD password CHAR(50) NOT NULL DEFAULT ''
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 1, 5);

	$query[] = "ALTER TABLE forum
			ADD canhavepassword SMALLINT NOT NULL DEFAULT '1',
			ADD link CHAR(200) NOT NULL DEFAULT '',
			ADD indexposts SMALLINT NOT NULL DEFAULT '1'
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 2, 5);

	$query[] = "ALTER TABLE forum
			CHANGE description description CHAR(250) NOT NULL DEFAULT '',
			CHANGE lastposter lastposter CHAR(50) NOT NULL DEFAULT '',
			CHANGE newpostemail newpostemail CHAR(250) NOT NULL DEFAULT ''
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 3, 5);

	$query[] = "ALTER TABLE forum
			CHANGE title title CHAR(100) NOT NULL DEFAULT '',
			CHANGE newthreademail newthreademail CHAR(250) NOT NULL DEFAULT '',
			CHANGE parentlist parentlist CHAR(250) NOT NULL DEFAULT ''
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 4, 5);

	$query[] = "ALTER TABLE forum
			ADD lasticonid SMALLINT NOT NULL DEFAULT '0',
			ADD options INT UNSIGNED NOT NULL DEFAULT '0',
			ADD childlist CHAR(250) NOT NULL DEFAULT ''
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'forum', 5, 5);

	$query[] = "
		UPDATE forum SET
			options =
				IF(active, {$vbulletin->bf_misc_forumoptions['active']}, 0) +
				IF(allowposting, {$vbulletin->bf_misc_forumoptions['allowposting']} ,0) +
				IF(cancontainthreads, {$vbulletin->bf_misc_forumoptions['cancontainthreads']}, 0) +
				IF(moderatenew, {$vbulletin->bf_misc_forumoptions['moderatenewpost']}, 0) +
				IF(moderatenew, {$vbulletin->bf_misc_forumoptions['moderatenewthread']}, 0) +
				IF(moderateattach, {$vbulletin->bf_misc_forumoptions['moderateattach']}, 0) +
				IF(allowbbcode, {$vbulletin->bf_misc_forumoptions['allowbbcode']}, 0) +
				IF(allowimages, {$vbulletin->bf_misc_forumoptions['allowimages']}, 0) +
				IF(allowhtml, {$vbulletin->bf_misc_forumoptions['allowhtml']}, 0) +
				IF(allowsmilies, {$vbulletin->bf_misc_forumoptions['allowsmilies']}, 0) +
				IF(allowicons, {$vbulletin->bf_misc_forumoptions['allowicons']}, 0) +
				IF(allowratings, {$vbulletin->bf_misc_forumoptions['allowratings']}, 0) +
				IF(countposts, {$vbulletin->bf_misc_forumoptions['countposts']}, 0) +
				IF(canhavepassword, {$vbulletin->bf_misc_forumoptions['canhavepassword']}, 0) +
				IF(indexposts, {$vbulletin->bf_misc_forumoptions['indexposts']}, 0) +
				IF(styleoverride, {$vbulletin->bf_misc_forumoptions['styleoverride']}, 0)
				+ {$vbulletin->bf_misc_forumoptions['showonforumjump']}
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['convert_forum_options'];

	$query[] = "ALTER TABLE forum
			DROP active,
			DROP allowposting,
			DROP cancontainthreads
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['dropping_option_fields'], 1, 5);

	$query[] = "ALTER TABLE forum
			DROP moderatenew,
			DROP moderateattach,
			DROP allowbbcode
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['dropping_option_fields'], 2, 5);

	$query[] = "ALTER TABLE forum
			DROP allowimages,
			DROP allowhtml,
			DROP allowsmilies
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['dropping_option_fields'], 3, 5);

	$query[] = "ALTER TABLE forum
			DROP allowicons,
			DROP allowratings,
			DROP countposts
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['dropping_option_fields'], 4, 5);

	$query[] = "ALTER TABLE forum
			DROP canhavepassword,
			DROP indexposts,
			DROP styleoverride
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['dropping_option_fields'], 5, 5);

	$query[] = "UPDATE forum SET styleid = 0";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['resetting_styleids'];

	exec_queries();

	echo_flush("<p>{$upgrade_phrases['upgrade_300b3.php']['updating_forum_child_lists']}</p>");
	build_forum_child_lists();
}

// #############################################################################
// drop privatemessages table and prepare user table for new pms
if ($vbulletin->GPC['step'] == 5)
{
	$db->hide_errors();
	$db->query_first("SELECT userid FROM " . TABLE_PREFIX . "tachyforumpost LIMIT 1");
	if ($db->errno())
	{
		$db->query_write("CREATE TABLE " . TABLE_PREFIX . "tachyforumpost (
			userid int unsigned NOT NULL default '0',
			forumid int unsigned NOT NULL default '0',
			lastpost int unsigned NOT NULL default '0',
			lastposter varchar(100) NOT NULL default '',
			lastthread varchar(250) NOT NULL default '',
			lastthreadid int unsigned NOT NULL default '0',
			lasticonid smallint unsigned NOT NULL default '0',
			PRIMARY KEY (userid, forumid),
			INDEX (forumid)
		)");

		$db->query_write("CREATE TABLE " . TABLE_PREFIX . "tachythreadpost (
			userid int unsigned NOT NULL default '0',
			threadid int unsigned NOT NULL default '0',
			lastpost int unsigned NOT NULL default '0',
			lastposter varchar(100) NOT NULL default '',
			PRIMARY KEY (userid, threadid),
			INDEX (threadid)
		)");
	}

	$db->errno = 0;
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "forum ADD lastpostid INT UNSIGNED NOT NULL DEFAULT '0'");

	$db->errno = 0;
	$db->query_first("SELECT forumid FROM " . TABLE_PREFIX . "podcast LIMIT 1");
	if ($db->errno())
	{
		$db->query_write("CREATE TABLE " . TABLE_PREFIX . "podcast (
			forumid INT UNSIGNED NOT NULL DEFAULT '0',
			author VARCHAR(255) NOT NULL DEFAULT '',
			category VARCHAR(255) NOT NULL DEFAULT '',
			image VARCHAR(255) NOT NULL DEFAULT '',
			explicit SMALLINT NOT NULL DEFAULT '0',
			enabled SMALLINT NOT NULL DEFAULT '1',
			keywords VARCHAR(255) NOT NULL DEFAULT '',
			owneremail VARCHAR(255) NOT NULL DEFAULT '',
			ownername VARCHAR(255) NOT NULL DEFAULT '',
			subtitle VARCHAR(255) NOT NULL DEFAULT '',
			summary MEDIUMTEXT,
			categoryid SMALLINT NOT NULL DEFAULT '0',
			PRIMARY KEY  (forumid)
		)");
	}

	$db->show_errors();

	$db->query_write("UPDATE forum SET lastpost=0, lastposter=''");
	$forums = $db->query_read("SELECT forumid, title FROM forum ORDER BY forumid DESC");

	echo "<ul>\n";

	while ($forum = $db->fetch_array($forums))
	{
		// update forum counters
		echo_flush("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['updating_counters_for_x'], $forum['title']) . "</li>\n");
		require_once(DIR . '/includes/functions_databuild.php');
		build_forum_counters($forum['forumid']);

		$thread = $db->query_first("
			SELECT * FROM thread
			WHERE forumid = $forum[forumid]
			ORDER BY lastpost DESC
			LIMIT 1
		");

		$query[] = "
			UPDATE forum SET
				lastpost = " . intval($thread['lastpost']) . ",
				lastposter = '" . $db->escape_string($thread['lastposter']) . "',
				lastthread = '" . $db->escape_string($thread['title']) . "',
				lastthreadid = " . intval($thread['threadid']) . ",
				lasticonid = " . intval($thread['iconid']) . "
			WHERE forumid = " . intval($thread['forumid']) . "
		";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['updating_lastpost_info_for_x'], $forum['title']);
	}

	echo "</ul>\n";

	exec_queries();

}

// #############################################################################
// Convert Private Messages
if ($vbulletin->GPC['step'] == 6)
{
	$vbulletin->GPC['perpage'] = 1000;
	$count = $db->query_first("SELECT COUNT(*) AS total FROM privatemessage");

	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade_300b3.php']['converting_priv_msg_x'], construct_upgrade_page_hint($count['total'], $vbulletin->GPC['startat'], $vbulletin->GPC['perpage'])) . "</p>\n");

	// query a batch of private messages
	$getpms = $db->query_read("
		SELECT
			privatemessage.*,
			IF(ISNULL(touser.username), '[{$upgrade_phrases['upgrade_300b3.php']['deleted_user']}]', touser.username) AS tousername,
			IF(ISNULL(fromuser.username), '[{$upgrade_phrases['upgrade_300b3.php']['deleted_user']}]', fromuser.username) AS fromusername
		FROM privatemessage
		LEFT JOIN user AS touser ON(touser.userid = privatemessage.touserid)
		LEFT JOIN user AS fromuser ON(fromuser.userid = privatemessage.fromuserid)
		LIMIT {$vbulletin->GPC['startat']}, {$vbulletin->GPC['perpage']}
	");

	// check to see if we have some results...
	if ($db->num_rows($getpms))
	{
		// populate our $pms array with the SQL results
		$pms = array();
		$receiptSql = array();
		while ($getpm = $db->fetch_array($getpms))
		{
			$pms[] = $getpm;
		}
		unset($getpm);
		$db->free_result($getpms);

		// get last inserted pm text
		if ($pmText = $db->query_first("SELECT * FROM pmtext ORDER BY pmtextid DESC LIMIT 1"))
		{
			// do nothing - we have $pmText returned from the query
		}
		else
		{
			$pmText = array();
		}

		echo "<ul>\n";
		foreach($pms as $pm)
		{
			// check if we need to insert a new pmtext record
			if ($pmText['message'] == $pm['message'] and $pmText['fromuserid'] == $pm['fromuserid'])
			{
				// use the previous pmtext
				$i++;
			}
			else
			{
				// insert a new pmtext record
				echo_flush("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['insert_priv_msg_txt_from_x'], $pm['fromusername']) . "</li>\n");
				$i = 1;
				$pmText = array(
					'fromuserid' => $pm['fromuserid'],
					'fromusername' => $pm['fromusername'],
					'title' => $pm['title'],
					'message' => $pm['message'],
					'touserarray' => serialize(array($pm['touserid'] => $pm['tousername'])),
					'iconid' => $pm['iconid'],
					'dateline' => $pm['dateline'],
					'showsignature' => $pm['showsignature']
				);
				/*insert query*/
				$db->query_write(fetch_query_sql($pmText, 'pmtext'));
				$pmText['pmtextid'] = $db->insert_id();
			}

			// insert the private message pointers
			echo_flush("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['insert_priv_msg_from_x_to_x'], $pm['fromusername'], $pm['tousername']) . "</li>\n");
			$newpm = array(
				'pmtextid' => $pmText['pmtextid'],
				'userid' => $pm['userid'],
				'folderid' => $pm['folderid'],
				'messageread' => $pm['messageread']
			);
			/*insert query*/
			$db->query_write(fetch_query_sql($newpm, 'pm'));
			$pmid = $db->insert_id();

			// check if we should insert a receipt
			if ($pm['receipt'] != 0)
			{
				// add to the $receiptSql array
				$receiptSql[] = "($pmid, $pm[fromuserid], $pm[touserid], '" . $db->escape_string($pm['tousername']) . "', '" . $db->escape_string($pm['title']) . "', $pm[dateline], $pm[readtime])";
			}

			if ($i > 1)
			{
				// look at the two values as both may exist
				if ($pm['tousername'] == '[Deleted User]' OR $pm['tousername'] == "[{$upgrade_phrases['upgrade_300b3.php']['deleted_user']}]")
				{
					// don't bother updating the touserarray to include this user name
				}
				else
				{
					$tousers = vb_unserialize($pmText['touserarray']);
					$tousers["$pm[touserid]"] = $pm['tousername'];
					$tousers = serialize($tousers);
					if ($pmText['touserarray'] != $tousers)
					{
						// update the pmtext record to reflect the multiple recipients
						$pmText['touserarray'] = $tousers;
						echo_flush("<li>{$upgrade_phrases['upgrade_300b3.php']['update_priv_msg_multiple_recip']}</li>\n");
						$db->query_write("UPDATE pmtext SET touserarray = '" . $db->escape_string($pmText['touserarray']) . "'\nWHERE pmtextid = $pmText[pmtextid]");
					}
				}
			}
		}

		if (!empty($receiptSql))
		{
			echo_flush("<li>{$upgrade_phrases['upgrade_300b3.php']['insert_priv_msg_receipts']}</li>\n");
			/*insert query*/
			$db->query_write("INSERT INTO pmreceipt\n\t(pmid, userid, touserid, tousername, title, sendtime, readtime)\nVALUES\n\t" . implode(",\n\t", $receiptSql));
		}

		echo "</ul>\n";

		print_next_page();
	}
	else
	{
		$query[] = "DROP TABLE privatemessage";
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['dropping_vb2_pm_table'];

		$query[] = "ALTER TABLE user
			ADD pmtotal SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			ADD pmunread SMALLINT UNSIGNED NOT NULL DEFAULT '0'";
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['alter_user_table_for_vb3_pm'];

		$query[] = "ALTER TABLE user
			CHANGE password password CHAR(32) NOT NULL DEFAULT '',
			ADD passworddate DATE NOT NULL DEFAULT '0000-00-00',
			ADD salt CHAR(3) NOT NULL DEFAULT ''";
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['alter_user_table_vb3_password'];

		exec_queries();

		echo "<p>{$upgrade_phrases['upgrade_300b3.php']['priv_msg_import_complete']}</p>";
	}
}

// #############################################################################
// Upgrade pmfolders, pmtotals and insert usertextfield entries
if ($vbulletin->GPC['step'] == 7)
{
	$vbulletin->GPC['perpage'] = 1000;
	$maxuser = $db->query_first("SELECT MAX(userid) AS userid FROM user");

	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade_300b3.php']['upgrading_users_x'], construct_upgrade_page_hint($maxuser['userid'], $vbulletin->GPC['startat'], $vbulletin->GPC['perpage'])) . "<br />\n");

	if ($vbulletin->GPC['startat'] <= $maxuser['userid'])
	{
		$endat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

		// Copy the textfields from the user table to the new table then remove them
		$users = $db->query_read("
			SELECT
				user.userid, user.username, user.pmfolders, user.buddylist, user.ignorelist,
				user.signature, user.password, user.usergroupid,
				COUNT(pmid) AS pmtotal,
				SUM(IF(messageread = 0, 1, 0)) AS pmunread
			FROM user
			LEFT JOIN pm USING(userid)
			WHERE user.userid > {$vbulletin->GPC['startat']}
			AND user.userid <= $endat
			GROUP BY user.userid
		");
		$batchnum = $db->num_rows($users);
		echo_flush(sprintf($upgrade_phrases['upgrade_300b3.php']['found_x_users'], $batchnum) . "</p>\n");

		$sql = array();
		require_once(DIR . '/includes/class_dm.php');
		require_once(DIR . '/includes/class_dm_user.php');
		$userdm = new vB_DataManager_User($vbulletin);

		while ($user = $db->fetch_array($users))
		{
			$salt = $userdm->fetch_user_salt(3);
			// update user table with new private message totals fields and salted password - only salt passwords for non-admins
			$query[] = "
				UPDATE user SET
					salt = '" . $db->escape_string($salt) . "',
					" . iif($user['usergroupid'] != 6, "password = '" . $db->escape_string(md5($user['password'] . $salt)) . "',") . "
					pmtotal = $user[pmtotal],
					pmunread = $user[pmunread]
				WHERE userid = $user[userid]
			";
			$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['updating_priv_messages_for_x'], $user['username']);

			// work out the new pmfolders format
			$user['pmfolders'] = trim($user['pmfolders']);
			$pmfolders = array();
			if ($user['pmfolders'] != '')
			{
				foreach(explode("\n", $user['pmfolders']) as $folderbits)
				{
					$folderbits = explode('|||', $folderbits);
					$pmfolders[intval($folderbits[0] + 1 )] = $folderbits[1];
				}
			}

			// add to sql
			$sql[] = "($user[userid], '" . iif(empty($pmfolders), '', $db->escape_string(serialize($pmfolders))) . "', '" . $db->escape_string($user['ignorelist']) . "', '" . $db->escape_string($user['buddylist']) . "', '" . $db->escape_string($user['signature']) . "')";
		}

		if (!empty($sql))
		{
			$query[] = "INSERT INTO usertextfield (userid, pmfolders, ignorelist, buddylist, signature) VALUES " . implode(', ', $sql);
			$explain[] = $upgrade_phrases['upgrade_300b3.php']['inserting_user_details_usertextfield'];
		}

		exec_queries();
		print_next_page();
	}
	else
	{
		// The above query leave many users without an entry in the usertextfield table so this fixes that since I don't
		// have time to mess with the above.

		$users = $db->query_read("
			SELECT user.userid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING(userid)
			WHERE usertextfield.userid IS NULL
		");

		$userids = array();
		while ($user = $db->fetch_array($users))
		{
			$userids[] = $user['userid'];
		}

		if (!empty($userids))
		{
			/*insert query*/
			$db->query_write("INSERT INTO usertextfield (userid) VALUES (" . implode( '),(' , $userids ) . ")");
		}

		echo_flush('</p><p>' . $upgrade_phrases['upgrade_300b3.php']['user_upgrades_complete'] . '</p>');
	}

}

// #############################################################################
// Alter User Table #1
if ($vbulletin->GPC['step'] == 8)
{
	// common hack-related fields we want to deal with in order to prevent DB errors
	$db->hide_errors();
	$db->query_write("ALTER TABLE user DROP avatarrevision");
	$db->query_write("ALTER TABLE user ADD showvbcode SMALLINT UNSIGNED NOT NULL DEFAULT '0'");
	$db->query_write("ALTER TABLE user ADD msn CHAR(100) NOT NULL DEFAULT ''");
	$db->show_errors();

	// now change those hack-related fields for those users who already had them
	$query[] = "ALTER TABLE user
		ADD avatarrevision INT UNSIGNED NOT NULL DEFAULT '0',
		CHANGE showvbcode showvbcode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		CHANGE msn msn CHAR(100) NOT NULL DEFAULT ''
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 7);

	// add membergroup / displaygroup fields
	$query[] = "ALTER TABLE user
		ADD membergroupids CHAR(250) NOT NULL DEFAULT '',
		ADD displaygroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 7);

	// add reputation fields
	$query[] = "ALTER TABLE user
		ADD reputation INT NOT NULL DEFAULT '10',
		ADD reputationlevelid INT UNSIGNED NOT NULL DEFAULT '1',
		ADD showreputation SMALLINT UNSIGNED NOT NULL DEFAULT '1'
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 3, 7);

	// add misc fields
	$query[] = "ALTER TABLE user
		ADD languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD threadedmode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD emailstamp INT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 4, 7);

	// drop fields
	$query[] = "ALTER TABLE user
		DROP nosessionhash,
		DROP cookieuser,
		DROP inforum
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 5, 7);

	// drop fields
	$query[] = "ALTER TABLE user
		DROP pmfolders,
		DROP ignorelist,
		DROP buddylist
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 6, 7);

	// drop signature and change 'options'...
	$query[] = "ALTER TABLE user
		DROP signature,
		CHANGE options options INT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 7, 7);

	# $_USEROPTIONS from vB3 init.php
	#	'showsignatures' => 1,
	#	'showavatars' => 2,
	#	'showimages' => 4,
	#	'coppauser' => 8,
	#	'adminemail' => 16,
	#	'insouth' => 32,
	#	'dstauto' => 64,
	#	'dstonoff' => 128,
	#	'showemail' => 256,
	#	'invisible' => 512,
	#	'showreputation' => 1024,
	#	'receivepm' => 2048,
	#	'emailonpm' => 4096,
	#	'hasaccessmask' => 8192,
	#	'emailnotification' => 16384
	# bitfield definitions from vB2 functions.php
	#	"SHOWSIGNATURES", 1
	#	"SHOWAVATARS", 2
	#	"SHOWIMAGES", 4
	#	"SHOWVBCODE", 8

	$query[] = "UPDATE user SET
		showvbcode = IF(options & 8, 1, 0),
		options = 0
			+ IF(options & {$vbulletin->bf_misc_useroptions['showsignatures']}, {$vbulletin->bf_misc_useroptions['showsignatures']}, 0)
			+ IF(options & {$vbulletin->bf_misc_useroptions['showavatars']}, {$vbulletin->bf_misc_useroptions['showavatars']}, 0)
			+ IF(options & {$vbulletin->bf_misc_useroptions['showimages']}, {$vbulletin->bf_misc_useroptions['showimages']}, 0)
			+ IF(coppauser, {$vbulletin->bf_misc_useroptions['coppauser']}, 0)
			+ IF(adminemail, {$vbulletin->bf_misc_useroptions['adminemail']}, 0)
			+ IF(showemail, {$vbulletin->bf_misc_useroptions['showemail']}, 0)
			+ IF(invisible, {$vbulletin->bf_misc_useroptions['invisible']}, 0)
			+ IF(showreputation, {$vbulletin->bf_misc_useroptions['showreputation']}, 0)
			+ IF(receivepm, {$vbulletin->bf_misc_useroptions['receivepm']}, 0)
			+ IF(emailonpm, {$vbulletin->bf_misc_useroptions['emailonpm']}, 0)
			+ IF(emailnotification,	16384, 0)
			+ {$vbulletin->bf_misc_useroptions['dstauto']}
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_user_table_options'];

	// drop fields converted to bitfield 'options'
	$query[] = "ALTER TABLE user
		DROP coppauser,
		DROP adminemail,
		DROP showemail
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['drop_user_option_fields'], 1);

	// drop fields converted to bitfield 'options'
	$query[] = "ALTER TABLE user
		DROP invisible,
		DROP showreputation,
		DROP receivepm
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['drop_user_option_fields'], 2);

	// drop fields converted to bitfield 'options'
	$query[] = "ALTER TABLE user
		DROP emailonpm,
		DROP emailnotification
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['drop_user_option_fields'], 3);

	// get userids who have access masks and update
	$accessmasks = $db->query_read("
		SELECT DISTINCT access.userid, user.username
		FROM access
		INNER JOIN user USING(userid)
	");
	while ($access = $db->fetch_array($accessmasks))
	{
		$updateuserids .= ',' . $access['userid'];
	}
	$query[] = "
		UPDATE user SET
			options = (options + {$vbulletin->bf_misc_useroptions['hasaccessmask']})
		WHERE userid IN(0$updateuserids)
		AND NOT (options & {$vbulletin->bf_misc_useroptions['hasaccessmask']})
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['update_access_masks'];

	exec_queries();

}

// #############################################################################
// Alter User Table #2
if ($vbulletin->GPC['step'] == 9)
{
	$query[] = "ALTER TABLE user
		CHANGE birthday birthday VARCHAR(10) NOT NULL DEFAULT '0000-00-00',
		CHANGE posts posts INT UNSIGNED NOT NULL DEFAULT '0',
		ADD INDEX(birthday)
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 1, 5);

	$query[] = "ALTER TABLE user
		CHANGE username username CHAR(50) NOT NULL DEFAULT '',
		CHANGE password password CHAR(32) NOT NULL DEFAULT '',
		CHANGE options options INT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 2, 5);

	$query[] = "ALTER TABLE user
		CHANGE email email CHAR(50) NOT NULL DEFAULT '',
		CHANGE parentemail parentemail CHAR(50) NOT NULL DEFAULT '',
		CHANGE homepage homepage CHAR(100) NOT NULL DEFAULT ''
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 3, 5);

	$query[] = "ALTER TABLE user
		CHANGE icq icq CHAR(20) NOT NULL DEFAULT '',
		CHANGE aim aim CHAR(20) NOT NULL DEFAULT '',
		CHANGE yahoo yahoo CHAR(20) NOT NULL DEFAULT ''
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 4, 5);

	$query[] = "ALTER TABLE user
		CHANGE usertitle usertitle CHAR(250) NOT NULL DEFAULT '',
		CHANGE timezoneoffset timezoneoffset CHAR(4) NOT NULL DEFAULT '',
		CHANGE ipaddress ipaddress CHAR(15) NOT NULL DEFAULT ''
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'user', 5, 5);

	$query[] = "
		UPDATE user SET
			birthday = IF(
				birthday <> '0000-00-00' AND birthday IS NOT NULL AND birthday <> '',
				CONCAT(
					SUBSTRING(birthday, 6, 2),
					'-',
					SUBSTRING(birthday, 9, 2),
					'-',
					SUBSTRING(birthday, 1, 4)
				),
				''
			)
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['convert_new_birthday_format'];

	$query[] = "
		INSERT INTO administrator (userid, adminpermissions)
		SELECT user.userid, " . (array_sum($vbulletin->bf_ugp_adminpermissions) - 3) . "
		FROM user INNER JOIN usergroup USING(usergroupid)
		WHERE usergroup.cancontrolpanel = 1
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['insert_admin_perms_admin_table'];

	exec_queries();
}

// #############################################################################
// Announcements
if ($vbulletin->GPC['step'] == 10)
{
	$query[] = "ALTER TABLE announcement
			ADD pagehtml MEDIUMTEXT,
			ADD allowhtml SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			ADD views INT UNSIGNED NOT NULL DEFAULT '0'
			";
	$explain[] = sprintf($vbphrase['alter_table'], 'announcement');

	exec_queries();

	// run code to update old announcements
	$ans = $db->query_read("SELECT announcementid, pagetext, title FROM announcement");
	echo "<p>{$upgrade_phrases['upgrade_300b3.php']['updating_announcements']}</p><ul>\n";
	while ($an = $db->fetch_array($ans))
	{
		echo "<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['announcement_x'], htmlspecialchars_uni($an['title'])) . " ... \n";
		$db->query_write("UPDATE announcement SET pagehtml='" . $db->escape_string($an['pagetext']) . "',allowhtml=1 WHERE announcementid=" . intval($an['announcementid']));
		echo "{$vbphrase['done']}</li>\n";
		vbflush();
	}
	echo "</ul>\n";
}

// #############################################################################
// Image Tables
if ($vbulletin->GPC['step'] == 11)
{
	$db->hide_errors();
	$db->query_write("ALTER TABLE avatar DROP INDEX title");
	$db->show_errors();

	$query[] = "ALTER TABLE avatar
			ADD imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			ADD displayorder SMALLINT UNSIGNED DEFAULT '1' NOT NULL
			";
	$explain[] = sprintf($vbphrase['alter_table'], 'avatar');

	$query[] = "ALTER TABLE avatar ADD INDEX avatarind (minimumposts,title)";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['add_index_avatar_table'];

	$query[] = "ALTER TABLE icon
			ADD imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			ADD displayorder SMALLINT UNSIGNED DEFAULT '1' NOT NULL
			";
	$explain[] = sprintf($vbphrase['alter_table'], 'icon');

	$query[] = "ALTER TABLE smilie
			ADD imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			ADD displayorder SMALLINT UNSIGNED DEFAULT '1' NOT NULL
			";
	$explain[] = sprintf($vbphrase['alter_table'], 'smilie');

	// code to add categorisation to avatars/icons/smilies

	// create a category for avatars
	/*insert query*/
	$avatarresult = $db->query_write("
		INSERT INTO imagecategory (title,imagetype,displayorder)
		VALUES ('{$upgrade_phrases['upgrade_300b3.php']['standard_avatars']}', 1, 1)
	");
	$avatarcategoryid = $db->insert_id($avatarresult);
	$query[] = "UPDATE avatar SET imagecategoryid = $avatarcategoryid";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['move_avatars_to_category'];

	// create a category for icons
	/*insert query*/
	$iconresult = $db->query_write("INSERT INTO imagecategory (title,imagetype,displayorder)
		VALUES ('{$upgrade_phrases['upgrade_300b3.php']['standard_icons']}', 2, 1)");
	$iconcategoryid = $db->insert_id($iconresult);
	$query[] = "UPDATE icon SET imagecategoryid = $iconcategoryid";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['move_icons_to_category'];

	// create a category for smilies
	/*insert query*/
	$smilieresult = $db->query_write("INSERT INTO imagecategory (title,imagetype,displayorder)
		VALUES ('{$upgrade_phrases['upgrade_300b3.php']['standard_smilies']}', 3, 1)");
	$smiliecategoryid = $db->insert_id($smilieresult);
	$query[] = "UPDATE smilie SET imagecategoryid = $smiliecategoryid";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['move_smilies_to_category'];


	//code to change the way avatars are displayed:

	$checks = $db->query_read("SELECT varname,value FROM setting WHERE varname='numavatarswide' OR varname='numavatarshigh'");
	while ($check = $db->fetch_array($checks))
	{
		$opt["$check[varname]"] = $opt['value'];
	}
	$avatarsperpage = $opt['numavatarshigh'] * $opt['numavatarswide'];
	$query[] = "UPDATE setting SET title='{$upgrade_phrases['upgrade_300b3.php']['avatar_setting_title']}',varname='numavatarsperpage',value=".intval($avatarsperpage).",description='{$upgrade_phrases['upgrade_300b3.php']['avatar_setting_desc']}',optioncode='',displayorder='9' WHERE varname='numavatarshigh'";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['update_avatars_per_page'];

	// Do we need to remove the 'numavatarswide' option??

	exec_queries();
}

// #############################################################################
// Attachments #1
if ($vbulletin->GPC['step'] == 12)
{
	$query[] = "
		ALTER TABLE attachment
		ADD postid INT UNSIGNED NOT NULL DEFAULT '0',
		ADD filesize INT UNSIGNED NOT NULL DEFAULT '0',
		ADD thumbnail MEDIUMTEXT";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 1, 3);

	$query[] = "
			ALTER TABLE attachment
			ADD filehash CHAR(32) NOT NULL DEFAULT '',
			ADD posthash CHAR(32) NOT NULL DEFAULT '',
			ADD INDEX (posthash, userid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 2, 3);

	$query[] = "
			ALTER TABLE attachment
			ADD INDEX (postid),
			ADD INDEX (filesize),
			ADD INDEX (filehash)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'attachment', 3, 3);

	exec_queries();
}

// #############################################################################
// Attachments #2
if ($vbulletin->GPC['step'] == 13)
{
	$vbulletin->GPC['perpage'] = 500;

	echo_flush("<p>{$upgrade_phrases['upgrade_300b3.php']['updating_attachments']}</p><ul>\n");
	$attachments = $db->query_read("
		SELECT post.attachmentid, post.postid, attachment.postid AS attachment_postid
		FROM post
		LEFT JOIN attachment ON (attachment.attachmentid = post.attachmentid)
		WHERE post.attachmentid > 0
		ORDER BY post.attachmentid DESC
		LIMIT {$vbulletin->GPC['startat']}, {$vbulletin->GPC['perpage']}
	");
	$postarray = array();
	while ($thisattach = $db->fetch_array($attachments))
	{
		$notdone = true;
		echo "<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['attachment_x'], $thisattach['attachmentid']) . " ... \n";

		if ($thisattach['attachment_postid'] OR $postarray["$thisattach[attachmentid]"])
		{
			// if attachment_postid is already set then that indicates that this attachment is already parented and we need to create a new entry
			// this should not happen all that often so take that approach and query again for the needed information
			$postid = $thisattach['postid'];
			$postarray["$thisattach[attachmentid]"] = 1;
			if ($thisattach = $db->query_first("
				SELECT *
				FROM attachment
				WHERE attachmentid = $thisattach[attachmentid]
			"))
			{
				/*insert query*/
				$db->query_write("
					INSERT INTO attachment
					(postid, filesize, filehash, userid, dateline, filename, filedata, visible, counter)
					VALUES
					($postid,
					" . strlen($thisattach['filedata']) . ",
					'" . $db->escape_string(md5($thisattach['filedata'])) . "',
					$thisattach[userid],
					$thisattach[dateline],
					'" . $db->escape_string($thisattach['filename']) . "',
					'" . $db->escape_string($thisattach['filedata']) . "',
					$thisattach[visible],
					$thisattach[counter]
					)
				");
				echo "{$vbphrase['done']}*</li>\n";
			}
		}
		else
		{
			$postarray["$thisattach[attachmentid]"] = 1;
			$db->query_write("
				UPDATE attachment
				SET
					postid = $thisattach[postid],
					filesize = LENGTH(filedata),
					filehash = MD5(filedata)
			WHERE attachmentid = $thisattach[attachmentid]");
			echo "{$vbphrase['done']}</li>\n";
		}
		vbflush();
	}
	echo "</ul>\n";

	if ($notdone)
	{
		print_next_page();
	}
}

// #############################################################################
// Attachments #3
if ($vbulletin->GPC['step'] == 14)
{

	$query[] = "
		DELETE FROM attachment
		WHERE postid = 0";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['remove_orphan_attachments'];

	$maxattachwidth = intval($maxattachwidth);
	$maxattachheight = intval($maxattachheight);
	$maxattachsize = intval($maxattachsize);

	$query[] = "
		INSERT INTO attachmenttype
		VALUES
			('gif', 'a:1:{i:0;s:23:\"Content-type: image/gif\";}', $maxattachsize, $maxattachwidth, $maxattachheight, 1, 0) ,
			('jpeg', 'a:1:{i:0;s:24:\"Content-type: image/jpeg\";}', $maxattachsize, $maxattachwidth, $maxattachheight, 1, 0),
			('jpg', 'a:1:{i:0;s:24:\"Content-type: image/jpeg\";}', $maxattachsize, $maxattachwidth, $maxattachheight, 1, 0),
			('jpe', 'a:1:{i:0;s:24:\"Content-type: image/jpeg\";}', $maxattachsize, $maxattachwidth, $maxattachheight, 1, 0),
			('png', 'a:1:{i:0;s:23:\"Content-type: image/png\";}', $maxattachsize, $maxattachwidth, $maxattachheight, 1, 0),
			('bmp', 'a:1:{i:0;s:23:\"Content-type: image/bmp\";}', $maxattachsize, $maxattachwidth, $maxattachheight, 1, 0),
			('psd', 'a:1:{i:0;s:29:\"Content-type: unknown/unknown\";}', $maxattachsize, 0, 0, 1, 0),
			('txt', 'a:1:{i:0;s:24:\"Content-type: plain/text\";}', $maxattachsize, 0, 0, 1, 0),
			('pdf', 'a:1:{i:0;s:29:\"Content-type: application/pdf\";}', $maxattachsize, 0, 0, 1, 0),
			('doc', 'a:2:{i:0;s:20:\"Accept-ranges: bytes\";i:1;s:32:\"Content-type: application/msword\";}', $maxattachsize, 0, 0, 1, 0),
			('zip', 'a:1:{i:0;s:29:\"Content-type: application/zip\";}', $maxattachsize, 0, 0, 1, 0)
		";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['populating_attachmenttype_table'];

	exec_queries();
}

// #############################################################################
// Edit Post Log
if ($vbulletin->GPC['step'] == 15)
{
	$vbulletin->GPC['perpage'] = 10000;
	$maxpost = $db->query_first("SELECT MAX(postid) AS maxpost FROM post");

	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade_300b3.php']['updating_editpost_log'], construct_upgrade_page_hint($maxpost['maxpost'], $vbulletin->GPC['startat'], $vbulletin->GPC['perpage'])) . "<br />\n");

	if ($vbulletin->GPC['startat'] <= $maxpost['maxpost'])
	{
		$endat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

		$editedposts = $db->query_read("
			SELECT post.postid, edituserid, editdate, user.username
			FROM post, user
			WHERE post.edituserid > 0
			AND post.edituserid = user.userid
			AND post.postid > {$vbulletin->GPC['startat']}
			AND post.postid <= $endat
			ORDER BY post.postid DESC");
		$batchnum = $db->num_rows($editedposts);
		echo_flush(sprintf($upgrade_phrases['upgrade_300b3.php']['found_x_posts'], $batchnum) . "</p>\n");
		if ($batchnum > 0)
		{
			echo "<ul>\n";
			while ($thispost = $db->fetch_array($editedposts))
			{
				echo_flush("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['post_x'], $thispost['postid']) . " ... \n");
				/*insert query*/
				$db->query_write("
					REPLACE INTO editlog
						(postid, userid, username, dateline, reason)
					VALUES
						($thispost[postid], $thispost[edituserid], '" . $db->escape_string($thispost['username']) . "', $thispost[editdate], '')
				");
				echo_flush("{$vbphrase['done']}</li>\n");
			}
			echo "</ul>\n";
		}
		print_next_page();
	}
	else
	{
		echo_flush("</p><p>" . $upgrade_phrases['upgrade_300b3.php']['post_editlog_complete'] . "</p>");
	}

}

// #############################################################################
// Thread & Post #1
if ($vbulletin->GPC['step'] == 16)
{
	require_once(DIR . '/includes/functions_misc.php');

	echo_flush("<p>{$upgrade_phrases['upgrade_300b3.php']['steps_may_take_several_minutes']}</p>\n<ul>\n");

	// this is a MONSTER query, beware!
	echo_flush("<li>{$upgrade_phrases['upgrade_300b3.php']['altering_post_table']}");
	$t = microtime();
	$db->query_write("
		ALTER TABLE post
		ADD parentid INT UNSIGNED NOT NULL DEFAULT '0',
		ADD attach SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		DROP edituserid,
		DROP editdate
	");
	$db->query_write("UPDATE post SET attach = 1 WHERE attachmentid > 0");
	$db->query_write("ALTER TABLE post DROP attachmentid");

	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');

	echo_flush("<li>{$upgrade_phrases['upgrade_300b3.php']['altering_thread_table']}");
	$t = microtime();

	// remove the hacked version of thread preview if it exists
	$db->hide_errors();
	$db->query_write("ALTER TABLE thread DROP preview");
	$db->query_write("ALTER TABLE thread DROP firstpostid");
	$db->show_errors();

	$db->query_write("
		ALTER TABLE thread
		ADD INDEX (postuserid)
	");

	$db->query_write("
		ALTER TABLE thread
		ADD firstpostid INT UNSIGNED NOT NULL DEFAULT '0',
		CHANGE title title CHAR(100) NOT NULL DEFAULT '',
		CHANGE postusername postusername CHAR(50) NOT NULL DEFAULT '',
		CHANGE lastposter lastposter CHAR(50) NOT NULL DEFAULT '',
		CHANGE notes notes CHAR(250) NOT NULL DEFAULT '',
		ADD similar CHAR(55) NOT NULL DEFAULT ''
	");
	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');

	/*insert query*/
	$db->query_write("
		INSERT IGNORE INTO moderation (threadid, postid, type)
		SELECT thread.threadid, post.postid, 'thread'
		FROM thread
		LEFT JOIN post ON(post.threadid = thread.threadid)
		WHERE thread.visible = 0 AND thread.open <> 10
	");
	echo_flush("<li>{$upgrade_phrases['upgrade_300b3.php']['inserting_moderated_threads']}</li>\n");

	/*insert query*/
	$db->query_write("
		INSERT INTO moderation (threadid, postid, type)
		SELECT threadid, postid, 'reply'
		FROM post
		WHERE visible = 0
	");
	echo_flush("<li>{$upgrade_phrases['upgrade_300b3.php']['inserting_moderated_posts']}</li>\n");

	echo_flush("</ul>\n");

}

// #############################################################################
// Thread & Post #2
if ($vbulletin->GPC['step'] == 17)
{
	$vbulletin->GPC['perpage'] = 500;
	$maxthread = $db->query_first("SELECT MAX(threadid) AS maxthread FROM thread");

	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade_300b3.php']['update_posts_support_threaded'], construct_upgrade_page_hint($maxthread['maxthread'], $vbulletin->GPC['startat'], $vbulletin->GPC['perpage'])) . "<br />\n");
	if ($vbulletin->GPC['startat'] <= $maxthread['maxthread'])
	{
		$endat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

		$threads = $db->query_read("
			SELECT MIN(postid) AS postid, thread.threadid
			FROM thread, post
			WHERE post.threadid = thread.threadid
			AND thread.threadid > {$vbulletin->GPC['startat']}
			AND thread.threadid <= $endat
			GROUP BY(thread.threadid)
			ORDER by thread.threadid
		");
		$batchnum = $db->num_rows($threads);
		echo_flush(sprintf($upgrade_phrases['upgrade_300b3.php']['found_x_threads'], $batchnum) . "</p>\n");
		if ($batchnum > 0)
		{
			echo "<ul>\n";
			while ($thread = $db->fetch_array($threads))
			{
				echo_flush("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['post_x'], $thread['postid']) . " ... \n");
				$db->query_write("
					UPDATE post
					SET parentid = $thread[postid]
					WHERE threadid = $thread[threadid]
					AND postid <> $thread[postid]
				");
				echo_flush("{$vbphrase['done']}</li>\n");
			}
			echo "</ul>\n";
		}
		print_next_page();
	}
	else
	{
		echo_flush("</p><p>{$upgrade_phrases['upgrade_300b3.php']['threaded_update_complete']}</p>");
	}

}

// #############################################################################
// Misc Alters #1
if ($vbulletin->GPC['step'] == 18)
{

	$query[] = "ALTER TABLE userfield ADD temp VARCHAR(250) NOT NULL DEFAULT ''";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "userfield");

	$query[] = "UPDATE subscribethread SET emailupdate = 1";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "subscribethread");

	$query[] = "ALTER TABLE poll ADD public SMALLINT NOT NULL DEFAULT '0'";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "poll");

	$query[] = "DELETE FROM searchindex";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['emptying_search'];

	$query[] = "ALTER TABLE searchindex
		CHANGE wordid wordid MEDIUMINT UNSIGNED NOT NULL DEFAULT '0',
		CHANGE intitle intitle TINYINT UNSIGNED NOT NULL DEFAULT '0',
		ADD score TINYINT UNSIGNED NOT NULL DEFAULT '0'";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "searchindex");

	$query[] = "DELETE FROM word";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['emptying_wordlist'];

	$query[] = "ALTER TABLE word CHANGE wordid wordid MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "word");

	$query[] = "ALTER TABLE customavatar ADD visible SMALLINT DEFAULT '1' NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "customavatar");

	$query[] = "ALTER TABLE subscribeforum ADD INDEX(forumid)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "subscribeforum");

	$query[] = "ALTER TABLE subscribeforum ADD UNIQUE subindex (userid, forumid)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "subscribeforum");

	$db->hide_errors();
	$db->query_write("ALTER TABLE subscribeforum DROP INDEX userid");
	$db->query_write("ALTER TABLE useractivation ADD usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0'");
	$db->query_write("UPDATE useractivation SET usergroupid=2 WHERE type=0");
	$db->query_write("ALTER TABLE subscribethread ADD folderid INT UNSIGNED NOT NULL DEFAULT '0'");
	$db->query_write("ALTER TABLE subscribethread ADD UNIQUE subscribeindex (userid, threadid)");
	$db->query_write("ALTER TABLE useractivation CHANGE activationid activationid INT UNSIGNED NOT NULL DEFAULT '0'");
	$db->show_errors();

	exec_queries();
}

// #############################################################################
// Misc Alters #2
if ($vbulletin->GPC['step'] == 19)
{

	$query[] = "ALTER TABLE session
			CHANGE host host CHAR(15) NOT NULL DEFAULT '',
			ADD badlocation SMALLINT UNSIGNED NOT NULL DEFAULT '0'
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'session', 1, 3);

	$query[] = "ALTER TABLE session
			ADD inforum SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			ADD inthread INT UNSIGNED NOT NULL DEFAULT '0',
			ADD incalendar INT UNSIGNED NOT NULL DEFAULT '0'
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'session', 2, 3);

	$query[] = "ALTER TABLE session
			CHANGE useragent useragent VARCHAR(100) NOT NULL DEFAULT '',
			ADD loggedin SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			ADD idhash CHAR(32) NOT NULL DEFAULT ''
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'session', 3, 3);

	$query[] = "ALTER TABLE profilefield
			ADD type ENUM('input','select','radio','textarea','checkbox','select_multiple') NOT NULL DEFAULT 'input',
			ADD data MEDIUMTEXT,
			ADD height SMALLINT NOT NULL DEFAULT '0'
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefield', 1, 3);

	$query[] = "ALTER TABLE profilefield
			ADD def SMALLINT NOT NULL DEFAULT '0',
			ADD optional SMALLINT NOT NULL DEFAULT '0',
			ADD searchable SMALLINT NOT NULL DEFAULT '0'
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefield', 2, 3);

	$query[] = "ALTER TABLE profilefield
			ADD memberlist SMALLINT NOT NULL DEFAULT '0',
			ADD regex VARCHAR(255) NOT NULL DEFAULT '',
			ADD form SMALLINT NOT NULL DEFAULT '0'
			";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'profilefield', 3, 3);

	$query[] = "ALTER TABLE threadrate CHANGE ipaddress ipaddress CHAR(15) NOT NULL DEFAULT ''";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'threadrate', 1, 2);

	$db->hide_errors();
	$db->query_write('ALTER TABLE threadrate DROP INDEX threadid');
	$db->show_errors();

	$query[] = "ALTER TABLE threadrate ADD INDEX threadid (threadid, userid)";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'threadrate', 2, 2);

	// Why are we enabling all unhidden profile fields on the memberlist by default???
	$query[] = 'UPDATE profilefield SET memberlist = 1 WHERE hidden = 0';
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "profilefield");

	exec_queries();

}

// #############################################################################
// BBCode Alters
if ($vbulletin->GPC['step'] == 20)
{
	// add title field and unique key to bbcode table
	$query[] = "
		ALTER TABLE bbcode
		ADD title CHAR (100) NOT NULL DEFAULT ''
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'bbcode', 1, 2);

	$query[] = "
		ALTER TABLE bbcode
		ADD UNIQUE KEY uniquetag (bbcodetag,twoparams)
	";
	$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['altering_x_table'], 'bbcode', 2, 2);

	// drop bbcodes that will now be hard-coded
	$query[] = "DELETE FROM bbcode WHERE bbcodetag IN('b', 'i', 'u', 'font', 'size', 'color')";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['remove_bbcodes_hardcoded_now'];

	// insert [quote=Username] bbcode
	$query[] = "
		REPLACE INTO bbcode (bbcodeid, bbcodetag, bbcodereplacement, bbcodeexample, bbcodeexplanation, twoparams, title) VALUES
		(
			NULL,
			'quote',
			'<blockquote><span class=\"smallfont\">Quote:</span><hr size=\"1\" />Originally Posted by <b>\\\\5</b><br /><i>\\\\7</i><hr size=\"1\" /></blockquote>',
			'[quote=\\'John Doe\\']This is a quote[/quote]',
			'The [quote] tag is used to denote a quote that is from another source.',
			1,
			'Quote With Username'
		)
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['inserting_quote_bbcode'];

	// update [QUOTE] bbcode
	$query[] = "
		UPDATE bbcode SET
			bbcodereplacement = '<blockquote><span class=\"smallfont\">Quote:</span><hr size=\"1\" />\\\\4<hr size=\"1\" /></blockquote>',
			bbcodeexample = '[quote]This is a quote[/quote]',
			bbcodeexplanation = 'The [quote] tag is used to denote a quote that is from another source.',
			twoparams = 0,
			title = 'Simple Quoting'
		WHERE bbcodetag = 'quote' AND twoparams = 0
	";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "bbcode");

	// add title to simple email bbcode
	$query[] = "UPDATE bbcode SET bbcodeexample='johndoe@example.com', title='Simple Email Linking' WHERE bbcodetag='email' AND twoparams=0";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "bbcode");

	// add title to named email bbcode
	$query[] = 	"UPDATE bbcode SET bbcodeexample='[email=johndoe@example.com]John Doe[/email]', title='Advanced Email Linking' WHERE bbcodetag='email' AND twoparams=1";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "bbcode");

	// add title to all other bbcodes
	$query[] =	"UPDATE bbcode SET title = CONCAT(UPPER(SUBSTRING(bbcodetag, 1, 1)), SUBSTRING(bbcodetag, 2)) WHERE title=''";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "bbcode");

	exec_queries();
}

// #############################################################################
// Usergroups (the biggie!)
if ($vbulletin->GPC['step'] == 21)
{
	// first of all let's just check that we know what usergroups are 'banned'
	$vbulletin->input->clean_gpc('p', 'bangroup', TYPE_ARRAY);
	if (empty($vbulletin->GPC['bangroup']))
	{
		$bangroup = array();
		$groups = $db->query_read("SELECT usergroupid, title FROM usergroup WHERE title LIKE('%banned%')");
		while ($group = $db->fetch_array($groups))
		{
			$bangroup[] = $group['usergroupid'];
		}
		print_form_header('upgrade_300b3', '');
		construct_hidden_code('step', 21);
		construct_hidden_code('bangroup[]', 0);
		print_table_header($upgrade_phrases['upgrade_300b3.php']['select_banned_groups']);
		print_description_row("<div align=\"center\" style=\"font-size:10pt\"><br />&nbsp;
			{$upgrade_phrases['upgrade_300b3.php']['explain_banned_groups']}
			<br />&nbsp;</div>");
		print_membergroup_row("<b>{$upgrade_phrases['upgrade_300b3.php']['user_groups']}</b>", 'bangroup', 2, array('usergroupid' => 0, 'membergroupids' => implode(',', $bangroup)));
		print_submit_row($vbphrase['proceed'], $vbphrase['reset']);
		exit;
	}

	// if we got this far the user has filled in the form above

	$query[] = "ALTER TABLE usergroup
		CHANGE title title CHAR(100) NOT NULL DEFAULT '',
		CHANGE usertitle usertitle CHAR(100) NOT NULL DEFAULT '',
		CHANGE maxbuddypm pmsendmax SMALLINT UNSIGNED NOT NULL DEFAULT '5',
		CHANGE showgroup genericoptions INT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 1, 9);

	$query[] = "ALTER TABLE usergroup
		ADD ispublicgroup SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD canoverride SMALLINT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 2, 9);

	$query[] = "ALTER TABLE usergroup
		ADD description CHAR(250) NOT NULL DEFAULT '',
		ADD opentag CHAR(100) NOT NULL DEFAULT '',
		ADD closetag CHAR(100) NOT NULL DEFAULT ''
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 3, 9);

	$query[] = "ALTER TABLE usergroup
		ADD forumpermissions INT UNSIGNED NOT NULL DEFAULT '0',
		ADD pmpermissions INT UNSIGNED NOT NULL DEFAULT '0',
		ADD calendarpermissions INT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 4, 9);

	$query[] = "ALTER TABLE usergroup
		ADD wolpermissions INT UNSIGNED NOT NULL DEFAULT '0',
		ADD adminpermissions INT UNSIGNED NOT NULL DEFAULT '0',
		ADD genericpermissions INT UNSIGNED NOT NULL DEFAULT '0',
		ADD signaturepermissions INT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 5, 9);

	$query[] = "ALTER TABLE usergroup
		ADD passwordexpires SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD passwordhistory SMALLINT UNSIGNED  NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 6, 9);

	$query[] = "ALTER TABLE usergroup
		ADD pmquota SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD attachlimit INT UNSIGNED  NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 7, 9);

	$query[] = "ALTER TABLE usergroup
		ADD avatarmaxwidth SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD avatarmaxheight SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD avatarmaxsize INT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 8, 9);

	$query[] = "ALTER TABLE usergroup
		ADD profilepicmaxwidth SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD profilepicmaxheight SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD profilepicmaxsize INT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 9, 9);

	// update users awaiting email confirmation and registered users to have a user title
	$query[] = "UPDATE usergroup SET
		usertitle = IF(usertitle = '', '{$upgrade_phrases['upgrade_300b3.php']['registered_user']}', usertitle)
		WHERE usergroupid IN(2,3,4)
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['update_some_usergroup_titles'];

	exec_queries();

	$usergroups = $db->query_read('SELECT * FROM usergroup');
	echo "<p>{$upgrade_phrases['upgrade_300b3.php']['updating_usergroup_permissions']}</p><ul>";

	require_once(DIR . '/includes/functions_misc.php');

	while ($usergroup = $db->fetch_array($usergroups))
	{
		if ($usergroup['usergroupid'] != 2 AND $usergroup['usergroupid'] != 5 AND $usergroup['usergroupid'] != 6 AND $usergroup['usergroupid'] != 7)
		{
			$calendarsql .= ", (2, $usergroup[usergroupid], 1)";
		}

		echo "<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['usergroup_x'], $usergroup['title']) . " ...\n";
		$ug = array();
		foreach($vbulletin->bf_ugp AS $dbfield => $fields)
		{
			$ug["$dbfield"] = convert_array_to_bits($usergroup, $fields);
		}
		if ($usergroup['genericoptions'])
		{
			$ug['genericoptions'] += 1;
		}
		$db->query_write(fetch_query_sql($ug, 'usergroup', "WHERE usergroupid=$usergroup[usergroupid]"));
		echo "{$vbphrase['done']}.</li>\n";
		vbflush();
	}
	echo "</ul>\n";

	$query[] = "UPDATE usergroup SET pmquota = IF(canusepm, " . iif($pmquota == 0, 10000, $pmquota) . ", 0)";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroups'];

	// update usergroups to allow membergroups EXCEPT for the following groups:
	// unregistered users (group 1)
	// users awaiting email confirmation (group 3)
	// COPPA users awaiting moderation (group 4)
	// any usergroup defined as 'banned'
	if (empty($vbulletin->GPC['bangroup']))
	{
		$bannedgroups = 0;
	}
	else
	{
		$bannedgroups = implode(',', $vbulletin->GPC['bangroup']);
	}
	$query[] = "
		UPDATE usergroup SET
			genericoptions = genericoptions + " . $vbulletin->bf_ugp_genericoptions['allowmembergroups'] . "
		WHERE usergroupid NOT IN(1,3,4)
		AND usergroupid NOT IN($bannedgroups)
		AND NOT (genericoptions & " . $vbulletin->bf_ugp_genericoptions['allowmembergroups'] . ")
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroups'];

	$query[] = "
		UPDATE usergroup SET
			genericoptions = genericoptions + " . (
				$vbulletin->bf_ugp_genericoptions['showbirthday'] +
				$vbulletin->bf_ugp_genericoptions['showmemberlist']
			)  . "
		WHERE usergroupid NOT IN (1, 3, 4)
		AND usergroupid NOT IN($bannedgroups)
		AND NOT (genericoptions & " . $vbulletin->bf_ugp_genericoptions['showbirthday'] . ")
		AND NOT (genericoptions & " . $vbulletin->bf_ugp_genericoptions['showmemberlist'] . ")
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroups'];

	$query[] = "
		UPDATE usergroup SET
			genericoptions = genericoptions + " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . "
		WHERE usergroupid IN($bannedgroups)
		AND NOT (genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroups'];

	if ($showeditedby)
	// Show edited by message setting from vb2 and $showeditedbyadmin
	{
		$query[] = "
			UPDATE usergroup SET
				genericoptions = genericoptions + " . $vbulletin->bf_ugp_genericoptions['showeditedby'] . "
			WHERE NOT (genericoptions & " . $vbulletin->bf_ugp_genericoptions['showeditedby'] . ")
			" . iif(!$showeditedbyadmin, "AND cancontrolpanel = 0", '') . "
		";
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_generic_options'];
	}

	$query[] = "
		UPDATE usergroup SET
			genericpermissions = genericpermissions + " . (
			$vbulletin->bf_ugp_genericpermissions['canseehidden'] +
			$vbulletin->bf_ugp_genericpermissions['canseeownrep'] +
			$vbulletin->bf_ugp_genericpermissions['canmanageownusernotes'] +
			$vbulletin->bf_ugp_genericpermissions['canmanageothersusernotes'] +
			$vbulletin->bf_ugp_genericpermissions['canusecustomtitle'] +
			$vbulletin->bf_ugp_genericpermissions['canuseavatar'] +
			$vbulletin->bf_ugp_genericpermissions['canprofilepic']
			). "
		WHERE cancontrolpanel = 1
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_generic_options'];

	$query[] = "
		UPDATE usergroup SET
			genericpermissions = genericpermissions + " . $vbulletin->bf_ugp_genericpermissions['canbeusernoted'] . "
		WHERE cancontrolpanel = 0
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_generic_options'];

	$query[] = "
		UPDATE usergroup SET
			calendarpermissions = calendarpermissions + " . (
			$vbulletin->bf_ugp_calendarpermissions['canviewcalendar'] +
			$vbulletin->bf_ugp_calendarpermissions['canviewothersevent']
			) . "
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroup_calendar'];

	$query[] = "
		UPDATE usergroup SET
			calendarpermissions = calendarpermissions + " . (
				$vbulletin->bf_ugp_calendarpermissions['caneditevent'] +
				$vbulletin->bf_ugp_calendarpermissions['candeleteevent']
			) . "
		WHERE ismoderator = 1
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroup_calendar'];

	$query[] = "
		UPDATE usergroup SET
			calendarpermissions = calendarpermissions + " . ($vbulletin->bf_ugp_calendarpermissions['canpostevent']) . "
		WHERE canpublicevent = 1
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroup_calendar'];

	$query[] = "INSERT INTO calendarpermission (calendarid, usergroupid, calendarpermissions)
					VALUES	(2, 2, 15), ### Registered Group ###
							(2, 6, 15), ### Admin Group ###
							(2, 5, 15), ### Super Moderators ###
							(2, 7, 15) ### Moderators ###
							$calendarsql
				";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['creating_priv_calendar_perms'];

	$query[] = "UPDATE usergroup SET
				genericpermissions = genericpermissions + " . (
					$vbulletin->bf_ugp_genericpermissions['caninvsible'] +
					$vbulletin->bf_ugp_genericpermissions['canusesignature'] +
					$vbulletin->bf_ugp_genericpermissions['canseeprofilepic'] +
					$vbulletin->bf_ugp_genericpermissions['canuserep'] +
					$vbulletin->bf_ugp_genericpermissions['cannegativerep']
				) . "
			WHERE usergroupid NOT IN (1, 3, 4) AND usergroupid NOT IN($bannedgroups)";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroups'];

	$query[] = "UPDATE usergroup SET
				wolpermissions = wolpermissions + " . (
					$vbulletin->bf_ugp_wolpermissions['canwhosonlinefull'] +
					$vbulletin->bf_ugp_wolpermissions['canwhosonlinebad'] +
					$vbulletin->bf_ugp_wolpermissions['canwhosonlinelocation']
				) . ",
				forumpermissions = forumpermissions + " . $vbulletin->bf_ugp_forumpermissions['canseedelnotice'] . ",
				genericpermissions = genericpermissions + " . $vbulletin->bf_ugp_genericpermissions['canviewothersusernotes'] . "
			WHERE ismoderator = 1";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroups'];

	$avatarmaxdimension = intval($avatarmaxdimension);
	$avatarmaxsize = intval($avatarmaxsize);
	$query[] = "UPDATE usergroup SET
			avatarmaxwidth = $avatarmaxdimension,
			avatarmaxheight = $avatarmaxdimension,
			avatarmaxsize = $avatarmaxsize,
			profilepicmaxwidth = 100,
			profilepicmaxheight = 100,
			profilepicmaxsize = 100000";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_usergroups'];

	$query[] = "ALTER TABLE usergroup
		DROP canviewmembers,
		DROP canview,
		DROP cansearch
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 1, 9);

	$query[] = "ALTER TABLE usergroup
		DROP canemail,
		DROP canpostnew,
		DROP canmove
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 2, 9);

	$query[] = "ALTER TABLE usergroup
		DROP canopenclose,
		DROP candeletethread,
		DROP canreplyown
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 3, 9);

	$query[] = "ALTER TABLE usergroup
		DROP canreplyothers,
		DROP canviewothers,
		DROP caneditpost
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 4, 9);

	$query[] = "ALTER TABLE usergroup
		DROP candeletepost,
		DROP canusepm,
		DROP canpostpoll
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 5, 9);

	$query[] = "ALTER TABLE usergroup
		DROP canvote,
		DROP canpostattachment,
		DROP canpublicevent
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 6, 9);

	$query[] = "ALTER TABLE usergroup
		DROP canpublicedit,
		DROP canthreadrate,
		DROP cantrackpm
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 7, 9);
	$query[] = "ALTER TABLE usergroup
		DROP candenypmreceipts,
		DROP canwhosonline,
		DROP canwhosonlineip
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 8, 9);

	$query[] = "ALTER TABLE usergroup
		DROP ismoderator,
		DROP cangetattachment
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 9, 9);

	exec_queries();
}

// #############################################################################
// Forum Permissions
if ($vbulletin->GPC['step'] == 22)
{
	// get rid of redundant forum permissions
	$deleteids = '0';
	$fps = $db->query_read("
		SELECT forumpermissionid
		FROM forumpermission
		LEFT JOIN forum ON(forum.forumid = forumpermission.forumid)
		LEFT JOIN usergroup ON(usergroup.usergroupid = forumpermission.usergroupid)
		WHERE ISNULL(forum.forumid) OR ISNULL(usergroup.usergroupid)
	");
	while($fp = $db->fetch_array($fps))
	{
		$deleteids .= ", $fp[forumpermissionid]";
	}
	if ($deleteids != '0')
	{
		$query[] = "DELETE FROM forumpermission WHERE forumpermissionid IN($deleteids)";
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['removing_orhpan_forum_perms'];
		exec_queries();
	}

	// make sure the forum permissions are not duplicated...
	$fperms = array();
	$fps = $db->query_read("
		SELECT forumpermission.*, forum.title AS forumtitle, usergroup.title AS usergrouptitle
		FROM forumpermission
		LEFT JOIN forum ON(forum.forumid=forumpermission.forumid)
		LEFT JOIN usergroup ON(usergroup.usergroupid=forumpermission.usergroupid)
		ORDER BY forumpermissionid
	");
	while ($fp = $db->fetch_array($fps))
	{
		$fperms["$fp[forumid]"]["$fp[usergroupid]"] = $fp;
	}

	$query[] = "INSERT INTO datastore (title, data) VALUES ('fperms_backup', '" . $db->escape_string(serialize($fperms)) . "')";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['backup_forum_perms'];

	$query[] = "DROP TABLE forumpermission";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['drop_old_forumperms'];

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "forumpermission (
	forumpermissionid smallint unsigned NOT NULL auto_increment,
	forumid smallint unsigned NOT NULL DEFAULT '0',
	usergroupid smallint unsigned NOT NULL DEFAULT '0',
	forumpermissions int unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (forumpermissionid),
	UNIQUE KEY ugid_fid (usergroupid, forumid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergroup");

	if (!empty($fperms))
	{
		echo "<ul>\n";
		$insertbits = array();

		require_once(DIR . '/includes/functions_misc.php');
		foreach($fperms as $tmp)
		{
			foreach($tmp as $fperm)
			{
				echo_flush("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['usergroup_x_forum_y'], $fperm['usergrouptitle'], $fperm['forumtitle']) . " ...\n");
				$insertbits[] = "($fperm[forumid], $fperm[usergroupid], " . convert_array_to_bits($fperm, $vbulletin->bf_ugp_forumpermissions) . ")";
				echo_flush("{$vbphrase['done']}.</li>\n");
			}
		}
		echo "</ul>\n";
		$query[] = "INSERT INTO forumpermission\n\t(forumid, usergroupid, forumpermissions)\nVALUES\n\t" . implode(",\n\t", $insertbits);
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['reinsert_forum_perms'];
	}

	$query[] = "DELETE FROM datastore WHERE title='fperms_backup'";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['remove_forum_perms_backup'];

	exec_queries();
}

// #############################################################################
// Moderator Permissions
if ($vbulletin->GPC['step'] == 23)
{
	$db->query_write("ALTER TABLE moderator ADD permissions INT UNSIGNED NOT NULL DEFAULT '0'");
	echo_flush(sprintf($vbphrase['alter_table'], TABLE_PREFIX . "moderator") . "\n");

	$moderators = $db->query_read("
		SELECT moderator.*,forum.title,user.username
		FROM moderator
		LEFT JOIN forum ON(forum.forumid=moderator.forumid)
		LEFT JOIN user ON(user.userid=moderator.userid)
	");
	echo "<p>{$upgrade_phrases['upgrade_300b3.php']['updating_moderator_perms']}</p><ul>";

	require_once(DIR . '/includes/functions_misc.php');
	while ($moderator = $db->fetch_array($moderators))
	{
		echo "<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['moderator_x_forum_y'], $moderator['username'], $moderator['title']) . " ...";
		vbflush();
		if ($moderator['title']=='' or $moderator['username'] == '')
		{
			echo "<i>{$upgrade_phrases['upgrade_300b3.php']['deleted_not_needed']}</i></li>\n";
		}
		else
		{
			$perms = convert_array_to_bits($moderator, $vbulletin->bf_misc_moderatorpermissions);
			$db->query_write("UPDATE moderator SET permissions=$perms WHERE moderatorid=$moderator[moderatorid]");
			echo "</li>\n";
		}
	}
	echo "</ul>\n";

	// drop fields converted to bitfield 'permissions'
	$query[] = "ALTER TABLE moderator
		DROP newthreademail,
		DROP newpostemail,
		DROP caneditposts,
		DROP candeleteposts
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'moderator', 1, 4);

	// drop fields converted to bitfield 'permissions'
	$query[] = "ALTER TABLE moderator
		DROP canviewips,
		DROP canmanagethreads,
		DROP canopenclose,
		DROP caneditthreads
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'moderator', 2, 4);

	// drop fields converted to bitfield 'permissions'
	$query[] = "ALTER TABLE moderator
		DROP caneditstyles,
		DROP canbanusers,
		DROP canviewprofile,
		DROP canannounce
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'moderator', 3, 4);

	// drop fields converted to bitfield 'permissions'
	$query[] = "ALTER TABLE moderator
		DROP canmassmove,
		DROP canmassprune,
		DROP canmoderateposts,
		DROP canmoderateattachments
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'moderator', 4, 4);

	exec_queries();
}

// #############################################################################
// Phrases
if ($vbulletin->GPC['step'] == 24)
{
	echo "<p>{$upgrade_phrases['upgrade_300b3.php']['insert_phrase_groups']}...</p>";
	/*insert query*/
	$db->query_write("
	INSERT INTO " . TABLE_PREFIX . "phrasetype
		(phrasetypeid, fieldname, title, editrows)
	VALUES
 		(1, 'global', '{$phrasetype['global']}', 3),
 		(2, 'cpglobal', '{$phrasetype['cpglobal']}', 3),
 		(3, 'cppermission', '{$phrasetype['cppermission']}', 3),
 		(4, 'forum', '{$phrasetype['forum']}', 3),
 		(5, 'calendar', '{$phrasetype['calendar']}', 3),
 		(6, 'attachment_image', '{$phrasetype['attachment_image']}', 3),
 		(7, 'style', '{$phrasetype['style']}', 3),
 		(8, 'logging', '{$phrasetype['logging']}', 3),
 		(9, 'cphome', '{$phrasetype['cphome']}', 3),
 		(10, 'promotion', '{$phrasetype['promotion']}', 3),
 		(11, 'user', '{$phrasetype['user']}', 3),
		(12, 'help_faq', '{$phrasetype['help_faq']}', 3),
		(13, 'sql', '{$phrasetype['sql']}', 3),
		(14, 'subscription', '{$phrasetype['subscription']}', 3),
		(15, 'language', '{$phrasetype['language']}', 3),
		(16, 'bbcode', '{$phrasetype['bbcode']}', 3),
		(17, 'stats', '{$phrasetype['stats']}', 3),
		(18, 'diagnostic', '{$phrasetype['diagnostics']}', 3),
		(19, 'maintenance', '{$phrasetype['maintenance']}', 3),
		(20, 'profilefield', '{$phrasetype['profile']}', 3),
		(21, 'thread', '{$phrasetype['thread']}', 3),
		(22, 'timezone', '{$phrasetype['timezone']}', 3),
		(23, 'banning', '{$phrasetype['banning']}', 3),
		(24, 'reputation', '{$phrasetype['reputation']}', 3),
		(25, 'wol', '{$phrasetype['wol']}', 3),
		(26, 'threadmanage', '{$phrasetype['threadmanage']}', 3),
		(27, 'pm', '{$phrasetype['pm']}', 3),
		(28, 'cpuser', '{$phrasetype['cpuser']}', 3),
		(29, 'accessmask', '{$phrasetype['accessmask']}', 3),
		(30, 'cron', '{$phrasetype['cron']}', 3),
		(31, 'moderator', '{$phrasetype['moderator']}', 3),
		(32, 'cpoption', '{$phrasetype['cpoption']}', 3),
		(33, 'cprank', '{$phrasetype['cprank']}', 3),
		(34, 'cpusergroup', '{$phrasetype['cpusergroup']}', 3),
		(35, 'holiday', '{$phrasetype['holiday']}', 3),
		(36, 'posting', '{$phrasetype['posting']}', 3),
		(37, 'poll', '{$phrasetype['poll']}', 3),
		(38, 'fronthelp', '{$phrasetype['fronthelp']}', 3),
		(39, 'register', '{$phrasetype['register']}', 3),
		(40, 'search', '{$phrasetype['search']}', 3),
		(41, 'showthread', '{$phrasetype['showthread']}', 3),
		(42, 'postbit', '{$phrasetype['postbit']}', 3),
		(43, 'forumdisplay', '{$phrasetype['forumdisplay']}', 3),
		(44, 'messaging', '{$phrasetype['messaging']}', 3),
		(45, '', '(reserved for future vBulletin use)', 0),
		(46, '', '(reserved for future vBulletin use)', 0),
		(47, '', '(reserved for future vBulletin use)', 0),
		(48, '', '(reserved for future vBulletin use)', 0),
		(49, '', '(reserved for future vBulletin use)', 0),
		(50, '', '(reserved for future vBulletin use)', 0),
		(51, '', '(reserved for future vBulletin use)', 0),
		(52, '', '(reserved for future vBulletin use)', 0),
		(53, '', '(reserved for future vBulletin use)', 0),
		(54, '', '(reserved for future vBulletin use)', 0),
		(55, '', '(reserved for future vBulletin use)', 0),
		(56, '', '(reserved for future vBulletin use)', 0),
		(57, '', '(reserved for future vBulletin use)', 0),
		(58, '', '(reserved for future vBulletin use)', 0),
		(59, '', '(reserved for future vBulletin use)', 0),
		(60, '', '(reserved for future vBulletin use)', 0),
		(61, '', '(reserved for future vBulletin use)', 0),
		(62, '', '(reserved for future vBulletin use)', 0),
		(63, '', '(reserved for future vBulletin use)', 0),
		(64, '', '(reserved for future vBulletin use)', 0),
		(65, '', '(reserved for future vBulletin use)', 0),
		(66, '', '(reserved for future vBulletin use)', 0),
		(67, '', '(reserved for future vBulletin use)', 0),
		(68, '', '(reserved for future vBulletin use)', 0),
		(69, '', '(reserved for future vBulletin use)', 0),
		(70, '', '(reserved for future vBulletin use)', 0),
		(71, '', '(reserved for future vBulletin use)', 0),
		(72, '', '(reserved for future vBulletin use)', 0),
		(73, '', '(reserved for future vBulletin use)', 0),
		(74, '', '(reserved for future vBulletin use)', 0),
		(75, '', '(reserved for future vBulletin use)', 0),
		(76, '', '(reserved for future vBulletin use)', 0),
		(77, '', '(reserved for future vBulletin use)', 0),
		(78, '', '(reserved for future vBulletin use)', 0),
		(79, '', '(reserved for future vBulletin use)', 0),
		(80, '', '(reserved for future vBulletin use)', 0),
		(81, '', '(reserved for future vBulletin use)', 0),
		(82, '', '(reserved for future vBulletin use)', 0),
		(83, '', '(reserved for future vBulletin use)', 0),
		(84, '', '(reserved for future vBulletin use)', 0),
		(85, '', '(reserved for future vBulletin use)', 0),
		(86, '', '(reserved for future vBulletin use)', 0),
		(87, '', '(reserved for future vBulletin use)', 0),
		(88, '', '(reserved for future vBulletin use)', 0),
		(89, '', '(reserved for future vBulletin use)', 0),
		(90, '', '(reserved for future vBulletin use)', 0),
		(91, '', '(reserved for future vBulletin use)', 0),
		(92, '', '(reserved for future vBulletin use)', 0),
		(93, '', '(reserved for future vBulletin use)', 0),
		(94, '', '(reserved for future vBulletin use)', 0),
		(95, '', '(reserved for future vBulletin use)', 0),
		(96, '', '(reserved for future vBulletin use)', 0),
		(97, '', '(reserved for future vBulletin use)', 0),
		(98, '', '(reserved for future vBulletin use)', 0),
		(99, '', '(reserved for future vBulletin use)', 0),
		(100, '', '(reserved for future vBulletin use)', 0),
		(101, '', '(reserved for future vBulletin use)', 0),
		(102, '', '(reserved for future vBulletin use)', 0),
		(103, '', '(reserved for future vBulletin use)', 0),
		(104, '', '(reserved for future vBulletin use)', 0),
		(105, '', '(reserved for future vBulletin use)', 0),
		(106, '', '(reserved for future vBulletin use)', 0),
		(107, '', '(reserved for future vBulletin use)', 0),
		(108, '', '(reserved for future vBulletin use)', 0),
		(109, '', '(reserved for future vBulletin use)', 0),
		(110, '', '(reserved for future vBulletin use)', 0),
		(111, '', '(reserved for future vBulletin use)', 0),
		(112, '', '(reserved for future vBulletin use)', 0),
		(113, '', '(reserved for future vBulletin use)', 0),
		(114, '', '(reserved for future vBulletin use)', 0),
		(115, '', '(reserved for future vBulletin use)', 0),
		(116, '', '(reserved for future vBulletin use)', 0),
		(117, '', '(reserved for future vBulletin use)', 0),
		(118, '', '(reserved for future vBulletin use)', 0),
		(119, '', '(reserved for future vBulletin use)', 0),
		(120, '', '(reserved for future vBulletin use)', 0),
		(121, '', '(reserved for future vBulletin use)', 0),
		(122, '', '(reserved for future vBulletin use)', 0),
		(123, '', '(reserved for future vBulletin use)', 0),
		(124, '', '(reserved for future vBulletin use)', 0),
		(125, '', '(reserved for future vBulletin use)', 0),
		(126, '', '(reserved for future vBulletin use)', 0),
		(127, '', '(reserved for future vBulletin use)', 0),
		(128, '', '(reserved for future vBulletin use)', 0),
		(129, '', '(reserved for future vBulletin use)', 0),
		(130, '', '(reserved for future vBulletin use)', 0),
		(131, '', '(reserved for future vBulletin use)', 0),
		(132, '', '(reserved for future vBulletin use)', 0),
		(133, '', '(reserved for future vBulletin use)', 0),
		(134, '', '(reserved for future vBulletin use)', 0),
		(135, '', '(reserved for future vBulletin use)', 0),
		(136, '', '(reserved for future vBulletin use)', 0),
		(137, '', '(reserved for future vBulletin use)', 0),
		(138, '', '(reserved for future vBulletin use)', 0),
		(139, '', '(reserved for future vBulletin use)', 0),
		(140, '', '(reserved for future vBulletin use)', 0),
		(141, '', '(reserved for future vBulletin use)', 0),
		(142, '', '(reserved for future vBulletin use)', 0),
		(143, '', '(reserved for future vBulletin use)', 0),
		(144, '', '(reserved for future vBulletin use)', 0),
		(145, '', '(reserved for future vBulletin use)', 0),
		(146, '', '(reserved for future vBulletin use)', 0),
		(147, '', '(reserved for future vBulletin use)', 0),
		(148, '', '(reserved for future vBulletin use)', 0),
		(149, '', '(reserved for future vBulletin use)', 0),
		(150, '', '(reserved for future vBulletin use)', 0),
		(1000, 'fronterror', '{$phrasetype['front_end_error']}', 8),
		(2000, 'frontredirect', '{$phrasetype['front_end_redirect']}', 8),
		(3000, 'emailbody', '{$phrasetype['email_body']}', 10),
		(4000, 'emailsubject', '{$phrasetype['email_subj']}', 3),
		(5000, 'vbsettings', '{$phrasetype['vbulletin_settings']}', 4),
		(6000, 'cphelptext', '{$phrasetype['cp_help']}', 8),
		(7000, 'faqtitle', '{$phrasetype['faq_title']}', 3),
		(8000, 'faqtext', '{$phrasetype['faq_text']}', 10),
		(9000, 'cpstopmsg', '{$phrasetype['stop_message']}', 8)
");
}


// #############################################################################
// Scheduled Tasks
if ($vbulletin->GPC['step'] == 25)
{

	$query[1] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 1, './includes/cron/birthday.php', 1, '{$upgrade_phrases['upgrade_300b3.php']['cron_birthday']}')";
	$explain[1] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 1);

	$query[2] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 56, './includes/cron/threadviews.php', 0, '{$upgrade_phrases['upgrade_300b3.php']['cron_thread_views']}')";
	$explain[2] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 2);

	$query[3] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 45, './includes/cron/promotion.php', 1, '{$upgrade_phrases['upgrade_300b3.php']['cron_user_promo']}')";
	$explain[3] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 3);

	$query[4] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 2, './includes/cron/digestdaily.php', 1, '{$upgrade_phrases['upgrade_300b3.php']['cron_daily_digest']}')";
	$explain[4] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 4);

	$query[5] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (1, -1, 0, 30, './includes/cron/digestweekly.php', 1, '{$upgrade_phrases['upgrade_300b3.php']['cron_weekly_digest']}')";
	$explain[5] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 5);

	$query[6] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 3, './includes/cron/activate.php', 1, '{$upgrade_phrases['upgrade_300b3.php']['cron_activation']}')";
	$explain[6] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 6);

	$query[7] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 0, './includes/cron/subscriptions.php', 1, '{$upgrade_phrases['upgrade_300b3.php']['cron_subscriptions']}')";
	$explain[7] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 7);

	$query[8] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 5, './includes/cron/cleanup.php', 0, '{$upgrade_phrases['upgrade_300b3.php']['cron_hourly_cleanup']}')";
	$explain[8] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 8);

	$query[9] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 40, './includes/cron/cleanup2.php', 0, '{$upgrade_phrases['upgrade_300b3.php']['cron_hourly_cleaup2']}')";
	$explain[9] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 9);

	$query[10] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 10, './includes/cron/attachmentviews.php', 0, '{$upgrade_phrases['upgrade_300b3.php']['cron_attachment_views']}')";
	$explain[10] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 10);

	$query[11] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 0, './includes/cron/removebans.php', 1, '{$upgrade_phrases['upgrade_300b3.php']['cron_unban_users']}')";
	$explain[11] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 11);

	$query[12] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 0, './includes/cron/stats.php', 0, '{$upgrade_phrases['upgrade_300b3.php']['cron_stats_log']}')";
	$explain[12] = sprintf($upgrade_phrases['upgrade_300b3.php']['inserting_task_x'], 12);

	$tmp = $explain;

	exec_queries(0, 1);

	require_once(DIR . '/includes/functions_cron.php');

	if (is_array($inserts))
	{
		echo "<ul>\n";
		foreach ($inserts as $index => $value)
		{
			$i++;
			preg_match('#(\d+)#', $tmp["$i"], $regs);
			echo "<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['scheduling_x'], $regs[1]) . "</li>";
			build_cron_item($value);
		}
		echo "</ul>\n";
	}

	build_cron_next_run();
}

// #############################################################################
// Settings #1
if ($vbulletin->GPC['step'] == 26)
{
	$vb2groups = array(
		'Turn Your vBulletin on and off' => 'onoff',
		'General Settings' => 'general',
		'Contact Details' => 'contact',
		'Posting Code allowances (vB code / HTML / etc)' => 'postingallow',
		'Forums Home Page Options' => 'forumhome',
		'User and registration options' => 'user',
		'Memberlist options' => 'memberlist',
		'Thread display options' => 'showthread',
		'Forum Display Options' => 'forumdisplay',
		'Search Options' => 'search',
		'Email Options' => 'email',
		'Date / Time options' => 'datetime',
		'Edit Options' => 'editpost',
		'IP Logging Options' => 'ip',
		'Floodcheck Options' => 'floodcheck',
		'Banning Options' => 'banning',
		'Private Messaging Options' => 'pm',
		'Censorship Options' => 'censor',
		'HTTP Headers and output' => 'http',
		'Version Info' => 'version',
		'Templates' => 'templates',
		'Load limiting options' => 'loadlimit',
		'Polls' => 'poll',
		'Avatars' => 'avatar',
		'Attachments' => 'attachment',
		'Custom User Titles' => 'usertitle',
		'Upload Options' => 'upload',
		'Who\'s Online' => 'online',
		'Language Options' => 'OLDlanguage',
		'Spell Check' => 'OLDspellcheck',
		'Calendar' => 'OLDcalendar'
	);
	// lets take language specific AND default just in case
	$vb2groups = array_merge($upgrade_phrases['upgrade_300b3.php']['settinggroups'], $vb2groups);

	$query[] = "
		ALTER TABLE settinggroup
		ADD grouptitle CHAR( 50 ) NOT NULL DEFAULT '' FIRST,
		ADD volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "settinggroup");

	$query[] = "
		ALTER TABLE setting
		ADD grouptitle CHAR(50) NOT NULL DEFAULT '',
		ADD defaultvalue MEDIUMTEXT,
		ADD advanced SMALLINT UNSIGNED NOT NULL DEFAULT '0',
		ADD volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0'
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "setting");

	$settinggroups = array();

	$groups = $db->query_read("SELECT * FROM settinggroup");
	while ($group = $db->fetch_array($groups))
	{
		$settinggroups["$group[settinggroupid]"] = $group;
	}

	$phrases = array();
	$hackgroup = 0;

	foreach($settinggroups as $settinggroupid => $settinggroup)
	{
		if (!isset($vb2groups["$settinggroup[title]"]))
		{
			$hackgroup++;
			$vb2groups["$settinggroup[title]"] = "hackgroup$hackgroup";
			$phrases[] = "(" . PHRASETYPEID_SETTING . ", 0, 'settinggroup_" . $vb2groups["$settinggroup[title]"] . "', '" . $db->escape_string($settinggroup['title']) . "')";
			$volatile = 0;
		}
		else
		{
			$volatile = 1;
		}

		$query[] = "UPDATE settinggroup SET grouptitle='" . $db->escape_string($vb2groups["$settinggroup[title]"]) . "', volatile=$volatile, displayorder=displayorder+1000 WHERE settinggroupid=$settinggroupid";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['update_setting_group_x'], htmlspecialchars_uni($settinggroup['title']));
		$query[] = "UPDATE setting SET grouptitle='" . $db->escape_string($vb2groups["$settinggroup[title]"]) . "' WHERE settinggroupid=$settinggroupid";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['update_settings_within_x'], htmlspecialchars_uni($settinggroup['title']));

	}

	if (!empty($phrases))
	{
		$query[] =  "INSERT INTO phrase\n\t(phrasetypeid, languageid, varname, text)\nVALUES\n\t" . implode(",\n\t", $phrases);
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['insert_phrases_nonstandard_groups'];
	}

	exec_queries();
}

// #############################################################################
// Settings #2
if ($vbulletin->GPC['step'] == 27)
{
	$vb2settings = array
	(
		'addheaders',
		'address',
		'addtemplatename',
		'allowbbcode',
		'allowbbimagecode',
		'allowchangestyles',
		'allowduplicates',
		'allowdynimg',
		'allowhtml',
		'allowimgsizefailure',
		'allowkeepbannedemail',
		'allowmultiregs',
		'allowregistration',
		'allowsignatures',
		'allowsmilies',
		'allowvbcodebuttons',
		'allowwildcards',
		'attachextensions',
		'avatarallowupload',
		'avatarallowwebsite',
		'avatarcustomposts',
		'avatarenabled',
		'avatarmaxdimension',
		'avatarmaxsize',
		'banemail',
		'banip',
		'bbactive',
		'bbclosedreason',
		'bbtitle',
		'bburl',
		'calallowbbcode',
		'calallowhtml',
		'calallowsmilies',
		'calbbimagecode',
		'calbirthday',
		'calendarenabled',
		'calformat1',
		'calformat2',
		'calshowbirthdays',
		'calStart',
		'caltitlelength',
		'censorchar',
		'censorwords',
		'checknewpm',
		'companyname',
		'contactuslink',
		'cookiedomain',
		'cookiepath',
		'cookietimeout',
		'copyrighttext',
		'ctAdmin',
		'ctCensorMod',
		'ctCensorWords',
		'ctDays',
		'ctEitherOr',
		'ctEnable',
		'ctMaxChars',
		'ctPosts',
		'dateformat',
		'displayemails',
		'displayloggedin',
		'editthreadtitlelimit',
		'edittimelimit',
		'enableaccess',
		'enablebanning',
		'enablecensor',
		'enableemail',
		'enablefloodcheck',
		'enablememberlist',
		'enablepms',
		'enablesearches',
		'faxnumber',
		'floodchecktime',
		'forumdisplaydepth',
		'forumhomedepth',
		'gziplevel',
		'gzipoutput',
		'hideprivateforums',
		'highlightadmin',
		'hometitle',
		'homeurl',
		'hotnumberposts',
		'hotnumberviews',
		'ignoremods',
		'illegalusernames',
		'inboxname',
		'linktopages',
		'loadlimit',
		'logip',
		'maxattachheight',
		'maxattachsize',
		'maxattachwidth',
		'maximages',
		'maxmultipage',
		'maxpolloptions',
		'maxposts',
		'maxsearchlength',
		'maxthreads',
		'maxuserlength',
		'memberAllGood',
		'memberlistperpage',
		'minsearchlength',
		'minuserlength',
		'moderatenewmembers',
		'movedthreadprefix',
		'newuseremail',
		'nocacheheaders',
		'noeditedbytime',
		'numavatarshigh',
		'numavatarswide',
		'offtext',
		'ontext',
		'pagenavpages',
		'pmcancelkill',
		'pmcancelledword',
		'pmfloodtime',
		'pmmaxchars',
		'pmquota',
		'pollthreadprefix',
		'postmaxchars',
		'postorder',
		'privacyurl',
		'privallowbbcode',
		'privallowbbimagecode',
		'privallowhtml',
		'privallowicons',
		'privallowsmilies',
		'registereddateformat',
		'requireuniqueemail',
		'safeupload',
		'searchfloodtime',
		'searchperpage',
		'secureemail',
		'sentitemsname',
		'sessionlimit',
		'showbirthdays',
		'showdeficon',
		'showdots',
		'showeditedby',
		'showeditedbyadmin',
		'showforumdescription',
		'showforumusers',
		'showlocks',
		'showonline',
		'showvotes',
		'smcolumns',
		'smtotal',
		'spellchecklang',
		'stickythreadprefix',
		'stopshouting',
		'templateversion',
		'timeformat',
		'timeoffset',
		'tmppath',
		'updatelastpost',
		'usecoppa',
		'useforumjump',
		'usehotthreads',
		'usememberlistadvsearch',
		'usereferrer',
		'usermaxposts',
		'verifyemail',
		'viewattachedimages',
		'votechange',
		'webmasteremail',
		'WOLenable',
		'WOLguests',
		'WOLrefresh',
		'WOLresolve',
		'wordwrap',
		// the following are varnames from popular hacks that are now
		// integrated into vBulletin 3 under different varnames
		'usefileavatar'
	);

	// ###############################################################################
	// MAKE A LIST OF THE SETTINGS THAT WILL BE INCOMING FROM THE XML SETTINGS FILE AND
	// REMOVE ANY SETTINGS THAT WILL CAUSE A DUPLICATE KEY MYSQL ERROR ON IMPORT
	// (THEIR VALUES WILL BE RETAINED IN THE DATASTORE FOR NOW, SO THIS IS NO PROBLEM)
	// ###############################################################################

	require_once(DIR . '/includes/class_xml.php');
	require_once(DIR . '/includes/functions_misc.php');

	$xmlobj = new vB_XML_Parser(false, DIR . '/install/vbulletin-settings.xml');
	$arr = $xmlobj->parse();

	foreach($arr['settinggroup'] AS $group)
	{
		foreach($group['setting'] AS $setting)
		{
			$vb3options[] = $setting['varname'];
		}
	}

	// now query the existing options to find out which should be
	// zapped and which need to be translated into the new system...
	$settings = $db->query_read("
		SELECT setting.*
		FROM setting LEFT JOIN settinggroup USING(settinggroupid)
		ORDER BY settinggroup.displayorder, setting.displayorder
	");

	$phrases = array();

	while ($setting = $db->fetch_array($settings))
	{
		$vb2options["$setting[varname]"] = $setting['value'];
		if (!in_array($setting['varname'], $vb2settings) and !in_array($setting['varname'], $vb3options))
		{
			$nonvolatilevars[] = $setting['varname'];
			$settinggroupid = $setting['settinggroupid'];
			$phrases[] = "(" . PHRASETYPEID_SETTING . ", 0, '" . $db->escape_string("setting_$setting[varname]_title") . "', '" . $db->escape_string($setting['title']) . "')";
			$phrases[] = "(" . PHRASETYPEID_SETTING . ", 0, '" . $db->escape_string("setting_$setting[varname]_desc") . "', '" . $db->escape_string($setting['description']) . "')";
		}
		else
		{
			$volatilevars[] = $setting['varname'];
		}
	}

	// insert necessary phrases
	if (!empty($phrases))
	{
		$query[] =  "INSERT IGNORE INTO phrase\n\t(phrasetypeid, languageid, varname, text)\nVALUES\n\t" . implode(",\n\t", $phrases);
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['insert_phrases_nonstandard_settings'];
	}

	// save current options into the datastore for retrieval later
	$query[] = "REPLACE INTO datastore\n\t(title, data)\nVALUES\n\t('options', '" . $db->escape_string(serialize($vb2options)) . "')";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['saving_your_settings'];

	// set unwanted settings as volatile
	$query[] = "UPDATE setting SET volatile=1 WHERE varname IN('" . implode("', '", $volatilevars) . "')";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "setting");

	$query[] = "
		ALTER TABLE settinggroup
		DROP settinggroupid,
		DROP title,
		DROP PRIMARY KEY,
		ADD PRIMARY KEY(grouptitle)
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "settinggroup");

	exec_queries();

	$db->hide_errors();
	$db->query_write("
		ALTER TABLE setting
		DROP settingid,
		DROP settinggroupid,
		DROP title,
		DROP description,
		DROP PRIMARY KEY,
		ADD PRIMARY KEY(varname)
	");
	$db->show_errors();

}

// #############################################################################
// Language
if ($vbulletin->GPC['step'] == 28)
{
	require_once(DIR . '/includes/adminfunctions_language.php');

	$query[] = "ALTER TABLE " . TABLE_PREFIX . "language ENGINE = MYISAM";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "language");

	$query[] = "INSERT INTO language (title, languagecode, charset, decimalsep, thousandsep) VALUES ('{$upgrade_phrases['upgrade_300b3.php']['master_language_title']}', '{$upgrade_phrases['upgrade_300b3.php']['master_language_langcode']}', '{$upgrade_phrases['upgrade_300b3.php']['master_language_charset']}', '{$upgrade_phrases['upgrade_300b3.php']['master_language_decimalsep']}', '{$upgrade_phrases['upgrade_300b3.php']['master_language_thousandsep']}')";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['master_language_just_created'];

	exec_queries();
}

// #############################################################################
// Import Admin Help
if ($vbulletin->GPC['step'] == 29)
{
	require_once(DIR . '/includes/adminfunctions_help.php');

	echo "<p><b>{$upgrade_phrases['upgrade_300b3.php']['ahelp_imported_sucessfully']}</b></p>";
}

// #############################################################################
// Alter style table & drop replacementset table
if ($vbulletin->GPC['step'] == 30)
{

	$query[] = "ALTER TABLE style RENAME style_vb2";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['renaming_style_table'];

	$query[] = "
	CREATE TABLE " . TABLE_PREFIX . "style (
		styleid smallint unsigned NOT NULL auto_increment,
		title varchar(250) NOT NULL DEFAULT '',
		parentid smallint NOT NULL DEFAULT '0',
		parentlist varchar(250) NOT NULL DEFAULT '',
		templatelist mediumtext,
		css mediumtext,
		stylevars mediumtext,
		replacements mediumtext,
		userselect smallint unsigned NOT NULL DEFAULT '1',
		displayorder smallint unsigned NOT NULL DEFAULT '0',
		csscolors MEDIUMTEXT,
		PRIMARY KEY (styleid)
	)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "style");


	$query[] = "DROP TABLE replacementset";
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "replacementset");

	exec_queries();

}

// #############################################################################
// Alter template table
if ($vbulletin->GPC['step'] == 31)
{

	require_once(DIR . '/includes/adminfunctions_template.php');

	$db->hide_errors();
	$db->query_write('ALTER TABLE template DROP INDEX title');
	$db->show_errors();

	$query[] = "DELETE FROM template WHERE templatesetid=-1 AND title <> 'options'";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['removing_default_templates'];

	$query[] = "ALTER TABLE template
		ADD styleid SMALLINT NOT NULL DEFAULT '0',
		ADD template_un MEDIUMTEXT,
		ADD templatetype ENUM('template','stylevar','css','replacement') NOT NULL DEFAULT 'template'";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'template', 1, 3);

	$query[] = "ALTER TABLE template
		CHANGE templateid templateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
		ADD dateline INT UNSIGNED NOT NULL DEFAULT '0',
		ADD username VARCHAR(50) NOT NULL DEFAULT ''";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'template', 2, 3);

	$query[] = "ALTER TABLE template
		ADD UNIQUE title (title, styleid)";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'template', 3, 3);

	exec_queries();

	$db->hide_errors();
	$db->query_write("ALTER TABLE template ADD version varchar(30) NOT NULL DEFAULT ''");
	$db->show_errors();

	// There was a script here to store templates in the new format. Well, I'm only partially doing that. The above ALTER
	// adds a column that stores an unprocessed version of the template. So if you need the script that populates that field
	// correctly, let me know since it's too big to post here.

	echo "<p>{$upgrade_phrases['upgrade_300b3.php']['updating_template_format']}</p>";

	$db->query_write("UPDATE template SET template_un = template");
	$temps = $db->query_read("SELECT templateid, title, template FROM template WHERE title <> 'options'");
	echo "<ul>\n";
	while($temp = $db->fetch_array($temps))
	{
		echo_flush("\t<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['updating_template_x'], htmlspecialchars_uni($temp['title'])) . "... ");
		$template = compile_template($temp['template']);
		$db->query_write("UPDATE template SET template='" . $db->escape_string($template) . "' WHERE templateid=$temp[templateid]");
		echo "{$vbphrase['ok']}</li>\n";
	}
	echo "</ul>\n";

}

// #############################################################################
// User Reputation Levels -- Feel free to change these
if ($vbulletin->GPC['step'] == 32)
{

	$query[] = "INSERT INTO reputationlevel (reputationlevelid, minimumreputation, level) VALUES
			(1, -999999, '{$upgrade_phrases['upgrade_300b3.php']['reputation_-999999']}'),
			(2, -50, '{$upgrade_phrases['upgrade_300b3.php']['reputation_-50']}'),
			(3, -10, '{$upgrade_phrases['upgrade_300b3.php']['reputation_-10']}'),
			(4, 0, '{$upgrade_phrases['upgrade_300b3.php']['reputation_0']}'),
			(5, 10, '{$upgrade_phrases['upgrade_300b3.php']['reputation_10']}'),
			(6, 50, '{$upgrade_phrases['upgrade_300b3.php']['reputation_50']}'),
			(7, 150, '{$upgrade_phrases['upgrade_300b3.php']['reputation_150']}'),
			(8, 250, '{$upgrade_phrases['upgrade_300b3.php']['reputation_250']}'),
			(9, 350, '{$upgrade_phrases['upgrade_300b3.php']['reputation_350']}'),
			(10, 450, '{$upgrade_phrases['upgrade_300b3.php']['reputation_450']}'),
			(11, 550, '{$upgrade_phrases['upgrade_300b3.php']['reputation_550']}'),
			(12, 650, '{$upgrade_phrases['upgrade_300b3.php']['reputation_650']}'),
			(13, 1000, '{$upgrade_phrases['upgrade_300b3.php']['reputation_1000']}'),
			(14, 1500, '{$upgrade_phrases['upgrade_300b3.php']['reputation_1500']}'),
			(15, 2000, '{$upgrade_phrases['upgrade_300b3.php']['reputation_2000']}')
			";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['populating_reputation_levels'];

	$query[] = "UPDATE user SET reputationlevelid = 4";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['set_reputation_to_neutral'];

	exec_queries();

}

// #############################################################################
// Reading existing style info
if ($vbulletin->GPC['step'] == 33)
{
	// set up default style
	$styleQuery = array(
		"('" . sprintf($upgrade_phrases['upgrade_300b3.php']['bbtitle_vb3_style'], $db->escape_string(htmlspecialchars_uni($bbtitle))) . "', 2, '', '', '', 1, 1)",
		"('vBulletin 3', -1, '', '', '', 0, 1)"
	);

	print_form_header('', '');
	print_table_header($upgrade_phrases['upgrade_300b3.php']['please_read_txt']);
	print_description_row($upgrade_phrases['upgrade_300b3.php']['replacement_upgrade_desc']);
	print_table_footer();

	$styleIds = array();
	$i = 2;

	$styles = $db->query_read("SELECT * FROM style_vb2 ORDER BY styleid");
	echo('<blockquote><ul>');
	while ($style = $db->fetch_array($styles))
	{
		// save style id info
		$i++;
		$styleIds["$style[styleid]"] = $i;

		if ($style['title'] == 'Default' OR $style['title'] == $upgrade_phrases['upgrade_300b3.php']['vb2_default_style_title'])
		{
			$style['title'] = $upgrade_phrases['upgrade_300b3.php']['new_vb2_default_style_title'];
		}

		echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['create_vb3_style_x'], $style['title']) . "</li>");
		$styleQuery[] = "('" . $db->escape_string(trim($style['title'])) . "', -1, $style[templatesetid], '', $style[replacementsetid], userselect = $style[userselect], $style[styleid])";
	}
	echo('</ul></blockquote>');

	print_form_header('', '');
	print_table_header($upgrade_phrases['upgrade_300b3.php']['please_read_txt']);
	print_description_row($upgrade_phrases['upgrade_300b3.php']['template_upgrade_desc']);
	print_table_footer();

	$tSets = $db->query_read("
		SELECT templateset.*, COUNT(templateid) AS templatecount
		FROM templateset
		LEFT JOIN template USING(templatesetid)
		GROUP BY templateset.templatesetid
	");
	echo('<blockquote><ul>');
	while($tSet = $db->fetch_array($tSets))
	{
		if ($tSet['title'] == 'Default' OR $tSet['title'] == $upgrade_phrases['upgrade_300b3.php']['vb2_default_style_title'])
		{
			$tSet['title'] = $upgrade_phrases['upgrade_300b3.php']['new_vb2_default_style_title'];
		}
		echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['create_vb2_refernce_style'], $tSet['title']) . "</li>");
		if ($tSet['templatecount'] > 0)
		{
			$templateset["$tSet[templatesetid]"] = $tSet['title'];
			$styleQuery[] = "('[" . sprintf($upgrade_phrases['upgrade_300b3.php']['x_old_custom_templates'], $db->escape_string($tSet['title'])) . "]', -1, $tSet[templatesetid], 'templateset', '', 0, 1)";
		}
	}
	echo('</ul></blockquote>');

	echo('<hr />');

	$query[] = "INSERT INTO style (title, parentid, templatelist, css, replacements, userselect, displayorder) VALUES " . implode(",\n", $styleQuery);
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['insert_styles_vb3_table'];

	$query[] = "UPDATE style SET parentlist=CONCAT(styleid,',-1')";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_style_parent_list'];

	$query[] = "UPDATE user SET styleid = 0";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['updating_user_to_new_style'];

	echo '<blockquote>';

	exec_queries(1);

	echo '</blockquote>';
}

// #############################################################################
// Populating stylevar/css/replacements templates
if ($vbulletin->GPC['step'] == 34)
{
	// #########################################################################
	// ##################### VB2 STYLE IMPORT FUNCTIONS ########################
	// #########################################################################

	// #########################################################################
	// translates <font size="x"> into CSS variables
	function convert_font_size_to_css($inputsize)
	{
		switch($inputsize)
		{
			case 1: return '10px'; break;
			case 2: return '10pt'; break;
			case 3: return '12pt'; break;
		}
	}

	// #########################################################################
	// translates a vB2 style into a vB3 style
	function convert_vb2_style_to_vb3_style(&$r)
	{
		global $upgrade_phrases, $vbphrase;

		$translate_stylevar = array(
			'{tablewidth}' => 'outertablewidth',
			'{contenttablewidth}' => 'tablewidth',
			'{tableouterborderwidth}' => 'outerborderwidth',
			'{tableinnerborderwidth}' => 'innerborderwidth',
			'{imagesfolder}' => 'imagesfolder',
			'{titleimage}' => 'titleimage',
			'{newthreadimage}' => 'newthreadimage',
			'{replyimage}' => 'newreplyimage',
			'{closedthreadimage}' => 'closedthreadimage',
			'{textareacols_IE}' => 'textareacols_ie4',
			'{textareacols_NS4}' => 'textareacols_ns4',
			'{textareacols_NS6}' => 'textareacols_ns6'
		);

		$stylevars = array();
		$css = array();
		$replacements = array();

		if (is_array($r))
		{

			// stylevars
			echo("<li><i>{$upgrade_phrases['upgrade_300b3.php']['translate_replacement_to_stylevars']}</i><ul>");
			foreach($translate_stylevar as $fromvar => $tovar)
			{
				if (isset($r["$fromvar"]))
				{
					$hasvalues = true;
					$stylevars["$tovar"] = $r["$fromvar"];
					echo("<li>$tovar: <b>$r[$fromvar]</b></li>");
					unset($r["$fromvar"]);
				}
			}
			if (empty($stylevars))
			{
				echo("<li>({$upgrade_phrases['upgrade_300b3.php']['no_value_to_translate']})</li>");
			}
			echo('</ul></li>');

			// remove redundant and generally nasty replacements
			unset(
				$r['{htmldoctype}'],
				$r['{tableouterextra}'],
				$r['{tableinnerextra}'],
				$r['{tableinvisibleextra}'],
				$r['<largefont'],
				$r['</highlight>'],
				$r['</largefont>'],
				$r['</normalfont>'],
				$r['</smallfont>'],
				$r['{calprivatecolor}'],
				$r['{calbirthdaycolor}'],
				$r['{caltodaycolor}'],
				$r['{caldaycolor}'],
				$r['{calbgcolor}'],
				$r['{calpubliccolor}']
			);

			echo("<li><i>{$upgrade_phrases['upgrade_300b3.php']['translating_replacement_to_css']}</i><ul>");

			// body CSS
			if (isset($r['<body>']))
			{
				if (preg_match('/ bgcolor="(.*)"/siU', $r['<body>'], $regs))
				{
					$css['body']['background'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['body_bg_color_x'], $regs[1]) . "</li>");
				}
				if (preg_match('/ text="(.*)"/siU', $r['<body>'], $regs))
				{
					$css['body']['color'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['body_text_color_x'], $regs[1]) . "</li>");
				}
				if (preg_match('/ marginwidth="(.*)"/siU', $r['<body>'], $regs))
				{
					$stylevars['spacersize'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['margin_width_x'], $regs[1]) . "</li>");
				}
				unset($r['<body>']);
			}
			if (isset($r['{linkcolor}']))
			{
				$css['body']['LINK_N']['color'] = $r['{linkcolor}'];
				$css['body']['LINK_V']['color'] = $r['{linkcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['link_color_x'], $r['{linkcolor}']) . "</li>");
				unset($r['{linkcolor}']);
			}
			if (isset($r['{hovercolor}']))
			{
				$css['body']['LINK_M']['color'] = $r['{hovercolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['hover_link_color_x'], $r['{hovercolor}']) . "</li>");
				unset($r['{hovercolor}']);
			}
			process_complete_css($css, 'body');

			// .page CSS
			if (isset($r['{pagebgcolor}']))
			{
				$css['.page']['background'] = $r['{pagebgcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['page_bg_color_x'], $r['{pagebgcolor}']) . "</li>");
				unset($r['{pagebgcolor}']);
			}
			if (isset($r['{pagetextcolor}']))
			{
				$css['.page']['color'] = $r['{pagetextcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['page_text_color_x'], $r['{pagetextcolor}']) . "</li>");
				unset($r['{pagetextcolor}']);
			}
			process_complete_css($css, '.page');

			// table border CSS
			if (isset($r['{tablebordercolor}']))
			{
				$css['.tborder']['background'] = $r['{tablebordercolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['table_border_color_x'], $r['{tablebordercolor}']) . "</li>");
				unset($r['{tablebordercolor}']);
			}
			process_complete_css($css, '.tborder');

			// category strip CSS
			if (isset($r['{categorybackcolor}']))
			{
				$css['.tcat']['background'] = $r['{categorybackcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['category_strip_bg_color'], $r['{categorybackcolor}']) . "</li>");
				unset($r['{categorybackcolor}']);
			}
			if (isset($r['{categoryfontcolor}']))
			{
				$css['.tcat']['color'] = $r['{categoryfontcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['category_strip_text_color'], $r['{categoryfontcolor}']) . "</li>");
				unset($r['{categoryfontcolor}']);
			}
			process_complete_css($css, '.tcat');

			// table header CSS
			if (isset($r['{tableheadbgcolor}']))
			{
				$css['.thead']['background'] = $r['{tableheadbgcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['tbl_head_bg_color_x'], $r['{tableheadbgcolor}']) . "</li>");
				unset($r['{tableheadbgcolor}']);
			}
			if (isset($r['{tableheadtextcolor}']))
			{
				$css['.thead']['color'] = $r['{tableheadtextcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['tbl_head_text_color_x'], $r['{tableheadtextcolor}']) . "</li>");
				unset($r['{tableheadtextcolor}']);
			}
			process_complete_css($css, '.thead');

			// alt1 & alt2 CSS
			if (isset($r['{firstaltcolor}']))
			{
				$css['.alt1, .alt1Active']['background'] = $r['{firstaltcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['first_alt_color_x'], $r['{firstaltcolor}']) . "</li>");
				unset($r['{firstaltcolor}']);
			}
			process_complete_css($css, '.alt1, .alt1Active');

			if (isset($r['{secondaltcolor}']))
			{
				$css['.alt2, .alt2Active']['background'] = $r['{secondaltcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['second_alt_color_x'], $r['{secondaltcolor}']) . "</li>");
				unset($r['{secondaltcolor}']);
			}
			process_complete_css($css, '.alt2, .alt2Active');

			// td, th, p, li (normalfont) CSS
			if (isset($r['<normalfont']))
			{
				if (preg_match('/ size="(.*)"/siU', $r['<normalfont'], $regs))
				{
					$newsize = convert_font_size_to_css($regs[1]);
					$css['td, th, p, li']['font']['size'] = $newsize;
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['normal_font_size'], $newsize) . "</li>");
				}
				if (preg_match('/ face="(.*)"/siU', $r['<normalfont'], $regs))
				{
					$css['td, th, p, li']['font']['family'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['normal_font_family'], $regs[1]) . "</li>");
				}
				if (preg_match('/ color="(.*)"/siU', $r['<normalfont'], $regs))
				{
					$css['td, th, p, li']['color'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['normal_font_color'], $regs[1]) . "</li>");
				}
				unset($r['<normalfont']);
			}
			process_complete_css($css, 'td, th, p, li');

			// smallfont CSS
			if (isset($r['<smallfont']))
			{
				if (preg_match('/ size="(.*)"/siU', $r['<smallfont'], $regs))
				{
					$newsize = convert_font_size_to_css($regs[1]);
					$css['.smallfont']['font']['size'] = $newsize;
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['small_font_size'], $newsize) . "</li>");
				}
				if (preg_match('/ face="(.*)"/siU', $r['<smallfont'], $regs))
				{
					$css['.smallfont']['font']['family'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['small_font_family'], $regs[1]) . "</li>");
				}
				if (preg_match('/ color="(.*)"/siU', $r['<smallfont'], $regs))
				{
					$css['.smallfont']['color'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['small_font_color'], $regs[1]) . "</li>");
				}
				unset($r['<smallfont']);
			}
			process_complete_css($css, '.smallfont');

			// highlight CSS
			if (isset($r['<highlight']))
			{
				if (preg_match('/ face="(.*)"/siU', $r['<highlight'], $regs))
				{
					$css['.highlight']['font']['family'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['highlight_font_family'], $regs[1]) . "</li>");
				}
				if (preg_match('/ size="(.*)"/siU', $r['<highlight'], $regs))
				{
					$newsize = convert_font_size_to_css($regs[1]);
					$css['.highlight']['font']['size'] = $newsize;
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['highlight_font_size'], $newsize) . "</li>");
				}
				if (preg_match('/ color="(.*)"/siU', $r['<highlight'], $regs))
				{
					$css['.highlight']['color'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['highlight_font_color'], $regs[1]) . "</li>");
				}
				unset($r['<highlight']);
			}
			process_complete_css($css, '.highlight');

			// time CSS
			if (isset($r['{timecolor}']))
			{
				$css['.time']['color'] = $r['{timecolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade_300b3.php']['time_color_x'], $r['{timecolor}']) . "</li>");
				unset($r['{timecolor}']);
			}
			process_complete_css($css, '.time');

			if (empty($css))
			{
				echo("<li>({$upgrade_phrases['upgrade_300b3.php']['no_replacements_to_translate']})</li>");
			}

			echo('</ul></li>');

			echo("<li><i>{$upgrade_phrases['upgrade_300b3.php']['translating_remaining_replacements']}</i><ul>");
			foreach($r as $findword => $replaceword)
			{
				$replacements["$findword"] = $replaceword;
				echo('<li>' . htmlspecialchars_uni($findword) . ' &raquo; <b>' . htmlspecialchars_uni($replaceword) . '</b></li>');
				unset($r["$findword"]);
			}
			if (empty($replacements))
			{
				echo("<li>({$upgrade_phrases['upgrade_300b3.php']['no_remaining_replacement_vars']})</li>");
			}
			echo('</ul></li>');

		}

		return array('stylevars' => $stylevars, 'css' => $css, 'replacements' => $replacements);

	}

	// #########################################################################
	// puts back a required field in the css array if necessary
	function process_complete_css(&$css, $attr)
	{
		if (isset($css["$attr"]['font']))
		{
			$css["$attr"]['font']['style'] = '';
		}
	}

	// #########################################################################
	// ##################### END STYLE IMPORT FUNCTIONS ########################
	// #########################################################################

	// initialize the array that will hold all the bits of the final insert query
	$templateQuery = array();

	// query headinclude templates for the old styles
	$headinclude = array();
	$hIs = $db->query_read("SELECT templatesetid, template FROM template WHERE title='headinclude'");
	while ($hI = $db->fetch_array($hIs))
	{
		$headinclude["$hI[templatesetid]"] = $hI['template'];
	}

	// query replacement vars for the old styles
	$replacement = array();
	$gRs = $db->query_read("SELECT replacementsetid, findword, replaceword FROM replacement WHERE replacementsetid<>-1");
	while ($gR = $db->fetch_array($gRs))
	{
		$replacement["$gR[replacementsetid]"]["$gR[findword]"] = $gR['replaceword'];
	}

	$i = 0;
	print_form_header('', '');

	// query the new styles and go through translating them
	$styles = $db->query_read("SELECT styleid, title, templatelist AS templatesetid, replacements AS replacementsetid FROM style WHERE styleid > 2 AND css=''");
	$numstyles = $db->num_rows($styles);
	while ($style = $db->fetch_array($styles))
	{
		print_table_header($upgrade_phrases['upgrade_300b3.php']['translate_vb2_style_settings']);
		print_description_row("<!--Style:--> <font size=\"+1\"><b><i>$style[title]</i></b></font>");
		echo '<tr><td class="alt2"><ul>';

		$style = array_merge($style, convert_vb2_style_to_vb3_style($replacement["$style[replacementsetid]"]));

		echo("<li><i>{$upgrade_phrases['upgrade_300b3.php']['add_css_headinclude_to_extra']}</i><ul>");
		if (isset($headinclude["$style[templatesetid]"]))
		{
			if (preg_match('#<style.*>(.*)</style>#siU', $headinclude["$style[templatesetid]"], $regs))
			{
				echo("<li>{$upgrade_phrases['upgrade_300b3.php']['found_css_data']}</li>");
				$style['css']['EXTRA']['all'] = $regs[1];
			}
			else

			{
				echo("<li>({$upgrade_phrases['upgrade_300b3.php']['no_css_data_found']})</li>");
			}
		}
		else
		{
			echo("<li>({$upgrade_phrases['upgrade_300b3.php']['no_headinclude_found']})</li>");
		}
		echo('</ul></li>');

		foreach($style['stylevars'] as $varname => $value)
		{
			$templateQuery[] = "($style[styleid], 'stylevar', '" . $db->escape_string($varname) . "', '" . $db->escape_string($value) . "')";
		}
		foreach($style['css'] as $identifier => $values)
		{
			$templateQuery[] = "($style[styleid], 'css', '" . $db->escape_string($identifier) . "', '" . $db->escape_string(serialize($values)) . "')";
		}
		foreach($style['replacements'] as $findword => $replaceword)
		{
			$templateQuery[] = "($style[styleid], 'replacement', '" . $db->escape_string($findword) . "', '" . $db->escape_string($replaceword) . "')";
		}

		echo '</ul></td></tr>';

		if (++$i < $numstyles)
		{
			print_table_break(' ');
		}

	}

	print_table_footer();

	if (!empty($templateQuery))
	{
		$query[] = "REPLACE INTO template\n\t(styleid, templatetype, title, template)\nVALUES\n\t" . implode(",\n\t", $templateQuery);
		$explain[] = $upgrade_phrases['upgrade_300b3.php']['insert_style_settings'];
	}

	exec_queries(1);

}

// #############################################################################
// Stick old templates into their own styles
if ($vbulletin->GPC['step'] == 35)
{

	$styles = $db->query_read("
		SELECT style.*, style.templatelist AS templatesetid, templateset.title AS templatesetname
		FROM style LEFT JOIN templateset ON(templateset.templatesetid=style.templatelist)
		WHERE css='templateset'
	");
	while ($style = $db->fetch_array($styles))
	{
		$query[] = "UPDATE template SET styleid=$style[styleid] WHERE templatesetid=$style[templatesetid]";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['moving_template_x_to_style_x'], $style['templatesetname'], $style['title']);

	}

	exec_queries(1);

}

// #############################################################################
// Drop the old tables and clean up
if ($vbulletin->GPC['step'] == 36)
{

	$query[] = "DROP TABLE IF EXISTS replacement";
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "replacement");

	$query[] = "DROP TABLE IF EXISTS templateset";
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "templateset");

	$query[] = "DROP TABLE IF EXISTS style_vb2";
	$explain[] = sprintf($vbphrase['remove_table'], TABLE_PREFIX . "style_vb2");

	$query[] = "UPDATE style SET templatelist='', css='', stylevars='', replacements='', displayorder=1";
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "style");

	$query[] = "ALTER TABLE template DROP templatesetid";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "template");

	exec_queries(1);

	$db->hide_errors();
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "template ADD product VARCHAR(15) NOT NULL DEFAULT ''"); // need to make sure we have this
	$db->show_errors();

}

// #############################################################################
// Import master style set file
if ($vbulletin->GPC['step'] == 37)
{
	require_once(DIR . '/includes/adminfunctions_template.php');

	/*if (!($xml = file_read(DIR . '/install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}*/

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	//xml_import_style($xml);
}

// #############################################################################
// Rebuild all styles
if ($vbulletin->GPC['step'] == 38)
{
	require_once(DIR . '/includes/adminfunctions_template.php');

	define('NO_POST_EDITOR_BUILD', true);
	//build_all_styles(0, 1);
}

// #############################################################################
// Insert FAQ entries
if ($vbulletin->GPC['step'] == 39)
{
	// this is a TEMPORARY method of importing FAQ entries
	// until I code up and XML importer for them - Kier

	$query[] = "
		INSERT INTO faq
			(faqname, faqparent, displayorder, volatile)
		VALUES
			('vb_faq', 'faqroot', 100, 1),
			('vb_user_maintain', 'vb_faq', 10, 1),
			('vb_why_register', 'vb_user_maintain', 1, 1),
			('vb_use_cookies', 'vb_user_maintain', 2, 1),
			('vb_clear_cookies', 'vb_user_maintain', 3, 1),
			('vb_update_profile', 'vb_user_maintain', 4, 1),
			('vb_sig_explain', 'vb_user_maintain', 5, 1),
			('vb_lost_password', 'vb_user_maintain', 6, 1),
			('vb_custom_status', 'vb_user_maintain', 7, 1),
			('vb_avatar_how', 'vb_user_maintain', 8, 1),
			('vb_buddy_explain', 'vb_user_maintain', 9, 1),
			('vb_board_usage', 'vb_faq', 20, 1),
			('vb_board_search', 'vb_board_usage', 1, 1),
			('vb_email_member', 'vb_board_usage', 2, 1),
			('vb_pm_explain', 'vb_board_usage', 3, 1),
			('vb_memberlist_how', 'vb_board_usage', 4, 1),
			('vb_calendar_how', 'vb_board_usage', 5, 1),
			('vb_announce_explain', 'vb_board_usage', 6, 1),
			('vb_thread_rate', 'vb_board_usage', 7, 1),
			('vb_referrals_explain', 'vb_board_usage', 8, 1),
			('vb_read_and_post', 'vb_faq', 30, 1),
			('vb_special_codes', 'vb_read_and_post', 1, 1),
			('vb_smilies_explain', 'vb_read_and_post', 2, 1),
			('vb_vbcode_toolbar', 'vb_read_and_post', 3, 1),
			('vb_poll_explain', 'vb_read_and_post', 4, 1),
			('vb_attachment_explain', 'vb_read_and_post', 5, 1),
			('vb_message_icons', 'vb_read_and_post', 6, 1),
			('vb_edit_posts', 'vb_read_and_post', 7, 1),
			('vb_moderator_explain', 'vb_read_and_post', 8, 1),
			('vb_censor_explain', 'vb_read_and_post', 9, 1),
			('vb_email_notification', 'vb_read_and_post', 1, 1)
	";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['importing_faq_entries'];

	exec_queries();
}

// #############################################################################
// Check for semi-colons in usernames
if ($vbulletin->GPC['step'] == 40)
{
	if ($do == 'downloadillegalusers')
	{
		fetch_illegal_usernames(true);
		exit;
	}
	else
	{
		if ($illegals = fetch_illegal_usernames())
		{
			echo "<p>{$upgrade_phrases['upgrade_300b3.php']['follow_users_contain_semicolons']}</p><ul>";
			foreach($illegals as $userid => $username)
			{
				echo "<li><b>" . htmlspecialchars_uni($username) . "</b></li>\n";
			}
			echo "</ul><p>" . sprintf($upgrade_phrases['upgrade_300b3.php']['download_semicolon_users'], "upgrade_300b3.php?step={$vbulletin->GPC['step']}&amp;do=downloadillegalusers") . "</p>";
		}
		else
		{
			echo "<p>{$upgrade_phrases['upgrade_300b3.php']['no_illegal_users_found']}</p>";
		}
	}
}

// #############################################################################
// The End Part 1 - Do things that would cause the admin to no longer be able to run this script.
if ($vbulletin->GPC['step'] == 41)
{
	$db->hide_errors();
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "setting ADD product VARCHAR(15) NOT NULL DEFAULT ''");
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "setting ADD datatype ENUM('free', 'number', 'boolean', 'bitfield', 'username') NOT NULL DEFAULT 'free'");
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "setting ADD blacklist SMALLINT NOT NULL DEFAULT '0'");
	$db->query_write("ALTER TABLE " . TABLE_PREFIX . "settinggroup ADD product VARCHAR(15) NOT NULL DEFAULT ''");
	$db->show_errors();

	// Update options from the vb2 style to the vb3 style but leave them in place
	// until the end so that this script can use them.
	// this should generate $vboptions and stick it in the datastore table
	require_once(DIR . '/includes/adminfunctions_options.php');

	$datastore = $db->query_first("SELECT data FROM datastore WHERE title='options'");
	$vboptions = vb_unserialize($datastore['data']);

	$path = DIR . '/install/vbulletin-settings.xml';
	xml_import_settings();

	echo "<p>{$upgrade_phrases['upgrade_300b3.php']['settings_imported_sucessfully']}</p>";

	// Remove old options template
	$query[] = "DELETE FROM template WHERE title = 'options'";
	$explain[] = $upgrade_phrases['upgrade_300b3.php']['remove_old_settings_storage'];

	$query[] = "ALTER TABLE usergroup DROP cancontrolpanel";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "usergroup");

	$users = $db->query_read("SELECT userid, username, salt, password FROM user WHERE usergroupid = 6");
	while($user = $db->fetch_array($users))
	{
		$query[] = "UPDATE user SET password = '" . $db->escape_string(md5($user['password'] . $user['salt'])) . "' WHERE userid = $user[userid]";
		$explain[] = sprintf($upgrade_phrases['upgrade_300b3.php']['salt_admin_x'], $user['username']);
	}

	exec_queries();

	echo_flush('<p>' . $upgrade_phrases['upgrade_300b3.php']['build_forum_and_usergroup_cache']);
	build_forum_permissions();

	echo_flush($vbphrase['done'] . '</p>');
}

// #############################################################################
// The End Part 2
if ($vbulletin->GPC['step'] == 42)
{
	// update datastore caches and values
	// doesn't matter if these get run multiple times
	build_image_cache('smilie');
	build_image_cache('avatar');
	build_image_cache('icon');
	build_bbcode_cache();
	require_once(DIR . '/includes/functions_databuild.php');
	build_user_statistics();

	?>
	<blockquote>
	<p><?php echo "{$upgrade_phrases['upgrade_300b3.php']['upgrade_complete']}"; ?></p>
	</blockquote>
	<?php

	// tell the print_next_step() function that this script is complete.
	define('SCRIPTCOMPLETE', true);

}

// #############################################################################

print_next_step();
print_upgrade_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 23:08, Mon Jul 17th 2023 : $Revision: 92875 $
|| # $Date: 2017-02-11 09:03:44 -0800 (Sat, 11 Feb 2017) $
|| ####################################################################
\*======================================================================*/
?>
