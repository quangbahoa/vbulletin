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

define('THIS_SCRIPT', 'upgrade1.php');
define('VERSION', '3.0.0 Beta 3');

$phrasegroups = array();
$specialtemplates = array();

// #############################################################################
// array of titles for each step - alter this if you change the contents of a step

// just temporary for my testing purposes - KD
define('VB3UPGRADE', 1);
// require the code that makes it all work...
require_once('./upgradecore.php');
if (TABLE_PREFIX != '')
{
	?>
	<blockquote>
		<p style="font-size:10pt; color: red;"><b><?php echo $upgrade_phrases['upgrade1.php']['tableprefix_not_empty']; ?></b></p>
		<p><?php echo $upgrade_phrases['upgrade1.php']['tableprefix_not_empty_fix']; ?></p>
	</blockquote>
	<?php
	print_upgrade_footer();
	exit;
}
// we need all the new table data
require_once('./install/mysql-schema.php');

// #############################################################################
// introduction
if ($step === 'welcome')
{
	echo "<blockquote>\n";
	echo sprintf($upgrade_phrases['upgrade1.php']['welcome'], $dbname);
	echo "</blockquote>\n";

	if (get_cfg_var('safe_mode') == 1)
	{
		// Use ini_set here instead?
		echo "<p><i>{$upgrade_phrases['upgrade1.php']['safe_mode_warning']}</i></p>";
	}

	// turn the board off
	$DB_site->query("UPDATE setting SET value = 0 WHERE varname = 'bbactive'");
	$DB_site->query("UPDATE template SET template = CONCAT(template,'\n\$bbactive = 0;\n') WHERE title = 'options'");

	// create the upgradelog table - don't worry if this table has already been created
	$DB_site->reporterror = 0;
	$DB_site->query("
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
	$DB_site->reporterror = 1;
}

// #############################################################################
// Create New vBulletin 3 Tables
if ($step == 1)
{
	$DB_site->reporterror = 0;
	$DB_site->query("SELECT COUNT(*) AS count FROM calendar");
	$DB_site->reporterror = 1;
	$errno = $DB_site->errno;
	if (!$errno)
	{
		$errno = 0;
	}

	if ($errno == 0)
	{
		echo "<blockquote>{$upgrade_phrases['upgrade1.php']['upgrade_already_run']}</blockquote>";
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
	options mediumtext NOT NULL,
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
	subfolders mediumtext NOT NULL,
	pmfolders mediumtext NOT NULL,
	buddylist mediumtext NOT NULL,
	ignorelist mediumtext NOT NULL,
	signature mediumtext NOT NULL,
	searchprefs mediumtext NOT NULL,
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

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "post_parsed (
	postid int unsigned NOT NULL DEFAULT '0',
	dateline int unsigned NOT NULL DEFAULT '0',
	hasimages smallint NOT NULL DEFAULT '0',
	pagetext_html mediumtext NOT NULL,
	PRIMARY KEY (postid),
	KEY dateline (dateline)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "post_parsed");

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
	message mediumtext NOT NULL,
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
	profilepicdata mediumtext NOT NULL,
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
	forumchoice mediumtext NOT NULL,
	sortby varchar(200) NOT NULL DEFAULT '',
	sortorder varchar(4) NOT NULL DEFAULT '',
	searchtime float NOT NULL DEFAULT '0',
	showposts smallint unsigned NOT NULL DEFAULT '0',
	orderedids mediumtext NOT NULL,
	dateline int unsigned NOT NULL DEFAULT '0',
	searchterms mediumtext NOT NULL,
	displayterms mediumtext NOT NULL,
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
	phrasegroup_global mediumtext NOT NULL,
	phrasegroup_cpglobal mediumtext NOT NULL,
	phrasegroup_cppermission mediumtext NOT NULL,
	phrasegroup_forum mediumtext NOT NULL,
	phrasegroup_calendar mediumtext NOT NULL,
	phrasegroup_attachment_image mediumtext NOT NULL,
	phrasegroup_style mediumtext NOT NULL,
	phrasegroup_logging mediumtext NOT NULL,
	phrasegroup_cphome mediumtext NOT NULL,
	phrasegroup_promotion mediumtext NOT NULL,
	phrasegroup_user mediumtext NOT NULL,
	phrasegroup_help_faq mediumtext NOT NULL,
	phrasegroup_sql mediumtext NOT NULL,
	phrasegroup_subscription mediumtext NOT NULL,
	phrasegroup_language mediumtext NOT NULL,
	phrasegroup_bbcode mediumtext NOT NULL,
	phrasegroup_stats mediumtext NOT NULL,
	phrasegroup_diagnostic mediumtext NOT NULL,
	phrasegroup_maintenance mediumtext NOT NULL,
	phrasegroup_profilefield mediumtext NOT NULL,
	phrasegroup_thread mediumtext NOT NULL,
	phrasegroup_timezone mediumtext NOT NULL,
	phrasegroup_banning mediumtext NOT NULL,
	phrasegroup_reputation mediumtext NOT NULL,
	phrasegroup_wol mediumtext NOT NULL,
	phrasegroup_threadmanage mediumtext NOT NULL,
	phrasegroup_pm mediumtext NOT NULL,
	phrasegroup_cpuser mediumtext NOT NULL,
	phrasegroup_accessmask MEDIUMTEXT NOT NULL,
	phrasegroup_cron MEDIUMTEXT NOT NULL,
	phrasegroup_moderator MEDIUMTEXT NOT NULL,
	phrasegroup_cpoption MEDIUMTEXT NOT NULL,
	phrasegroup_cprank MEDIUMTEXT NOT NULL,
	phrasegroup_cpusergroup MEDIUMTEXT NOT NULL,
	phrasegroup_holiday MEDIUMTEXT NOT NULL,
	phrasegroup_posting mediumtext NOT NULL,
	phrasegroup_poll mediumtext NOT NULL,
	phrasegroup_fronthelp mediumtext NOT NULL,
	phrasegroup_register mediumtext NOT NULL,
	phrasegroup_search mediumtext NOT NULL,
	phrasegroup_showthread mediumtext NOT NULL,
	phrasegroup_postbit mediumtext NOT NULL,
	phrasegroup_forumdisplay mediumtext NOT NULL,
	phrasegroup_messaging mediumtext NOT NULL,
	PRIMARY KEY  (languageid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "language");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "phrase (
	phraseid int unsigned NOT NULL auto_increment,
	languageid smallint NOT NULL DEFAULT '0',
	varname varchar(250) NOT NULL DEFAULT '',
	text mediumtext NOT NULL,
	phrasetypeid smallint unsigned NOT NULL DEFAULT '0',
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
	PRIMARY KEY (phrasetypeid)
)
";
	$explain[] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "phrasetype");

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "mailqueue (
	mailqueueid int unsigned NOT NULL auto_increment,
	dateline int unsigned NOT NULL DEFAULT '0',
	toemail mediumtext NOT NULL,
	subject mediumtext NOT NULL,
	message mediumtext NOT NULL,
	header mediumtext NOT NULL,
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
	description mediumtext NOT NULL,
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
	text mediumtext NOT NULL,
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
	title char(15) NOT NULL DEFAULT '',
	data mediumtext NOT NULL,
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
	forums mediumtext NOT NULL,
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
	reason varchar(250) DEFAULT NULL,
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
	level varchar(250) DEFAULT NULL,
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
	message mediumtext NOT NULL,
	touserarray mediumtext NOT NULL,
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
	$DB_site->reporterror = 0;
	$DB_site->query("
CREATE TABLE " . TABLE_PREFIX . "regimage (
	regimagehash char(32) NOT NULL DEFAULT '',
	imagestamp char(6) NOT NULL DEFAULT '',
	dateline int unsigned NOT NULL DEFAULT '0',
	KEY regimagehash (regimagehash, dateline)
)
");
	$DB_site->reporterror = 1;

}

// #############################################################################
// Update Templates - Do ALTER command in small steps as some servers bomb out if you specify too much
if ($step == 2)
{
	if ($maxloggedin = $DB_site->query_first("SELECT template FROM template WHERE templatesetid = -2 AND title = 'maxloggedin'"))
	{
		$maxusers = explode(' ', $maxloggedin['template']);
		$maxtemp['maxonline'] = $maxusers[0];
		$maxtemp['maxonlinedate'] = $maxusers[1];
		$template = serialize($maxtemp);
		$query[] = "INSERT INTO datastore (title, data) VALUES ('maxloggedin', '" . addslashes($template) . "')";
		$explain[] = $upgrade_phrases['upgrade1.php']['moving_maxloggedin_datastore'];
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
			'display' => iif($extension == 'txt', 2, 0)
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
			('attachmentcache', '" . addslashes($attachmentcache) . "'),
			('banemail', '" . addslashes($banemail) . "'),
			('eventcache', 'a:0:{}'),
			('usergroupcache', 'a:0:{}'),
			('forumcache', 'a:0:{}')
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['new_datastore_values'];

	$query[] = "DELETE FROM template WHERE templatesetid = -2";
	$explain[] = $upgrade_phrases['upgrade1.php']['removing_special_templates'];

	$query[] = "DELETE FROM privatemessage WHERE userid = 0";
	$explain[] = $upgrade_phrases['upgrade1.php']['removing_orphan_pms'];

	exec_queries();
}

// #############################################################################
// Calendar Updates
if ($step == 3)
{
	$query[] = "ALTER TABLE calendar_events RENAME event";
	$explain[] = $upgrade_phrases['upgrade1.php']['rename_calendar_events'];

	$query[] = "ALTER TABLE event
		ADD calendarid INT UNSIGNED NOT NULL ,
		ADD recurring SMALLINT NOT NULL ,
		ADD recuroption VARCHAR(6) NOT NULL ";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'event', 1, 5);

	$query[] = "ALTER TABLE event
		ADD dateline_from INT UNSIGNED NOT NULL ,
		ADD dateline_to INT UNSIGNED NOT NULL ";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'event', 2, 5);

	$query[] = "ALTER TABLE event
		ADD visible SMALLINT DEFAULT 1 NOT NULL,
		ADD utc SMALLINT NOT NULL";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'event', 3, 5);

	$query[] = "ALTER TABLE event
		ADD dateline INT UNSIGNED NOT NULL ,
		ADD INDEX daterange (calendarid,visible,dateline_from,dateline_to)
		";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'event', 4, 5);

	$query[] = "ALTER TABLE event ADD customfields MEDIUMTEXT NOT NULL";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'event', 5, 5);

	exec_queries();

	// Convert dates to new style
	$events = $DB_site->query("SELECT eventid, eventdate FROM event");
	while ($event = $DB_site->fetch_array($events))
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
		$DB_site->query("
			UPDATE event
			SET	dateline_from = $from_timestamp,
				dateline_to = $to_timestamp
			WHERE eventid = $event[eventid]
		");
	}

	$query[] = "ALTER TABLE event
				DROP eventdate";
	$explain[] = $upgrade_phrases['upgrade1.php']['droping_event_date'];

	$query[] = "ALTER TABLE event
				CHANGE subject title VARCHAR(254) NOT NULL";
	$explain[] = $upgrade_phrases['upgrade1.php']['changing_subject_to_title'];

	// Create a private and public calendar

	$query[] = "INSERT INTO calendar (title, description, displayorder, startofweek, options, cutoff, eventcount, birthdaycount)
			VALUES ('{$upgrade_phrases['upgrade1.php']['public']}', '{$upgrade_phrases['upgrade1.php']['public_calendar']}', 1, 1, 119, 15, 5, 2)";
	$explain[] = $upgrade_phrases['upgrade1.php']['creating_pub_calendar'];

	$query[] = "INSERT INTO calendar (title, description, displayorder, startofweek, options, cutoff, eventcount, birthdaycount)
			VALUES ('{$upgrade_phrases['upgrade1.php']['private']}', '{$upgrade_phrases['upgrade1.php']['private_calendar']}', 2, 1, 118, 15, 5, 2)";
	$explain[] = $upgrade_phrases['upgrade1.php']['creating_priv_calendar'];

	$query[] = 'UPDATE event SET calendarid = 1 WHERE public = 1';
	$explain[] = $upgrade_phrases['upgrade1.php']['moving_pub_events'];

	$query[] = 'UPDATE event SET calendarid = 2 WHERE public = 0';
	$explain[] = $upgrade_phrases['upgrade1.php']['moving_priv_events'];

	$query[] = 'ALTER TABLE event DROP public';
	$explain[] = $upgrade_phrases['upgrade1.php']['drop_public_field'];

	exec_queries();
}

