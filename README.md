# personal-data-request-backups

## Keep a backup of the Export & Erasure Personal Data Requests.

![Tests](https://github.com/mrxkon/personal-data-request-backups/workflows/Tests/badge.svg)
[![PHP Compatibility 7.0+](https://img.shields.io/badge/PHP%20Compatibility-7.0+-8892BF)](https://github.com/PHPCompatibility/PHPCompatibility)
[![WordPress Coding Standards](https://img.shields.io/badge/WordPress%20Coding%20Standards-latest-blue)](https://github.com/WordPress/WordPress-Coding-Standards)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=mrxkon_personal-data-request-backups&metric=alert_status)](https://sonarcloud.io/dashboard?id=mrxkon_personal-data-request-backups) [![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=mrxkon_personal-data-request-backups&metric=security_rating)](https://sonarcloud.io/dashboard?id=mrxkon_personal-data-request-backups)
 [![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=mrxkon_personal-data-request-backups&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=mrxkon_personal-data-request-backups) [![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=mrxkon_personal-data-request-backups&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=mrxkon_personal-data-request-backups)

[![My Website](https://img.shields.io/badge/My-Website-orange.svg)](https://xkon.gr)  [![WordPress Profile](https://img.shields.io/badge/WordPress-Profile-blue.svg)](https://profiles.wordpress.org/xkon) [![PRs Welcomed](https://img.shields.io/badge/PRs-Welcomed%20!-brightgreen)](https://github.com/mrxkon/personal-data-request-backups/pulls)

[![Built for WordPress](https://img.shields.io/badge/built%20for-WordPress-blue)](https://wordpress.org) [![View on WordPress.org](https://img.shields.io/badge/View%20on-WordPress.org-blue.svg)](https://wordpress.org/plugins/personal-data-request-backups/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2+-red)](http://www.gnu.org/licenses/gpl-2.0.html)

---

When you restore your website to an earlier backup you might lose some of the Personal Data Export & Personal Data Erasure requests.

This leads to an issue as you might have newer requests especially for Erasures that will need to be fulfilled again according to the regulations.

Keeping a separate backup will help you on having always the latest possible copy of the requests for occasions like that.

You can set up an e-mail to receive the backup as an attached file on a daily basis or manually create additional backups.

![Screenshot](https://raw.githubusercontent.com/mrxkon/personal-data-request-backups/master/assets/screenshot1.png)

You can use these filters to change the e-mail subject & message.

```
<?php

add_filter(
	'pdr_backups_email_subject',
	function( $subject ) {
		$subject = 'A different subject.';
		return $subject;
	}
);

add_filter(
	'pdr_backups_email_message',
	function( $message ) {
		$message = 'A different message.';
		return $message;
	}
);
```