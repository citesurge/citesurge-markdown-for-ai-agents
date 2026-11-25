=== CiteSurge Markdown for AI Agents ===
Contributors: citesurge
Tags: markdown, ai, content negotiation, llms.txt
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Serve public WordPress posts and pages as Markdown with content negotiation, .md URLs, and llms.txt discovery.

== Description ==

CiteSurge Markdown for AI Agents adds a Markdown representation for public posts and pages. Existing HTML links still work in browsers. A request that prefers text/markdown can receive Markdown at the same URL.

Sites with pretty permalinks enabled also get .md addresses and alternate links. The plugin provides /llms.txt when WordPress does not already serve one. It sends no site content to an external service.

== Installation ==

1. In WordPress, open Plugins, select Add Plugin, and upload the release ZIP.
2. Activate CiteSurge Markdown for AI Agents.
3. If your site uses Plain permalinks, enable a pretty permalink format to use .md URLs and llms.txt.

== Frequently Asked Questions ==

= How do I keep my existing llms.txt? =

An existing file or WordPress route takes precedence. Site developers can also disable this plugin's fallback by returning false from the citesurge_markdown_llms_enabled filter.

= What if my page cache already serves a copy of the HTML? =

Use the .md URL to request a distinct address.

= What happens to private content? =

Drafts, private and password-protected content never appear in the Markdown representation or index.

== Changelog ==

= 1.0.0 =
* First release.
