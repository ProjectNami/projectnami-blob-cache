=== Project Nami Blob Cache ===
Contributors: patricknami, spencercameron
Tags: cache, caching, speed, optimize, optimization, performance
Requires at least: 3.3
Tested up to: 3.8.2
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Supercharge your WordPress site with full-page caching.

== Description ==
Simplify caching in the cloud on Windows Azure Websites ( or anywhere else fast, affordable caching would be beneficial ). Uses Azure Blob Storage as the cache backend. To get the fastest response times to and from the cache, locating your Storage Account in the same datacenter your web server's located in is vital. With this approach, you can easily get double or even single digit cache response times ( measured in milliseconds ).

If you'd like to see how long it's taking to generate your pages, you can view the page source. Just before the closing head tag ( `</head>` ), you'll find the following:

**When the page is not served from the cache:**
`<!-- Page generated without caching in *your generation time* seconds. -->`

**When the page is served from the cache:**
`<!-- Served by Project Nami Blob Cache in *time with caching* seconds. -->`

**Note:**
Nothing is cached for logged in users. If you'd like to see the caching in action ( and see your site actually speeding up ), you'll need to log out or view the page in another browser that's not logged in. Be sure to refresh a few times to see the difference. The first visit caches the page and subsequent visits are served from the cache ( these visits are the fast ones ).

== Installation ==

1. Upload the folder 'project-nami-blob-cache' to the '/wp-content/plugins/' directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. Optional: Set the cache expiration ( in seconds ) via the Settings->Cache menu.
4. Enjoy a faster WordPress site.

== Changelog ==

= 1.0 =
* Initial release.
