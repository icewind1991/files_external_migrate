# External storage migration

## This app is experimental, use at your own risk and make backups!! ##

This application helps migrating you external storage to a new configuration while maintaining all file metadata.

Normally, when changing the configuration of an external storage, all file metadata will be lost, this is because Nextcloud can not be sure
that the files on the new configuration are the same files as on the old one, this app bypasses that but the administrator is responsible for ensuring that
the new configuration holds the same files as the old ones. This is mainly aimed at migrating storage servers to a new hostname.

Even with the help of this app this is a potential destructive operation, it's highly recommended that you make a backup of your database,
disable the Nextcloud cron job and stop the webserver before using the app.
