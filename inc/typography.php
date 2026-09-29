<?php
/**
 * Front-end assets, editor styles, and language support.
 *
 * Every script font is declared in theme.json with a unicode-range, so a
 * page downloads a font only when it contains that script. The Language
 * button in the editor tags text with lang/dir; languages.css then picks
 * the right face automatically.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/**
 * Languages offered in the editor (inline format and block setting).
 */
function menj_click_languages() {
	return array(
		'he'            => array( 'label' => __( 'Hebrew', 'menj-click' ), 'lang' => 'he', 'dir' => 'rtl', 'variant' => '' ),
		'grc'           => array( 'label' => __( 'Greek', 'menj-click' ), 'lang' => 'grc', 'dir' => 'ltr', 'variant' => '' ),
		'syc'           => array( 'label' => __( 'Syriac', 'menj-click' ), 'lang' => 'syc', 'dir' => 'rtl', 'variant' => '' ),
		'cpa'           => array( 'label' => __( 'Christian Palestinian Aramaic', 'menj-click' ), 'lang' => 'arc', 'dir' => 'rtl', 'variant' => 'cpa' ),
		'ar'            => array( 'label' => __( 'Arabic', 'menj-click' ), 'lang' => 'ar', 'dir' => 'rtl', 'variant' => '' ),
		'quran'         => array( 'label' => __( 'Qur’an', 'menj-click' ), 'lang' => 'ar', 'dir' => 'rtl', 'variant' => 'quran' ),
		'calligraphy'   => array( 'label' => __( 'Arabic calligraphy', 'menj-click' ), 'lang' => 'ar', 'dir' => 'rtl', 'variant' => 'calligraphy' ),
		'calligraphy-b' => array( 'label' => __( 'Arabic calligraphy (alternate)', 'menj-click' ), 'lang' => 'ar', 'dir' => 'rtl', 'variant' => 'calligraphy-b' ),
		'akk'           => array( 'label' => __( 'Cuneiform', 'menj-click' ), 'lang' => 'akk', 'dir' => 'ltr', 'variant' => '' ),
		'translit'      => array( 'label' => __( 'Transliteration', 'menj-click' ), 'lang' => '', 'dir' => '', 'variant' => 'translit' ),
		'estrangelo'    => array( 'label' => __( 'Estrangelo (legacy encoding)', 'menj-click' ), 'lang' => '', 'dir' => '', 'variant' => 'estrangelo' ),
	);
}

/**
 * Front-end styles and scripts.
 */
function menj_click_enqueue_assets() {
	wp_enqueue_style( 'menj-click-front', MENJ_CLICK_URI . '/assets/css/front.css', array(), MENJ_CLICK_VERSION );
	wp_enqueue_style( 'menj-click-languages', MENJ_CLICK_URI . '/assets/css/languages.css', array( 'menj-click-front' ), MENJ_CLICK_VERSION );
}
add_action( 'wp_enqueue_scripts', 'menj_click_enqueue_assets' );

/**
 * Editor styles (loaded into the editor canvas).
 */
function menj_click_editor_styles() {
	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/front.css', 'assets/css/languages.css', 'assets/css/directory.css', 'assets/css/editor.css' ) );
}
add_action( 'after_setup_theme', 'menj_click_editor_styles', 20 );

/**
 * The Language button and block setting.
 */
function menj_click_editor_language_assets() {
	wp_enqueue_script(
		'menj-click-language',
		MENJ_CLICK_URI . '/assets/js/language-format.js',
		array( 'wp-rich-text', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-hooks', 'wp-compose', 'wp-i18n' ),
		MENJ_CLICK_VERSION,
		true
	);
	wp_localize_script( 'menj-click-language', 'menjClickLanguages', menj_click_languages() );
}
add_action( 'enqueue_block_editor_assets', 'menj_click_editor_language_assets' );

/**
 * Apply a block's Language setting on the front end. The attribute lives
 * in the block comment only, so core blocks stay valid without the theme.
 */
function menj_click_apply_block_language( $content, $block ) {
	if ( empty( $block['attrs']['menjLang'] ) || '' === trim( $content ) ) {
		return $content;
	}
	$langs = menj_click_languages();
	$key   = $block['attrs']['menjLang'];
	if ( ! isset( $langs[ $key ] ) ) {
		return $content;
	}
	$p = new WP_HTML_Tag_Processor( $content );
	if ( ! $p->next_tag() ) {
		return $content;
	}
	if ( '' !== $langs[ $key ]['lang'] ) {
		$p->set_attribute( 'lang', $langs[ $key ]['lang'] );
	}
	if ( '' !== $langs[ $key ]['dir'] ) {
		$p->set_attribute( 'dir', $langs[ $key ]['dir'] );
	}
	if ( '' !== $langs[ $key ]['variant'] ) {
		$p->set_attribute( 'data-menj-variant', $langs[ $key ]['variant'] );
	}
	return $p->get_updated_html();
}
add_filter( 'render_block', 'menj_click_apply_block_language', 10, 2 );
