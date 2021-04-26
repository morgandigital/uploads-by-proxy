=== Uploads by Proxy ===
Contributors: pdclark, skithund
Author URI: https://www.morgan.fi/
Tags: localhost, local, development, staging, uploads, media library, xampp, mamp, wamp, git, svn, subversion
Requires at least: 5.5
Tested up to: 5.6.1
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

For development/staging: Automatically load images from the production version of wp-content/uploads if they are missing locally.

== Description ==

This plugin is meant to be used by developers who work on sites in development or staging environment before deploying changes to a production (live) server. It allows you skip downloading the contents of `wp-content/uploads` to your local WordPress install. Instead, images missing from the uploads directory are loaded and copied locally from the production server as needed.

= Setup =

In most cases, you should be able to activate the plugin and go. If the plugin does not work automatically, then you need to set the address of your live WordPress install in `wp-config.php`, like this:

define('UBP_SITEURL', 'https://www.example.com/');

or in multisite setup like this:

define('UBP_SITEURL', [ 'localdev.example.com' => 'www.example.com', 'localdev2.example.com' => 'site2.example.com' ] );

== Installation ==

1. Upload the `uploads-by-proxy` folder to the `/wp-content/plugins/` directory
1. In a local or staging development site, activate the Uploads by Proxy plugin through the 'Plugins' menu in WordPress
1. If your development site address is different than your live site address, or you are not using `WP_SITEURL`, set your live site address in `wp-config.php` like this: `define('UBP_SITEURL', 'http://example-live.com/wordpress');`

== Frequently Asked Questions ==

= Why would I want to use this? =

Maybe you work on a site with gigabytes of images in the uploads directory, or maybe you use a version control system like SVN or Git, and prefer to not store `wp-content/uploads` in your repository. Either way, this plugin allows you to not worry about the uploads folder being up-to-date.

= What is a production environment/site/server? =

"Production" is a term that's used to refer to a version of a site that is running live on the Internet. If you normally edit your site through the WordPress code editor or over FTP, you are making edits on the production server. Editing your site in this way risks crashing the site for your visitors each time you make an edit.

= What is a development environment/site/server? =

"Development" refers to a version of your site that is in a protected area only accessible to you. Programs like [MAMP](http://www.mamp.info), [WAMP](http://www.wampserver.com/), and [XAMPP](http://www.apachefriends.org/en/xampp.html) allow you run a copy of your WordPress site in a way that is only accessible from your computer. This allows you to work on a copy and test changes without effecting the live site until you are ready to deploy your changes.

= An image changed on my live server, but it didn't update locally. =

This plugin only goes into action when an image is missing on your local copy. When it runs, it copies the file into your local wp-content/uploads folder and doesn't run again. If you'd like to update an image with the production copy again, delete your local copy.

= What will happen if I enable this plugin on a live site? =

Nothing. The plugin only takes action if it detects it is on development or staging environment.

= How does the plugin detect the difference between a production and development/staging environment? =

The plugin only loads if the site has WP_ENVIRONMENT_TYPE as 'development' or 'staging'.

= Where are those icons in the wordpress.org plugin header from? =

[Font Awesome](http://fortawesome.github.com/Font-Awesome)

== Changelog ==

= 1.2.0 =

* Preliminary support for multisites with <code>UBP_SITEURL</code> defined as an array of <code>'localname.example.com' => 'www.example.com'</code> pairs

= 1.1.4 =

* Fix: Code cleanup
* Fix: Rename *Uploads-By-Proxy HTTP header as X-Uploads-By-Proxy
* Fix: Remove Referer usage in HTTP requests due to privacy
* Fix: Use WordPress coding standards
* Feature: Use WordPress environment type instead of IPs

= 1.1.3 =

* Fix: Remove hostnametoip.com from IP service providers.
* New: Add <code>ubp_remote_ip</code> filter to set IP address programatticaly. Usage: <code>add_filter( 'ubp_remote_ip', function(){ return '12.34.56.789'; } );</code>
* Notice: Only one of three IP address services is still available. If this last one goes down, the plugin will no longer support automatic live IP detection when local development environments use the same domain name as the live environment. If this happens, use the new  <code>ubp_remote_ip</code> filter to set your remote IP address.

= 1.1.2 =

* Fix: Resolve a warning output in debug environments. (Static function not declared as static.)

= 1.1.1 =

* Fix: Suppress notice that could keep image from displaying on first load when WordPress installed in root directory. Thanks [@justnorris](http://wordpress.org/support/topic/fixed-some-issues?replies=1).

= 1.1 =

* Auto-detect live URL in cases where `WP_SITEURL` is being set in `wp-config.php`.
* Change `UBP_LIVE_SITE` to `UBP_SITEURL` and match format to `WP_SITEURL`.
* Maintain legacy support for installs using `UBP_LIVE_SITE`.
* Add support for root directory installs mapping to subdirectory installs.
* Add support for subdirectory installs mapping to root directory installs.
* Add IPv6 localhost address when checking for local development environment.

= 1.0 =

* Initial public release.

== Upgrade Notice ==

* Fix: Resolve a warning output in debug environments. (Static function not declared as static.)
* Fix: Suppress notice that could keep image from displaying on first load when WordPress installed in root directory. Thanks [@justnorris](http://wordpress.org/support/topic/fixed-some-issues?replies=1).
