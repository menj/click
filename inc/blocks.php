<?php
/**
 * Dynamic blocks. Content comes from menj.click → settings; every block is
 * also insertable and previewable in the Site Editor.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the blocks and the shared editor script.
 */
function menj_click_register_blocks() {
	wp_register_script(
		'menj-click-blocks',
		MENJ_CLICK_URI . '/assets/js/blocks-editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		MENJ_CLICK_VERSION,
		true
	);
	wp_localize_script(
		'menj-click-blocks',
		'menjClickBlocks',
		array(
			'icons' => menj_click_icon_choices(),
			'tones' => menj_click_tone_choices(),
		)
	);
	wp_register_script( 'menj-click-front', MENJ_CLICK_URI . '/assets/js/front.js', array(), MENJ_CLICK_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	$string = array( 'type' => 'string' );
	$blocks = array(
		'wordmark'       => array(
			'title'      => __( 'Wordmark', 'menj-click' ),
			'icon'       => 'admin-links',
			'attributes' => array( 'variant' => array_merge( $string, array( 'default' => 'header' ) ) ),
		),
		'hero'           => array(
			'title'      => __( 'Link hub hero', 'menj-click' ),
			'icon'       => 'cover-image',
			'supports'   => array( 'html' => false, 'align' => array( 'full' ) ),
			'attributes' => array( 'align' => array_merge( $string, array( 'default' => 'full' ) ) ),
		),
		'link-panel'     => array(
			'title'      => __( 'Link panel', 'menj-click' ),
			'icon'       => 'list-view',
			'attributes' => array( 'layout' => array_merge( $string, array( 'default' => 'list' ) ) ),
		),
		'icon'           => array(
			'title'      => __( 'Feature icon', 'menj-click' ),
			'icon'       => 'star-empty',
			'attributes' => array(
				'name' => array_merge( $string, array( 'default' => 'link' ) ),
				'tone' => array_merge( $string, array( 'default' => 'blue' ) ),
				'size' => array( 'type' => 'number', 'default' => 44 ),
			),
		),
		'short-links'    => array(
			'title'      => __( 'Short links', 'menj-click' ),
			'icon'       => 'admin-links',
			'attributes' => array(
				'mode' => array_merge( $string, array( 'default' => 'featured' ) ),
				'copy' => array( 'type' => 'boolean', 'default' => false ),
			),
		),
		'qr-grid'        => array(
			'title'      => __( 'QR codes', 'menj-click' ),
			'icon'       => 'screenoptions',
			'attributes' => array(
				'mode'      => array_merge( $string, array( 'default' => 'featured' ) ),
				'downloads' => array( 'type' => 'boolean', 'default' => false ),
			),
		),
		'latest-notes'   => array(
			'title'      => __( 'Latest notes', 'menj-click' ),
			'icon'       => 'text-page',
			'attributes' => array( 'count' => array( 'type' => 'number', 'default' => 0 ) ),
		),
		'notes-masthead' => array(
			'title' => __( 'Notes masthead', 'menj-click' ),
			'icon'  => 'heading',
		),
		'social'         => array(
			'title' => __( 'Social links', 'menj-click' ),
			'icon'  => 'share',
		),
		'directory'      => array(
			'title' => __( 'Directory', 'menj-click' ),
			'icon'  => 'index-card',
		),
		'listing'        => array(
			'title' => __( 'Directory listing', 'menj-click' ),
			'icon'  => 'id-alt',
		),
	);

	foreach ( $blocks as $name => $args ) {
		register_block_type(
			'menj/' . $name,
			array_merge(
				array(
					'api_version'           => 3,
					'category'              => 'theme',
					'editor_script_handles' => array( 'menj-click-blocks' ),
					'supports'              => array( 'html' => false ),
					'attributes'            => array(),
					'render_callback'       => 'menj_click_render_' . str_replace( '-', '_', $name ),
				),
				$args
			)
		);
	}
}
add_action( 'init', 'menj_click_register_blocks' );

/**
 * True inside the editor's live preview request.
 */
function menj_click_is_preview() {
	return defined( 'REST_REQUEST' ) && REST_REQUEST;
}

/* -------------------------------------------------------------------------- */
/* Renderers                                                                   */
/* -------------------------------------------------------------------------- */

function menj_click_render_wordmark( $attrs ) {
	list( $base, $accent ) = menj_click_wordmark_parts();
	$variant = ( isset( $attrs['variant'] ) && 'footer' === $attrs['variant'] ) ? 'footer' : 'header';
	return sprintf(
		'<div %1$s><a class="menj-wordmark__link" href="%2$s" rel="home"%3$s>%4$s<span class="menj-wordmark__accent">%5$s</span></a></div>',
		get_block_wrapper_attributes( array( 'class' => 'menj-wordmark menj-wordmark--' . $variant ) ),
		esc_url( home_url( '/' ) ),
		is_front_page() && ! menj_click_is_preview() ? ' aria-current="page"' : '',
		esc_html( $base ),
		esc_html( $accent )
	);
}

/**
 * The hub list, shared by the hero and the Links page.
 *
 * @param string $layout list|grid.
 */
function menj_click_hub_markup( $layout = 'list' ) {
	$items = menj_click_settings( 'hub' );
	$tones = menj_click_tone_choices();
	$rows  = '';
	foreach ( $items as $item ) {
		$url = menj_click_resolve_link( isset( $item['link'] ) ? $item['link'] : '' );
		if ( '' === $url || '' === trim( (string) $item['label'] ) ) {
			continue;
		}
		$tone  = isset( $tones[ $item['tone'] ] ) ? $item['tone'] : 'blue';
		$rows .= '<li class="menj-hub__row"><a class="menj-hub__item" href="' . esc_url( $url ) . '">'
			. '<span class="menj-tile menj-tile--' . esc_attr( $tone ) . '">' . menj_click_icon( $item['icon'], array( 'size' => 22 ) ) . '</span>'
			. '<span class="menj-hub__text"><span class="menj-hub__label">' . esc_html( $item['label'] ) . '</span>'
			. ( '' !== trim( (string) $item['description'] ) ? '<span class="menj-hub__desc">' . esc_html( $item['description'] ) . '</span>' : '' )
			. '</span>' . menj_click_icon( 'arrow-right', array( 'size' => 18, 'class' => 'menj-hub__arrow' ) ) . '</a></li>';
	}
	if ( '' === $rows ) {
		return '';
	}
	return '<nav class="menj-hub menj-hub--' . esc_attr( $layout ) . '" aria-label="' . esc_attr__( 'Featured links', 'menj-click' ) . '"><ul class="menj-hub__list">' . $rows . '</ul></nav>';
}

function menj_click_render_hero( $attrs ) {
	$h                     = menj_click_settings( 'hero' );
	list( $base, $accent ) = menj_click_wordmark_parts();
	$mode                  = in_array( $h['background'], array( 'ridges', 'image', 'plain' ), true ) ? $h['background'] : 'ridges';
	$bg                    = '';

	if ( 'image' === $mode && ! empty( $h['image_id'] ) ) {
		$bg = wp_get_attachment_image(
			(int) $h['image_id'],
			'full',
			false,
			array( 'class' => 'menj-hero__image', 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'sizes' => '100vw' )
		);
	}
	if ( '' === $bg && 'plain' !== $mode ) {
		$mode = 'ridges';
		$bg   = menj_click_ridges_svg();
	}

	$lines    = array_filter( array_map( 'trim', preg_split( '/\R/', (string) $h['headline'] ) ), 'strlen' );
	$headline = implode( '<br>', array_map( 'esc_html', $lines ) );
	$shade    = max( 0, min( 90, (int) $h['shade'] ) ) / 100;
	$wrapper  = get_block_wrapper_attributes(
		array(
			'class' => 'menj-hero menj-hero--' . $mode,
			'style' => '--menj-hero-shade:' . $shade . ';',
		)
	);

	$html  = '<section ' . $wrapper . ' aria-labelledby="menj-hero-title">';
	$html .= '<div class="menj-hero__bg" aria-hidden="true">' . $bg . '</div>';
	$html .= '<div class="menj-hero__inner"><div class="menj-hero__copy">';
	if ( '' !== trim( (string) $h['eyebrow'] ) ) {
		$html .= '<p class="menj-hero__eyebrow">' . esc_html( $h['eyebrow'] ) . '</p>';
	}
	$html .= '<h1 id="menj-hero-title" class="menj-hero__wordmark">' . esc_html( $base ) . '<span>' . esc_html( $accent ) . '</span></h1>';
	if ( '' !== $headline ) {
		$html .= '<p class="menj-hero__headline">' . $headline . '</p>';
	}
	if ( '' !== trim( (string) $h['lede'] ) ) {
		$html .= '<p class="menj-hero__lede">' . esc_html( $h['lede'] ) . '</p>';
	}
	$html .= '</div>' . menj_click_hub_markup( 'list' ) . '</div></section>';
	return $html;
}

function menj_click_render_link_panel( $attrs ) {
	$layout = ( isset( $attrs['layout'] ) && 'grid' === $attrs['layout'] ) ? 'grid' : 'list';
	$inner  = menj_click_hub_markup( $layout );
	return '' === $inner ? '' : '<div ' . get_block_wrapper_attributes( array( 'class' => 'menj-link-panel' ) ) . '>' . $inner . '</div>';
}

function menj_click_render_icon( $attrs ) {
	$name  = isset( $attrs['name'] ) ? sanitize_key( $attrs['name'] ) : 'link';
	$tones = menj_click_tone_choices();
	$tone  = ( isset( $attrs['tone'] ) && isset( $tones[ $attrs['tone'] ] ) ) ? $attrs['tone'] : 'blue';
	$size  = isset( $attrs['size'] ) ? max( 16, min( 96, (int) $attrs['size'] ) ) : 44;
	return '<div ' . get_block_wrapper_attributes( array( 'class' => 'menj-feature-icon menj-tone--' . $tone ) ) . '>'
		. menj_click_icon( $name, array( 'size' => $size, 'class' => 'menj-feature-icon__svg' ) ) . '</div>';
}

function menj_click_render_short_links( $attrs ) {
	$mode    = ( isset( $attrs['mode'] ) && 'all' === $attrs['mode'] ) ? 'all' : 'featured';
	$copy    = ! empty( $attrs['copy'] );
	$is_mgr  = current_user_can( 'manage_options' );
	$map     = menj_click_link_map();
	$rows    = array();

	if ( 'all' === $mode ) {
		$links = menj_click_query_links( array( 'status' => 'active', 'per_page' => 1000, 'orderby' => 'slug', 'order' => 'ASC' ) )['items'];
		foreach ( $links as $l ) {
			if ( '' === $l['target'] ) {
				continue;
			}
			$rows[] = array( 'slug' => $l['slug'], 'label' => '' !== $l['title'] ? $l['title'] : wp_trim_words( $l['description'], 8 ), 'missing' => false );
		}
	} else {
		foreach ( menj_click_settings( 'featured' ) as $f ) {
			$slug = menj_click_clean_slug( isset( $f['slug'] ) ? $f['slug'] : '' );
			if ( '' === $slug ) {
				continue;
			}
			$missing = ! isset( $map[ $slug ] );
			if ( $missing && ! $is_mgr ) {
				continue; // Visitors never see links that aren't live.
			}
			$rows[] = array( 'slug' => $slug, 'label' => (string) $f['label'], 'missing' => $missing );
		}
	}

	if ( ! $rows ) {
		return ( $is_mgr && 'all' === $mode ) ? '<p ' . get_block_wrapper_attributes( array( 'class' => 'menj-empty' ) ) . '>' . esc_html__( 'No active short links yet. Add them under menj.click → Short Links.', 'menj-click' ) . '</p>' : '';
	}
	if ( $copy ) {
		wp_enqueue_script( 'menj-click-front' );
	}

	$host = menj_click_host();
	$out  = '<div ' . get_block_wrapper_attributes( array( 'class' => 'menj-shortlist' . ( $copy ? ' has-copy' : '' ) ) ) . '><ul class="menj-shortlist__list">';
	foreach ( $rows as $r ) {
		$url  = home_url( '/' . $r['slug'] );
		$out .= '<li class="menj-shortlist__row' . ( $r['missing'] ? ' is-missing' : '' ) . '"' . ( $r['missing'] ? ' title="' . esc_attr__( 'Only you can see this row: the short link isn’t set up yet.', 'menj-click' ) . '"' : '' ) . '>'
			. '<a class="menj-shortlist__link" href="' . esc_url( $url ) . '">'
			. '<span class="menj-shortlist__url"><span class="menj-shortlist__host">' . esc_html( $host ) . '/</span><span class="menj-shortlist__slug">' . esc_html( $r['slug'] ) . '</span></span>'
			. '<span class="menj-shortlist__label">' . esc_html( $r['label'] ) . '</span>'
			. menj_click_icon( 'square-arrow-out-up-right', array( 'size' => 16, 'class' => 'menj-shortlist__icon' ) )
			. '</a>';
		if ( $copy ) {
			$out .= '<button type="button" class="menj-copy" data-menj-copy="' . esc_attr( $url ) . '" data-copied="' . esc_attr__( 'Copied', 'menj-click' ) . '">' . esc_html__( 'Copy', 'menj-click' ) . '</button>';
		}
		$out .= '</li>';
	}
	return $out . '</ul></div>';
}

function menj_click_render_qr_grid( $attrs ) {
	$mode      = ( isset( $attrs['mode'] ) && 'all' === $attrs['mode'] ) ? 'all' : 'featured';
	$downloads = ! empty( $attrs['downloads'] );
	$is_mgr    = current_user_can( 'manage_options' );
	$map       = menj_click_link_map();
	$cards     = array();

	if ( 'all' === $mode ) {
		$links = menj_click_query_links( array( 'status' => 'active', 'per_page' => 1000, 'orderby' => 'slug', 'order' => 'ASC' ) )['items'];
		foreach ( $links as $l ) {
			if ( '' !== $l['target'] ) {
				$cards[] = array( 'slug' => $l['slug'], 'label' => '' !== $l['title'] ? $l['title'] : $l['slug'], 'caption' => wp_trim_words( $l['description'], 8 ), 'missing' => false );
			}
		}
	} else {
		foreach ( menj_click_settings( 'qr' )['cards'] as $c ) {
			$slug = menj_click_clean_slug( isset( $c['slug'] ) ? $c['slug'] : '' );
			if ( '' === $slug ) {
				continue;
			}
			$missing = ! isset( $map[ $slug ] );
			if ( $missing && ! $is_mgr ) {
				continue;
			}
			$cards[] = array( 'slug' => $slug, 'label' => (string) $c['label'], 'caption' => (string) $c['caption'], 'missing' => $missing );
		}
	}

	if ( ! $cards ) {
		return ( $is_mgr && 'all' === $mode ) ? '<p ' . get_block_wrapper_attributes( array( 'class' => 'menj-empty' ) ) . '>' . esc_html__( 'No active short links yet, so there are no QR codes to show.', 'menj-click' ) . '</p>' : '';
	}

	$host = menj_click_host();
	$out  = '<div ' . get_block_wrapper_attributes( array( 'class' => 'menj-qrgrid' . ( $downloads ? ' has-downloads' : '' ) ) ) . '>';
	foreach ( $cards as $c ) {
		/* translators: %s: short URL */
		$title = sprintf( __( 'QR code for %s', 'menj-click' ), $host . '/' . $c['slug'] );
		$svg   = menj_click_qr_svg( menj_click_qr_url( $c['slug'] ), array( 'title' => $title ) );
		$out  .= '<figure class="menj-qrcard' . ( $c['missing'] ? ' is-missing' : '' ) . '">'
			. '<a class="menj-qrcard__code" href="' . esc_url( home_url( '/' . $c['slug'] ) ) . '">' . $svg . '</a>'
			. '<figcaption class="menj-qrcard__text"><strong class="menj-qrcard__label">' . esc_html( $c['label'] ) . '</strong>'
			. ( '' !== trim( $c['caption'] ) ? '<span class="menj-qrcard__caption">' . esc_html( $c['caption'] ) . '</span>' : '' );
		if ( $downloads && ! $c['missing'] ) {
			$base = home_url( '/qr/' . $c['slug'] );
			$out .= '<span class="menj-qrcard__downloads">'
				. '<a href="' . esc_url( $base . '.svg?download=1' ) . '" download>' . esc_html__( 'SVG', 'menj-click' ) . '</a>'
				. '<a href="' . esc_url( $base . '.png?download=1' ) . '" download>' . esc_html__( 'PNG', 'menj-click' ) . '</a></span>';
		}
		$out .= '</figcaption></figure>';
	}
	return $out . '</div>';
}

function menj_click_render_latest_notes( $attrs ) {
	$n = menj_click_settings( 'notes' );
	if ( empty( $n['home_strip'] ) ) {
		return menj_click_is_preview() ? '<p class="menj-empty">' . esc_html__( 'The latest-notes strip is switched off in menj.click → Notes.', 'menj-click' ) . '</p>' : '';
	}
	$count = ! empty( $attrs['count'] ) ? (int) $attrs['count'] : (int) $n['home_count'];
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 6, $count ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( ! $query->have_posts() ) {
		return '';
	}
	$page_id = (int) get_option( 'page_for_posts' );
	$all_url = $page_id ? get_permalink( $page_id ) : home_url( '/notes/' );
	/* translators: %s: Notes section title */
	$heading = sprintf( __( 'Latest from %s', 'menj-click' ), $n['title'] );

	$items = '';
	while ( $query->have_posts() ) {
		$query->the_post();
		$items .= '<li><article class="menj-latest__item">'
			. '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>'
			. '<h3 class="menj-latest__post"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>'
			. '<p>' . esc_html( wp_trim_words( get_the_excerpt(), 24 ) ) . '</p></article></li>';
	}
	wp_reset_postdata();

	return '<section ' . get_block_wrapper_attributes( array( 'class' => 'menj-latest' ) ) . ' aria-labelledby="menj-latest-title"><div class="menj-latest__inner">'
		. '<div class="menj-latest__head"><h2 id="menj-latest-title" class="menj-latest__title">' . esc_html( $heading ) . '</h2>'
		. '<a class="menj-latest__all" href="' . esc_url( $all_url ) . '">' . esc_html__( 'All notes', 'menj-click' ) . '</a></div>'
		. '<ul class="menj-latest__list">' . $items . '</ul></div></section>';
}

function menj_click_render_notes_masthead( $attrs ) {
	$n = menj_click_settings( 'notes' );
	return '<header ' . get_block_wrapper_attributes( array( 'class' => 'menj-masthead' . ( ! empty( $n['typewriter'] ) ? ' is-typewriter' : '' ) ) ) . '>'
		. '<h1 class="menj-masthead__title">' . esc_html( $n['title'] ) . '</h1>'
		. ( '' !== trim( (string) $n['intro'] ) ? '<p class="menj-masthead__intro">' . esc_html( $n['intro'] ) . '</p>' : '' )
		. '</header>';
}

function menj_click_render_social( $attrs ) {
	$f     = menj_click_settings( 'footer' );
	$items = '';
	if ( ! empty( $f['website'] ) ) {
		$items .= '<li><a href="' . esc_url( $f['website'] ) . '" rel="me">' . menj_click_icon( 'link', array( 'size' => 20, 'label' => __( 'Website', 'menj-click' ) ) ) . '</a></li>';
	}
	if ( ! empty( $f['email'] ) && is_email( $f['email'] ) ) {
		$items .= '<li><a href="mailto:' . antispambot( $f['email'], 1 ) . '">' . menj_click_icon( 'mail', array( 'size' => 20, 'label' => __( 'Email', 'menj-click' ) ) ) . '</a></li>';
	}
	if ( ! empty( $f['x'] ) ) {
		$handle = trim( preg_replace( '#^https?://(www\.)?(x|twitter)\.com/#i', '', (string) $f['x'] ), "@/ \t" );
		if ( '' !== $handle ) {
			$items .= '<li><a href="' . esc_url( 'https://x.com/' . rawurlencode( $handle ) ) . '" rel="me">' . menj_click_icon( 'x-logo', array( 'size' => 18, 'label' => 'X' ) ) . '</a></li>';
		}
	}
	return '' === $items ? '' : '<ul ' . get_block_wrapper_attributes( array( 'class' => 'menj-social' ) ) . '>' . $items . '</ul>';
}

/* -------------------------------------------------------------------------- */
/* Navigation: mark the current section                                        */
/* -------------------------------------------------------------------------- */

/**
 * Core only marks the current item for links to posts by ID. This adds the
 * same state for custom links such as /links/ or /notes/ (and every note
 * under /notes/), so the active underline works everywhere.
 */
function menj_click_nav_current_state( $html, $block ) {
	$url = isset( $block['attrs']['url'] ) ? (string) $block['attrs']['url'] : '';
	if ( '' === $url || false !== strpos( $html, 'current-menu-item' ) ) {
		return $html;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( $host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $host ) {
		return $html;
	}
	$link    = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$current = untrailingslashit( menj_click_request_path() );
	$link    = '' === $link ? '/' : $link;
	$current = '' === $current ? '/' : $current;
	$active  = $link === $current || ( '/' !== $link && 0 === strpos( $current . '/', $link . '/' ) );
	if ( ! $active ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	if ( $p->next_tag( 'li' ) ) {
		$p->add_class( 'current-menu-item' );
	}
	if ( $p->next_tag( 'a' ) ) {
		$p->set_attribute( 'aria-current', 'page' );
	}
	return $p->get_updated_html();
}
add_filter( 'render_block_core/navigation-link', 'menj_click_nav_current_state', 10, 2 );
