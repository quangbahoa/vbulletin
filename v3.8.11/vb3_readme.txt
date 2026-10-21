vBulletin 3 Installation / Upgrade



vBulletin 3 zip file contents

do_not_upload/ folder (This folder contains administration files. Only use these when you're told to upload it by a support staff, or if you know what you're doing)
`- file: tools.php (a script to help you fix (login) problems and rebuild caches for usergroups, bitfields, etc.)
`- file: searchshell.php (a php shell script to rebuild search index from console)
`- file: vb_backup.sh (a bash shell script to backup the database, especially handy for nightly backups)

license_agreement.html file (All customers must agree to this license agreement prior to downloading and using the vBulletin software)
vb3_readme.html (HTML version of this installation, upgrade, information file)
vb3_readme.txt (TEXT version of this installation, upgrade, information file)

upload/ folder (This folder contains all the files from the vBulletin forum software that you need to upload when installing or upgrading)
`- All the files inside this folder are required to run vBulletin, see the below installation and/or upgrade instructions on which files to modify and/or to delete after an installation or upgrade.



Installing a new vBulletin 3

First, unzip the vBulletin zip file you downloaded from the members' area [http://members.vbulletin.com] to your hard disk and then open the 'upload/includes/' folder. In this you will find config.php.new. You should rename this to config.php and then open it in a text editor.

The config.php file is commented throughout, so you should be able to work out what values to enter for the variables yourself. When you have finished, save the file and then upload the entire contents of the 'upload/' folder to your web server.

When this is done, point your browser at http://www.example.com/forums/install/install.php (where www.example.com/forums/ is the URL of your vBulletin) and proceed to click the Next Step buttons until the script asks to fill in some addresses and names for your board.

After you have done this, the installer will ask you for some details to set you up as the administrator of the new vBulletin. A few more clicks and the script will be finished.

Before proceeding to the Admin Control Panel, you must delete the 'install/install.php' and 'install/upgrade1.php' files from your webserver. You may then enter the control panel and start working on your new vBulletin!

The entire installation process should take no more than five minutes.

