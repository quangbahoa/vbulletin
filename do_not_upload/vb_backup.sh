#!/bin/bash
####################################################################
# vBulletin 3.8.11 - Licence Number VBS4AAFB47
# ---------------------------------------------------------------- #
# Copyright ©2000–2023 vBulletin Solutions Inc. All Rights Reserved.
# This file may not be redistributed in whole or significant part. #
# ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- #
#        www.vbulletin.com | www.vbulletin.com/license.html        #
####################################################################

# Please change this variable to the path to your config.php
path_to_config="/home/example/htdocs/includes/config.php"

# If you need the full path to the mysqldump binaries or gzip binaries set below
mysqldumpcmd=mysqldump
gzipcmd=gzip

################# No Editing below this point #################

if [ ! -e "$path_to_config" ]
then
	echo "$path_to_config doesn't exist"
	exit 1
fi

servername=`perl -n -e "if (/MasterServer']\['servername'\]\s*=\s*'(.*)'/) { print \\$1; } else { print ''; }" $path_to_config`
dbusername=`perl -n -e "if (/MasterServer']\['username'\]\s*=\s*'(.*)'/) { print \\$1; } else { print ''; }" $path_to_config`
dbpassword=`perl -n -e "if (/MasterServer']\['password'\]\s*=\s*'(.*)'/) { print \\$1; } else { print ''; }" $path_to_config`
dbname=`perl -n -e "if (/Database']\['dbname'\]\s*=\s*'(.*)'/) { print \\$1; } else { print ''; }" $path_to_config`

if [ ! -n "$servername" ]
then
        echo "Unable to read server name"
        exit 1
fi

if [ ! -n "$dbusername" ]
then
        echo "Unable to read database user name"
        exit 1
fi

if [ ! -n "$dbname" ]
then
        echo "Unable to read database name"
        exit 1
fi

date=`date "+%d-%m-%y"`
database="${dbname}-${date}.sql"

case "$1" in
	backup)
		if [ -n "$dbpassword" ]
		then
			passwordinfo=-p$dbpassword
		fi
		$mysqldumpcmd -h$servername -u$dbusername $passwordinfo --add-drop-table --add-locks --all --extended-insert --quick --compress $dbname > $database
		$gzipcmd $database
	;;

	*)
		echo "vb_backup.sh: Database backup script for vBulletin 3."
		echo "Please use the following command to backup your vBulletin database:"
		echo "./vb_backup.sh backup"
	;;
esac
exit 0