// #############################################################################
// Alter Forum Table
if ($step == 4)
{
	// Try to delete some fields that vB3 needs but seem popular as hacks in vB2
	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE forum DROP lastthread");
	$DB_site->query("ALTER TABLE forum DROP lastthreadid");
	$DB_site->reporterror = 1;

	$query[] = "ALTER TABLE forum
			ADD lastthread CHAR(250) NOT NULL,
			ADD lastthreadid INT UNSIGNED NOT NULL,
			ADD password CHAR(50) NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'forum', 1, 5);

	$query[] = "ALTER TABLE forum
			ADD canhavepassword SMALLINT DEFAULT 1 NOT NULL,
			ADD link CHAR(200) NOT NULL,
			ADD indexposts SMALLINT DEFAULT 1 NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'forum', 2, 5);

	$query[] = "ALTER TABLE forum
			CHANGE description description CHAR(250) NOT NULL,
			CHANGE lastposter lastposter CHAR(50) NOT NULL,
			CHANGE newpostemail newpostemail CHAR(250) NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'forum', 3, 5);

	$query[] = "ALTER TABLE forum
			CHANGE title title CHAR(100) NOT NULL,
			CHANGE newthreademail newthreademail CHAR(250) NOT NULL,
			CHANGE parentlist parentlist CHAR(250) NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'forum', 4, 5);

	$query[] = "ALTER TABLE forum
			ADD lasticonid SMALLINT NOT NULL,
			ADD options INT UNSIGNED NOT NULL,
			ADD childlist CHAR(250) NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'forum', 5, 5);

	$query[] = "
		UPDATE forum SET
			options =
				IF(active, $_FORUMOPTIONS[active], 0) +
				IF(allowposting, $_FORUMOPTIONS[allowposting] ,0) +
				IF(cancontainthreads, $_FORUMOPTIONS[cancontainthreads], 0) +
				IF(moderatenew, $_FORUMOPTIONS[moderatenewpost], 0) +
				IF(moderatenew, $_FORUMOPTIONS[moderatenewthread], 0) +
				IF(moderateattach, $_FORUMOPTIONS[moderateattach], 0) +
				IF(allowbbcode, $_FORUMOPTIONS[allowbbcode], 0) +
				IF(allowimages, $_FORUMOPTIONS[allowimages], 0) +
				IF(allowhtml, $_FORUMOPTIONS[allowhtml], 0) +
				IF(allowsmilies, $_FORUMOPTIONS[allowsmilies], 0) +
				IF(allowicons, $_FORUMOPTIONS[allowicons], 0) +
				IF(allowratings, $_FORUMOPTIONS[allowratings], 0) +
				IF(countposts, $_FORUMOPTIONS[countposts], 0) +
				IF(canhavepassword, $_FORUMOPTIONS[canhavepassword], 0) +
				IF(indexposts, $_FORUMOPTIONS[indexposts], 0) +
				IF(styleoverride, $_FORUMOPTIONS[styleoverride], 0)
				+ $_FORUMOPTIONS[showonforumjump]
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['convert_forum_options'];

	$query[] = "ALTER TABLE forum
			DROP active,
			DROP allowposting,
			DROP cancontainthreads
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['dropping_option_fields'], 1, 5);

	$query[] = "ALTER TABLE forum
			DROP moderatenew,
			DROP moderateattach,
			DROP allowbbcode
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['dropping_option_fields'], 2, 5);

	$query[] = "ALTER TABLE forum
			DROP allowimages,
			DROP allowhtml,
			DROP allowsmilies
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['dropping_option_fields'], 3, 5);

	$query[] = "ALTER TABLE forum
			DROP allowicons,
			DROP allowratings,
			DROP countposts
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['dropping_option_fields'], 4, 5);

	$query[] = "ALTER TABLE forum
			DROP canhavepassword,
			DROP indexposts,
			DROP styleoverride
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['dropping_option_fields'], 5, 5);

	$query[] = "UPDATE forum SET styleid = 0";
	$explain[] = $upgrade_phrases['upgrade1.php']['resetting_styleids'];

	exec_queries();

	echo_flush("<p>{$upgrade_phrases['upgrade1.php']['updating_forum_child_lists']}</p>");
	build_forum_child_lists();
}

// #############################################################################
// drop privatemessages table and prepare user table for new pms
if ($step == 5)
{
	$DB_site->query("UPDATE forum SET lastpost=0, lastposter=''");
	$forums = $DB_site->query("SELECT forumid, title FROM forum ORDER BY forumid DESC");

	echo "<ul>\n";

	while ($forum = $DB_site->fetch_array($forums))
	{
		// update forum counters
		echo_flush("<li>" . sprintf($upgrade_phrases['upgrade1.php']['updating_counters_for_x'], $forum['title']) . "</li>\n");
		require_once('./includes/functions_databuild.php');
		build_forum_counters($forum['forumid']);

		$thread = $DB_site->query_first("
			SELECT * FROM thread
			WHERE forumid = $forum[forumid]
			ORDER BY lastpost DESC
			LIMIT 1
		");

		$query[] = "
			UPDATE forum SET
				lastpost = " . intval($thread['lastpost']) . ",
				lastposter = '" . addslashes($thread['lastposter']) . "',
				lastthread = '" . addslashes($thread['title']) . "',
				lastthreadid = " . intval($thread['threadid']) . ",
				lasticonid = " . intval($thread['iconid']) . "
			WHERE forumid = " . intval($thread['forumid']) . "
		";
		$explain[] = sprintf($upgrade_phrases['upgrade1.php']['updating_lastpost_info_for_x'], $forum['title']);
	}

	echo "</ul>\n";

	exec_queries();

}

// #############################################################################
// Convert Private Messages
if ($step == 6)
{
	$perpage = 1000;
	$count = $DB_site->query_first("SELECT COUNT(*) AS total FROM privatemessage");

	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade1.php']['converting_priv_msg_x'], construct_upgrade_page_hint($count['total'], $startat, $perpage)) . "</p>\n");

	// query a batch of private messages
	$getpms = $DB_site->query("
		SELECT
			privatemessage.*,
			IF(ISNULL(touser.username), '[{$upgrade_phrases['upgrade1.php']['deleted_user']}]', touser.username) AS tousername,
			IF(ISNULL(fromuser.username), '[{$upgrade_phrases['upgrade1.php']['deleted_user']}]', fromuser.username) AS fromusername
		FROM privatemessage
		LEFT JOIN user AS touser ON(touser.userid = privatemessage.touserid)
		LEFT JOIN user AS fromuser ON(fromuser.userid = privatemessage.fromuserid)
		LIMIT $startat, $perpage
	");

	// check to see if we have some results...
	if ($DB_site->num_rows($getpms))
	{
		// populate our $pms array with the SQL results
		$pms = array();
		$receiptSql = array();
		while ($getpm = $DB_site->fetch_array($getpms))
		{
			$pms[] = $getpm;
		}
		unset($getpm);
		$DB_site->free_result($getpms);

		// get last inserted pm text
		if ($pmText = $DB_site->query_first("SELECT * FROM pmtext ORDER BY pmtextid DESC LIMIT 1"))
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
				echo_flush("<li>" . sprintf($upgrade_phrases['upgrade1.php']['insert_priv_msg_txt_from_x'], $pm['fromusername']) . "</li>\n");
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
				$DB_site->query(fetch_query_sql($pmText, 'pmtext'));
				$pmText['pmtextid'] = $DB_site->insert_id();
			}

			// insert the private message pointers
			echo_flush("<li>" . sprintf($upgrade_phrases['upgrade1.php']['insert_priv_msg_from_x_to_x'], $pm['fromusername'], $pm['tousername']) . "</li>\n");
			$newpm = array(
				'pmtextid' => $pmText['pmtextid'],
				'userid' => $pm['userid'],
				'folderid' => $pm['folderid'],
				'messageread' => $pm['messageread']
			);
			$DB_site->query(fetch_query_sql($newpm, 'pm'));
			$pmid = $DB_site->insert_id();

			// check if we should insert a receipt
			if ($pm['receipt'] != 0)
			{
				// add to the $receiptSql array
				$receiptSql[] = "($pmid, $pm[fromuserid], $pm[touserid], '" . addslashes($pm['tousername']) . "', '" . addslashes($pm['title']) . "', $pm[dateline], $pm[readtime])";
			}

			if ($i > 1)
			{
				// look at the two values as both may exist
				if ($pm['tousername'] == '[Deleted User]' OR $pm['tousername'] == "[{$upgrade_phrases['upgrade1.php']['deleted_user']}]")
				{
					// don't bother updating the touserarray to include this user name
				}
				else
				{
					$tousers = unserialize($pmText['touserarray']);
					$tousers["$pm[touserid]"] = $pm['tousername'];
					$tousers = serialize($tousers);
					if ($pmText['touserarray'] != $tousers)
					{
						// update the pmtext record to reflect the multiple recipients
						$pmText['touserarray'] = $tousers;
						echo_flush("<li>{$upgrade_phrases['upgrade1.php']['update_priv_msg_multiple_recip']}</li>\n");
						$DB_site->query("UPDATE pmtext SET touserarray = '" . addslashes($pmText['touserarray']) . "'\nWHERE pmtextid = $pmText[pmtextid]");
					}
				}
			}
		}

		if (!empty($receiptSql))
		{
			echo_flush("<li>{$upgrade_phrases['upgrade1.php']['insert_priv_msg_receipts']}</li>\n");
			$receiptSql = "INSERT INTO pmreceipt\n\t(pmid, userid, touserid, tousername, title, sendtime, readtime)\nVALUES\n\t" . implode(",\n\t", $receiptSql);
			$DB_site->query($receiptSql);
		}

		echo "</ul>\n";

		print_next_page();
	}
	else
	{
		$query[] = "DROP TABLE privatemessage";
		$explain[] = $upgrade_phrases['upgrade1.php']['dropping_vb2_pm_table'];

		$query[] = "ALTER TABLE user
			ADD pmtotal SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
			ADD pmunread SMALLINT UNSIGNED DEFAULT 0 NOT NULL";
		$explain[] = $upgrade_phrases['upgrade1.php']['alter_user_table_for_vb3_pm'];

		$query[] = "ALTER TABLE user
			CHANGE password password CHAR(32) NOT NULL,
			ADD passworddate DATE NOT NULL,
			ADD salt CHAR(3) NOT NULL";
		$explain[] = $upgrade_phrases['upgrade1.php']['alter_user_table_vb3_password'];

		exec_queries();

		echo "<p>{$upgrade_phrases['upgrade1.php']['priv_msg_import_complete']}</p>";
	}
}

