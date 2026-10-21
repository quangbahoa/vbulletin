vBulletin 4 zip file contents

do_not_upload/ folder (This folder contains administration files. Only use
these when you're told to upload it by a support staff, or if you know what
you're doing)
`- file: tools.php (a script to help you fix (login) problems and rebuild
caches for usergroups, bitfields, etc.)
`- file: searchshell.php (a php shell script to rebuild search index from
console)
`- file: vb_backup.sh (a bash shell script to backup the database,
especially handy for nightly backups)

license_agreement.html file (All customers must agree to this license
agreement prior to downloading and using the vBulletin software)
vb4_readme.html (HTML version of this installation, upgrade, information
file)
vb4_readme.txt (TEXT version of this installation, upgrade, information
file)

upload/ folder (This folder contains all the files from the vBulletin forum
software that you need to upload when installing or upgrading)
`- All the files inside this folder are required to run vBulletin, see the
below installation and/or upgrade instructions on which files to modify
and/or to delete after an installation or upgrade.


vBulletin 4 Installation Instructions
This is an overview and quick guide to installing vBulletin 4.0 on your
server. For a complete description of how to install vBulletin 4, see the
installation section [http://www.vbulletin.com/docs/html/install] of the
vBulletin Manual [http://www.vbulletin.com/docs/html].

First, unzip the vBulletin zip file you downloaded from the members' area
[http://members.vbulletin.com] to your hard disk and then open the
'upload/includes/' folder. In this you will find config.php.new. You should
rename this to config.php and then open it in a text editor.

The config.php file is commented throughout, so you should be able to work
out what values to enter for the variables yourself. The values for the
config.php file are described in detail here
[http://www.vbulletin.com/docs/html/editconfig].


When you have finished, save the file and then upload the entire contents of
the 'upload/' folder to your web server.

When this is done, point your browser at
http://www.example.com/forums/install/install.php (where
www.example.com/forums/ is the URL of your vBulletin) and proceed to click
the Next Step buttons until the script asks to fill in some addresses and
names for your board.

After you have done this, the installer will ask you for some details to set
you up as the administrator of the new vBulletin. A few more clicks and the
script will be finished.

Before proceeding to the Admin Control Panel, you must delete the
'install/install.php' and 'install/upgrade1.php' files from your webserver.
You may then enter the control panel and start working on your new
vBulletin!

The entire installation process should take no more than five minutes.

Upgrades
Before starting any upgrade to a new version of vBulletin the first step that 
should be done is to take a backup.

You can find more information about backing up here: Database Backup

After backing up it may be a good idea to also disable your plugins, especially
between major versions of vBulletin. You can do this a few ways:

- Disable from the config.php file: define("DISABLE_HOOKS", 1);
- Disable from the AdminCP: AdminCP > Settings > Options > 
Plugin & Product System > 
- Disable one by one via the Product Manager

Upgrading from vBulletin 4.0.x

Close your board via the Admin Control Panel.

Upload all files from the 'upload/' folder in the zip, with the exception of 
'install/install.php'. Then open the 'upload/includes/' folder. In this you 
will find config.php.new. You should rename this to config.php and then open
it in a text editor.

Open your browser and point the URL to 
http://www.example.com/forum/install/upgrade.php (where 
www.example.com/forums/ is the URL of your vBulletin). You should now be
automatically forwarded to the appropriate upgrade script and step.

If any errors occur, copy the error and please contact support. DO NOT 
continue with the upgrade. Doing so may cause irreversible damage to 
the database.

Follow the instructions on the screen. Make sure you click next step or 
proceed until you are redirected to your Admin Control Panel. Here, you 
can reopen your board.




Upgrading from vBulletin 3.8.4

Close your board via the Admin Control Panel.

Upload all files from the 'upload/' folder in the zip, with the exception of
'install/install.php'. Then open the 'upload/includes/' folder. In this you
will find config.php.new. You should rename this to config.php and then open
it in a text editor.

Open your browser and point the URL to
http://www.example.com/forum/install/upgrade.php (where
www.example.com/forums/ is the URL of your vBulletin). You should now be
automatically forwarded to the appropriate upgrade script and step.

If any errors occur, copy the error and please contact support. DO NOT 
continue with the upgrade. Doing so may cause irreversible damage to 
the database.

Follow the instructions on the screen. Make sure you click next step or
proceed until you are redirected to your Admin Control Panel. Here, you can
reopen your board.

For a complete description of how to upgrade from a previous version of
vBulletin 3 to the latest version, see the upgrade section
[http://www.vbulletin.com/docs/html/upgrade] of the vBulletin 3 Manual
[http://www.vbulletin.com/docs/html].

Please note that the format for config.php in vBulletin 3.5.x is different
from previous versions of vBulletin, and you will need to manually update
your config file to the new format. Instructions are here
[http://www.vbulletin.com/docs/html/editconfig].

Upgrading from older versions
Before upgrading to vBulletin 4.0, you will need to download the files to
upgrade to vBulletin 3.8.4. Once your community is running on vBulletin
3.8.4, then you can upload the vBulletin 4.0 files and perform the upgrade
to this version. If you need assistance with this, contact vBulletin
Support.

A note on Styles
vBulletin 4.0 uses a new Template and Stylevar engine. This means your
previous styles are not compatible with vBulletin 4.0. When you upgrade,
they will be disabled and a new style will be created and set as the default
style.


Appendix: More information

Here are some important links with more information:

The vBulletin Online Manual (http://www.vBulletin.com/docs/html/) - With
installation and upgrade instructions, indepth feature and options
information and more technical documents.

The vBulletin Members Area (http://members.vBulletin.com/) - Download area
for vBulletin, private customer support tickets area, etc.

The vBulletin Support Forums (http://www.vBulletin.com/forum/) - Free
priority support forums, latest announcements with indepth release details,
etc.





Copyright ©2000 - 2010 vBulletin Solutions Inc. All rights reserved.

