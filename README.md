# vBulletin Legacy Archive

An archive of source code for old **vBulletin 3.x** releases, kept for reference, study, and as a way to restore or migrate legacy forums.

> ⚠️ **These versions are end-of-life.** They no longer get security updates, they have known vulnerabilities, and they do not run on modern PHP. Do **not** put them on a public production server. Use them only in local or isolated environments.

## Available Versions

| Version      | Directory                        | Notes                                     |
| ------------ | -------------------------------- | ----------------------------------------- |
| 3.8.11       | [`v3.8.11/`](v3.8.11/)           | Latest 3.x release. Includes a pre-installed database |
| 3.7.7        | [`v3.7.7/`](v3.7.7/)             |                                           |
| 3.6.12 PL2   | [`v3.6.12-pl2/`](v3.6.12-pl2/)   |                                           |
| 3.5.8        | [`v3.5.8/`](v3.5.8/)             |                                           |
| 3.0.17       | [`v3.0.17/`](v3.0.17/)           |                                           |

## Directory Structure

Every version keeps the layout of the original vBulletin distribution package:

```
vX.Y.Z/
├── upload/              # Files to upload to your web root (admincp, includes, install, ...)
├── do_not_upload/       # Server-side tools (tools.php, searchshell.php, vb_backup.sh) — keep out of the web root
├── license_agreement.html
└── vb3_readme.html      # Official install/upgrade guide for that version
```

## Quick Start (v3.8.11 with pre-installed database)

1. Copy the contents of `v3.8.11/upload/` to your web root.
2. Create a database and import [`v3.8.11/database/vb3.sql`](v3.8.11/database/vb3.sql).
3. Rename `includes/config.php.new` to `includes/config.php`, then fill in your database credentials.
4. Log in with the default super admin account:
   - Username: `vbadmin`
   - Password: `vb3811_passcode`

   Change this password right after your first login.

For a fresh install of any other version, follow the `vb3_readme.html` in that version's directory and run `install/install.php`.

## Tested Environment

| Component  | Version                              |
| ---------- | ------------------------------------ |
| PHP        | 7.4 (PHP 8.x is **not** supported)   |
| MySQL      | 5.x – 8.x                            |
| Web server | Apache 2 (Nginx not confirmed to work) |

Older releases (3.0.x – 3.6.x) were built for PHP 4/5 and may need an even older PHP version to run correctly.

## License & Disclaimer

vBulletin is commercial software and a trademark of its owner (originally Jelsoft Enterprises Ltd., now MH Sub I, LLC dba vBulletin). This repository is not affiliated with or endorsed by vBulletin. The code is archived here for historical and educational purposes only. See `license_agreement.html` in each version's directory for the original license terms.
