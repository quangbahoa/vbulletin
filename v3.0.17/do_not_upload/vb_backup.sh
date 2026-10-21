#!/bin/bash
####################################################################
# vBulletin 3.0.17 - Licence Number VBS4AAFB47
# ---------------------------------------------------------------- #
# Copyright ©2000–2026 Jelsoft Enterprises Ltd. All Rights Reserved.
# This file may not be redistributed in whole or significant part. #
# ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- #
# http://www.vbulletin.com | http://www.vbulletin.com/license.html #
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

servername=`sed -n '/servername/p' $path_to_config`
dbusername=`sed -n '/dbusername/p' $path_to_config`
dbpassword=`sed -n '/dbpassword/p' $path_to_config`
dbname=`sed -n '/dbname/p' $path_to_config`

# get the stuff between the string
servername=${servername#*\'*}
servername=${servername%*\'*}

dbusername=${dbusername#*\'*}
dbusername=${dbusername%*\'*}

dbpassword=${dbpassword#*\'*}
dbpassword=${dbpassword%*\'*}

dbname=${dbname#*\'*}
dbname=${dbname%*\'*}

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