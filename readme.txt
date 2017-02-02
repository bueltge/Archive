﻿=== Archive ===
Contributors: Bueltge, inpsyde
Tags: post, custom post type, archive
Requires at least: 3.0
Tested up to: 4.7.2
Stable tag: trunk

Archive your post types, also possible with cron and list via shortcode on frontend.

== Description ==
Archive your post types, also possible via cron; but only active via var inside the php-file.
Use the shortcode [archive] to list all posts from Archive with status publish to a page or post.
The shortcode can use different params and use the follow defaults.

	'count'         => -1, // count or -1 for all posts
	'category'      => '', // Show posts associated with certain categories.
	'tag'           => '', // Show posts associated with certain tags.
	'post_status'   => 'publish', // status or all for all posts
	'echo'          => 'true', // echo or give an array for use external
	'return_markup' => 'ul', // markup before echo title, content
	'title_markup'  => 'li', // markup before item
	'content'       => 'false', // view also content?
	'debug'         => 'false' // debug mor vor view an array

An example for use shortcode with params: `[archive count="10" content="true"]`

Also you can change the parameters to create the custom post type of the Archiv via the filter hook `archive_post_type_arguments`.

**Crafted by [Inpsyde](http://inpsyde.com) &middot; Engineering the web since 2006.**

== Installation ==
= Requirements =
* WordPress version 3.0 and later (tested at 4.2-alpha)
* PHP 5.3

= Installation =
1. Unpack the download-package
1. Upload the folder and all folder and files includes this to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Now you find a link on all post types for archive the item

= Licence =
Good news, this plugin is free for everyone! Since it's released under the GPL3+, you can use it free of charge on your personal or commercial blog.

== Screenshots ==
1. Possibility to archive on posts, WP 4.2-alpha
2. Possibility to archive on posts, WP 3*
3. In Archive, also possible to restore, WP 3*

== Changelog ==
= 1.0.2 (2015-04-06) =
* Fix for activation and 3 characters of unexpected output
* Small sanitation enhancements

= 1.0.1 (2015-03-12) =
* Fix activate, deactivate values for all new capabilities on custom post type; Result was an bug to see the menu item
* Fix value on register post type
* Code maintenance

= 1.0.0 (2015-01-18) =
* Remove custom function to check for right post type, fixes error notice since WP 4.0.
* Remove custom css, switch to Dashicon.
* Enhance Shortcode parameters with `category` and `tag`.
* Add filter hook `archive_post_type_arguments` to change default parameters on create custom post type archiv.
* Code inspections, simplify post type and screen checks.

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
