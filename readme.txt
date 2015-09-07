=== FV Swiftype ===
Contributors: FolioVision
Tags: search, swiftype
Requires at least: 4.0
Tested up to: 4.1.2
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Donate link: http://foliovision.com/donate/

Use Swiftype external crawler engine for your search.

== Description ==

WordPress search yields very poor results. There have been some attempts to improve WordPress search, but the results are still far worse than what one can get from a Google search (try "site:yourdomain.com search term" to test). Google offers Custom Search Engines but either you have to allow ads or GCS can be very expensive (for a single site with middle level traffic, about $800/year). Really what Google wants is their ads in your search.

There have been attempts to create advanced search plugins based on open source search engine technology like Solar (i.e. Relevanssi). These attempts haven't worked well.

* they've been far too complex technologically (i.e. even with our full programming team we'd be booking dozens of hours per year maintaining our search custom search engines)
* the out of the box versions don't work well on large sites (limitations of MySQL Lite)
* wildly inconsistent pricing (at one point Relevannsi would have cost us thousands of dollars per year for a single site).

One solution SearchWP works well for small sites but for our clients' professional sites with thousands of posts, search would take two to five seconds and spike the CPU of the server.

After deploying every other solution for our large client sites (self-administered open source engines, Solar, Relevanssi, SearchWP), we were no closer to the holy grail of perfect WordPress search:

* fast (even on large databases)
* sensible rankings (not based on date but on relevance
* tweakable (so clients can pull their most important posts to the top)
* low maintenance
* affordable

Then we found Swiftype.

Swiftype is the best WordPress search plugin available. It's not just the plugin, it's also a service, so their powerful server crawl your website and put together a coherent search index which brings great results.

More than that, you get full control over the search results. For example you can specify which pages should show up at the top of the search results. You can adjust importance of various fields and so on.

There's a free version of Swiftype works for free which suit someone starting a weblog and affordable plans for either active bloggers and professional and even enterprise sites. You can group search across a network of sites to yield results from all of them.

There's two kinds of Swiftype serach:

* WordPress database (Swiftype offers a plugin)
* External crawler (no plugin available)

We far prefer the external crawler. If you use the WordPress database, there is the risk of member only documents or even unpublished documents appearing in search. With the external crawler you are able to control what shows up in search (only public pages) and even use your Swiftype search as a control your content is properly published and indexed.

With the external crawler, your search can cover the non-Wordpress parts of your site in a single index. For example:

* online store
* forums
* help documents

If the document can be read on the web the external plugin can index it.

Our Solution: a WordPress plugin for Swiftype External Crawler

To allow us to quickly and reliably deploy Swiftype we wrote a WordPress plugin which replaces default WordPress search with the Swiftype external crawler search. It's literally single click upload to start offering first rate results across all the sites and subdomains you want indexed.

And once you have search inside Swiftype you can drag and drop the results to make sure your most relevant or most lucrative page is indexed first.

== Installation ==

1. Install and activate the plugin.
2. You will be asked to provide the API key. It needs to be your master account API key.
3. Once provided, you will be able to pick your engine. Make sure you pick the external crawler engine and not a Wordpress one (if you used the original Switftype Wordpress plugin before).
4. Done, check your search. There is a test mode as well as debug mode for your testing.

== Frequently Asked Questions ==

= What is Swiftype? =

Swiftype is a service which allows you to replace the rather basic search engine of Wordpress with something a lot more powerful. The basic account is free.

= Where do I setup my crawler? =

You need to setup the crawler in your Swiftype account. [Get one for free](https://swiftype.com/users/sign_up)

= What is the advantage over the official Swiftype plugin? =

With this plugin you can use the external crawler. This is important in case that part of your website doesn't use Wordpress or if you want category (and other) archives included in search results.

= What is the difference between this and the normal Swiftype search? =

Swiftype already has a Wordpress plugin, but it's simply sending of all your posts and pages to their engine. With this plugin you can use the external crawler. This is important in case that part of your website doesn't use Wordpress or if you want category (and other) archives included in search results. Our plugin is "read-only", ie. you need to setup the crawler in your Swiftype account and our plugin only connects to that.

== Screenshots ==

1. FV Switftype settings

== Changelog ==

= 0.3 - 2015/09/07 =

* Fixing error reporting - ignoring "name lookup timed out" error which sometime occurs (server connection issues?)
* Search engine error reports in wp-admin now only show to editors and admins

= 0.2 - 2015/04/23 =

* Adding robots.txt rule for Swiftbot crawler
* Settings screen improvements

= 0.1.9 - 2014/04/02 =

* Fix for per page settings
* Fixes for exceptions
* Improving error reporting

= 0.1.3 - 2015/02/27 =

* Fix for results where only external items are found

= 0.1.1 - 2014/12/19 =

* Fix for external items excluding

= 0.1 - 2014/12/19 =

* First public release.

== Other Notes ==

== Upgrade Notice ==

No upgrades so far.
