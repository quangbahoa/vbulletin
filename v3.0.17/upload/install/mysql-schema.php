<?php

error_reporting(E_ALL & ~E_NOTICE);

define('SCHEMA', 'mysql');

$phrasegroups = array();
$specialtemplates = array();

// Check userfield table is still used and how long the default length should be

$schema['CREATE']['query']['access'] = "
CREATE TABLE " . TABLE_PREFIX . "access (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	accessmask SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY userid (userid, forumid)
)
";
$schema['CREATE']['explain']['access'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "access");

$schema['CREATE']['query']['adminhelp'] = "
CREATE TABLE " . TABLE_PREFIX . "adminhelp (
	adminhelpid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	script VARCHAR(50) NOT NULL DEFAULT '',
	action VARCHAR(25) NOT NULL DEFAULT '',
	optionname VARCHAR(25) NOT NULL DEFAULT '',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (adminhelpid),
	UNIQUE KEY phraseunique (script, action, optionname)
)
";
$schema['CREATE']['explain']['adminhelp'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminhelp");



$schema['CREATE']['query']['administrator'] = "
CREATE TABLE " . TABLE_PREFIX . "administrator (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	adminpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	navprefs VARCHAR(250) NOT NULL,
	cssprefs VARCHAR(250) NOT NULL,
	notes MEDIUMTEXT NOT NULL,
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['administrator'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "administrator");



$schema['CREATE']['query']['adminlog'] = "
CREATE TABLE " . TABLE_PREFIX . "adminlog (
	adminlogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	script VARCHAR(50) NOT NULL DEFAULT '',
	action VARCHAR(20) NOT NULL DEFAULT '',
	extrainfo VARCHAR(200) NOT NULL DEFAULT '',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	PRIMARY KEY (adminlogid)
)
";
$schema['CREATE']['explain']['adminlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminlog");



$schema['CREATE']['query']['adminutil'] = "
CREATE TABLE " . TABLE_PREFIX . "adminutil (
	title VARCHAR(50) NOT NULL DEFAULT '',
	text MEDIUMTEXT NOT NULL,
	PRIMARY KEY (title)
)
";
$schema['CREATE']['explain']['adminutil'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "adminutil");



$schema['CREATE']['query']['announcement'] = "
CREATE TABLE " . TABLE_PREFIX . "announcement (
	announcementid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	startdate INT UNSIGNED NOT NULL DEFAULT '0',
	enddate INT UNSIGNED NOT NULL DEFAULT '0',
	pagetext MEDIUMTEXT NOT NULL,
	allowhtml SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	allowbbcode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	allowsmilies SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT NOT NULL DEFAULT '0',
	views INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (announcementid),
	KEY forumid (forumid)
)
";
$schema['CREATE']['explain']['announcement'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "announcement");



$schema['CREATE']['query']['attachment'] = "
CREATE TABLE " . TABLE_PREFIX . "attachment (
	attachmentid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	thumbnail_dateline INT UNSIGNED NOT NULL DEFAULT '0',
	filename VARCHAR(100) NOT NULL DEFAULT '',
	filedata MEDIUMBLOB NOT NULL,
	visible SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	counter SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	filesize INT UNSIGNED NOT NULL DEFAULT '0',
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	filehash CHAR(32) NOT NULL DEFAULT '',
	posthash CHAR(32) NOT NULL DEFAULT '',
	thumbnail MEDIUMBLOB NOT NULL,
	thumbnail_filesize INT UNSIGNED NOT NULL,
	PRIMARY KEY (attachmentid),
	KEY filesize (filesize),
	KEY filehash (filehash),
	KEY userid (userid),
	KEY posthash (posthash, userid),
	KEY postid (postid)
)
";
$schema['CREATE']['explain']['attachment'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachment");



$schema['CREATE']['query']['attachmenttype'] = "
CREATE TABLE " . TABLE_PREFIX . "attachmenttype (
	extension CHAR(20) NOT NULL DEFAULT '',
	mimetype VARCHAR(255) NOT NULL DEFAULT '',
	size INT UNSIGNED NOT NULL DEFAULT '0',
	width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	enabled SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	display SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (extension)
)
";
$schema['CREATE']['explain']['attachmenttype'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmenttype");



$schema['CREATE']['query']['attachmentviews'] = "
CREATE TABLE " . TABLE_PREFIX . "attachmentviews (
	attachmentid INT UNSIGNED NOT NULL DEFAULT '0',
	KEY postid (attachmentid)
)
";
$schema['CREATE']['explain']['attachmentviews'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "attachmentviews");



$schema['CREATE']['query']['avatar'] = "
CREATE TABLE " . TABLE_PREFIX . "avatar (
	avatarid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(100) NOT NULL DEFAULT '',
	minimumposts SMALLINT NOT NULL DEFAULT '0',
	avatarpath VARCHAR(100) NOT NULL DEFAULT '',
	imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (avatarid)
)
";
$schema['CREATE']['explain']['avatar'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "avatar");



$schema['CREATE']['query']['bbcode'] = "
CREATE TABLE " . TABLE_PREFIX . "bbcode (
	bbcodeid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	bbcodetag VARCHAR(200) NOT NULL DEFAULT '',
	bbcodereplacement MEDIUMTEXT NOT NULL,
	bbcodeexample VARCHAR(200) NOT NULL DEFAULT '',
	bbcodeexplanation MEDIUMTEXT NOT NULL,
	twoparams SMALLINT NOT NULL DEFAULT '0',
	title VARCHAR(100) NOT NULL DEFAULT '',
	buttonimage VARCHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (bbcodeid),
	UNIQUE KEY uniquetag (bbcodetag, twoparams)
)
";
$schema['CREATE']['explain']['bbcode'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "bbcode");



$schema['CREATE']['query']['calendar'] = "
CREATE TABLE " . TABLE_PREFIX . "calendar (
	calendarid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(255) NOT NULL DEFAULT '',
	description VARCHAR(100) NOT NULL DEFAULT '',
	displayorder SMALLINT NOT NULL DEFAULT '0',
	neweventemail VARCHAR(255) NOT NULL DEFAULT '',
	moderatenew SMALLINT NOT NULL DEFAULT '0',
	startofweek SMALLINT NOT NULL DEFAULT '0',
	options INT UNSIGNED NOT NULL DEFAULT '0',
	cutoff SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	eventcount SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	birthdaycount SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	startyear SMALLINT UNSIGNED NOT NULL DEFAULT '2000',
	endyear SMALLINT UNSIGNED NOT NULL DEFAULT '2006',
	holidays INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarid),
	KEY displayorder (displayorder)
)
";
$schema['CREATE']['explain']['calendar'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendar");



$schema['CREATE']['query']['calendarcustomfield'] = "
CREATE TABLE " . TABLE_PREFIX . "calendarcustomfield (
	calendarcustomfieldid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	calendarid INT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(255) NOT NULL DEFAULT '',
	description MEDIUMTEXT NOT NULL,
	options MEDIUMTEXT NOT NULL,
	allowentry SMALLINT NOT NULL DEFAULT '1',
	required SMALLINT NOT NULL DEFAULT '0',
	length SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarcustomfieldid),
	KEY calendarid (calendarid)
)
";
$schema['CREATE']['explain']['calendarcustomfield'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarcustomfield");



$schema['CREATE']['query']['calendarmoderator'] = "
CREATE TABLE " . TABLE_PREFIX . "calendarmoderator (
 	calendarmoderatorid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	calendarid INT UNSIGNED NOT NULL DEFAULT '0',
	neweventemail SMALLINT NOT NULL DEFAULT '0',
	permissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarmoderatorid),
	KEY userid (userid, calendarid)
)
";
$schema['CREATE']['explain']['calendarmoderator'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarmoderator");



$schema['CREATE']['query']['calendarpermission'] = "
CREATE TABLE " . TABLE_PREFIX . "calendarpermission (
	calendarpermissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	calendarid INT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	calendarpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (calendarpermissionid),
	KEY calendarid (calendarid),
	KEY usergroupid (usergroupid)
)
";
$schema['CREATE']['explain']['calendarpermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "calendarpermission");



$schema['CREATE']['query']['cpsession'] = "
CREATE TABLE " . TABLE_PREFIX . "cpsession (
		userid INT UNSIGNED NOT NULL DEFAULT '0',
		hash VARCHAR(32) NOT NULL DEFAULT '',
		dateline INT UNSIGNED NOT NULL DEFAULT '0',
		PRIMARY KEY (userid, hash)
)
";
$schema['CREATE']['explain']['cpsession'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cpsession");



$schema['CREATE']['query']['cron'] = "
CREATE TABLE " . TABLE_PREFIX . "cron (
	cronid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	nextrun INT UNSIGNED NOT NULL DEFAULT '0',
	weekday SMALLINT NOT NULL DEFAULT '0',
	day SMALLINT NOT NULL DEFAULT '0',
	hour SMALLINT NOT NULL DEFAULT '0',
	minute SMALLINT NOT NULL DEFAULT '0',
	filename CHAR(50) NOT NULL DEFAULT '',
	loglevel SMALLINT NOT NULL DEFAULT '0',
	title VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (cronid),
	KEY nextrun (nextrun)
)
";
$schema['CREATE']['explain']['cron'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cron");



$schema['CREATE']['query']['cronlog'] = "
CREATE TABLE " . TABLE_PREFIX . "cronlog (
	cronlogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	cronid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	description MEDIUMTEXT NOT NULL,
	PRIMARY KEY (cronlogid),
	KEY cronid (cronid)
)
";
$schema['CREATE']['explain']['cronlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "cronlog");



$schema['CREATE']['query']['customavatar'] = "
CREATE TABLE " . TABLE_PREFIX . "customavatar (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	avatardata MEDIUMBLOB NOT NULL,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	filename VARCHAR(100) NOT NULL DEFAULT '',
	visible SMALLINT NOT NULL DEFAULT '1',
	filesize INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['customavatar'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "customavatar");



$schema['CREATE']['query']['customprofilepic'] = "
CREATE TABLE " . TABLE_PREFIX . "customprofilepic (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	profilepicdata MEDIUMBLOB NOT NULL,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	filename VARCHAR(100) NOT NULL DEFAULT '',
	visible SMALLINT NOT NULL DEFAULT '1',
	filesize INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['customprofilepic'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "customprofilepic");



$schema['CREATE']['query']['datastore'] = "
CREATE TABLE " . TABLE_PREFIX . "datastore (
	title CHAR(15) NOT NULL DEFAULT '',
	data MEDIUMTEXT NOT NULL,
	PRIMARY KEY (title)
)
";
$schema['CREATE']['explain']['datastore'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "datastore");

$schema['CREATE']['query']['deletionlog'] = "
CREATE TABLE " . TABLE_PREFIX . "deletionlog (
	primaryid INT UNSIGNED NOT NULL DEFAULT '0',
	type ENUM('post', 'thread') NOT NULL DEFAULT 'post',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(50) NOT NULL DEFAULT '',
	reason VARCHAR(125) NOT NULL DEFAULT '',
	PRIMARY KEY (primaryid, type)
)
";
$schema['CREATE']['explain']['deletionlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "deletionlog");



$schema['CREATE']['query']['editlog'] = "
CREATE TABLE " . TABLE_PREFIX . "editlog (
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(50) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	reason VARCHAR(200) NOT NULL DEFAULT '',
	PRIMARY KEY (postid)
)
";
$schema['CREATE']['explain']['editlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "editlog");



$schema['CREATE']['query']['event'] = "
CREATE TABLE " . TABLE_PREFIX . "event (
	eventid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	event MEDIUMTEXT NOT NULL,
	title VARCHAR(250) NOT NULL DEFAULT '',
	allowsmilies SMALLINT NOT NULL DEFAULT '1',
	recurring SMALLINT NOT NULL DEFAULT '0',
	recuroption CHAR(6) NOT NULL DEFAULT '',
	calendarid INT UNSIGNED NOT NULL DEFAULT '0',
	customfields MEDIUMTEXT NOT NULL,
	visible SMALLINT NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	utc SMALLINT NOT NULL DEFAULT '0',
	dateline_from INT UNSIGNED NOT NULL DEFAULT '0',
	dateline_to INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (eventid),
	KEY userid (userid),
	KEY daterange (calendarid, visible, dateline_from, dateline_to)
)
";
$schema['CREATE']['explain']['event'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "event");



$schema['CREATE']['query']['faq'] = "
CREATE TABLE " . TABLE_PREFIX . "faq (
	faqname VARCHAR(50) NOT NULL DEFAULT '',
	faqparent VARCHAR(50) NOT NULL DEFAULT '',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (faqname),
	KEY faqparent (faqparent)
)
";
$schema['CREATE']['explain']['faq'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "faq");



$schema['CREATE']['query']['forum'] = "
CREATE TABLE " . TABLE_PREFIX . "forum (
	forumid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(100) NOT NULL DEFAULT '',
	description VARCHAR(250) NOT NULL DEFAULT '',
	options INT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT NOT NULL DEFAULT '0',
	replycount INT UNSIGNED NOT NULL DEFAULT '0',
	lastpost INT NOT NULL DEFAULT '0',
	lastposter VARCHAR(50) NOT NULL DEFAULT '',
	lastthread VARCHAR(250) NOT NULL DEFAULT '',
	lastthreadid INT UNSIGNED NOT NULL DEFAULT '0',
	lasticonid SMALLINT NOT NULL DEFAULT '0',
	threadcount mediumint UNSIGNED NOT NULL DEFAULT '0',
	daysprune SMALLINT NOT NULL DEFAULT '0',
	newpostemail VARCHAR(250) NOT NULL DEFAULT '',
	newthreademail VARCHAR(250) NOT NULL DEFAULT '',
	parentid SMALLINT NOT NULL DEFAULT '0',
	parentlist VARCHAR(250) NOT NULL DEFAULT '',
	password VARCHAR(50) NOT NULL DEFAULT '',
	link VARCHAR(200) NOT NULL DEFAULT '',
	childlist VARCHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (forumid)
)
";
$schema['CREATE']['explain']['forum'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "forum");



$schema['CREATE']['query']['forumpermission'] = "
CREATE TABLE " . TABLE_PREFIX . "forumpermission (
	forumpermissionid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	forumpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (forumpermissionid),
	UNIQUE KEY ugid_fid (usergroupid, forumid)
)
";
$schema['CREATE']['explain']['forumpermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "forumpermission");



$schema['CREATE']['query']['holiday'] = "
CREATE TABLE " . TABLE_PREFIX . "holiday (
	holidayid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	varname VARCHAR(100) NOT NULL DEFAULT '',
	recurring SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	recuroption CHAR(6) NOT NULL DEFAULT '',
	allowsmilies SMALLINT NOT NULL DEFAULT '1',
	PRIMARY KEY (holidayid),
	KEY varname (varname)
)
";
$schema['CREATE']['explain']['holiday'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "holiday");



$schema['CREATE']['query']['icon'] = "
CREATE TABLE " . TABLE_PREFIX . "icon (
	iconid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(100) NOT NULL DEFAULT '',
	iconpath VARCHAR(100) NOT NULL DEFAULT '',
	imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (iconid)
)
";
$schema['CREATE']['explain']['icon'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "icon");



$schema['CREATE']['query']['imagecategory'] = "
CREATE TABLE " . TABLE_PREFIX . "imagecategory (
	imagecategoryid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(255) NOT NULL DEFAULT '',
	imagetype SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (imagecategoryid)
)
";
$schema['CREATE']['explain']['imagecategory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "imagecategory");



$schema['CREATE']['query']['imagecategorypermission'] = "
CREATE TABLE " . TABLE_PREFIX . "imagecategorypermission (
	imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	KEY imagecategoryid (imagecategoryid, usergroupid)
)
";
$schema['CREATE']['explain']['imagecategorypermission'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "imagecategorypermission");



$schema['CREATE']['query']['language'] = "
CREATE TABLE " . TABLE_PREFIX . "language (
	languageid smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(50) NOT NULL default '',
	userselect smallint(5) UNSIGNED NOT NULL default '1',
	options smallint(5) UNSIGNED NOT NULL default '1',
	languagecode VARCHAR(12) NOT NULL default '',
	charset VARCHAR(15) NOT NULL default '',
	imagesoverride VARCHAR(150) NOT NULL default '',
	dateoverride VARCHAR(50) NOT NULL default '',
	timeoverride VARCHAR(50) NOT NULL default '',
	registereddateoverride VARCHAR(50) NOT NULL default '',
	calformat1override VARCHAR(50) NOT NULL default '',
	calformat2override VARCHAR(50) NOT NULL default '',
	logdateoverride VARCHAR(50) NOT NULL default '',
	locale VARCHAR(20) NOT NULL default '',
	decimalsep CHAR(1) NOT NULL default '.',
	thousandsep CHAR(1) NOT NULL default ',',
	phrasegroup_global MEDIUMTEXT NOT NULL,
	phrasegroup_cpglobal MEDIUMTEXT NOT NULL,
	phrasegroup_cppermission MEDIUMTEXT NOT NULL,
	phrasegroup_forum MEDIUMTEXT NOT NULL,
	phrasegroup_calendar MEDIUMTEXT NOT NULL,
	phrasegroup_attachment_image MEDIUMTEXT NOT NULL,
	phrasegroup_style MEDIUMTEXT NOT NULL,
	phrasegroup_logging MEDIUMTEXT NOT NULL,
	phrasegroup_cphome MEDIUMTEXT NOT NULL,
	phrasegroup_promotion MEDIUMTEXT NOT NULL,
	phrasegroup_user MEDIUMTEXT NOT NULL,
	phrasegroup_help_faq MEDIUMTEXT NOT NULL,
	phrasegroup_sql MEDIUMTEXT NOT NULL,
	phrasegroup_subscription MEDIUMTEXT NOT NULL,
	phrasegroup_language MEDIUMTEXT NOT NULL,
	phrasegroup_bbcode MEDIUMTEXT NOT NULL,
	phrasegroup_stats MEDIUMTEXT NOT NULL,
	phrasegroup_diagnostic MEDIUMTEXT NOT NULL,
	phrasegroup_maintenance MEDIUMTEXT NOT NULL,
	phrasegroup_profilefield MEDIUMTEXT NOT NULL,
	phrasegroup_thread MEDIUMTEXT NOT NULL,
	phrasegroup_timezone MEDIUMTEXT NOT NULL,
	phrasegroup_banning MEDIUMTEXT NOT NULL,
	phrasegroup_reputation MEDIUMTEXT NOT NULL,
	phrasegroup_wol MEDIUMTEXT NOT NULL,
	phrasegroup_threadmanage MEDIUMTEXT NOT NULL,
	phrasegroup_pm MEDIUMTEXT NOT NULL,
	phrasegroup_cpuser MEDIUMTEXT NOT NULL,
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


$schema['CREATE']['explain']['language'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "language");



$schema['CREATE']['query']['mailqueue'] = "
CREATE TABLE " . TABLE_PREFIX . "mailqueue (
	mailqueueid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	toemail MEDIUMTEXT NOT NULL,
	subject MEDIUMTEXT NOT NULL,
	message MEDIUMTEXT NOT NULL,
	header MEDIUMTEXT NOT NULL,
	PRIMARY KEY (mailqueueid)
)
";
$schema['CREATE']['explain']['mailqueue'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "mailqueue");



$schema['CREATE']['query']['moderation'] = "
CREATE TABLE " . TABLE_PREFIX . "moderation (
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	type ENUM('thread', 'reply') NOT NULL DEFAULT 'thread',
	PRIMARY KEY (postid, type),
	KEY type (type)
)
";
$schema['CREATE']['explain']['moderation'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "moderation");



$schema['CREATE']['query']['moderator'] = "
CREATE TABLE " . TABLE_PREFIX . "moderator (
	moderatorid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT NOT NULL DEFAULT '0',
	permissions INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (moderatorid),
	KEY userid (userid, forumid)
)
";
$schema['CREATE']['explain']['moderator'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "moderator");



$schema['CREATE']['query']['moderatorlog'] = "
CREATE TABLE " . TABLE_PREFIX . "moderatorlog (
	moderatorlogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	pollid INT UNSIGNED NOT NULL DEFAULT '0',
	action VARCHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (moderatorlogid),
	KEY threadid (threadid)
)
";
$schema['CREATE']['explain']['moderatorlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "moderatorlog");



$schema['CREATE']['query']['passwordhistory'] = "
CREATE TABLE " . TABLE_PREFIX . "passwordhistory (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	password VARCHAR(50) NOT NULL DEFAULT '',
	passworddate date NOT NULL DEFAULT '0000-00-00',
	KEY userid (userid)
)
";
$schema['CREATE']['explain']['passwordhistory'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "passwordhistory");



$schema['CREATE']['query']['phrase'] = "
CREATE TABLE " . TABLE_PREFIX . "phrase (
	phraseid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	languageid SMALLINT NOT NULL DEFAULT '0',
	varname VARCHAR(250) BINARY NOT NULL DEFAULT '',
	text MEDIUMTEXT NOT NULL,
	phrasetypeid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY  (phraseid),
	UNIQUE KEY name_lang_type (varname, languageid, phrasetypeid),
	KEY languageid (languageid,phrasetypeid)
)
";
$schema['CREATE']['explain']['phrase'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "phrase");



$schema['CREATE']['query']['phrasetype'] = "
CREATE TABLE " . TABLE_PREFIX . "phrasetype (
	phrasetypeid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	fieldname CHAR(20) NOT NULL default '',
	title CHAR(50) NOT NULL DEFAULT '',
	editrows SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (phrasetypeid)
)
";
$schema['CREATE']['explain']['phrasetype'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "phrasetype");



$schema['CREATE']['query']['pm'] = "
CREATE TABLE " . TABLE_PREFIX . "pm (
	pmid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	pmtextid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	folderid SMALLINT NOT NULL DEFAULT '0',
	messageread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pmid),
	KEY pmtextid (pmtextid),
	KEY userid (userid),
	KEY folderid (folderid)
)
";
$schema['CREATE']['explain']['pm'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pm");



$schema['CREATE']['query']['pmreceipt'] = "
CREATE TABLE " . TABLE_PREFIX . "pmreceipt (
	pmid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	touserid INT UNSIGNED NOT NULL DEFAULT '0',
	tousername VARCHAR(50) NOT NULL DEFAULT '',
	title VARCHAR(250) NOT NULL DEFAULT '',
	sendtime INT UNSIGNED NOT NULL DEFAULT '0',
	readtime INT UNSIGNED NOT NULL DEFAULT '0',
	denied SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pmid),
	KEY userid (userid),
	KEY touserid (touserid)
)
";
$schema['CREATE']['explain']['pmreceipt'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pmreceipt");



$schema['CREATE']['query']['pmtext'] = "
CREATE TABLE " . TABLE_PREFIX . "pmtext (
	pmtextid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	fromuserid INT UNSIGNED NOT NULL DEFAULT '0',
	fromusername VARCHAR(50) NOT NULL DEFAULT '',
	title VARCHAR(250) NOT NULL DEFAULT '',
	message MEDIUMTEXT NOT NULL,
	touserarray MEDIUMTEXT NOT NULL,
	iconid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	showsignature SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (pmtextid),
	KEY fromuserid (fromuserid)
)
";
$schema['CREATE']['explain']['pmtext'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pmtext");



$schema['CREATE']['query']['poll'] = "
CREATE TABLE " . TABLE_PREFIX . "poll (
	pollid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	question VARCHAR(100) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	options text NOT NULL,
	votes text NOT NULL,
	active SMALLINT NOT NULL DEFAULT '1',
	numberoptions SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	timeout SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	multiple SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	voters SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	public SMALLINT NOT NULL DEFAULT '0',
	lastvote INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pollid)
)
";
$schema['CREATE']['explain']['poll'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "poll");



$schema['CREATE']['query']['pollvote'] = "
CREATE TABLE " . TABLE_PREFIX . "pollvote (
	pollvoteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	pollid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	votedate INT UNSIGNED NOT NULL DEFAULT '0',
	voteoption INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (pollvoteid),
	KEY pollid (pollid, userid)
)
";
$schema['CREATE']['explain']['pollvote'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "pollvote");



$schema['CREATE']['query']['post'] = "
CREATE TABLE " . TABLE_PREFIX . "post (
	postid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	parentid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(50) NOT NULL DEFAULT '',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(250) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	pagetext MEDIUMTEXT NOT NULL,
	allowsmilie SMALLINT NOT NULL DEFAULT '0',
	showsignature SMALLINT NOT NULL DEFAULT '0',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	iconid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	visible SMALLINT NOT NULL DEFAULT '0',
	attach SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (postid),
	KEY iconid (iconid),
	KEY userid (userid),
	KEY threadid (threadid, userid)
)
";
$schema['CREATE']['explain']['post'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "post");



$schema['CREATE']['query']['post_parsed'] = "
CREATE TABLE " . TABLE_PREFIX . "post_parsed (
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	hasimages SMALLINT NOT NULL DEFAULT '0',
	pagetext_html MEDIUMTEXT NOT NULL,
	PRIMARY KEY (postid),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['post_parsed'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "post_parsed");



$schema['CREATE']['query']['posthash'] = "
CREATE TABLE " . TABLE_PREFIX . "posthash (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	dupehash CHAR(32) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	KEY userid (userid, dupehash),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['posthash'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "posthash");



$schema['CREATE']['query']['profilefield'] = "
CREATE TABLE " . TABLE_PREFIX . "profilefield (
	profilefieldid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(50) NOT NULL DEFAULT '',
	description VARCHAR(250) NOT NULL DEFAULT '',
	required SMALLINT NOT NULL DEFAULT '0',
	hidden SMALLINT NOT NULL DEFAULT '0',
	maxlength SMALLINT NOT NULL DEFAULT '250',
	size SMALLINT NOT NULL DEFAULT '25',
	displayorder SMALLINT NOT NULL DEFAULT '0',
	editable SMALLINT NOT NULL DEFAULT '1',
	type ENUM('input','select','radio','textarea','checkbox','select_multiple') NOT NULL DEFAULT 'input',
	data MEDIUMTEXT NOT NULL,
	height SMALLINT NOT NULL DEFAULT '0',
	def SMALLINT NOT NULL DEFAULT '0',
	optional SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	searchable SMALLINT NOT NULL DEFAULT '0',
	memberlist SMALLINT NOT NULL DEFAULT '0',
	regex VARCHAR(255) NOT NULL DEFAULT '',
	form SMALLINT NOT NULL DEFAULT '0',
	html SMALLINT NOT NULL DEFAULT '0',
	PRIMARY KEY (profilefieldid),
	KEY editable (editable)
)
";
$schema['CREATE']['explain']['profilefield'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "profilefield");



$schema['CREATE']['query']['ranks'] = "
CREATE TABLE " . TABLE_PREFIX . "ranks (
	rankid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	minposts SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	ranklevel SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	rankimg MEDIUMTEXT NOT NULL,
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	type SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (rankid),
	KEY grouprank (usergroupid, minposts)
)
";
$schema['CREATE']['explain']['ranks'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "ranks");



$schema['CREATE']['query']['regimage'] = "
CREATE TABLE " . TABLE_PREFIX . "regimage (
	regimagehash CHAR(32) NOT NULL DEFAULT '',
	imagestamp CHAR(6) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	KEY regimagehash (regimagehash, dateline)
)
";
$schema['CREATE']['explain']['regimage'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "regimage");



$schema['CREATE']['query']['reminder'] = "
CREATE TABLE " . TABLE_PREFIX . "reminder (
	reminderid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	title VARCHAR(50) NOT NULL DEFAULT '',
	text MEDIUMTEXT NOT NULL,
	duedate INT UNSIGNED NOT NULL DEFAULT '0',
	adminonly SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	completedby INT UNSIGNED NOT NULL DEFAULT '0',
	completedtime INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (reminderid)
)
";
$schema['CREATE']['explain']['reminder'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reminder");



$schema['CREATE']['query']['reputation'] = "
CREATE TABLE " . TABLE_PREFIX . "reputation (
	reputationid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	postid INT NOT NULL DEFAULT '1',
	userid INT NOT NULL DEFAULT '1',
	reputation INT NOT NULL DEFAULT '0',
	whoadded INT NOT NULL DEFAULT '0',
	reason VARCHAR(250) DEFAULT NULL,
	dateline INT NOT NULL DEFAULT '0',
	PRIMARY KEY (reputationid),
	KEY userid (userid),
	KEY whoadded (whoadded),
	KEY multi (postid, userid),
	KEY dateline (dateline)
)
";
$schema['CREATE']['explain']['reputation'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reputation");



$schema['CREATE']['query']['reputationlevel'] = "
CREATE TABLE " . TABLE_PREFIX . "reputationlevel (
	reputationlevelid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	minimumreputation INT NOT NULL DEFAULT '0',
	level VARCHAR(250) DEFAULT NULL,
	PRIMARY KEY (reputationlevelid),
	KEY reputationlevel (minimumreputation)
)
";
$schema['CREATE']['explain']['reputationlevel'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "reputationlevel");



$schema['CREATE']['query']['search'] = "
CREATE TABLE " . TABLE_PREFIX . "search (
	searchid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	personal SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	query VARCHAR(200) NOT NULL DEFAULT '',
	searchuser VARCHAR(200) NOT NULL DEFAULT '',
	forumchoice MEDIUMTEXT NOT NULL,
	sortby VARCHAR(200) NOT NULL DEFAULT '',
	sortorder VARCHAR(4) NOT NULL DEFAULT '',
	searchtime float NOT NULL DEFAULT '0',
	showposts SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	orderedids MEDIUMTEXT NOT NULL,
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	searchterms MEDIUMTEXT NOT NULL,
	displayterms MEDIUMTEXT NOT NULL,
	searchhash VARCHAR(32) NOT NULL DEFAULT '',
	PRIMARY KEY (searchid),
	UNIQUE KEY searchunique (searchhash, sortby, sortorder)
)
";
$schema['CREATE']['explain']['search'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "search");



$schema['CREATE']['query']['postindex'] = "
CREATE TABLE " . TABLE_PREFIX . "postindex (
	wordid INT UNSIGNED NOT NULL DEFAULT '0',
	postid INT UNSIGNED NOT NULL DEFAULT '0',
	intitle SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	score SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	UNIQUE KEY wordid (wordid, postid)
)
";
$schema['CREATE']['explain']['postindex'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "postindex");



$schema['CREATE']['query']['session'] = "
CREATE TABLE " . TABLE_PREFIX . "session (
	sessionhash CHAR(32) NOT NULL DEFAULT '',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	host CHAR(15) NOT NULL DEFAULT '',
	idhash CHAR(32) NOT NULL DEFAULT '',
	lastactivity INT UNSIGNED NOT NULL DEFAULT '0',
	location CHAR(255) NOT NULL DEFAULT '',
	useragent CHAR(100) NOT NULL DEFAULT '',
	styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	loggedin SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	inforum SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	inthread INT UNSIGNED NOT NULL DEFAULT '0',
	incalendar SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	badlocation SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	bypass TINYINT NOT NULL DEFAULT '0',
	PRIMARY KEY (sessionhash)
)
";
$schema['CREATE']['explain']['session'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "session");



$schema['CREATE']['query']['setting'] = "
CREATE TABLE " . TABLE_PREFIX . "setting (
	varname VARCHAR(100) NOT NULL DEFAULT '',
	grouptitle VARCHAR(50) NOT NULL DEFAULT '',
	value MEDIUMTEXT NOT NULL,
	defaultvalue MEDIUMTEXT NOT NULL,
	optioncode MEDIUMTEXT NOT NULL,
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	advanced SMALLINT NOT NULL DEFAULT '0',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (varname)
)
";
$schema['CREATE']['explain']['setting'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "setting");



$schema['CREATE']['query']['settinggroup'] = "
CREATE TABLE " . TABLE_PREFIX . "settinggroup (
	grouptitle CHAR(50) NOT NULL DEFAULT '',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	volatile SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (grouptitle)
)
";
$schema['CREATE']['explain']['settinggroup'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "settinggroup");



$schema['CREATE']['query']['smilie'] = "
CREATE TABLE " . TABLE_PREFIX . "smilie (
	smilieid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title CHAR(100) NOT NULL DEFAULT '',
	smilietext CHAR(10) NOT NULL DEFAULT '',
	smiliepath CHAR(100) NOT NULL DEFAULT '',
	imagecategoryid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (smilieid)
)
";
$schema['CREATE']['explain']['smilie'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "smilie");



$schema['CREATE']['query']['stats'] = "
CREATE TABLE " . TABLE_PREFIX . "stats (
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	nuser mediumint UNSIGNED NOT NULL DEFAULT '0',
	nthread mediumint UNSIGNED NOT NULL DEFAULT '0',
	npost mediumint UNSIGNED NOT NULL DEFAULT '0',
	ausers mediumint UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (dateline)
)
";
$schema['CREATE']['explain']['stats'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "stats");



$schema['CREATE']['query']['strikes'] = "
CREATE TABLE " . TABLE_PREFIX . "strikes (
	striketime INT UNSIGNED NOT NULL DEFAULT '0',
	strikeip CHAR(15) NOT NULL DEFAULT '',
	username CHAR(50) NOT NULL DEFAULT '',
	KEY striketime (striketime),
	KEY strikeip (strikeip)
)
";
$schema['CREATE']['explain']['strikes'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "strikes");



$schema['CREATE']['query']['style'] = "
CREATE TABLE " . TABLE_PREFIX . "style (
	styleid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	parentid SMALLINT NOT NULL DEFAULT '0',
	parentlist VARCHAR(250) NOT NULL DEFAULT '',
	templatelist MEDIUMTEXT NOT NULL,
	csscolors MEDIUMTEXT NOT NULL,
	css MEDIUMTEXT NOT NULL,
	stylevars MEDIUMTEXT NOT NULL,
	replacements MEDIUMTEXT NOT NULL,
	editorstyles MEDIUMTEXT NOT NULL,
	userselect SMALLINT UNSIGNED NOT NULL DEFAULT '1',
	displayorder SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (styleid)
)
";
$schema['CREATE']['explain']['style'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "style");



$schema['CREATE']['query']['subscribeevent'] = "
CREATE TABLE " . TABLE_PREFIX . "subscribeevent (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	eventid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid,eventid),
	KEY eventid (eventid)
)
";
$schema['CREATE']['explain']['subscribeevent'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribeevent");



$schema['CREATE']['query']['subscribeforum'] = "
CREATE TABLE " . TABLE_PREFIX . "subscribeforum (
	subscribeforumid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	emailupdate SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (subscribeforumid),
	UNIQUE KEY subindex (userid, forumid),
	KEY forumid (forumid)
)
";
$schema['CREATE']['explain']['subscribeforum'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribeforum");



$schema['CREATE']['query']['subscribethread'] = "
CREATE TABLE " . TABLE_PREFIX . "subscribethread (
	subscribethreadid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	emailupdate SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	folderid INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (subscribethreadid),
	UNIQUE KEY indexname (userid, threadid),
	KEY threadid (threadid)
)
";
$schema['CREATE']['explain']['subscribethread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscribethread");



$schema['CREATE']['query']['subscription'] = "
CREATE TABLE " . TABLE_PREFIX . "subscription (
	subscriptionid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(100) NOT NULL DEFAULT '',
	description VARCHAR(255) NOT NULL DEFAULT '',
	cost VARCHAR(255) NOT NULL DEFAULT '',
	length CHAR(10) NOT NULL DEFAULT '',
	units CHAR(1) NOT NULL DEFAULT '',
	forums MEDIUMTEXT NOT NULL,
	nusergroupid SMALLINT NOT NULL DEFAULT '0',
	membergroupids VARCHAR(255) NOT NULL DEFAULT '',
	active SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (subscriptionid)
)
";
$schema['CREATE']['explain']['subscription'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscription");



$schema['CREATE']['query']['subscriptionlog'] = "
CREATE TABLE " . TABLE_PREFIX . "subscriptionlog (
	subscriptionlogid MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
	subscriptionid SMALLINT NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	pusergroupid SMALLINT NOT NULL DEFAULT '0',
	status SMALLINT NOT NULL DEFAULT '0',
	regdate INT UNSIGNED NOT NULL DEFAULT '0',
	expirydate INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (subscriptionlogid)
)
";
$schema['CREATE']['explain']['subscriptionlog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "subscriptionlog");



$schema['CREATE']['query']['template'] = "
CREATE TABLE " . TABLE_PREFIX . "template (
	templateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	styleid SMALLINT NOT NULL DEFAULT '0',
	title VARCHAR(100) NOT NULL DEFAULT '',
	template MEDIUMTEXT NOT NULL,
	template_un MEDIUMTEXT NOT NULL,
	templatetype enum('template','stylevar','css','replacement') NOT NULL default 'template',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(50) NOT NULL DEFAULT '',
	version VARCHAR(30) NOT NULL DEFAULT '',
	PRIMARY KEY (templateid),
	UNIQUE KEY title (title, styleid)
)
";
$schema['CREATE']['explain']['template'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "template");



$schema['CREATE']['query']['thread'] = "
CREATE TABLE " . TABLE_PREFIX . "thread (
	threadid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(250) NOT NULL DEFAULT '',
	firstpostid INT UNSIGNED NOT NULL DEFAULT '0',
	lastpost INT UNSIGNED NOT NULL DEFAULT '0',
	forumid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pollid INT UNSIGNED NOT NULL DEFAULT '0',
	open SMALLINT NOT NULL DEFAULT '0',
	replycount INT UNSIGNED NOT NULL DEFAULT '0',
	postusername CHAR(50) NOT NULL DEFAULT '',
	postuserid INT UNSIGNED NOT NULL DEFAULT '0',
	lastposter CHAR(50) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	views INT UNSIGNED NOT NULL DEFAULT '0',
	iconid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	notes VARCHAR(250) NOT NULL DEFAULT '',
	visible SMALLINT NOT NULL DEFAULT '0',
	sticky SMALLINT NOT NULL DEFAULT '0',
	votenum SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	votetotal SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	attach SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	similar VARCHAR(55) NOT NULL DEFAULT '',
	PRIMARY KEY (threadid),
	KEY iconid (iconid),
	KEY postuserid (postuserid),
	KEY pollid (pollid),
	KEY forumid (forumid, visible, sticky, lastpost)
)
";
$schema['CREATE']['explain']['thread'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "thread");



$schema['CREATE']['query']['threadrate'] = "
CREATE TABLE " . TABLE_PREFIX . "threadrate (
	threadrateid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	vote SMALLINT NOT NULL DEFAULT '0',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	PRIMARY KEY (threadrateid),
	KEY threadid (threadid, userid)
)
";
$schema['CREATE']['explain']['threadrate'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadrate");



$schema['CREATE']['query']['threadviews'] = "
CREATE TABLE " . TABLE_PREFIX . "threadviews (
	threadid INT UNSIGNED NOT NULL DEFAULT '0',
	KEY threadid (threadid)
)
";
$schema['CREATE']['explain']['threadviews'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "threadviews");



$schema['CREATE']['query']['upgradelog'] = "
CREATE TABLE " . TABLE_PREFIX . "upgradelog (
	upgradelogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	script VARCHAR(50) NOT NULL DEFAULT '',
	steptitle VARCHAR(250) NOT NULL DEFAULT '',
	step smallint(5) UNSIGNED NOT NULL DEFAULT '0',
	startat INT UNSIGNED NOT NULL DEFAULT '0',
	perpage SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (upgradelogid)
)
";
$schema['CREATE']['explain']['upgradelog'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "upgradelog");



$schema['CREATE']['query']['user'] = "
CREATE TABLE " . TABLE_PREFIX . "user (
	userid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	membergroupids CHAR(250) NOT NULL DEFAULT '',
	displaygroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	username CHAR(50) NOT NULL DEFAULT '',
	password CHAR(32) NOT NULL DEFAULT '',
	passworddate date NOT NULL DEFAULT '0000-00-00',
	email CHAR(100) NOT NULL DEFAULT '',
	styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	parentemail CHAR(50) NOT NULL DEFAULT '',
	homepage CHAR(100) NOT NULL DEFAULT '',
	icq CHAR(20) NOT NULL DEFAULT '',
	aim CHAR(20) NOT NULL DEFAULT '',
	yahoo CHAR(32) NOT NULL DEFAULT '',
	showvbcode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usertitle CHAR(250) NOT NULL DEFAULT '',
	customtitle SMALLINT NOT NULL DEFAULT '0',
	joindate INT UNSIGNED NOT NULL DEFAULT '0',
	daysprune SMALLINT NOT NULL DEFAULT '0',
	lastvisit INT UNSIGNED NOT NULL DEFAULT '0',
	lastactivity INT UNSIGNED NOT NULL DEFAULT '0',
	lastpost INT UNSIGNED NOT NULL DEFAULT '0',
	posts INT UNSIGNED NOT NULL DEFAULT '0',
	reputation INT NOT NULL DEFAULT '10',
	reputationlevelid INT UNSIGNED NOT NULL DEFAULT '1',
	timezoneoffset CHAR(4) NOT NULL DEFAULT '',
	pmpopup SMALLINT NOT NULL DEFAULT '0',
	avatarid SMALLINT NOT NULL DEFAULT '0',
	avatarrevision INT UNSIGNED NOT NULL DEFAULT '0',
	options INT UNSIGNED NOT NULL DEFAULT '15',
	birthday CHAR(10) NOT NULL DEFAULT '',
	birthday_search DATE NOT NULL,
	maxposts SMALLINT NOT NULL DEFAULT '-1',
	startofweek SMALLINT NOT NULL DEFAULT '1',
	ipaddress CHAR(15) NOT NULL DEFAULT '',
	referrerid INT UNSIGNED NOT NULL DEFAULT '0',
	languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	msn CHAR(100) NOT NULL DEFAULT '',
	emailstamp INT UNSIGNED NOT NULL DEFAULT '0',
	threadedmode SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	autosubscribe SMALLINT NOT NULL DEFAULT '-1',
	pmtotal SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pmunread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	salt CHAR(3) NOT NULL DEFAULT '',
	PRIMARY KEY (userid),
	KEY usergroupid (usergroupid),
	KEY username (username),
	KEY birthday (birthday),
	KEY birthday_search (birthday_search)
)
";
$schema['CREATE']['explain']['user'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "user");



$schema['CREATE']['query']['useractivation'] = "
CREATE TABLE " . TABLE_PREFIX . "useractivation (
	useractivationid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	activationid bigint UNSIGNED NOT NULL DEFAULT '0',
	type SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (useractivationid),
	KEY userid (userid, type)
)
";
$schema['CREATE']['explain']['useractivation'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "useractivation");



$schema['CREATE']['query']['userban'] = "
CREATE TABLE " . TABLE_PREFIX . "userban (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	displaygroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	usertitle VARCHAR(250) NOT NULL DEFAULT '',
	customtitle SMALLINT NOT NULL DEFAULT '0',
	adminid INT UNSIGNED NOT NULL DEFAULT '0',
	bandate INT UNSIGNED NOT NULL DEFAULT '0',
	liftdate INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (userid),
	KEY liftdate (liftdate)
)
";
$schema['CREATE']['explain']['userban'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userban");



$schema['CREATE']['query']['userfield'] = "
CREATE TABLE " . TABLE_PREFIX . "userfield (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	temp MEDIUMTEXT NOT NULL DEFAULT '',
	field1 MEDIUMTEXT NOT NULL,
	field2 MEDIUMTEXT NOT NULL,
	field3 MEDIUMTEXT NOT NULL,
	field4 MEDIUMTEXT NOT NULL,
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['userfield'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userfield");



$schema['CREATE']['query']['usergroup'] = "
CREATE TABLE " . TABLE_PREFIX . "usergroup (
	usergroupid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	title CHAR(100) NOT NULL DEFAULT '',
	description VARCHAR(250) NOT NULL DEFAULT '',
	usertitle CHAR(100) NOT NULL DEFAULT '',
	passwordexpires SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	passwordhistory SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pmquota SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	pmsendmax SMALLINT UNSIGNED NOT NULL DEFAULT '5',
	pmforwardmax SMALLINT UNSIGNED NOT NULL DEFAULT '5',
	opentag CHAR(100) NOT NULL DEFAULT '',
	closetag CHAR(100) NOT NULL DEFAULT '',
	canoverride SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	ispublicgroup SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	forumpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	pmpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	calendarpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	wolpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	adminpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	genericpermissions INT UNSIGNED NOT NULL DEFAULT '0',
	genericoptions INT UNSIGNED NOT NULL DEFAULT '0',
	attachlimit INT UNSIGNED NOT NULL DEFAULT '0',
	avatarmaxwidth SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	avatarmaxheight SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	avatarmaxsize INT UNSIGNED NOT NULL DEFAULT '0',
	profilepicmaxwidth SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	profilepicmaxheight SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	profilepicmaxsize INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (usergroupid)
)
";
$schema['CREATE']['explain']['usergroup'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergroup");



$schema['CREATE']['query']['usergroupleader'] = "
CREATE TABLE " . TABLE_PREFIX . "usergroupleader (
	usergroupleaderid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (usergroupleaderid),
	KEY ugl (userid, usergroupid)
)
";
$schema['CREATE']['explain']['usergroupleader'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergroupleader");



$schema['CREATE']['query']['usergrouprequest'] = "
CREATE TABLE " . TABLE_PREFIX . "usergrouprequest (
	usergrouprequestid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	usergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	reason VARCHAR(250) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	PRIMARY KEY (usergrouprequestid),
	KEY usergroupid (usergroupid)
)
";
$schema['CREATE']['explain']['usergrouprequest'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usergrouprequest");



$schema['CREATE']['query']['usernote'] = "
CREATE TABLE " . TABLE_PREFIX . "usernote (
	usernoteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	posterid INT UNSIGNED NOT NULL DEFAULT '0',
	username VARCHAR(50) NOT NULL DEFAULT '',
	dateline INT UNSIGNED NOT NULL DEFAULT '0',
	message MEDIUMTEXT NOT NULL,
	title VARCHAR(255) NOT NULL DEFAULT '',
	allowsmilies SMALLINT NOT NULL DEFAULT '0',
	PRIMARY KEY (usernoteid),
	KEY userid (userid)
)
";
$schema['CREATE']['explain']['usernote'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usernote");



$schema['CREATE']['query']['userpromotion'] = "
CREATE TABLE " . TABLE_PREFIX . "userpromotion (
	userpromotionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	usergroupid INT UNSIGNED NOT NULL DEFAULT '0',
	joinusergroupid INT UNSIGNED NOT NULL DEFAULT '0',
	reputation INT NOT NULL DEFAULT '0',
	date INT UNSIGNED NOT NULL DEFAULT '0',
	posts INT UNSIGNED NOT NULL DEFAULT '0',
	strategy SMALLINT NOT NULL DEFAULT '0',
	type SMALLINT NOT NULL DEFAULT '2',
	PRIMARY KEY (userpromotionid),
	KEY usergroupid (usergroupid)
)
";
$schema['CREATE']['explain']['userpromotion'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "userpromotion");



$schema['CREATE']['query']['usertextfield'] = "
CREATE TABLE " . TABLE_PREFIX . "usertextfield (
	userid INT UNSIGNED NOT NULL DEFAULT '0',
	subfolders MEDIUMTEXT NOT NULL,
	pmfolders MEDIUMTEXT NOT NULL,
	buddylist MEDIUMTEXT NOT NULL,
	ignorelist MEDIUMTEXT NOT NULL,
	signature MEDIUMTEXT NOT NULL,
	searchprefs MEDIUMTEXT NOT NULL,
	PRIMARY KEY (userid)
)
";
$schema['CREATE']['explain']['usertextfield'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usertextfield");



$schema['CREATE']['query']['usertitle'] = "
CREATE TABLE " . TABLE_PREFIX . "usertitle (
	usertitleid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	minposts SMALLINT UNSIGNED NOT NULL DEFAULT '0',
	title CHAR(250) NOT NULL DEFAULT '',
	PRIMARY KEY (usertitleid)
)
";
$schema['CREATE']['explain']['usertitle'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "usertitle");



$schema['CREATE']['query']['word'] = "
CREATE TABLE " . TABLE_PREFIX . "word (
	wordid INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title CHAR(50) NOT NULL DEFAULT '',
	PRIMARY KEY (wordid),
	UNIQUE KEY title (title)
)
";
$schema['CREATE']['explain']['word'] = sprintf($vbphrase['create_table'], TABLE_PREFIX . "word");

// ***************************************************************************************************************************

$schema['ALTER']['query']['session'] = "ALTER TABLE " . TABLE_PREFIX . "session TYPE = HEAP";
$schema['ALTER']['explain']['session'] = $install_phrases['session_to_heap'];

$schema['ALTER']['query']['cpsession'] = "ALTER TABLE " . TABLE_PREFIX . "cpsession TYPE = HEAP";
$schema['ALTER']['explain']['cpsession'] = $install_phrases['session_to_heap'];

// the user might have innodb table which has a row size limit unlike MyISAM
$schema['ALTER']['query']['language'] = "ALTER TABLE " . TABLE_PREFIX . "language TYPE = MYISAM";
$schema['ALTER']['explain']['language'] = $install_phrases['language_to_myisam'];
// ***************************************************************************************************************************

// Do not change this query, without modifying the datastore query below.
$schema['INSERT']['query']['attachmenttype'] = "
INSERT INTO " . TABLE_PREFIX . "attachmenttype (extension, mimetype, size, width, height, enabled, display) VALUES
('gif', '" . addslashes(serialize(array('Content-type: image/gif'))) . "', '20000', '620', '280', '1', '0'),
('jpeg', '" . addslashes(serialize(array('Content-type: image/jpeg'))) . "', '20000', '620', '280', '1', '0'),
('jpg', '" . addslashes(serialize(array('Content-type: image/jpeg'))) . "', '100000', '0', '0', '1', '0'),
('jpe', '" . addslashes(serialize(array('Content-type: image/jpeg'))) . "', '20000', '620', '280', '1', '0'),
('txt', '" . addslashes(serialize(array('Content-type: plain/text'))) . "', '20000', '0', '0', '1', '2'),
('png', '" . addslashes(serialize(array('Content-type: image/png'))) . "', '20000', '620', '280', '1', '0'),
('doc', '" . addslashes(serialize(array('Accept-ranges: bytes', 'Content-type: application/msword'))) . "', '20000', '0', '0', '1', '0'),
('pdf', '" . addslashes(serialize(array('Content-type: application/pdf'))) . "', '20000', '0', '0', '1', '0'),
('bmp', '" . addslashes(serialize(array('Content-type: image/bitmap'))) . "', '20000', '620', '280', '1', '0'),
('psd', '" . addslashes(serialize(array('Content-type: unknown/unknown'))) . "', '20000', '0', '0', '1', '0'),
('zip', '" . addslashes(serialize(array('Content-type: application/zip'))) . "', '100000', '0', '0', '1', '0')
";

$schema['INSERT']['explain']['attachmenttype'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "attachmenttype");



$schema['INSERT']['query']['datastore'] = "INSERT INTO " . TABLE_PREFIX . "datastore (title, data) VALUES ('wol_spiders', '" . addslashes(serialize(array(
	'spiderdesc' => "Google\nLycos\nAsk Jeeves\nAltavista\nAlltheWeb\nInktomi\nTurnitin.com",
	'spiderstrings' => "googlebot\nlycos\nask jeeves\nscooter\nfast-webcrawler\nslurp@inktomi\nturnitinbot",
	'spiderstring' => 'googlebot|lycos|ask jeeves|scooter|fast-webcrawler|slurp@inktomi|turnitinbot',
	'spiderarray' => array(
		'googlebot' => 'Google',
		'lycos' => 'Lycos',
		'ask jeeves' => 'Ask Jeeves',
		'scooter' => 'Altavista',
		'fast-webcrawler' => 'AllTheWeb',
		'slurp@inktomi' => 'Inktomi',
		'turnitinbot' => 'Turnitin.com'
	)
))) . "'),
	('attachmentcache', '" . addslashes(serialize($attachtypes2 = array(
	'extensions'	=>	'bmp doc gif jpe jpeg jpg pdf png psd txt zip',
	'bmp'	=> array(
		'extension'	=>	'bmp',
		'size'		=>	40000,
		'height'	=>	768,
		'width'		=>	1024,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'doc'	=> array(
		'extension'	=>	'doc',
		'size'		=>	40000,
		'height'	=>	0,
		'width'		=>	0,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'gif'	=> array(
		'extension'	=>	'gif',
		'size'		=>	40000,
		'height'	=>	768,
		'width'		=>	1024,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'jpe'	=> array(
		'extension'	=>	'jpe',
		'size'		=>	40000,
		'height'	=>	768,
		'width'		=>	1024,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'jpeg'	=> array(
		'extension'	=>	'jpeg',
		'size'		=>	40000,
		'height'	=>	768,
		'width'		=>	1024,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'jpg'	=> array(
		'extension'	=>	'jpg',
		'size'		=>	40000,
		'height'	=>	768,
		'width'		=>	1024,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'pdf'	=> array(
		'extension'	=>	'pdf',
		'size'		=>	40000,
		'height'	=>	0,
		'width'		=>	0,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'png'	=> array(
		'extension'	=>	'png',
		'size'		=>	40000,
		'height'	=>	768,
		'width'		=>	1024,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'psd'	=> array(
		'extension'	=>	'psd',
		'size'		=>	40000,
		'height'	=>	0,
		'width'		=>	0,
		'enabled'	=>	1,
		'display'	=>	0
	),
	'txt'	=> array(
		'extension'	=>	'txt',
		'size'		=>	40000,
		'height'	=>	0,
		'width'		=>	0,
		'enabled'	=>	1,
		'display'	=>	2
	),
	'zip'	=> array(
		'extension'	=>	'zip',
		'size'		=>	40000,
		'height'	=>	0,
		'width'		=>	0,
		'enabled'	=>	1,
		'display'	=>	0
	)
))) . "')";
$schema['INSERT']['explain']['datastore'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "datastore");



$schema['INSERT']['query']['calendar'] = "
INSERT INTO " . TABLE_PREFIX . "calendar (title, description, displayorder, neweventemail, moderatenew, startofweek, options, cutoff, eventcount, birthdaycount, startyear, endyear) VALUES
('" . addslashes($install_phrases['default_calendar']) . "', '', 1, '" . serialize(array()) . "', 0, 1, 119, 40, 4, 4, 2000, 2006)
";

$schema['INSERT']['explain']['calendar'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "calendar");



$schema['INSERT']['query']['cron'] = "
INSERT INTO " . TABLE_PREFIX . "cron (nextrun, weekday, day, hour, minute, filename, loglevel, title) VALUES
('1053271660', '-1', '-1', '0', '1', './includes/cron/birthday.php', '1', '" . addslashes($install_phrases['cron_birthday']) . "'),
('1053532560', '-1', '-1', '-1', '56', './includes/cron/threadviews.php', '0', '" . addslashes($install_phrases['cron_thread_views']) . "'),
('1053531900', '-1', '-1', '-1', '25', './includes/cron/promotion.php', '1', '" . addslashes($install_phrases['cron_user_promo']) . "'),
('1053271720', '-1', '-1', '0', '2', './includes/cron/digestdaily.php', '1', '" . addslashes($install_phrases['cron_daily_digest']) . "'),
('1053991800', '1', '-1', '0', '30', './includes/cron/digestweekly.php', '1', '" . addslashes($install_phrases['cron_weekly_digest']) . "'),
('1053271820', '-1', '-1', '0', '2', './includes/cron/subscriptions.php', '1', '" . addslashes($install_phrases['cron_subscriptions']) . "'),
('1053533100', '-1', '-1', '-1', '5', './includes/cron/cleanup.php', '0', '" . addslashes($install_phrases['cron_hourly_cleanup']) . "'),
('1053533200', '-1', '-1', '-1', '10', './includes/cron/attachmentviews.php', '0', '" . addslashes($install_phrases['cron_attachment_views']) . "'),
('1053990180', '-1', '-1', '0', '3', './includes/cron/activate.php', '1', '" . addslashes($install_phrases['cron_activation']) . "'),
('1053271600', '-1', '-1', '-1', '15', './includes/cron/removebans.php', '1', '" . addslashes($install_phrases['cron_unban_users']) . "'),
('1053531600', '-1', '-1', '-1', '20', './includes/cron/cleanup2.php', '0', '" . addslashes($install_phrases['cron_hourly_cleaup2']) . "'),
('1053271600', '-1', '-1', '0', '0', './includes/cron/stats.php', '0', '" . addslashes($install_phrases['cron_stats_log']) . "')
";

$schema['INSERT']['explain']['cron'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "cron");



$schema['INSERT']['query']['faq'] = "
INSERT INTO " . TABLE_PREFIX . "faq (faqname, faqparent, displayorder, volatile)
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
('vb_threadedmode', 'vb_board_usage', 9, 1),
('vb_rss_syndication', 'vb_board_usage', 10, 1),
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
$schema['INSERT']['explain']['faq'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "faq");



$schema['INSERT']['query']['forum'] = "
INSERT INTO " . TABLE_PREFIX . "forum (forumid, styleid, title, description, options, displayorder, replycount, lastpost, lastposter, lastthread, lastthreadid, lasticonid, threadcount, daysprune, newpostemail, newthreademail, parentid, parentlist, password, link, childlist)
VALUES (1, 0, '" . addslashes($install_phrases['category_title']) . "', '" . addslashes($install_phrases['category_desc']) . "', '86017', '1', '0', '0', '', '', '0', '0', '0', '0', '', '', '-1', '1,-1', '', '', '1,2,-1'),
(2, 0, '" . addslashes($install_phrases['forum_title']) . "', '" . addslashes($install_phrases['forum_desc']) . "', '89799', '1', '0', '0', '', '', '', '', '0', '30', '', '', '1', '2,1,-1', '', '', '2,-1')
";

$schema['INSERT']['explain']['forum'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "forum");


$schema['INSERT']['query']['icon'] = "
INSERT INTO " . TABLE_PREFIX . "icon (title, iconpath, imagecategoryid, displayorder) VALUES
('{$install_phrases['posticon_1']}', 'images/icons/icon1.gif', '2', '1'),
('{$install_phrases['posticon_2']}', 'images/icons/icon2.gif', '2', '1'),
('{$install_phrases['posticon_3']}', 'images/icons/icon3.gif', '2', '1'),
('{$install_phrases['posticon_4']}', 'images/icons/icon4.gif', '2', '1'),
('{$install_phrases['posticon_5']}', 'images/icons/icon5.gif', '2', '1'),
('{$install_phrases['posticon_6']}', 'images/icons/icon6.gif', '2', '1'),
('{$install_phrases['posticon_7']}', 'images/icons/icon7.gif', '2', '1'),
('{$install_phrases['posticon_8']}', 'images/icons/icon8.gif', '2', '1'),
('{$install_phrases['posticon_9']}', 'images/icons/icon9.gif', '2', '1'),
('{$install_phrases['posticon_10']}', 'images/icons/icon10.gif', '2', '1'),
('{$install_phrases['posticon_11']}', 'images/icons/icon11.gif', '2', '1'),
('{$install_phrases['posticon_12']}', 'images/icons/icon12.gif', '2', '1'),
('{$install_phrases['posticon_13']}', 'images/icons/icon13.gif', '2', '1'),
('{$install_phrases['posticon_14']}', 'images/icons/icon14.gif', '2', '1')
";

$schema['INSERT']['explain']['icon'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "icon");



$schema['INSERT']['query']['imagecategory'] = "
INSERT INTO " . TABLE_PREFIX . "imagecategory (title, imagetype, displayorder) VALUES
('{$install_phrases['generic_smilies']}', 3, 1),
('{$install_phrases['generic_icons']}', 2, 1),
('{$install_phrases['generic_avatars']}', 1, 1)
";
$schema['INSERT']['explain']['imagecategory'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "imagecategory");



$schema['INSERT']['query']['language'] = "INSERT INTO " . TABLE_PREFIX . "language (title, languagecode, charset, decimalsep, thousandsep) VALUES ('{$install_phrases['master_language_title']}', '{$install_phrases['master_language_langcode']}', '{$install_phrases['master_language_charset']}', '{$install_phrases['master_language_decimalsep']}', '{$install_phrases['master_language_thousandsep']}')";
$schema['INSERT']['explain']['language'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "language");


$schema['INSERT']['query']['profilefield'] = "
INSERT INTO " . TABLE_PREFIX . "profilefield (profilefieldid, title, description, required, hidden, maxlength, size, displayorder, editable, type, data, height, def, optional, searchable, memberlist, regex, form) VALUES
('1', '{$install_phrases['biography_title']}', '{$install_phrases['biography_desc']}', '0', '0', '100', '25', '1', '1', 'input', '', '0', '0', '0', '1', '1', '', '0'),
('2', '{$install_phrases['location_title']}', '{$install_phrases['location_desc']}', '0', '0', '100', '25', '2', '1', 'input', '', '0', '0', '0', '1', '1', '', '0'),
('3', '{$install_phrases['interests_title']}', '{$install_phrases['interests_desc']}', '0', '0', '100', '25', '3', '1', 'input', '', '0', '0', '0', '1', '1', '', '0'),
('4', '{$install_phrases['occupation_title']}', '{$install_phrases['occupation_desc']}', '0', '0', '100', '25', '4', '1', 'input', '', '0', '0', '0', '1', '1', '', '0')
";
$schema['INSERT']['explain']['profilefield'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "profilefield");



// *** MAKE THIS NICER ***
$schema['INSERT']['query']['phrasetype'] = "
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
";
// *** END MAKE THIS NICER ***
$schema['INSERT']['explain']['phrasetype'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "phrasetype");



$schema['INSERT']['query']['style'] = "INSERT INTO " . TABLE_PREFIX . "style (styleid, title, parentid, templatelist, css, replacements, userselect, displayorder) VALUES
(1, '{$install_phrases['default_style']}', -1, '1, -1', '', '', 1, 1)
";
$schema['INSERT']['explain']['style'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "style");



$schema['INSERT']['query']['reputationlevel'] = "INSERT INTO " . TABLE_PREFIX . "reputationlevel (minimumreputation, level) VALUES
(-999999, '{$install_phrases['reputation_-999999']}'),
(-50, '{$install_phrases['reputation_-50']}'),
(-10, '{$install_phrases['reputation_-10']}'),
(0, '{$install_phrases['reputation_0']}'),
(10, '{$install_phrases['reputation_10']}'),
(50, '{$install_phrases['reputation_50']}'),
(150, '{$install_phrases['reputation_150']}'),
(250, '{$install_phrases['reputation_250']}'),
(350, '{$install_phrases['reputation_350']}'),
(450, '{$install_phrases['reputation_450']}'),
(550, '{$install_phrases['reputation_550']}'),
(650, '{$install_phrases['reputation_650']}'),
(2000, '{$install_phrases['reputation_2000']}'),
(1500, '{$install_phrases['reputation_1500']}'),
(1000, '{$install_phrases['reputation_1000']}')
";

$schema['INSERT']['explain']['reputationlevel'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "reputationlevel");



$schema['INSERT']['query']['smilie'] = "
INSERT INTO " . TABLE_PREFIX . "smilie (title, smilietext, smiliepath, imagecategoryid, displayorder) VALUES
('{$install_phrases['smilie_smile']}', ':)', 'images/smilies/smile.gif', '1', '1'),
('{$install_phrases['smilie_embarrass']}', ':o', 'images/smilies/redface.gif', '1', '1'),
('{$install_phrases['smilie_grin']}', ':D', 'images/smilies/biggrin.gif', '1', '1'),
('{$install_phrases['smilie_wink']}', ';)', 'images/smilies/wink.gif', '1', '1'),
('{$install_phrases['smilie_tongue']}', ':p', 'images/smilies/tongue.gif', '1', '1'),
('{$install_phrases['smilie_cool']}', ':cool:', 'images/smilies/cool.gif', '1', '5'),
('{$install_phrases['smilie_roll']}', ':rolleyes:', 'images/smilies/rolleyes.gif', '1', '3'),
('{$install_phrases['smilie_mad']}', ':mad:', 'images/smilies/mad.gif', '1', '1'),
('{$install_phrases['smilie_eek']}', ':eek:', 'images/smilies/eek.gif', '1', '7'),
('{$install_phrases['smilie_confused']}', ':confused:', 'images/smilies/confused.gif', '1', '1'),
('{$install_phrases['smilie_frown']}', ':(', 'images/smilies/frown.gif', '1', '1')
";

$schema['INSERT']['explain']['smilie'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "smilie");


// Need to change the hard coded values to use the defined constants so we can easily see what permissions we are giving.
$schema['INSERT']['query']['usergroup'] = "
INSERT INTO " . TABLE_PREFIX . "usergroup (usergroupid, title, description, usertitle, passwordexpires, passwordhistory, pmquota, pmsendmax, pmforwardmax, opentag, closetag, canoverride, ispublicgroup, forumpermissions, pmpermissions, calendarpermissions, wolpermissions, adminpermissions, genericpermissions, genericoptions, attachlimit, avatarmaxwidth, avatarmaxheight, avatarmaxsize, profilepicmaxwidth, profilepicmaxheight, profilepicmaxsize) VALUES
(1, '{$install_phrases['usergroup_guest_title']}', '', '{$install_phrases['usergroup_guest_usertitle']}', 0, 0, 50, 0, 0, '', '', 0, 0, 4103, 0, 0, 1, 0, 1, 8, 0, 80, 80, 20000, 100, 100, 65535),
(2, '{$install_phrases['usergroup_registered_title']}', '', '', 0, 0, 50, 5, 5, '', '', 0, 0, 127487, 3, 19, 1, 0, 2626759, 30, 0, 80, 80, 20000, 100, 100, 65535),
(3, '{$install_phrases['usergroup_activation_title']}', '', '', 0, 0, 50, 0, 0, '', '', 0, 0, 4111, 0, 0, 0, 0, 1031, 24, 0, 80, 80, 20000, 100, 100, 65535),
(4, '{$install_phrases['usergroup_coppa_title']}', '', '', 0, 0, 50, 0, 0, '', '', 0, 0, 15, 0, 0, 0, 0, 3, 16, 0, 80, 80, 20000, 100, 100, 65535),
(5, '{$install_phrases['usergroup_super_title']}', '', '{$install_phrases['usergroup_super_usertitle']}', 0, 0, 50, 0, 0, '', '', 0, 0, 393215, 3, 31, 15, 1, 7298415, 31, 0, 80, 80, 20000, 100, 100, 65535),
(6, '{$install_phrases['usergroup_admin_title']}', '', '{$install_phrases['usergroup_admin_usertitle']}', 180, 360, 50, 5, 5, '', '', 0, 0, 393215, 3, 31, 31, 3, 8388543, 31, 0, 80, 80, 20000, 100, 100, 65535),
(7, '{$install_phrases['usergroup_mod_title']}', '', '{$install_phrases['usergroup_mod_usertitle']}', 0, 0, 50, 5, 5, '', '', 0, 0, 127487, 1, 31, 3, 0, 2629263, 22, 0, 80, 80, 20000, 100, 100, 65535),
(8, '{$install_phrases['usergroup_banned_title']}', '', '{$install_phrases['usergroup_banned_usertitle']}', 0, 0, 0, 0, 0, '', '', 0, 0, 0, 0, 0, 0, 0, 0, 32, 0, 80, 80, 20000, 100, 100, 65535)
";

$schema['INSERT']['explain']['usergroup'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "usergroup");



$schema['INSERT']['query']['usertitle'] = "
INSERT INTO " . TABLE_PREFIX . "usertitle (minposts, title) VALUES
('0', '{$install_phrases['usertitle_jnr']}'),
('30', '{$install_phrases['usertitle_mbr']}'),
('100', '{$install_phrases['usertitle_snr']}')
";

$schema['INSERT']['explain']['usertitle'] = sprintf($vbphrase['default_data_type'], TABLE_PREFIX . "usertitle");
?>