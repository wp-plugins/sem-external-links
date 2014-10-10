=== External Links ===
Contributors: Denis-de-Bernardy, Mike_Koepke
Donate link: http://www.semiologic.com/partners/
Tags: external-links, nofollow, link-target, link-icon, semiologic
Requires at least: 2.8
Tested up to: 3.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The external links plugin for WordPress lets you process outgoing links differently from internal links.


== Description ==

The external links plugin for WordPress lets you process outgoing links differently from internal links.

Under Settings / External Links, you can configure the plugin to:

- Process all outgoing links, rather than only those within your entries' content and text widgets.
- Add an external link icon to outgoing links. You can use a class="no_icon" attribute on links to override this.
- Add rel=nofollow to the links. You can use a rel="follow" attribute on links to override this.
- Open outgoing links in new windows. Note that this can damage your visitor's trust towards your site in that they can think your site used a pop-under.

= Help Me! =

The [Semiologic forum](http://forum.semiologic.com) is the best place to report issues. Please note, however, that while community members and I do our best to answer all queries, we're assisting you on a voluntary basis.

If you require more dedicated assistance, consider using [Semiologic Pro](http://www.semiologic.com).


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Change Log ==

= 5.4 =

- Handle nested parenthesis in javascript event attributes on links and images

= 5.3.2 =

- Temporarily placeholders links - http:// and https:// (no other url components) are no longer processed.

= 5.3.1 =

- Fix localization

= 5.3 =

- Fix: Conflict with Auto Thickbox plugin that would result in text widgets still being filtered even though option was turned off
- Fix: Ensure this plugin filter is executed way back in the change to prevent other plugins/themes from reversing our changes
- Code refactoring
- WP 3.9 compat

= 5.2.1 =

- Checks for new sem_dofollow class to determine if Do Follow plugin is active
- WP 3.8 compat

= 5.2 =

- Further updates to the link attribute parsing code
- Fixed bug where external link was not processed if it was preceded by an empty text anchor link.

= 5.1 =

- Take two!  With issues now with breaking google adsense code reverted back to 4.2 parsing code but added more advanced dom attribute parsing code to handle various link configurations.

= 5.0 =

- Completely replaced the mechanism for parsing links to resolve the various errors that have been occurring with different external services' link attributes
- Tested with WP 3.7

= 4.2 =

- WP 3.6 compat
- PHP 5.4 compat
- Fixed issue with parsing of links with non-standard (class, href, rel, target) attributes included in the <a> tag.  This caused Twitter Widgets to break.
- Fixed issue where the external link icon was not added if the url specified by href had a preceding space  href=" http://www.example.com"
- Fixed issue with links containing onClick (or other javascript event) attributes with embedded javascript code.  WordPress' Threaded Comments does this
- Fixed issue with 2 spaces being injected between <a and class/href/rel/etc.   i.e   <a  href="http://example.com">

= 4.1 =

- WP 3.5 compat

= 4.0.6 =

- WP 3.0.1 compat

= 4.0.5 =

- WP 3.0 compat

= 4.0.4 =

- Force a higher pcre.backtrack_limit and pcre.recursion_limit to avoid blank screens on large posts

= 4.0.3 =

- Improve case-insensitive handling of domains
- Improve image handling
- Switch back to using a target attribute: work around double windows getting opened in Vista/IE7
- Disable entirely in feeds

= 4.0.2 =

- Don't enforce new window pref in feeds

= 4.0.1 =

- Ignore case when comparing domains

= 4.0 =

- Allow to force a follow when the nofollow option is toggled
- Enhance escape/unescape methods
- Localization
- Code enhancements and optimizations