// #############################################################################
// Upgrade pmfolders, pmtotals and insert usertextfield entries
if ($step == 7)
{
	$perpage = 1000;
	$maxuser = $DB_site->query_first("SELECT MAX(userid) AS userid FROM user");

	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade1.php']['upgrading_users_x'], construct_upgrade_page_hint($maxuser['userid'], $startat, $perpage)) . "<br />\n");

	if ($startat <= $maxuser['userid'])
	{
		$endat = $startat + $perpage;

		// Copy the textfields from the user table to the new table then remove them
		$users = $DB_site->query("
			SELECT
				user.userid, user.username, user.pmfolders, user.buddylist, user.ignorelist,
				user.signature, user.password, user.usergroupid,
				COUNT(pmid) AS pmtotal,
				SUM(IF(messageread = 0, 1, 0)) AS pmunread
			FROM user
			LEFT JOIN pm USING(userid)
			WHERE user.userid > $startat
			AND user.userid <= $endat
			GROUP BY user.userid
		");
		$batchnum = $DB_site->num_rows($users);
		echo_flush(sprintf($upgrade_phrases['upgrade1.php']['found_x_users'], $batchnum) . "</p>\n");
		$sql = array();

		require_once('./includes/functions_user.php');
		while ($user = $DB_site->fetch_array($users))
		{
			$salt = fetch_user_salt(3);
			// update user table with new private message totals fields and salted password - only salt passwords for non-admins
			$query[] = "
				UPDATE user SET
					salt = '" . addslashes($salt) . "',
					" . iif($user['usergroupid'] != 6, "password = '" . addslashes(md5($user['password'] . $salt)) . "',") . "
					pmtotal = $user[pmtotal],
					pmunread = $user[pmunread]
				WHERE userid = $user[userid]
			";
			$explain[] = sprintf($upgrade_phrases['upgrade1.php']['updating_priv_messages_for_x'], $user['username']);

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
			$sql[] = "($user[userid], '" . iif(empty($pmfolders), '', addslashes(serialize($pmfolders))) . "', '" . addslashes($user['ignorelist']) . "', '" . addslashes($user['buddylist']) . "', '" . addslashes($user['signature']) . "')";
		}

		if (!empty($sql))
		{
			$query[] = "INSERT INTO usertextfield (userid, pmfolders, ignorelist, buddylist, signature) VALUES " . implode(', ', $sql);
			$explain[] = $upgrade_phrases['upgrade1.php']['inserting_user_details_usertextfield'];
		}

		exec_queries();
		print_next_page();
	}
	else
	{
		// The above query leave many users without an entry in the usertextfield table so this fixes that since I don't
		// have time to mess with the above.

		$users = $DB_site->query("
			SELECT user.userid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield USING(userid)
			WHERE usertextfield.userid IS NULL
		");

		$userids = array();
		while ($user = $DB_site->fetch_array($users))
		{
			$userids[] = $user['userid'];
		}

		if (!empty($userids))
		{
			$DB_site->query("INSERT INTO usertextfield (userid) VALUES (" . implode( '),(' , $userids ) . ")");
		}

		echo_flush('</p><p>' . $upgrade_phrases['upgrade1.php']['user_upgrades_complete'] . '</p>');
	}

}

// #############################################################################
// Alter User Table #1
if ($step == 8)
{
	// common hack-related fields we want to deal with in order to prevent DB errors
	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE user DROP avatarrevision");
	$DB_site->query("ALTER TABLE user ADD showvbcode SMALLINT UNSIGNED NOT NULL DEFAULT 0");
	$DB_site->query("ALTER TABLE user ADD msn CHAR(100) NOT NULL");
	$DB_site->reporterror = 1;

	// now change those hack-related fields for those users who already had them
	$query[] = "ALTER TABLE user
		ADD avatarrevision INT UNSIGNED NOT NULL DEFAULT 0,
		CHANGE showvbcode showvbcode SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		CHANGE msn msn CHAR(100) NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 1, 7);

	// add membergroup / displaygroup fields
	$query[] = "ALTER TABLE user
		ADD membergroupids CHAR(250) NOT NULL,
		ADD displaygroupid SMALLINT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 2, 7);

	// add reputation fields
	$query[] = "ALTER TABLE user
		ADD reputation INT NOT NULL DEFAULT 10,
		ADD reputationlevelid INT UNSIGNED NOT NULL DEFAULT 1,
		ADD showreputation SMALLINT UNSIGNED NOT NULL DEFAULT 1
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 3, 7);

	// add misc fields
	$query[] = "ALTER TABLE user
		ADD languageid SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		ADD threadedmode SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		ADD emailstamp INT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 4, 7);

	// drop fields
	$query[] = "ALTER TABLE user
		DROP nosessionhash,
		DROP cookieuser,
		DROP inforum
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 5, 7);

	// drop fields
	$query[] = "ALTER TABLE user
		DROP pmfolders,
		DROP ignorelist,
		DROP buddylist
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 6, 7);

	// drop signature and change 'options'...
	$query[] = "ALTER TABLE user
		DROP signature,
		CHANGE options options INT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 7, 7);

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

	$uopt = &$_USEROPTIONS;
	$query[] = "UPDATE user SET
		showvbcode = IF(options & 8, 1, 0),
		options = 0
			+ IF(options & $uopt[showsignatures], $uopt[showsignatures], 0)
			+ IF(options & $uopt[showavatars], $uopt[showavatars], 0)
			+ IF(options & $uopt[showimages], $uopt[showimages], 0)
			+ IF(coppauser, $uopt[coppauser], 0)
			+ IF(adminemail, $uopt[adminemail], 0)
			+ IF(showemail, $uopt[showemail], 0)
			+ IF(invisible, $uopt[invisible], 0)
			+ IF(showreputation, $uopt[showreputation], 0)
			+ IF(receivepm, $uopt[receivepm], 0)
			+ IF(emailonpm, $uopt[emailonpm], 0)
			+ IF(emailnotification,	16384, 0)
			+ $uopt[dstauto]
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_user_table_options'];

	// drop fields converted to bitfield 'options'
	$query[] = "ALTER TABLE user
		DROP coppauser,
		DROP adminemail,
		DROP showemail
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['drop_user_option_fields'], 1);

	// drop fields converted to bitfield 'options'
	$query[] = "ALTER TABLE user
		DROP invisible,
		DROP showreputation,
		DROP receivepm
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['drop_user_option_fields'], 2);

	// drop fields converted to bitfield 'options'
	$query[] = "ALTER TABLE user
		DROP emailonpm,
		DROP emailnotification
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['drop_user_option_fields'], 3);

	// get userids who have access masks and update
	$accessmasks = $DB_site->query("
		SELECT DISTINCT access.userid, user.username
		FROM access
		INNER JOIN user USING(userid)
	");
	while ($access = $DB_site->fetch_array($accessmasks))
	{
		$updateuserids .= ',' . $access['userid'];
	}
	$query[] = "
		UPDATE user SET
			options = (options + $_USEROPTIONS[hasaccessmask])
		WHERE userid IN(0$updateuserids)
		AND NOT (options & $_USEROPTIONS[hasaccessmask])
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['update_access_masks'];

	exec_queries();

}

// #############################################################################
// Alter User Table #2
if ($step == 9)
{
	$query[] = "ALTER TABLE user
		CHANGE birthday birthday VARCHAR(10) DEFAULT '0000-00-00' NOT NULL,
		CHANGE posts posts INT UNSIGNED  NOT NULL,
		ADD INDEX(birthday)
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 1, 5);

	$query[] = "ALTER TABLE user
		CHANGE username username CHAR(50) NOT NULL,
		CHANGE password password CHAR(32) NOT NULL,
		CHANGE options options INT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 2, 5);

	$query[] = "ALTER TABLE user
		CHANGE email email CHAR(50) NOT NULL,
		CHANGE parentemail parentemail CHAR(50) NOT NULL,
		CHANGE homepage homepage CHAR(100) NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 3, 5);

	$query[] = "ALTER TABLE user
		CHANGE icq icq CHAR(20) NOT NULL,
		CHANGE aim aim CHAR(20) NOT NULL,
		CHANGE yahoo yahoo CHAR(20) NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 4, 5);

	$query[] = "ALTER TABLE user
		CHANGE usertitle usertitle CHAR( 250 ) NOT NULL,
		CHANGE timezoneoffset timezoneoffset CHAR( 4 ) NOT NULL,
		CHANGE ipaddress ipaddress CHAR( 15 ) NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'user', 5, 5);

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
	$explain[] = $upgrade_phrases['upgrade1.php']['convert_new_birthday_format'];

	$query[] = "
		INSERT INTO administrator (userid, adminpermissions)
		SELECT user.userid, " . (array_sum($_BITFIELD['usergroup']['adminpermissions']) - 3) . "
		FROM user INNER JOIN usergroup USING(usergroupid)
		WHERE usergroup.cancontrolpanel = 1
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['insert_admin_perms_admin_table'];

	exec_queries();
}

// #############################################################################
// Announcements
if ($step == 10)
{
	$query[] = "ALTER TABLE announcement
			ADD pagehtml MEDIUMTEXT NOT NULL,
			ADD allowhtml SMALLINT UNSIGNED NOT NULL,
			ADD views INT UNSIGNED NOT NULL
			";
	$explain[] = sprintf($vbphrase['alter_table'], 'announcement');

	exec_queries();

	// run code to update old announcements
	$ans = $DB_site->query('SELECT announcementid,pagetext,title FROM announcement');
	echo "<p>{$upgrade_phrases['upgrade1.php']['updating_announcements']}</p><ul>\n";
	while ($an = $DB_site->fetch_array($ans))
	{
		echo "<li>" . sprintf($upgrade_phrases['upgrade1.php']['announcement_x'], htmlspecialchars($an['title'])) . " ... \n";
		$DB_site->query("UPDATE announcement SET pagehtml='" . addslashes($an['pagetext']) . "',allowhtml=1 WHERE announcementid=" . intval($an['announcementid']));
		echo "{$vbphrase['done']}</li>\n";
		flush();
	}
	echo "</ul>\n";
}

