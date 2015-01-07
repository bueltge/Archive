# Archive

Archive your post types, also possible with cron and list via shortcode on frontend.

## Description
Archive your post types, also possible via cron; but only active via var inside the php-file.
Use the shortcode [archive] to list al posts from Archive with status publish to a page or post.
The Shortcode can use different params and use the folow defaults.

```php
'count'         => -1, // count or -1 for all posts
'post_status'   => 'publish', // status or all for all posts
'echo'          => TRUE, // echo or give an array for use external
'return_markup' => 'ul', // markup before echo title, content
'title_markup'  => 'li', // markup before item
'content'       => FALSE, // view also content?
'debug'         => FALSE // debug mor vor view an array
```

An example for use shortcode with params: `[archive count="10" content="true"]`

### Crafted by [Inpsyde](http://inpsyde.com) &middot; Engineering the web since 2006.
Yes, we also run that [marketplace for premium WordPress plugins and themes](http://marketpress.com).

## Installation
### Requirements
* WordPress version 3.0 and later
* PHP 5.3

### Installation
1. Unpack the download-package
1. Upload the folder and all folder and files includes this to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Now you find a link on all post types for archive the item

## Screenshots
1. Possibility to archive on posts
![Possibility to archive on posts](./assets/screenshot-1.png)

2. In Archive, also possible to restore
![In Archive, also possible to restore](./assets/screenshot-2.png)

## Other Notes
#### License
Good news, this plugin is free for everyone! Since it's released under the GPL, you can use it free of charge on your personal or commercial blog. But if you enjoy this plugin, you can thank me and leave a [small donation](http://bueltge.de/wunschliste/ "Wishliste and Donate") for the time I've spent writing and supporting this plugin. And I really don't want to know how many hours of my life this plugin has already eaten ;)

#### Localizations
* Thanks to [Frank B&uuml;ltge](http://bueltge.de/ "Frank B&uuml;ltge") for german language file
* Thanks to [Brian Flores](http://www.inmotionhosting.com/) for spanish translation
* Lithuanian translation files by [Vincent G](http://www.host1plus.com)