# CiteSurge Markdown for AI Agents

This plugin serves public WordPress posts and pages as Markdown over HTTP. It runs inside WordPress and uses the site's existing page output.

The release ZIP includes the locked Composer dependencies. Activate it through Plugins in WordPress. Pretty permalinks enable .md aliases and the fallback /llms.txt index. Markdown responses are marked private and uncacheable.

## Development

Run `composer install`, then `php tests/negotiation.php` and `php tests/converter.php`. The WordPress HTTP smoke uses Playground and a disposable fixture site.

The plugin is GPL-2.0-or-later. The WordPress delivery approach follows [Roots Post Content to Markdown](https://github.com/roots/post-content-to-markdown) (MIT). [League HTML to Markdown](https://github.com/thephpleague/html-to-markdown) (MIT) handles conversion.