// #############################################################################
// Image Tables
if ($step == 11)
{
	$DB_site->reporterror = 0;
	$DB_site->query('ALTER TABLE avatar DROP INDEX title');
	$DB_site->reporterror = 1;

	$query[] = "ALTER TABLE avatar
			ADD imagecategoryid SMALLINT UNSIGNED NOT NULL,
			ADD displayorder SMALLINT UNSIGNED DEFAULT 1 NOT NULL
			";
	$explain[] = sprintf($vbphrase['alter_table'], 'avatar');

	$query[] = "ALTER TABLE avatar ADD INDEX avatarind (minimumposts,title)";
	$explain[] = $upgrade_phrases['upgrade1.php']['add_index_avatar_table'];

	$query[] = "ALTER TABLE icon
			ADD imagecategoryid SMALLINT UNSIGNED NOT NULL,
			ADD displayorder SMALLINT UNSIGNED DEFAULT 1 NOT NULL
			";
	$explain[] = sprintf($vbphrase['alter_table'], 'icon');

	$query[] = "ALTER TABLE smilie
			ADD imagecategoryid SMALLINT UNSIGNED NOT NULL,
			ADD displayorder SMALLINT UNSIGNED DEFAULT 1 NOT NULL
			";
	$explain[] = sprintf($vbphrase['alter_table'], 'smilie');

	// code to add categorisation to avatars/icons/smilies

	// create a category for avatars
	$avatarresult = $DB_site->query("INSERT INTO imagecategory (title,imagetype,displayorder)
		VALUES ('{$upgrade_phrases['upgrade1.php']['standard_avatars']}', 1, 1)");
	$avatarcategoryid = $DB_site->insert_id($avatarresult);
	$query[] = "UPDATE avatar SET imagecategoryid = $avatarcategoryid";
	$explain[] = $upgrade_phrases['upgrade1.php']['move_avatars_to_category'];

	// create a category for icons
	$iconresult = $DB_site->query("INSERT INTO imagecategory (title,imagetype,displayorder)
		VALUES ('{$upgrade_phrases['upgrade1.php']['standard_icons']}', 2, 1)");
	$iconcategoryid = $DB_site->insert_id($iconresult);
	$query[] = "UPDATE icon SET imagecategoryid = $iconcategoryid";
	$explain[] = $upgrade_phrases['upgrade1.php']['move_icons_to_category'];

	// create a category for smilies
	$smilieresult = $DB_site->query("INSERT INTO imagecategory (title,imagetype,displayorder)
		VALUES ('{$upgrade_phrases['upgrade1.php']['standard_smilies']}', 3, 1)");
	$smiliecategoryid = $DB_site->insert_id($smilieresult);
	$query[] = "UPDATE smilie SET imagecategoryid = $smiliecategoryid";
	$explain[] = $upgrade_phrases['upgrade1.php']['move_smilies_to_category'];


	//code to change the way avatars are displayed:

	$checks = $DB_site->query("SELECT varname,value FROM setting WHERE varname='numavatarswide' OR varname='numavatarshigh'");
	while ($check = $DB_site->fetch_array($checks))
	{
		$opt["$check[varname]"] = $opt['value'];
	}
	$avatarsperpage = $opt['numavatarshigh'] * $opt['numavatarswide'];
	$query[] = "UPDATE setting SET title='{$upgrade_phrases['upgrade1.php']['avatar_setting_title']}',varname='numavatarsperpage',value=".intval($avatarsperpage).",description='{$upgrade_phrases['upgrade1.php']['avatar_setting_desc']}',optioncode='',displayorder='9' WHERE varname='numavatarshigh'";
	$explain[] = $upgrade_phrases['upgrade1.php']['update_avatars_per_page'];

	// Do we need to remove the 'numavatarswide' option??

	exec_queries();
}

// #############################################################################
// Attachments #1
if ($step == 12)
{
	$query[] = "
		ALTER TABLE attachment
		ADD postid INT UNSIGNED NOT NULL,
		ADD filesize INT UNSIGNED NOT NULL,
		ADD thumbnail MEDIUMTEXT NOT NULL";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'attachment', 1, 3);

	$query[] = "
			ALTER TABLE attachment
			ADD filehash CHAR(32) NOT NULL,
			ADD posthash CHAR(32) NOT NULL,
			ADD INDEX (posthash, userid)";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'attachment', 2, 3);

	$query[] = "
			ALTER TABLE attachment
			ADD INDEX (postid),
			ADD INDEX (filesize),
			ADD INDEX (filehash)";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'attachment', 3, 3);

	exec_queries();
}

// #############################################################################
// Attachments #2
if ($step == 13)
{
	$perpage = 500;

	$mysqlv = $DB_site->query_first("SELECT VERSION() AS version");

	echo_flush("<p>{$upgrade_phrases['upgrade1.php']['updating_attachments']}</p><ul>\n");
	$attachments = $DB_site->query("
		SELECT post.attachmentid, post.postid, attachment.postid AS attachment_postid
		" . iif($mysqlv['version'] < '3.23.2', ', attachment.filedata', '') . "
		FROM post
		LEFT JOIN attachment ON (attachment.attachmentid = post.attachmentid)
		WHERE post.attachmentid > 0
		ORDER BY post.attachmentid DESC
		LIMIT $startat, $perpage
	");
	$postarray = array();
	while ($thisattach = $DB_site->fetch_array($attachments))
	{
		$notdone = true;
		echo "<li>" . sprintf($upgrade_phrases['upgrade1.php']['attachment_x'], $thisattach['attachmentid']) . " ... \n";

		if ($thisattach['attachment_postid'] OR $postarray["$thisattach[attachmentid]"])
		{
			// if attachment_postid is already set then that indicates that this attachment is already parented and we need to create a new entry
			// this should not happen all that often so take that approach and query again for the needed information
			$postid = $thisattach['postid'];
			$postarray["$thisattach[attachmentid]"] = 1;
			if ($thisattach = $DB_site->query_first("
				SELECT *
				FROM attachment
				WHERE attachmentid = $thisattach[attachmentid]
			"))
			{
				$DB_site->query("
					INSERT INTO attachment
					(postid, filesize, filehash, userid, dateline, filename, filedata, visible, counter)
					VALUES
					($postid,
					" . strlen($thisattach['filedata']) . ",
					'" . addslashes(md5($thisattach['filedata'])) . "',
					$thisattach[userid],
					$thisattach[dateline],
					'" . addslashes($thisattach['filename']) . "',
					'" . $DB_site->escape_string($thisattach['filedata']) . "',
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
			$DB_site->query("
				UPDATE attachment
				SET
					postid = $thisattach[postid],
					filesize = LENGTH(filedata),
				filehash = " . iif($mysqlv['version'] < '3.23.2', "'" . addslashes(md5($thisattach['filedata'])) . "'",  'MD5(filedata)') . "
			WHERE attachmentid = $thisattach[attachmentid]");
			echo "{$vbphrase['done']}</li>\n";
		}
		flush();
	}
	echo "</ul>\n";

	if ($notdone)
	{
		print_next_page();
	}
}

// #############################################################################
// Attachments #3
if ($step == 14)
{

	$query[] = "
		DELETE FROM attachment
		WHERE postid = 0";
	$explain[] = $upgrade_phrases['upgrade1.php']['remove_orphan_attachments'];

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
	$explain[] = $upgrade_phrases['upgrade1.php']['populating_attachmenttype_table'];

	exec_queries();
}

// #############################################################################
// Edit Post Log
if ($step == 15)
{
	$perpage = 10000;
	$maxpost = $DB_site->query_first("SELECT MAX(postid) AS maxpost FROM post");

	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade1.php']['updating_editpost_log'], construct_upgrade_page_hint($maxpost['maxpost'], $startat, $perpage)) . "<br />\n");

	if ($startat <= $maxpost['maxpost'])
	{
		$endat = $startat + $perpage;

		$editedposts = $DB_site->query("
			SELECT post.postid, edituserid, editdate, user.username
			FROM post, user
			WHERE post.edituserid > 0
			AND post.edituserid = user.userid
			AND post.postid > $startat
			AND post.postid <= $endat
			ORDER BY post.postid DESC");
		$batchnum = $DB_site->num_rows($editedposts);
		echo_flush(sprintf($upgrade_phrases['upgrade1.php']['found_x_posts'], $batchnum) . "</p>\n");
		if ($batchnum > 0)
		{
			echo "<ul>\n";
			while ($thispost = $DB_site->fetch_array($editedposts))
			{
				echo_flush("<li>" . sprintf($upgrade_phrases['upgrade1.php']['post_x'], $thispost['postid']) . " ... \n");
				$DB_site->query("
					REPLACE INTO editlog
						(postid, userid, username, dateline, reason)
					VALUES
						($thispost[postid], $thispost[edituserid], '" . addslashes($thispost['username']) . "', $thispost[editdate], '')
				");
				echo_flush("{$vbphrase['done']}</li>\n");
			}
			echo "</ul>\n";
		}
		print_next_page();
	}
	else
	{
		echo_flush("</p><p>" . $upgrade_phrases['upgrade1.php']['post_editlog_complete'] . "</p>");
	}

}

// #############################################################################
// Thread & Post #1
if ($step == 16)
{
	require_once('./includes/functions_misc.php');

	echo_flush("<p>{$upgrade_phrases['upgrade1.php']['steps_may_take_several_minutes']}</p>\n<ul>\n");

	// this is a MONSTER query, beware!
	echo_flush("<li>{$upgrade_phrases['upgrade1.php']['altering_post_table']}");
	$t = microtime();
	$DB_site->query("
		ALTER TABLE post
		ADD parentid INT UNSIGNED NOT NULL,
		ADD attach SMALLINT UNSIGNED NOT NULL,
		DROP edituserid,
		DROP editdate
	");
	$DB_site->query("UPDATE post SET attach = 1 WHERE attachmentid > 0");
	$DB_site->query("ALTER TABLE post DROP attachmentid");

	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');

	echo_flush("<li>{$upgrade_phrases['upgrade1.php']['altering_thread_table']}");
	$t = microtime();

	// remove the hacked version of thread preview if it exists
	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE thread DROP preview");
	$DB_site->query("ALTER TABLE thread DROP firstpostid");
	$DB_site->reporterror = 1;

	$DB_site->query("
		ALTER TABLE thread
		ADD INDEX (postuserid)
	");

	$DB_site->query("
		ALTER TABLE thread
		ADD firstpostid INT UNSIGNED NOT NULL,
		CHANGE title title CHAR(100) NOT NULL,
		CHANGE postusername postusername CHAR(50) NOT NULL,
		CHANGE lastposter lastposter CHAR(50) NOT NULL,
		CHANGE notes notes CHAR(250) NOT NULL,
		ADD similar CHAR(55) NOT NULL
	");
	echo_flush('<br />' . sprintf($vbphrase['query_took'], number_format(fetch_microtime_difference($t), 2)) . '</p>');

	$DB_site->query("
		INSERT IGNORE INTO moderation (threadid, postid, type)
		SELECT thread.threadid, post.postid, 'thread'
		FROM thread
		LEFT JOIN post ON(post.threadid = thread.threadid)
		WHERE thread.visible = 0 AND thread.open <> 10
	");
	echo_flush("<li>{$upgrade_phrases['upgrade1.php']['inserting_moderated_threads']}</li>\n");

	$DB_site->query("
		INSERT INTO moderation (threadid, postid, type)
		SELECT threadid, postid, 'reply'
		FROM post
		WHERE visible = 0
	");
	echo_flush("<li>{$upgrade_phrases['upgrade1.php']['inserting_moderated_posts']}</li>\n");

	echo_flush("</ul>\n");

}

// #############################################################################
// Thread & Post #2
if ($step == 17)
{
	$perpage = 500;
	$maxthread = $DB_site->query_first("SELECT MAX(threadid) AS maxthread FROM thread");

	echo_flush("<p>" . sprintf($upgrade_phrases['upgrade1.php']['update_posts_support_threaded'], construct_upgrade_page_hint($maxthread['maxthread'], $startat, $perpage)) . "<br />\n");
	if ($startat <= $maxthread['maxthread'])
	{
		$endat = $startat + $perpage;

		$threads = $DB_site->query("
			SELECT MIN(postid) AS postid, thread.threadid
			FROM thread, post
			WHERE post.threadid = thread.threadid
			AND thread.threadid > $startat
			AND thread.threadid <= $endat
			GROUP BY(thread.threadid)
			ORDER by thread.threadid
		");
		$batchnum = $DB_site->num_rows($threads);
		echo_flush(sprintf($upgrade_phrases['upgrade1.php']['found_x_threads'], $batchnum) . "</p>\n");
		if ($batchnum > 0)
		{
			echo "<ul>\n";
			while ($thread = $DB_site->fetch_array($threads))
			{
				echo_flush("<li>" . sprintf($upgrade_phrases['upgrade1.php']['post_x'], $thread['postid']) . " ... \n");
				$DB_site->query("
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
		echo_flush("</p><p>{$upgrade_phrases['upgrade1.php']['threaded_update_complete']}</p>");
	}

}

// #############################################################################
// Misc Alters #1
if ($step == 18)
{

	$query[] = "ALTER TABLE userfield ADD temp VARCHAR(250) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "userfield");

	$query[] = 'UPDATE subscribethread SET emailupdate = 1';
	$explain[] = sprintf($vbphrase['update_table'], TABLE_PREFIX . "subscribethread");

	$query[] = 'ALTER TABLE poll ADD public SMALLINT NOT NULL';
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "poll");

	$query[] = 'DELETE FROM searchindex';
	$explain[] = $upgrade_phrases['upgrade1.php']['emptying_search'];

	$query[] = 'ALTER TABLE searchindex
		CHANGE wordid wordid MEDIUMINT UNSIGNED NOT NULL,
		CHANGE intitle intitle TINYINT UNSIGNED NOT NULL,
		ADD score TINYINT UNSIGNED NOT NULL';
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "searchindex");

	$query[] = 'DELETE FROM word';
	$explain[] = $upgrade_phrases['upgrade1.php']['emptying_wordlist'];

	$query[] = 'ALTER TABLE word CHANGE wordid wordid MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT';
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "word");

	$query[] = 'ALTER TABLE customavatar ADD visible SMALLINT DEFAULT 1 NOT NULL';
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "customavatar");

	$query[] = "ALTER TABLE subscribeforum ADD INDEX(forumid)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "subscribeforum");

	$query[] = "ALTER IGNORE TABLE subscribeforum ADD UNIQUE subindex (userid, forumid)";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "subscribeforum");

	$DB_site->reporterror = 0;
	$DB_site->query('ALTER TABLE subscribeforum DROP INDEX userid');
	$DB_site->query('ALTER TABLE useractivation ADD usergroupid SMALLINT UNSIGNED NOT NULL');
	$DB_site->query('UPDATE useractivation SET usergroupid=2 WHERE type=0');
	$DB_site->query("ALTER TABLE subscribethread ADD folderid INT UNSIGNED NOT NULL");
	$DB_site->query("ALTER IGNORE TABLE subscribethread ADD UNIQUE subscribeindex (userid, threadid)");
	$DB_site->query("ALTER TABLE useractivation CHANGE activationid activationid INT UNSIGNED NOT NULL");
	$DB_site->reporterror = 1;

	exec_queries();
}

// #############################################################################
// Misc Alters #2
if ($step == 19)
{

	$query[] = "ALTER TABLE session
			CHANGE host host CHAR(15) NOT NULL,
			ADD badlocation SMALLINT UNSIGNED NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'session', 1, 3);

	$query[] = "ALTER TABLE session
			ADD inforum SMALLINT UNSIGNED NOT NULL,
			ADD inthread INT UNSIGNED NOT NULL,
			ADD incalendar INT UNSIGNED NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'session', 2, 3);

	$query[] = "ALTER TABLE session
			CHANGE useragent useragent VARCHAR(100) NOT NULL,
			ADD loggedin SMALLINT UNSIGNED NOT NULL,
			ADD idhash CHAR(32) NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'session', 3, 3);

	$query[] = "ALTER TABLE profilefield
			ADD type ENUM('input','select','radio','textarea','checkbox','select_multiple') DEFAULT 'input',
			ADD data MEDIUMTEXT NOT NULL,
			ADD height SMALLINT NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'profilefield', 1, 3);

	$query[] = "ALTER TABLE profilefield
			ADD def SMALLINT NOT NULL,
			ADD optional SMALLINT NOT NULL,
			ADD searchable SMALLINT NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'profilefield', 2, 3);

	$query[] = "ALTER TABLE profilefield
			ADD memberlist SMALLINT NOT NULL,
			ADD regex VARCHAR(255) NOT NULL,
			ADD form SMALLINT NOT NULL
			";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'profilefield', 3, 3);

	$query[] = "ALTER TABLE threadrate CHANGE ipaddress ipaddress CHAR( 15 ) NOT NULL";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'threadrate', 1, 2);

	$DB_site->reporterror = 0;
	$DB_site->query('ALTER TABLE threadrate DROP INDEX threadid');
	$DB_site->reporterror = 1;

	$query[] = "ALTER TABLE threadrate ADD INDEX threadid (threadid, userid)";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'threadrate', 2, 2);

	// Why are we enabling all unhidden profile fields on the memberlist by default???
	$query[] = 'UPDATE profilefield SET memberlist = 1 WHERE hidden = 0';
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "profilefield");

	exec_queries();

}

// #############################################################################
// BBCode Alters
if ($step == 20)
{
	// add title field and unique key to bbcode table
	$query[] = "
		ALTER TABLE bbcode
		ADD title CHAR (100) NOT NULL
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'bbcode', 1, 2);

	$query[] = "
		ALTER IGNORE TABLE bbcode
		ADD UNIQUE KEY uniquetag (bbcodetag,twoparams)
	";
	$explain[] = sprintf($upgrade_phrases['upgrade1.php']['altering_x_table'], 'bbcode', 2, 2);

	// drop bbcodes that will now be hard-coded
	$query[] = "DELETE FROM bbcode WHERE bbcodetag IN('b', 'i', 'u', 'font', 'size', 'color')";
	$explain[] = $upgrade_phrases['upgrade1.php']['remove_bbcodes_hardcoded_now'];

	// insert [quote=Username] bbcode
	$query[] = "
		REPLACE INTO bbcode (bbcodeid, bbcodetag, bbcodereplacement, bbcodeexample, bbcodeexplanation, twoparams, title) VALUES
		(
			NULL,
			'quote',
			'<blockquote><span class=\"smallfont\">Quote:</span><hr size=\"1\" />Originally Posted by <b>\\\\5</b><br /><i>\\\\7</i><hr size=\"1\" /></blockquote>',
			'[quote=\'John Doe\']This is a quote[/quote]',
			'The [quote] tag is used to denote a quote that is from another source.',
			1,
			'Quote With Username'
		)
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['inserting_quote_bbcode'];

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
if ($step == 21)
{
	// first of all let's just check that we know what usergroups are 'banned'
	if (!is_array($_POST['bangroup']))
	{
		$bangroup = array();
		$groups = $DB_site->query("SELECT usergroupid, title FROM usergroup WHERE title LIKE('%banned%')");
		while ($group = $DB_site->fetch_array($groups))
		{
			$bangroup[] = $group['usergroupid'];
		}
		print_form_header('upgrade1', '');
		construct_hidden_code('step', 21);
		construct_hidden_code('bangroup[]', 0);
		print_table_header($upgrade_phrases['upgrade1.php']['select_banned_groups']);
		print_description_row("<div align=\"center\" style=\"font-size:10pt\"><br />&nbsp;
			{$upgrade_phrases['upgrade1.php']['explain_banned_groups']}
			<br />&nbsp;</div>");
		print_membergroup_row("<b>{$upgrade_phrases['upgrade1.php']['user_groups']}</b>", 'bangroup', 2, array('usergroupid' => 0, 'membergroupids' => implode(',', $bangroup)));
		print_submit_row($vbphrase['proceed'], $vbphrase['reset']);
		exit;
	}

	// if we got this far the user has filled in the form above

	$query[] = "ALTER TABLE usergroup
		CHANGE title title CHAR(100) NOT NULL,
		CHANGE usertitle usertitle CHAR(100) NOT NULL,
		CHANGE maxbuddypm pmsendmax SMALLINT UNSIGNED DEFAULT 5 NOT NULL,
		CHANGE showgroup genericoptions INT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 1, 9);

	$query[] = "ALTER TABLE usergroup
		ADD ispublicgroup SMALLINT UNSIGNED NOT NULL,
		ADD canoverride SMALLINT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 2, 9);

	$query[] = "ALTER TABLE usergroup
		ADD description CHAR(250) NOT NULL,
		ADD opentag CHAR(100) NOT NULL,
		ADD closetag CHAR(100) NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 3, 9);

	$query[] = "ALTER TABLE usergroup
		ADD forumpermissions INT UNSIGNED NOT NULL,
		ADD pmpermissions INT UNSIGNED NOT NULL,
		ADD calendarpermissions INT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 4, 9);

	$query[] = "ALTER TABLE usergroup
		ADD wolpermissions INT UNSIGNED NOT NULL,
		ADD adminpermissions INT UNSIGNED NOT NULL,
		ADD genericpermissions INT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 5, 9);

	$query[] = "ALTER TABLE usergroup
		ADD passwordexpires SMALLINT UNSIGNED NOT NULL,
		ADD passwordhistory SMALLINT UNSIGNED  NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 6, 9);

	$query[] = "ALTER TABLE usergroup
		ADD pmquota SMALLINT UNSIGNED NOT NULL,
		ADD attachlimit INT UNSIGNED  NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 7, 9);

	$query[] = "ALTER TABLE usergroup
		ADD avatarmaxwidth SMALLINT UNSIGNED NOT NULL,
		ADD avatarmaxheight SMALLINT UNSIGNED NOT NULL,
		ADD avatarmaxsize INT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 8, 9);

	$query[] = "ALTER TABLE usergroup
		ADD profilepicmaxwidth SMALLINT UNSIGNED NOT NULL,
		ADD profilepicmaxheight SMALLINT UNSIGNED NOT NULL,
		ADD profilepicmaxsize INT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'usergroup', 9, 9);

	// update users awaiting email confirmation and registered users to have a user title
	$query[] = "UPDATE usergroup SET
		usertitle = IF(usertitle = '', '{$upgrade_phrases['upgrade1.php']['registered_user']}', usertitle)
		WHERE usergroupid IN(2,3,4)
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['update_some_usergroup_titles'];

	exec_queries();

	$usergroups = $DB_site->query('SELECT * FROM usergroup');
	echo "<p>{$upgrade_phrases['upgrade1.php']['updating_usergroup_permissions']}</p><ul>";

	require_once('./includes/functions_misc.php');

	while ($usergroup = $DB_site->fetch_array($usergroups))
	{
		if ($usergroup['usergroupid'] != 2 AND $usergroup['usergroupid'] != 5 AND $usergroup['usergroupid'] != 6 AND $usergroup['usergroupid'] != 7)
		{
			$calendarsql .= ", (2, $usergroup[usergroupid], 1)";
		}

		echo "<li>" . sprintf($upgrade_phrases['upgrade1.php']['usergroup_x'], $usergroup['title']) . " ...\n";
		$ug = array();
		foreach($_BITFIELD['usergroup'] AS $dbfield => $fields)
		{
			$ug["$dbfield"] = convert_array_to_bits($usergroup, $fields);
		}
		if ($usergroup['genericoptions'])
		{
			$ug['genericoptions'] += 1;
		}
		$DB_site->query(fetch_query_sql($ug, 'usergroup', "WHERE usergroupid=$usergroup[usergroupid]"));
		echo "{$vbphrase['done']}.</li>\n";
		flush();
	}
	echo "</ul>\n";

	$query[] = "UPDATE usergroup SET pmquota = IF(canusepm, " . iif($pmquota == 0, 10000, $pmquota) . ", 0)";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroups'];

	// update usergroups to allow membergroups EXCEPT for the following groups:
	// unregistered users (group 1)
	// users awaiting email confirmation (group 3)
	// COPPA users awaiting moderation (group 4)
	// any usergroup defined as 'banned'
	if (empty($_POST['bangroup']))
	{
		$bannedgroups = 0;
	}
	else
	{
		$bannedgroups = implode(',', $_POST['bangroup']);
	}
	$query[] = "
		UPDATE usergroup SET
			genericoptions = genericoptions + " . ALLOWMEMBERGROUPS . "
		WHERE usergroupid NOT IN(1,3,4)
		AND usergroupid NOT IN($bannedgroups)
		AND NOT (genericoptions & " . ALLOWMEMBERGROUPS . ")
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroups'];

	$query[] = "
		UPDATE usergroup SET
			genericoptions = genericoptions + " . (SHOWBIRTHDAY + SHOWMEMBERLIST)  . "
		WHERE usergroupid NOT IN (1, 3, 4)
		AND usergroupid NOT IN($bannedgroups)
		AND NOT (genericoptions & " . SHOWBIRTHDAY . ")
		AND NOT (genericoptions & " . SHOWMEMBERLIST . ")
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroups'];

	$query[] = "
		UPDATE usergroup SET
			genericoptions = genericoptions + " . ISBANNEDGROUP . "
		WHERE usergroupid IN($bannedgroups)
		AND NOT (genericoptions & " . ISBANNEDGROUP . ")
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroups'];

	if ($showeditedby)
	// Show edited by message setting from vb2 and $showeditedbyadmin
	{
		$query[] = "
			UPDATE usergroup SET
				genericoptions = genericoptions + " . SHOWEDITEDBY . "
			WHERE NOT (genericoptions & " . SHOWEDITEDBY . ")
			" . iif(!$showeditedbyadmin, "AND cancontrolpanel = 0", '') . "
		";
		$explain[] = $upgrade_phrases['upgrade1.php']['updating_generic_options'];
	}

	$query[] = "
		UPDATE usergroup SET
			genericpermissions = genericpermissions + " . (CANSEEHIDDEN + CANSEEOWNREP + CANMANAGEOWNUSERNOTES + CANMANAGEOTHERSUSERNOTES + CANUSECUSTOMTITLE + CANUSEAVATAR + CANPROFILEPIC) . "
		WHERE cancontrolpanel = 1
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_generic_options'];

	$query[] = "
		UPDATE usergroup SET
			genericpermissions = genericpermissions + " . CANBEUSERNOTED . "
		WHERE cancontrolpanel = 0
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_generic_options'];

	$query[] = "
		UPDATE usergroup SET
			calendarpermissions = calendarpermissions + " . (CANVIEWCALENDAR + CANVIEWOTHERSEVENT) . "
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroup_calendar'];

	$query[] = "
		UPDATE usergroup SET
			calendarpermissions = calendarpermissions + " . (CANEDITEVENT + CANDELETEEVENT) . "
		WHERE ismoderator = 1
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroup_calendar'];

	$query[] = "
		UPDATE usergroup SET
			calendarpermissions = calendarpermissions + " . (CANPOSTEVENT) . "
		WHERE canpublicevent = 1
	";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroup_calendar'];

	$query[] = "INSERT INTO calendarpermission (calendarid, usergroupid, calendarpermissions)
					VALUES	(2, 2, 15), ### Registered Group ###
							(2, 6, 15), ### Admin Group ###
							(2, 5, 15), ### Super Moderators ###
							(2, 7, 15) ### Moderators ###
							$calendarsql
				";
	$explain[] = $upgrade_phrases['upgrade1.php']['creating_priv_calendar_perms'];

	$query[] = "UPDATE usergroup SET
				genericpermissions = genericpermissions + " . (CANINVISIBLE + CANUSESIGNATURE + CANSEEPROFILEPIC + CANUSEREP + CANNEGATIVEREP) . "
			WHERE usergroupid NOT IN (1, 3, 4) AND usergroupid NOT IN($bannedgroups)";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroups'];

	$query[] = "UPDATE usergroup SET
				wolpermissions = wolpermissions + " . (CANWHOSONLINEFULL + CANWHOSONLINEBAD + CANWHOSONLINELOCATION) . ",
				forumpermissions = forumpermissions + " . CANSEEDELNOTICE . ",
				genericpermissions = genericpermissions + " . CANVIEWOTHERSUSERNOTES . "
			WHERE ismoderator = 1";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroups'];

	$avatarmaxdimension = intval($avatarmaxdimension);
	$avatarmaxsize = intval($avatarmaxsize);
	$query[] = "UPDATE usergroup SET
			avatarmaxwidth = $avatarmaxdimension,
			avatarmaxheight = $avatarmaxdimension,
			avatarmaxsize = $avatarmaxsize,
			profilepicmaxwidth = 100,
			profilepicmaxheight = 100,
			profilepicmaxsize = 100000";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_usergroups'];

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
if ($step == 22)
{
	// get rid of redundant forum permissions
	$deleteids = '0';
	$fps = $DB_site->query("
		SELECT forumpermissionid
		FROM forumpermission
		LEFT JOIN forum ON(forum.forumid = forumpermission.forumid)
		LEFT JOIN usergroup ON(usergroup.usergroupid = forumpermission.usergroupid)
		WHERE ISNULL(forum.forumid) OR ISNULL(usergroup.usergroupid)
	");
	while($fp = $DB_site->fetch_array($fps))
	{
		$deleteids .= ", $fp[forumpermissionid]";
	}
	if ($deleteids != '0')
	{
		$query[] = "DELETE FROM forumpermission WHERE forumpermissionid IN($deleteids)";
		$explain[] = $upgrade_phrases['upgrade1.php']['removing_orhpan_forum_perms'];
		exec_queries();
	}

	// make sure the forum permissions are not duplicated...
	$fperms = array();
	$fps = $DB_site->query("
		SELECT forumpermission.*, forum.title AS forumtitle, usergroup.title AS usergrouptitle
		FROM forumpermission
		LEFT JOIN forum ON(forum.forumid=forumpermission.forumid)
		LEFT JOIN usergroup ON(usergroup.usergroupid=forumpermission.usergroupid)
		ORDER BY forumpermissionid
	");
	while ($fp = $DB_site->fetch_array($fps))
	{
		$fperms["$fp[forumid]"]["$fp[usergroupid]"] = $fp;
	}

	$query[] = "INSERT INTO datastore (title, data) VALUES ('fperms_backup', '" . addslashes(serialize($fperms)) . "')";
	$explain[] = $upgrade_phrases['upgrade1.php']['backup_forum_perms'];

	$query[] = "DROP TABLE forumpermission";
	$explain[] = $upgrade_phrases['upgrade1.php']['drop_old_forumperms'];

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

		require_once('./includes/functions_misc.php');
		foreach($fperms as $tmp)
		{
			foreach($tmp as $fperm)
			{
				echo_flush("<li>" . sprintf($upgrade_phrases['upgrade1.php']['usergroup_x_forum_y'], $fperm['usergrouptitle'], $fperm['forumtitle']) . " ...\n");
				$insertbits[] = "($fperm[forumid], $fperm[usergroupid], " . convert_array_to_bits($fperm, $_BITFIELD['usergroup']['forumpermissions']) . ")";
				echo_flush("{$vbphrase['done']}.</li>\n");
			}
		}
		echo "</ul>\n";
		$query[] = "INSERT INTO forumpermission\n\t(forumid, usergroupid, forumpermissions)\nVALUES\n\t" . implode(",\n\t", $insertbits);
		$explain[] = $upgrade_phrases['upgrade1.php']['reinsert_forum_perms'];
	}

	$query[] = "DELETE FROM datastore WHERE title='fperms_backup'";
	$explain[] = $upgrade_phrases['upgrade1.php']['remove_forum_perms_backup'];

	exec_queries();
}

// #############################################################################
// Moderator Permissions
if ($step == 23)
{
	$DB_site->query("ALTER TABLE moderator ADD permissions INT UNSIGNED NOT NULL");
	echo_flush(sprintf($vbphrase['alter_table'], TABLE_PREFIX . "moderator") . "\n");

	$moderators = $DB_site->query("
		SELECT moderator.*,forum.title,user.username
		FROM moderator
		LEFT JOIN forum ON(forum.forumid=moderator.forumid)
		LEFT JOIN user ON(user.userid=moderator.userid)
	");
	echo "<p>{$upgrade_phrases['upgrade1.php']['updating_moderator_perms']}</p><ul>";

	require_once('./includes/functions_misc.php');
	while ($moderator = $DB_site->fetch_array($moderators))
	{
		echo "<li>" . sprintf($upgrade_phrases['upgrade1.php']['moderator_x_forum_y'], $moderator['username'], $moderator['title']) . " ...";
		flush();
		if ($moderator['title']=='' or $moderator['username'] == '')
		{
			echo "<i>{$upgrade_phrases['upgrade1.php']['deleted_not_needed']}</i></li>\n";
		}
		else
		{
			$perms = convert_array_to_bits($moderator,$_BITFIELD['moderatorpermissions']);
			$DB_site->query("UPDATE moderator SET permissions=$perms WHERE moderatorid=$moderator[moderatorid]");
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
if ($step == 24)
{
	echo "<p>{$upgrade_phrases['upgrade1.php']['insert_phrase_groups']}...</p>";
	$DB_site->query("
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
if ($step == 25)
{

	$query[1] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 1, './includes/cron/birthday.php', 1, '{$upgrade_phrases['upgrade1.php']['cron_birthday']}')";
	$explain[1] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 1);

	$query[2] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 56, './includes/cron/threadviews.php', 0, '{$upgrade_phrases['upgrade1.php']['cron_thread_views']}')";
	$explain[2] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 2);

	$query[3] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 45, './includes/cron/promotion.php', 1, '{$upgrade_phrases['upgrade1.php']['cron_user_promo']}')";
	$explain[3] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 3);

	$query[4] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 2, './includes/cron/digestdaily.php', 1, '{$upgrade_phrases['upgrade1.php']['cron_daily_digest']}')";
	$explain[4] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 4);

	$query[5] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (1, -1, 0, 30, './includes/cron/digestweekly.php', 1, '{$upgrade_phrases['upgrade1.php']['cron_weekly_digest']}')";
	$explain[5] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 5);

	$query[6] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 3, './includes/cron/activate.php', 1, '{$upgrade_phrases['upgrade1.php']['cron_activation']}')";
	$explain[6] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 6);

	$query[7] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 0, './includes/cron/subscriptions.php', 1, '{$upgrade_phrases['upgrade1.php']['cron_subscriptions']}')";
	$explain[7] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 7);

	$query[8] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 5, './includes/cron/cleanup.php', 0, '{$upgrade_phrases['upgrade1.php']['cron_hourly_cleanup']}')";
	$explain[8] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 8);

	$query[9] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 40, './includes/cron/cleanup2.php', 0, '{$upgrade_phrases['upgrade1.php']['cron_hourly_cleaup2']}')";
	$explain[9] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 9);

	$query[10] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, -1, 10, './includes/cron/attachmentviews.php', 0, '{$upgrade_phrases['upgrade1.php']['cron_attachment_views']}')";
	$explain[10] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 10);

	$query[11] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 0, './includes/cron/removebans.php', 1, '{$upgrade_phrases['upgrade1.php']['cron_unban_users']}')";
	$explain[11] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 11);

	$query[12] = "INSERT INTO cron (weekday, day, hour, minute, filename, loglevel, title) VALUES (-1, -1, 0, 0, './includes/cron/stats.php', 0, '{$upgrade_phrases['upgrade1.php']['cron_stats_log']}')";
	$explain[12] = sprintf($upgrade_phrases['upgrade1.php']['inserting_task_x'], 12);

	$tmp = $explain;

	exec_queries(0, 1);

	require_once('./includes/functions_cron.php');

	if (is_array($inserts))
	{
		echo "<ul>\n";
		foreach ($inserts as $index => $value)
		{
			$i++;
			preg_match('#(\d+)#', $tmp["$i"], $regs);
			echo "<li>" . sprintf($upgrade_phrases['upgrade1.php']['scheduling_x'], $regs[1]) . "</li>";
			build_cron_item($value);
		}
		echo "</ul>\n";
	}

	build_cron_next_run();
}

