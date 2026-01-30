<?php
/**
 * Plugin Name: Accordion JSON-LD
 * Description: Generates FAQPage JSON-LD from the core Accordion block and outputs it in wp_head.
 * Version: 0.3.0
 * Author: Koji Kuno
 * Requires at least: 6.9
 * Requires PHP: 8.3
 * Text Domain: accordion-json-ld
 * Domain Path: /languages
 * License: GPL 2.0 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
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
 * 初期化。
 */
function accordion_json_ld_init() {
	add_action( 'init', 'accordion_json_ld_load_textdomain' );
	add_action( 'wp_head', 'accordion_json_ld_output_json_ld', 20 );
	add_action( 'enqueue_block_editor_assets', 'accordion_json_ld_enqueue_editor_assets' );
	add_action( 'plugins_loaded', 'accordion_json_ld_bootstrap_github_updater' );
}

/**
 * GitHub プラグインアップデータを初期化。
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
 * 翻訳ファイルを読み込み。
 */
function accordion_json_ld_load_textdomain() {
	load_plugin_textdomain(
		'accordion-json-ld',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

/**
 * ブロックエディター用スクリプトを読み込み。
 */
function accordion_json_ld_enqueue_editor_assets() {
	$script_path = __DIR__ . '/build/index.js';
	$script_url  = plugin_dir_url( __FILE__ ) . 'build/index.js';
	$asset_path  = __DIR__ . '/build/index.asset.php';

	if ( ! file_exists( $script_path ) ) {
		return;
	}

	$script_version = filemtime( $script_path );
	$asset = array(
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
 * JSON-LD を出力。
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
 * 再帰的に Q&A を抽出。
 *
 * @param array $blocks ブロック配列。
 * @return array
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
 * アコーディオン内容ブロックから Q&A を抽出。
 *
 * @param array $block ブロック。
 * @return array|null
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
 * ブロックからプレーンテキストを抽出。
 *
 * @param array $block ブロック。
 * @return string
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

	// aria-hidden の装飾文字（例: 開閉アイコン）を除外。
	$text = preg_replace( '/<[^>]*aria-hidden=("|\')true\\1[^>]*>.*?<\\/[^>]+>/i', ' ', (string) $text );
	$text = wp_strip_all_tags( $text, true );
	$text = preg_replace( '/\s+/', ' ', (string) $text );

	return is_string( $text ) ? $text : '';
}

/**
 * ブロック名を取得。
 *
 * @param array $block ブロック配列。
 * @return string
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
 * アコーディオン内容ブロック判定。
 *
 * @param string $block_name ブロック名。
 * @return bool
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
 * アコーディオンコンテナブロック判定。
 *
 * @param string $block_name ブロック名。
 * @return bool
 */
function accordion_json_ld_is_accordion_container_block( $block_name ) {
	$block_name = accordion_json_ld_normalize_block_name( $block_name );
	if ( '' === $block_name ) {
		return false;
	}

	if ( 'core/accordion' === $block_name ) {
		return true;
	}

	$needle = '/accordion';
	if ( strlen( $block_name ) < strlen( $needle ) ) {
		return false;
	}

	return $needle === substr( $block_name, -strlen( $needle ) );
}

/**
 * ブロックの JSON-LD 出力可否を判定。
 *
 * @param array $block ブロック。
 * @return bool
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
 * アコーディオン見出しブロック判定。
 *
 * @param string $block_name ブロック名。
 * @return bool
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
 * アコーディオン本文ブロック判定。
 *
 * @param string $block_name ブロック名。
 * @return bool
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
 * ブロック名を正規化。
 *
 * @param string $block_name ブロック名。
 * @return string
 */
function accordion_json_ld_normalize_block_name( $block_name ) {
	return is_string( $block_name ) ? strtolower( $block_name ) : '';
}

accordion_json_ld_init();
