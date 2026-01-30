<?php
/**
 * Plugin Name: Accordion JSON-LD
 * Description: Generates FAQPage JSON-LD from the core Accordion block and outputs it in wp_head.
 * Version: 0.4.0
 * Author: Koji Kuno
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Text Domain: accordion-json-ld
 * Domain Path: /languages
 * License: GPL 2.0 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Accordion_JSON_LD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Composer autoload (optional for GitHub updater).
$accordion_json_ld_autoload = __DIR__ . '/vendor/autoload.php';
$accordion_json_ld_updater  = __DIR__ . '/vendor/inc2734/wp-github-plugin-updater/src/Bootstrap.php';
if ( file_exists( $accordion_json_ld_autoload ) && file_exists( $accordion_json_ld_updater ) ) {
	require_once $accordion_json_ld_autoload;
}

/**
 * Initialize hooks.
 *
 * @return void
 */
function accordion_json_ld_init() {
	add_action( 'init', 'accordion_json_ld_load_textdomain' );
	add_action( 'wp_head', 'accordion_json_ld_output_json_ld', 20 );
	add_action( 'enqueue_block_editor_assets', 'accordion_json_ld_enqueue_editor_assets' );
	add_action( 'plugins_loaded', 'accordion_json_ld_bootstrap_github_updater' );
}

/**
 * Initialize the GitHub plugin updater.
 *
 * @return void
 */
function accordion_json_ld_bootstrap_github_updater() {
	if ( ! class_exists( 'Inc2734\\WP_GitHub_Plugin_Updater\\Bootstrap' ) ) {
		return;
	}

	new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
		plugin_basename( __FILE__ ),
		'Olein-jp',
		'accordion-json-ld'
	);
}

/**
 * Load translation files.
 *
 * @return void
 */
function accordion_json_ld_load_textdomain() {
	load_plugin_textdomain(
		'accordion-json-ld',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

/**
 * Enqueue block editor assets.
 *
 * @return void
 */
function accordion_json_ld_enqueue_editor_assets() {
	$script_path = __DIR__ . '/build/index.js';
	$script_url  = plugin_dir_url( __FILE__ ) . 'build/index.js';
	$asset_path  = __DIR__ . '/build/index.asset.php';

	if ( ! file_exists( $script_path ) ) {
		return;
	}

	$script_version = filemtime( $script_path );
	$asset          = array(
		'dependencies' => array(
			'wp-blocks',
			'wp-hooks',
			'wp-compose',
			'wp-element',
			'wp-components',
			'wp-block-editor',
		),
		'version'      => $script_version,
	);

	if ( file_exists( $asset_path ) ) {
		$asset = include $asset_path;
	}

	wp_enqueue_script(
		'accordion-json-ld-editor',
		$script_url,
		isset( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
		isset( $asset['version'] ) ? $asset['version'] : $script_version,
		true
	);

	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations(
			'accordion-json-ld-editor',
			'accordion-json-ld',
			plugin_dir_path( __FILE__ ) . 'languages'
		);
	}
}

/**
 * Output JSON-LD.
 *
 * @return void
 */
function accordion_json_ld_output_json_ld() {
	if ( ! is_singular() ) {
		return;
	}

	if ( ! function_exists( 'parse_blocks' ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$content = get_post_field( 'post_content', $post_id );
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return;
	}

	$blocks = parse_blocks( $content );
	if ( empty( $blocks ) || ! is_array( $blocks ) ) {
		return;
	}

	$qa_items = accordion_json_ld_extract_qa_items( $blocks );
	if ( empty( $qa_items ) ) {
		return;
	}

	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $qa_items,
	);

	$schema = apply_filters( 'accordion_json_ld_schema', $schema, $post_id );
	if ( empty( $schema['mainEntity'] ) ) {
		return;
	}

	$json = wp_json_encode(
		$schema,
		JSON_UNESCAPED_UNICODE
			| JSON_UNESCAPED_SLASHES
			| JSON_PRETTY_PRINT
			| JSON_HEX_TAG
			| JSON_HEX_AMP
			| JSON_HEX_APOS
			| JSON_HEX_QUOT
	);
	if ( false === $json ) {
		return;
	}

	wp_print_inline_script_tag(
		$json,
		array(
			'type' => 'application/ld+json',
		)
	);
}

/**
 * Recursively extract Q&A items.
 *
 * @param array $blocks Block array.
 * @return array Q&A items for FAQ schema.
 */
function accordion_json_ld_extract_qa_items( $blocks ) {
	$items = array();

	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		$block_name = accordion_json_ld_get_block_name( $block );
		if ( accordion_json_ld_is_accordion_container_block( $block_name ) ) {
			if ( ! accordion_json_ld_is_json_ld_enabled_for_block( $block ) ) {
				continue;
			}
		}

		if ( accordion_json_ld_is_accordion_content_block( $block_name ) ) {
			$qa = accordion_json_ld_extract_qa_from_accordion_content( $block );
			if ( $qa ) {
				$items[] = $qa;
			}
		}

		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$items = array_merge( $items, accordion_json_ld_extract_qa_items( $block['innerBlocks'] ) );
		}
	}

	return $items;
}

/**
 * Extract Q&A from an accordion content block.
 *
 * @param array $block Block data.
 * @return array|null FAQ item data or null when incomplete.
 */