// #############################################################################
// Settings #1
if ($step == 26)
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
	$vb2groups = array_merge($upgrade_phrases['upgrade1.php']['settinggroups'], $vb2groups);

	$query[] = "
		ALTER TABLE settinggroup
		ADD grouptitle CHAR( 50 ) NOT NULL FIRST,
		ADD volatile SMALLINT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "settinggroup");

	$query[] = "
		ALTER TABLE setting
		ADD grouptitle CHAR(50) NOT NULL,
		ADD defaultvalue MEDIUMTEXT NOT NULL,
		ADD advanced SMALLINT UNSIGNED NOT NULL,
		ADD volatile SMALLINT UNSIGNED NOT NULL
	";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "setting");

	$settinggroups = array();

	$groups = $DB_site->query("SELECT * FROM settinggroup");
	while ($group = $DB_site->fetch_array($groups))
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
			$phrases[] = "(" . PHRASETYPEID_SETTING . ", 0, 'settinggroup_" . $vb2groups["$settinggroup[title]"] . "', '" . addslashes($settinggroup['title']) . "')";
			$volatile = 0;
		}
		else
		{
			$volatile = 1;
		}

		$query[] = "UPDATE settinggroup SET grouptitle='" . addslashes($vb2groups["$settinggroup[title]"]) . "', volatile=$volatile, displayorder=displayorder+1000 WHERE settinggroupid=$settinggroupid";
		$explain[] = sprintf($upgrade_phrases['upgrade1.php']['update_setting_group_x'], htmlspecialchars_uni($settinggroup['title']));
		$query[] = "UPDATE setting SET grouptitle='" . addslashes($vb2groups["$settinggroup[title]"]) . "' WHERE settinggroupid=$settinggroupid";
		$explain[] = sprintf($upgrade_phrases['upgrade1.php']['update_settings_within_x'], htmlspecialchars_uni($settinggroup['title']));

	}

	if (!empty($phrases))
	{
		$query[] =  "INSERT INTO phrase\n\t(phrasetypeid, languageid, varname, text)\nVALUES\n\t" . implode(",\n\t", $phrases);
		$explain[] = $upgrade_phrases['upgrade1.php']['insert_phrases_nonstandard_groups'];
	}

	exec_queries();
}

