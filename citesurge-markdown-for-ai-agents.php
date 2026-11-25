<?php
/**
 * Plugin Name: CiteSurge Markdown for AI Agents
 * Description: Serve public posts and pages as Markdown with content negotiation, .md URLs, and llms.txt discovery.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: CiteSurge
 * Author URI: https://citesurge.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: citesurge-markdown-for-ai-agents
 */

namespace CiteSurge\Markdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_file( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/negotiation.php';
require_once __DIR__ . '/src/converter.php';
require_once __DIR__ . '/src/routes.php';

register_hooks();