function accordion_json_ld_extract_qa_from_accordion_content( $block ) {
	if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
		return null;
	}

	$question = '';
	$answer   = '';

	foreach ( $block['innerBlocks'] as $inner ) {
		if ( ! is_array( $inner ) ) {
			continue;
		}

		$inner_name = accordion_json_ld_get_block_name( $inner );
		if ( '' === $question && accordion_json_ld_is_accordion_header_block( $inner_name ) ) {
			$question = accordion_json_ld_extract_text_from_block( $inner );
			continue;
		}

		if ( '' === $answer && accordion_json_ld_is_accordion_panel_block( $inner_name ) ) {
			$answer = accordion_json_ld_extract_text_from_block( $inner );
			continue;
		}
	}

	$question = trim( $question );
	$answer   = trim( $answer );

	if ( '' === $question || '' === $answer ) {
		return null;
	}

	return array(
		'@type'          => 'Question',
		'name'           => $question,
		'acceptedAnswer' => array(
			'@type' => 'Answer',
			'text'  => $answer,
		),
	);
}

/**
 * Extract plain text from a block.
 *
 * @param array $block Block data.
 * @return string Extracted text.
 */
function accordion_json_ld_extract_text_from_block( $block ) {
	$text = '';

	if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
		foreach ( $block['innerBlocks'] as $inner ) {
			if ( is_array( $inner ) ) {
				$text .= ' ' . accordion_json_ld_extract_text_from_block( $inner );
			}
		}
	} elseif ( isset( $block['innerHTML'] ) ) {
		$text = $block['innerHTML'];
	} elseif ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
		$text = implode( ' ', $block['innerContent'] );
	}

	// Exclude aria-hidden decorative characters (e.g. toggle icons).
	$text = preg_replace( '/<[^>]*aria-hidden=("|\')true\\1[^>]*>.*?<\\/[^>]+>/i', ' ', (string) $text );
	$text = wp_strip_all_tags( $text, true );
	$text = preg_replace( '/\s+/', ' ', (string) $text );

	return is_string( $text ) ? $text : '';
}

/**
 * Get the block name.
 *
 * @param array $block Block data.
 * @return string Block name or empty string.
 */
function accordion_json_ld_get_block_name( $block ) {
	if ( ! is_array( $block ) ) {
		return '';
	}

	return isset( $block['blockName'] ) && is_string( $block['blockName'] )
		? $block['blockName']
		: '';
}

/**
 * Check if the block is an accordion content block.
 *
 * @param string $block_name Block name.
 * @return bool True if the block matches an accordion content block.
 */
function accordion_json_ld_is_accordion_content_block( $block_name ) {
	$block_name = accordion_json_ld_normalize_block_name( $block_name );
	if ( '' === $block_name ) {
		return false;
	}

	return (
		'core/accordion-content' === $block_name ||
		'core/accordion-item' === $block_name ||
		false !== strpos( $block_name, 'accordion-content' ) ||
		false !== strpos( $block_name, 'accordion-item' )
	);
}

/**
 * Check if the block is an accordion container block.
 *
 * @param string $block_name Block name.
 * @return bool True if the block matches an accordion container block.
 */
function accordion_json_ld_is_accordion_container_block( $block_name ) {
	$block_name = accordion_json_ld_normalize_block_name( $block_name );
	if ( '' === $block_name ) {
		return false;
	}

	if ( 'core/accordion' === $block_name ) {
		return true;
	}

	$needle_length = strlen( '/accordion' );
	if ( strlen( $block_name ) < $needle_length ) {
		return false;
	}

	return '/accordion' === substr( $block_name, -$needle_length );
}

/**
 * Check whether JSON-LD output is enabled for a block.
 *
 * @param array $block Block data.
 * @return bool True if JSON-LD output is enabled for the block.
 */
function accordion_json_ld_is_json_ld_enabled_for_block( $block ) {
	if ( ! is_array( $block ) ) {
		return false;
	}

	if ( empty( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
		return false;
	}

	if ( array_key_exists( 'accordionJsonLdEnabled', $block['attrs'] ) ) {
		return false !== $block['attrs']['accordionJsonLdEnabled'];
	}

	return false;
}

/**
 * Check if the block is an accordion header block.
 *
 * @param string $block_name Block name.
 * @return bool True if the block matches an accordion header block.
 */
function accordion_json_ld_is_accordion_header_block( $block_name ) {
	$block_name = accordion_json_ld_normalize_block_name( $block_name );
	if ( '' === $block_name ) {
		return false;
	}

	return (
		'core/accordion-header' === $block_name ||
		'core/accordion-heading' === $block_name ||
		false !== strpos( $block_name, 'accordion-header' ) ||
		false !== strpos( $block_name, 'accordion-heading' )
	);
}

/**
 * Check if the block is an accordion panel block.
 *
 * @param string $block_name Block name.
 * @return bool True if the block matches an accordion panel block.
 */
function accordion_json_ld_is_accordion_panel_block( $block_name ) {
	$block_name = accordion_json_ld_normalize_block_name( $block_name );
	if ( '' === $block_name ) {
		return false;
	}

	return (
		'core/accordion-panel' === $block_name ||
		false !== strpos( $block_name, 'accordion-panel' )
	);
}

/**
 * Normalize a block name.
 *
 * @param string $block_name Block name.
 * @return string Normalized block name.
 */
function accordion_json_ld_normalize_block_name( $block_name ) {
	return is_string( $block_name ) ? strtolower( $block_name ) : '';
}

accordion_json_ld_init();