// #############################################################################
// Settings #2
if ($step == 27)
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

	require_once('./includes/adminfunctions_options.php');

	// initialize vars
	$arr = array();
	// create parser
	$parser = xml_parser_create('ISO-8859-1');
	// set parser options
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_set_element_handler($parser, 'xml_parse_settings_otag', 'xml_parse_settings_ctag');
	xml_set_character_data_handler($parser, 'xml_parse_settings_cdata');
	xml_parse($parser, file_read('./install/vbulletin-settings.xml'));

	foreach(array_keys($arr) as $grouptitle)
	{
		foreach(array_keys($arr["$grouptitle"]['settings']) as $varname)
		{
			$vb3options[] = $varname;
		}
	}

	// now query the existing options to find out which should be
	// zapped and which need to be translated into the new system...
	$settings = $DB_site->query("
		SELECT setting.*
		FROM setting LEFT JOIN settinggroup USING(settinggroupid)
		ORDER BY settinggroup.displayorder, setting.displayorder
	");

	$phrases = array();

	while ($setting = $DB_site->fetch_array($settings))
	{
		$vb2options["$setting[varname]"] = $setting['value'];
		if (!in_array($setting['varname'], $vb2settings) and !in_array($setting['varname'], $vb3options))
		{
			$nonvolatilevars[] = $setting['varname'];
			$settinggroupid = $setting['settinggroupid'];
			$phrases[] = "(" . PHRASETYPEID_SETTING . ", 0, '" . addslashes("setting_$setting[varname]_title") . "', '" . addslashes($setting['title']) . "')";
			$phrases[] = "(" . PHRASETYPEID_SETTING . ", 0, '" . addslashes("setting_$setting[varname]_desc") . "', '" . addslashes($setting['description']) . "')";
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
		$explain[] = $upgrade_phrases['upgrade1.php']['insert_phrases_nonstandard_settings'];
	}

	// save current options into the datastore for retrieval later
	$query[] = "REPLACE INTO datastore\n\t(title, data)\nVALUES\n\t('options', '" . addslashes(serialize($vb2options)) . "')";
	$explain[] = $upgrade_phrases['upgrade1.php']['saving_your_settings'];

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

	$DB_site->reporterror = 0;
	$DB_site->query("
		ALTER TABLE setting
		DROP settingid,
		DROP settinggroupid,
		DROP title,
		DROP description,
		DROP PRIMARY KEY,
		ADD PRIMARY KEY(varname)
	");
	$DB_site->reporterror = 1;

}

// #############################################################################
// Language
if ($step == 28)
{
	require_once('./includes/adminfunctions_language.php');

	$path = './install/vbulletin-language.xml';
	xml_import_language();

	// Create English Language Kier?

	$query[] = "INSERT INTO language (title, languagecode, charset, decimalsep, thousandsep) VALUES ('{$upgrade_phrases['upgrade1.php']['master_language_title']}', '{$upgrade_phrases['upgrade1.php']['master_language_langcode']}', '{$upgrade_phrases['upgrade1.php']['master_language_charset']}', '{$upgrade_phrases['upgrade1.php']['master_language_decimalsep']}', '{$upgrade_phrases['upgrade1.php']['master_language_thousandsep']}')";
	$explain[] = $upgrade_phrases['upgrade1.php']['master_language_just_created'];

	exec_queries();

	$languages = fetch_languages_array(0, true); // only grabs id, title
	echo "<ul>\n";
	foreach($languages as $languageid => $language)
	{
		echo "<li>" . sprintf($upgrade_phrases['upgrade1.php']['building_lang_x'], $language['title']) . "</li>";
		build_language($languageid);
		flush();
	}
	echo "</ul>\n";
	echo "<p><b>{$upgrade_phrases['upgrade1.php']['language_imported_sucessfully']}</b></p>";
}

// #############################################################################
// Import Admin Help
if ($step == 29)
{
	require_once('./includes/adminfunctions_help.php');

	$path = './install/vbulletin-adminhelp.xml';
	xml_import_help_topics();

	echo "<p><b>{$upgrade_phrases['upgrade1.php']['ahelp_imported_sucessfully']}</b></p>";
}

// #############################################################################
// Alter style table & drop replacementset table
if ($step == 30)
{

	$query[] = "ALTER TABLE style RENAME style_vb2";
	$explain[] = $upgrade_phrases['upgrade1.php']['renaming_style_table'];

	$query[] = "
CREATE TABLE " . TABLE_PREFIX . "style (
	styleid smallint unsigned NOT NULL auto_increment,
	title varchar(250) NOT NULL DEFAULT '',
	parentid smallint NOT NULL DEFAULT '0',
	parentlist varchar(250) NOT NULL DEFAULT '',
	templatelist mediumtext NOT NULL,
	css mediumtext NOT NULL,
	stylevars mediumtext NOT NULL,
	replacements mediumtext NOT NULL,
	userselect smallint unsigned NOT NULL DEFAULT '1',
	displayorder smallint unsigned NOT NULL DEFAULT '0',
	csscolors MEDIUMTEXT NOT NULL,
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
if ($step == 31)
{

	require_once('./includes/adminfunctions_template.php');

	$DB_site->reporterror = 0;
	$DB_site->query('ALTER TABLE template DROP INDEX title');
	$DB_site->reporterror = 1;

	$query[] = "DELETE FROM template WHERE templatesetid=-1 AND title <> 'options'";
	$explain[] = $upgrade_phrases['upgrade1.php']['removing_default_templates'];

	$query[] = "ALTER TABLE template
		ADD styleid SMALLINT NOT NULL,
		ADD template_un MEDIUMTEXT NOT NULL,
		ADD templatetype ENUM('template','stylevar','css','replacement') NOT NULL DEFAULT 'template'";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'template', 1, 3);

	$query[] = "ALTER TABLE template
		CHANGE templateid templateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
		ADD dateline INT UNSIGNED NOT NULL,
		ADD username VARCHAR(50) NOT NULL";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'template', 2, 3);

	$query[] = "ALTER IGNORE TABLE template
		ADD UNIQUE title (title, styleid)";
	$explain[] = sprintf($vbphrase['alter_table_step_x'], 'template', 3, 3);

	exec_queries();

	$DB_site->reporterror = 0;
	$DB_site->query("ALTER TABLE template ADD version varchar(30) NOT NULL");
	$DB_site->reporterror = 1;

	// There was a script here to store templates in the new format. Well, I'm only partially doing that. The above ALTER
	// adds a column that stores an unprocessed version of the template. So if you need the script that populates that field
	// correctly, let me know since it's too big to post here.

	echo "<p>{$upgrade_phrases['upgrade1.php']['updating_template_format']}</p>";

	$DB_site->query("UPDATE template SET template_un = template");
	$temps = $DB_site->query("SELECT templateid, title, template FROM template WHERE title <> 'options'");
	echo "<ul>\n";
	while($temp = $DB_site->fetch_array($temps))
	{
		echo_flush("\t<li>" . sprintf($upgrade_phrases['upgrade1.php']['updating_template_x'], htmlspecialchars($temp['title'])) . "... ");
		$template = compile_template($temp['template']);
		$DB_site->query("UPDATE template SET template='" . addslashes($template) . "' WHERE templateid=$temp[templateid]");
		echo "{$vbphrase['ok']}</li>\n";
	}
	echo "</ul>\n";

}

// #############################################################################
// User Reputation Levels -- Feel free to change these
if ($step == 32)
{

	$query[] = "INSERT INTO reputationlevel (minimumreputation, level) VALUES
			(-999999, '{$upgrade_phrases['upgrade1.php']['reputation_-999999']}'),
			(-50, '{$upgrade_phrases['upgrade1.php']['reputation_-50']}'),
			(-10, '{$upgrade_phrases['upgrade1.php']['reputation_-10']}'),
			(0, '{$upgrade_phrases['upgrade1.php']['reputation_0']}'),
			(10, '{$upgrade_phrases['upgrade1.php']['reputation_10']}'),
			(50, '{$upgrade_phrases['upgrade1.php']['reputation_50']}'),
			(150, '{$upgrade_phrases['upgrade1.php']['reputation_150']}'),
			(250, '{$upgrade_phrases['upgrade1.php']['reputation_250']}'),
			(350, '{$upgrade_phrases['upgrade1.php']['reputation_350']}'),
			(450, '{$upgrade_phrases['upgrade1.php']['reputation_450']}'),
			(550, '{$upgrade_phrases['upgrade1.php']['reputation_550']}'),
			(650, '{$upgrade_phrases['upgrade1.php']['reputation_650']}'),
			(2000, '{$upgrade_phrases['upgrade1.php']['reputation_2000']}'),
			(1500, '{$upgrade_phrases['upgrade1.php']['reputation_1500']}'),
			(1000, '{$upgrade_phrases['upgrade1.php']['reputation_1000']}')
			";
	$explain[] = $upgrade_phrases['upgrade1.php']['populating_reputation_levels'];

	$id = $DB_site->query_first("SELECT reputationlevelid FROM reputationlevel WHERE minimumreputation = 0");

	$query[] = "UPDATE user SET reputationlevelid = " . intval($id['reputationlevelid']);
	$explain[] = $upgrade_phrases['upgrade1.php']['set_reputation_to_neutral'];

	exec_queries();

}

// #############################################################################
// Reading existing style info
if ($step == 33)
{
	// set up default style
	$styleQuery = array(
		"('" . sprintf($upgrade_phrases['upgrade1.php']['bbtitle_vb3_style'], addslashes(htmlspecialchars($bbtitle))) . "', 2, '', '', '', 1, 1)",
		"('vBulletin 3', -1, '', '', '', 0, 1)"
	);

	print_form_header('', '');
	print_table_header($upgrade_phrases['upgrade1.php']['please_read_txt']);
	print_description_row($upgrade_phrases['upgrade1.php']['replacement_upgrade_desc']);
	print_table_footer();

	$styleIds = array();
	$i = 2;

	$styles = $DB_site->query("SELECT * FROM style_vb2 ORDER BY styleid");
	echo('<blockquote><ul>');
	while ($style = $DB_site->fetch_array($styles))
	{
		// save style id info
		$i++;
		$styleIds["$style[styleid]"] = $i;

		if ($style['title'] == 'Default' OR $style['title'] == $upgrade_phrases['upgrade1.php']['vb2_default_style_title'])
		{
			$style['title'] = $upgrade_phrases['upgrade1.php']['new_vb2_default_style_title'];
		}

		echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['create_vb3_style_x'], $style['title']) . "</li>");
		$styleQuery[] = "('" . addslashes(trim($style['title'])) . "', -1, $style[templatesetid], '', $style[replacementsetid], userselect = $style[userselect], $style[styleid])";
	}
	echo('</ul></blockquote>');

	print_form_header('', '');
	print_table_header($upgrade_phrases['upgrade1.php']['please_read_txt']);
	print_description_row($upgrade_phrases['upgrade1.php']['template_upgrade_desc']);
	print_table_footer();

	$tSets = $DB_site->query("
		SELECT templateset.*, COUNT(templateid) AS templatecount
		FROM templateset
		LEFT JOIN template USING(templatesetid)
		GROUP BY templateset.templatesetid
	");
	echo('<blockquote><ul>');
	while($tSet = $DB_site->fetch_array($tSets))
	{
		if ($tSet['title'] == 'Default' OR $tSet['title'] == $upgrade_phrases['upgrade1.php']['vb2_default_style_title'])
		{
			$tSet['title'] = $upgrade_phrases['upgrade1.php']['new_vb2_default_style_title'];
		}
		echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['create_vb2_refernce_style'], $tSet['title']) . "</li>");
		if ($tSet['templatecount'] > 0)
		{
			$templateset["$tSet[templatesetid]"] = $tSet['title'];
			$styleQuery[] = "('[" . sprintf($upgrade_phrases['upgrade1.php']['x_old_custom_templates'], addslashes($tSet['title'])) . "]', -1, $tSet[templatesetid], 'templateset', '', 0, 1)";
		}
	}
	echo('</ul></blockquote>');

	echo('<hr />');

	$query[] = "INSERT INTO style (title, parentid, templatelist, css, replacements, userselect, displayorder) VALUES " . implode(",\n", $styleQuery);
	$explain[] = $upgrade_phrases['upgrade1.php']['insert_styles_vb3_table'];

	$query[] = "UPDATE style SET parentlist=CONCAT(styleid,',-1')";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_style_parent_list'];

	$query[] = "UPDATE user SET styleid = 0";
	$explain[] = $upgrade_phrases['upgrade1.php']['updating_user_to_new_style'];

	echo '<blockquote>';

	exec_queries(1);

	echo '</blockquote>';
}

// #############################################################################
// Populating stylevar/css/replacements templates
if ($step == 34)
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
			echo("<li><i>{$upgrade_phrases['upgrade1.php']['translate_replacement_to_stylevars']}</i><ul>");
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
				echo("<li>({$upgrade_phrases['upgrade1.php']['no_value_to_translate']})</li>");
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

			echo("<li><i>{$upgrade_phrases['upgrade1.php']['translating_replacement_to_css']}</i><ul>");

			// body CSS
			if (isset($r['<body>']))
			{
				if (preg_match('/ bgcolor="(.*)"/siU', $r['<body>'], $regs))
				{
					$css['body']['background'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['body_bg_color_x'], $regs[1]) . "</li>");
				}
				if (preg_match('/ text="(.*)"/siU', $r['<body>'], $regs))
				{
					$css['body']['color'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['body_text_color_x'], $regs[1]) . "</li>");
				}
				if (preg_match('/ marginwidth="(.*)"/siU', $r['<body>'], $regs))
				{
					$stylevars['spacersize'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['margin_width_x'], $regs[1]) . "</li>");
				}
				unset($r['<body>']);
			}
			if (isset($r['{linkcolor}']))
			{
				$css['body']['LINK_N']['color'] = $r['{linkcolor}'];
				$css['body']['LINK_V']['color'] = $r['{linkcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['link_color_x'], $r['{linkcolor}']) . "</li>");
				unset($r['{linkcolor}']);
			}
			if (isset($r['{hovercolor}']))
			{
				$css['body']['LINK_M']['color'] = $r['{hovercolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['hover_link_color_x'], $r['{hovercolor}']) . "</li>");
				unset($r['{hovercolor}']);
			}
			process_complete_css($css, 'body');

			// .page CSS
			if (isset($r['{pagebgcolor}']))
			{
				$css['.page']['background'] = $r['{pagebgcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['page_bg_color_x'], $r['{pagebgcolor}']) . "</li>");
				unset($r['{pagebgcolor}']);
			}
			if (isset($r['{pagetextcolor}']))
			{
				$css['.page']['color'] = $r['{pagetextcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['page_text_color_x'], $r['{pagetextcolor}']) . "</li>");
				unset($r['{pagetextcolor}']);
			}
			process_complete_css($css, '.page');

			// table border CSS
			if (isset($r['{tablebordercolor}']))
			{
				$css['.tborder']['background'] = $r['{tablebordercolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['table_border_color_x'], $r['{tablebordercolor}']) . "</li>");
				unset($r['{tablebordercolor}']);
			}
			process_complete_css($css, '.tborder');

			// category strip CSS
			if (isset($r['{categorybackcolor}']))
			{
				$css['.tcat']['background'] = $r['{categorybackcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['category_strip_bg_color'], $r['{categorybackcolor}']) . "</li>");
				unset($r['{categorybackcolor}']);
			}
			if (isset($r['{categoryfontcolor}']))
			{
				$css['.tcat']['color'] = $r['{categoryfontcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['category_strip_text_color'], $r['{categoryfontcolor}']) . "</li>");
				unset($r['{categoryfontcolor}']);
			}
			process_complete_css($css, '.tcat');

			// table header CSS
			if (isset($r['{tableheadbgcolor}']))
			{
				$css['.thead']['background'] = $r['{tableheadbgcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['tbl_head_bg_color_x'], $r['{tableheadbgcolor}']) . "</li>");
				unset($r['{tableheadbgcolor}']);
			}
			if (isset($r['{tableheadtextcolor}']))
			{
				$css['.thead']['color'] = $r['{tableheadtextcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['tbl_head_text_color_x'], $r['{tableheadtextcolor}']) . "</li>");
				unset($r['{tableheadtextcolor}']);
			}
			process_complete_css($css, '.thead');

			// alt1 & alt2 CSS
			if (isset($r['{firstaltcolor}']))
			{
				$css['.alt1, .alt1Active']['background'] = $r['{firstaltcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['first_alt_color_x'], $r['{firstaltcolor}']) . "</li>");
				unset($r['{firstaltcolor}']);
			}
			process_complete_css($css, '.alt1, .alt1Active');

			if (isset($r['{secondaltcolor}']))
			{
				$css['.alt2, .alt2Active']['background'] = $r['{secondaltcolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['second_alt_color_x'], $r['{secondaltcolor}']) . "</li>");
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
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['normal_font_size'], $newsize) . "</li>");
				}
				if (preg_match('/ face="(.*)"/siU', $r['<normalfont'], $regs))
				{
					$css['td, th, p, li']['font']['family'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['normal_font_family'], $regs[1]) . "</li>");
				}
				if (preg_match('/ color="(.*)"/siU', $r['<normalfont'], $regs))
				{
					$css['td, th, p, li']['color'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['normal_font_color'], $regs[1]) . "</li>");
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
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['small_font_size'], $newsize) . "</li>");
				}
				if (preg_match('/ face="(.*)"/siU', $r['<smallfont'], $regs))
				{
					$css['.smallfont']['font']['family'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['small_font_family'], $regs[1]) . "</li>");
				}
				if (preg_match('/ color="(.*)"/siU', $r['<smallfont'], $regs))
				{
					$css['.smallfont']['color'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['small_font_color'], $regs[1]) . "</li>");
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
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['highlight_font_family'], $regs[1]) . "</li>");
				}
				if (preg_match('/ size="(.*)"/siU', $r['<highlight'], $regs))
				{
					$newsize = convert_font_size_to_css($regs[1]);
					$css['.highlight']['font']['size'] = $newsize;
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['highlight_font_size'], $newsize) . "</li>");
				}
				if (preg_match('/ color="(.*)"/siU', $r['<highlight'], $regs))
				{
					$css['.highlight']['color'] = $regs[1];
					echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['highlight_font_color'], $regs[1]) . "</li>");
				}
				unset($r['<highlight']);
			}
			process_complete_css($css, '.highlight');

			// time CSS
			if (isset($r['{timecolor}']))
			{
				$css['.time']['color'] = $r['{timecolor}'];
				echo("<li>" . sprintf($upgrade_phrases['upgrade1.php']['time_color_x'], $r['{timecolor}']) . "</li>");
				unset($r['{timecolor}']);
			}
			process_complete_css($css, '.time');

			if (empty($css))
			{
				echo("<li>({$upgrade_phrases['upgrade1.php']['no_replacements_to_translate']})</li>");
			}

			echo('</ul></li>');

			echo("<li><i>{$upgrade_phrases['upgrade1.php']['translating_remaining_replacements']}</i><ul>");
			foreach($r as $findword => $replaceword)
			{
				$replacements["$findword"] = $replaceword;
				echo('<li>' . htmlspecialchars_uni($findword) . ' &raquo; <b>' . htmlspecialchars_uni($replaceword) . '</b></li>');
				unset($r["$findword"]);
			}
			if (empty($replacements))
			{
				echo("<li>({$upgrade_phrases['upgrade1.php']['no_remaining_replacement_vars']})</li>");
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
	$hIs = $DB_site->query("SELECT templatesetid, template FROM template WHERE title='headinclude'");
	while ($hI = $DB_site->fetch_array($hIs))
	{
		$headinclude["$hI[templatesetid]"] = $hI['template'];
	}

	// query replacement vars for the old styles
	$replacement = array();
	$gRs = $DB_site->query("SELECT replacementsetid, findword, replaceword FROM replacement WHERE replacementsetid<>-1");
	while ($gR = $DB_site->fetch_array($gRs))
	{
		$replacement["$gR[replacementsetid]"]["$gR[findword]"] = $gR['replaceword'];
	}

	$i = 0;
	print_form_header('', '');

	// query the new styles and go through translating them
	$styles = $DB_site->query("SELECT styleid, title, templatelist AS templatesetid, replacements AS replacementsetid FROM style WHERE styleid > 2 AND css=''");
	$numstyles = $DB_site->num_rows($styles);
	while ($style = $DB_site->fetch_array($styles))
	{
		print_table_header($upgrade_phrases['upgrade1.php']['translate_vb2_style_settings']);
		print_description_row("<!--Style:--> <font size=\"+1\"><b><i>$style[title]</i></b></font>");
		echo '<tr><td class="alt2"><ul>';

		$style = array_merge($style, convert_vb2_style_to_vb3_style($replacement["$style[replacementsetid]"]));

		echo("<li><i>{$upgrade_phrases['upgrade1.php']['add_css_headinclude_to_extra']}</i><ul>");
		if (isset($headinclude["$style[templatesetid]"]))
		{
			if (preg_match('#<style.*>(.*)</style>#siU', $headinclude["$style[templatesetid]"], $regs))
			{
				echo("<li>{$upgrade_phrases['upgrade1.php']['found_css_data']}</li>");
				$style['css']['EXTRA']['all'] = $regs[1];
			}
			else

			{
				echo("<li>({$upgrade_phrases['upgrade1.php']['no_css_data_found']})</li>");
			}
		}
		else
		{
			echo("<li>({$upgrade_phrases['upgrade1.php']['no_headinclude_found']})</li>");
		}
		echo('</ul></li>');

		foreach($style['stylevars'] as $varname => $value)
		{
			$templateQuery[] = "($style[styleid], 'stylevar', '" . addslashes($varname) . "', '" . addslashes($value) . "')";
		}
		foreach($style['css'] as $identifier => $values)
		{
			$templateQuery[] = "($style[styleid], 'css', '" . addslashes($identifier) . "', '" . addslashes(serialize($values)) . "')";
		}
		foreach($style['replacements'] as $findword => $replaceword)
		{
			$templateQuery[] = "($style[styleid], 'replacement', '" . addslashes($findword) . "', '" . addslashes($replaceword) . "')";
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
		$explain[] = $upgrade_phrases['upgrade1.php']['insert_style_settings'];
	}

	exec_queries(1);

}

// #############################################################################
// Stick old templates into their own styles
if ($step == 35)
{

	$styles = $DB_site->query("
		SELECT style.*, style.templatelist AS templatesetid, templateset.title AS templatesetname
		FROM style LEFT JOIN templateset ON(templateset.templatesetid=style.templatelist)
		WHERE css='templateset'
	");
	while ($style = $DB_site->fetch_array($styles))
	{
		$query[] = "UPDATE template SET styleid=$style[styleid] WHERE templatesetid=$style[templatesetid]";
		$explain[] = sprintf($upgrade_phrases['upgrade1.php']['moving_template_x_to_style_x'], $style['templatesetname'], $style['title']);

	}

	exec_queries(1);

}

// #############################################################################
// Drop the old tables and clean up
if ($step == 36)
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

}

// #############################################################################
// Import master style set file
if ($step == 37)
{
	require_once('./includes/adminfunctions_template.php');

	if (!($xml = file_read('./install/vbulletin-style.xml')))
	{
		echo '<p>' . sprintf($vbphrase['file_not_found'], 'vbulletin-style.xml') . '</p>';
		print_cp_footer();
	}

	echo '<p>' . sprintf($vbphrase['importing_file'], 'vbulletin-style.xml');

	xml_import_style($xml);
}

// #############################################################################
// Rebuild all styles
if ($step == 38)
{
	require_once('./includes/adminfunctions_template.php');

	define('NO_POST_EDITOR_BUILD', true);
	build_all_styles(0, 1);
}

// #############################################################################
// Insert FAQ entries
if ($step == 39)
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
	$explain[] = $upgrade_phrases['upgrade1.php']['importing_faq_entries'];

	exec_queries();
}

