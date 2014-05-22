=== Archive ===
Contributors: Bueltge, inpsyde
Tags: post, custom post type, archive
Requires at least: 3.0
Tested up to: 4.0-alpha
Stable tag: trunk

Archive your post types, also possible with cron and list via shortcode on frontend.

== Description ==
Archive your post types, also possible via cron; but only active via var inside the php-file.
Use the shortcode [archive] to list al posts from Archive with status publish to a page or post.
The Shortcode can use different params and use the folow defaults.

`
'count'         => -1, // count or -1 for all posts
'post_status'   => 'publish', // status or all for all posts
'echo'          => TRUE, // echo or give an array for use external
'return_markup' => 'ul', // markup before echo title, content
'title_markup'  => 'li', // markup before item
'content'       => FALSE, // view also content?
'debug'         => FALSE // debug mor vor view an array
`

An example for use shortcode with params: `[archive count="10" content="true"]`

**Made by [Inpsyde](http://inpsyde.com) &middot; We love WordPress**

Have a look at the premium plugins in our [market](http://marketpress.com).

= Localizations =
* Thanks to [Frank B&uuml;ltge](http://bueltge.de/ "Frank B&uuml;ltge") for german language file
* Thanks to [Brian Flores](http://www.inmotionhosting.com/) for spanish translation
* Lithuanian translation files by [Vincent G](http://www.host1plus.com)

== Installation ==
= Requirements =
* WordPress version 3.0 and later (tested at 3.1.3)
* PHP 5.3

= Installation =
1. Unpack the download-package
1. Upload the folder and all folder and files includes this to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Now you find a link on all post types for archive the item

= Licence =
Good news, this plugin is free for everyone! Since it's released under the GPL, you can use it free of charge on your personal or commercial blog.

= Translations =
The plugin comes with various translations, please refer to the [WordPress Codex](http://codex.wordpress.org/Installing_WordPress_in_Your_Language "Installing WordPress in Your Language") for more information about activating the translation. If you want to help to translate the plugin to your language, please have a look at the .po file which contains all defintions and may be used with a [gettext](http://www.gnu.org/software/gettext/) editor like [Poedit](http://www.poedit.net/) (Windows) or plugin for WordPress [Localization](http://wordpress.org/extend/plugins/codestyling-localization/).

== Screenshots ==
1. Possibility to archive on posts
2. In Archive, also possible to restore

== Changelog ==
= 0.0.5 =
* Fix php notices

= 0.0.4 =
* add shortcode to list on frontend
* add function to add all items to wp query, set only via var in php file

= 0.0.3 =
* small fixes on language file for better read.
* cron on default inactive

= 0.0.2 =
* first release on wp.org

= 0.0.1 =
* Release first version