For a complete description of how to install vBulletin 3, see the installation section [http://www.vbulletin.com/docs/html/install] of the vBulletin 3 Manual [http://www.vbulletin.com/docs/html].

The values for the config.php file are described in detail here [http://www.vbulletin.com/docs/html/editconfig].



Upgrading from a previous version of vBulletin 3

Close your board via the Admin Control Panel.

Upload all files from the 'upload/' folder in the zip, with the exception of 'install/install.php'. Then open the 'upload/includes/' folder. In this you will find config.php.new. You should rename this to config.php and then open it in a text editor.

Open your browser and point the URL to http://www.example.com/forum/install/upgrade.php (where www.example.com/forums/ is the URL of your vBulletin). You should now be automatically forwarded to the appropriate upgrade script and step.

Follow the instructions on the screen. Make sure you click next step or proceed until you are redirected to your Admin Control Panel. Here, you can reopen your board.

For a complete description of how to upgrade from a previous version of vBulletin 3 to the latest version, see the upgrade section [http://www.vbulletin.com/docs/html/upgrade] of the vBulletin 3 Manual [http://www.vbulletin.com/docs/html].

Please note that the format for config.php in vBulletin 3.5.x is different from previous versions of vBulletin, and you will need to manually update your config file to the new format. Instructions are here [http://www.vbulletin.com/docs/html/editconfig].



Upgrading from vBulletin 2 to vBulletin 3

The vBulletin 2 to vBulletin 3 upgrade script will upgrade your vBulletin 2 installation to vBulletin 3. You will not lose any data. Your vB2 styles will not be usable in vB3, but they will not be deleted, they will be kept in the database for your reference.

To upgrade, to vBulletin 3 your existing installation must be running at least vBulletin 2.2.9 or higher.

If you want to upgrade your forums to vBulletin 3, you should first close your board via the vBulletin 2 Admin Control Panel, and then make a complete back-up your database using the following command from the system command line:

mysqldump --opt -Q -uusername -p databasename > databasename.sql

(Where username is your MySQL username and databasename is the name of your database)

When your database backup is complete you should back up your existing vBulletin 2 PHP files and images, just in case you want to revert back to vBulletin 2 for whatever reason.

Before you proceed, you should run the Database Conflict Detection Script [http://www.vbulletin.com/forum/showthread.php?threadid=73163] to make sure that the upgrade script will not fail due to any differences from the default vBulletin 2 installation in your database. If any conflicts are detected, you should resolve them before proceeding with the upgrade.

Next, unzip the vBulletin zip file you downloaded from the members' area to your hard disk and then open the 'upload/includes/' folder. In this you will find config.php.new. You should rename this to config.php and then open it in a text editor.

The config.php file is commented throughout, so you should be able to work out what values to enter for the variables yourself. When you have finished, save the file back into the 'upload/includes/' folder as config.php.

Next, you should delete all vBulletin 2 files, including PHP, CSS and style files, along with any images. You should then upload the contents of the 'upload/' folder to your server.

When the upload is complete, point your browser at http://www.example.com/forum/install/upgrade.php where www.example.com/forum/ is the URL of your vBulletin files.

Please note that we have found that due to the large number of pages that the upgrade script loads, and the speed at which it loads them, certain browser plug-ins can cause the browser to crash out for various reasons. The Google Toolbar is one such plug-in, and we have seen browsers crashing due to the amount of browsing statistics being sent back to Google. If you have plug-ins of this kind installed, turn them off while running the upgrade script.

Running upgrade.php will automatically load the correct upgrade script and present you with a message, which you should read carefully. When you have finished reading, click the Next Step button at the bottom of the page to start the upgrade process. When each page has completed its processing, it will either give you a Next Step button to click, or automatically load the next page, depending on the type of job the page in question is doing.

Some steps will take quite a while to run, especially on large boards with large numbers of posts/threads. Please be patient and allow the script to complete.

If your browser crashes during the upgrade, or if you are on dial-up and your connection is dropped (etc.) then to restart the upgrade script simply point your browser at install/upgrade.php again and it will work out where you got to in the process and restart the process at the appropriate step.

For the most part, you do not need to do anything except click the 'Next Step' buttons to keep the upgrade script running. However, there are a few steps where the script will require your input to continue. One of these is the step where the script asks you to tell it which of your usergroups you use for 'Banned' users. It is important to select these carefully in order to make the enhanced vBulletin 3 banning system work. Additionally, towards the end of the upgrade, as the cookie and user format is converted from vB2 format to vB3 format, you maybe be required to log-in again a few times. This is the expected behavior.

Note: There are several upgrade scripts that need to be run. You do not need to determine which you need to run; they will be determined automatically. Simply click "next step" or "proceed" until you are redirected to your admin control panel.

When the scripts are complete, it will tell you to delete some files, and then redirect you to the Admin Control Panel. You should log in to this and proceed to the Import and Maintenance section of the control panel, where you should run the Rebuild Search Index and Rebuild Statistics controls. Both of these functions will take a quite a long time to run on larger boards.

For a complete description of how to upgrade from a previous version of vBulletin 3 to the latest version, see the upgrade section [http://www.vbulletin.com/docs/html/upgrade] and specifically the upgrading from vBulletin 2 section [http://www.vbulletin.com/docs/html/upgrade_vb2] of the vBulletin 3 Manual [http://www.vbulletin.com/docs/html].



Appendix: More information

Here are some important links with more information:

The vBulletin Online Manual (http://www.vBulletin.com/docs/html/) - With installation and upgrade instructions, indepth feature and options information and more technical documents.

The vBulletin Members Area (http://members.vBulletin.com/) - Download area for vBulletin, private customer support tickets area, etc.

The vBulletin Support Forums (http://www.vBulletin.com/forum/) - Free priority support forums, latest announcements with indepth release details, etc.





Copyright ©2000 - 2008 Jelsoft Enterprises Limited. All rights reserved.