// #############################################################################
// Check for semi-colons in usernames
if ($step == 40)
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
			echo "<p>{$upgrade_phrases['upgrade1.php']['follow_users_contain_semicolons']}</p><ul>";
			foreach($illegals as $userid => $username)
			{
				echo "<li><b>" . htmlspecialchars($username) . "</b></li>\n";
			}
			echo "</ul><p>" . sprintf($upgrade_phrases['upgrade1.php']['download_semicolon_users'], "upgrade1.php?step=$step&amp;do=downloadillegalusers") . "</p>";
		}
		else
		{
			echo "<p>{$upgrade_phrases['upgrade1.php']['no_illegal_users_found']}</p>";
		}
	}
}

// #############################################################################
// The End Part 1 - Do things that would cause the admin to no longer be able to run this script.
if ($step == 41)
{
	// Update options from the vb2 style to the vb3 style but leave them in place
	// until the end so that this script can use them.
	// this should generate $vboptions and stick it in the datastore table
	require_once('./includes/adminfunctions_options.php');

	$datastore = $DB_site->query_first("SELECT data FROM datastore WHERE title='options'");
	$vboptions = unserialize($datastore['data']);

	$path = './install/vbulletin-settings.xml';
	xml_import_settings();

	echo "<p>{$upgrade_phrases['upgrade1.php']['settings_imported_sucessfully']}</p>";

	// Remove old options template
	$query[] = "DELETE FROM template WHERE title = 'options'";
	$explain[] = $upgrade_phrases['upgrade1.php']['remove_old_settings_storage'];

	$query[] = "ALTER TABLE usergroup DROP cancontrolpanel";
	$explain[] = sprintf($vbphrase['alter_table'], TABLE_PREFIX . "usergroup");

	$users = $DB_site->query("SELECT userid, username, salt, password FROM user WHERE usergroupid = 6");
	while($user = $DB_site->fetch_array($users))
	{
		$query[] = "UPDATE user SET password = '" . addslashes(md5($user['password'] . $user['salt'])) . "' WHERE userid = $user[userid]";
		$explain[] = sprintf($upgrade_phrases['upgrade1.php']['salt_admin_x'], $user['username']);
	}

	exec_queries();

	echo_flush('<p>' . $upgrade_phrases['upgrade1.php']['build_forum_and_usergroup_cache']);
	build_forum_permissions();

	echo_flush($vbphrase['done'] . '</p>');
}

// #############################################################################
// The End Part 2
if ($step == 42)
{
	// update datastore caches and values
	// doesn't matter if these get run multiple times
	build_image_cache('smilie');
	build_image_cache('avatar');
	build_image_cache('icon');
	build_bbcode_cache();
	require_once('./includes/functions_databuild.php');
	build_user_statistics();

	?>
	<blockquote>
	<p><?php echo "{$upgrade_phrases['upgrade1.php']['upgrade_complete']}"; ?></p>
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
|| # Downloaded: 04:52, Wed Oct 21st 2026
|| # CVS: $RCSfile$ - $Revision: 12989 $
|| ####################################################################
\*======================================================================*/
?>