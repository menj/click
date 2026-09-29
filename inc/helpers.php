<?php
/**
 * Shared helpers: settings model, icons, link resolution.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default settings. Every section is replaced whole on save, so lists
 * (hub items, QR cards) never merge by index with their defaults.
 *
 * @return array
 */
function menj_click_defaults() {
	return array(
		'hero'     => array(
			'eyebrow'    => 'One domain. Many destinations.',
			'headline'   => "Your personal link hub.\nShort links. QR destinations.",
			'lede'       => 'Quick access to everything — from my websites and projects to useful tools and resources.',
			'background' => 'ridges',
			'image_id'   => 0,
			'shade'      => 55,
		),
		'hub'      => array(
			array( 'label' => 'SEO Services', 'description' => 'Get in touch for SEO support and consulting.', 'link' => '/seo', 'icon' => 'link', 'tone' => 'green' ),
			array( 'label' => 'Blog', 'description' => 'Articles, thoughts and updates.', 'link' => '/blog', 'icon' => 'file-text', 'tone' => 'blue' ),
			array( 'label' => 'About Me', 'description' => 'Who I am and what I do.', 'link' => '/bio', 'icon' => 'user', 'tone' => 'purple' ),
			array( 'label' => 'Books', 'description' => 'My books and writing projects.', 'link' => '/book', 'icon' => 'book-open', 'tone' => 'amber' ),
			array( 'label' => 'Projects', 'description' => 'Web projects, tools and experiments.', 'link' => '/projects', 'icon' => 'code-xml', 'tone' => 'red' ),
			array( 'label' => 'All Links', 'description' => 'Everything in one place.', 'link' => '/links/', 'icon' => 'link', 'tone' => 'teal' ),
		),
		'featured' => array(
			array( 'slug' => 'seo', 'label' => 'SEO services' ),
			array( 'slug' => 'blog', 'label' => 'Blog' ),
			array( 'slug' => 'bio', 'label' => 'About me' ),
			array( 'slug' => 'buzz', 'label' => 'Social media' ),
			array( 'slug' => 'book', 'label' => 'Books' ),
			array( 'slug' => 'apo', 'label' => 'Apostle of Doom' ),
		),
		'qr'       => array(
			'cards'      => array(
				array( 'slug' => 'book', 'label' => 'Book', 'caption' => 'Read more / Author info' ),
				array( 'slug' => 'contact', 'label' => 'Contact', 'caption' => 'Get in touch' ),
				array( 'slug' => 'work', 'label' => 'Portfolio', 'caption' => 'View my work' ),
				array( 'slug' => 'apo', 'label' => 'Apo', 'caption' => 'Visit the site' ),
			),
			'foreground' => '#0E1624',
			'background' => '#FFFFFF',
			'ecc'        => 'M',
			'margin'     => 4,
		),
		'notes'    => array(
			'title'      => 'Notes',
			'intro'      => 'Articles, thoughts and updates.',
			'typewriter' => 1,
			'home_strip' => 1,
			'home_count' => 3,
		),
		'footer'   => array(
			'website' => 'https://menj.me/',
			'email'   => '',
			'x'       => '',
		),
		'links'    => array(
			'default_redirect' => 307,
			'retention_days'   => 365,
			'count_bots'       => 0,
		),
		'directory' => menj_click_directory_defaults(),
		'dir_emails' => menj_click_directory_email_defaults(),
	);
}

/**
 * Saved settings merged over defaults, per section.
 *
 * @param string|null $section Optional section key.
 * @param bool        $refresh Rebuild from the database.
 * @return array
 */
function menj_click_settings( $section = null, $refresh = false ) {
	static $cache = null;
	if ( null === $cache || $refresh ) {
		$saved    = get_option( 'menj_click_settings', array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$defaults = menj_click_defaults();
		$cache    = array();
		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $saved[ $key ] ) || ! is_array( $saved[ $key ] ) ) {
				$cache[ $key ] = $value;
			} elseif ( array_values( $value ) === $value ) {
				$cache[ $key ] = array_values( $saved[ $key ] ); // Lists replace whole.
			} else {
				$cache[ $key ] = array_merge( $value, $saved[ $key ] );
			}
		}
	}
	if ( null === $section ) {
		return $cache;
	}
	return isset( $cache[ $section ] ) ? $cache[ $section ] : array();
}

/**
 * Save one settings section and flush the static cache.
 *
 * @param string $section Section key.
 * @param array  $value   Sanitised value.
 */
function menj_click_update_section( $section, array $value ) {
	$saved = get_option( 'menj_click_settings', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$saved[ $section ] = $value;
	update_option( 'menj_click_settings', $saved, true );
	menj_click_settings( null, true );
	do_action( 'menj_click_settings_changed', $section );
}

/**
 * Inline SVG icon (Lucide, ISC licence; X logo from Simple Icons, CC0).
 *
 * @param string $name Icon key.
 * @param array  $args { class, size, label }.
 * @return string
 */
function menj_click_icon( $name, array $args = array() ) {
	static $icons = array(
		'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />',
		'file-text' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z" /><path d="M14 2v5a1 1 0 0 0 1 1h5" /><path d="M10 9H8" /><path d="M16 13H8" /><path d="M16 17H8" />',
		'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />',
		'book-open' => '<path d="M12 5v16" /><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z" />',
		'code-xml' => '<path d="m18 16 4-4-4-4" /><path d="m6 8-4 4 4 4" /><path d="m14.5 4-5 16" />',
		'qr-code' => '<rect width="5" height="5" x="3" y="3" rx="1" /><rect width="5" height="5" x="16" y="3" rx="1" /><rect width="5" height="5" x="3" y="16" rx="1" /><path d="M21 16h-3a2 2 0 0 0-2 2v3" /><path d="M21 21v.01" /><path d="M12 7v3a2 2 0 0 1-2 2H7" /><path d="M3 12h.01" /><path d="M12 3h.01" /><path d="M12 16v.01" /><path d="M16 12h1" /><path d="M21 12v.01" /><path d="M12 21v-1" />',
		'sliders-horizontal' => '<path d="M10 5H3" /><path d="M12 19H3" /><path d="M14 3v4" /><path d="M16 17v4" /><path d="M21 12h-9" /><path d="M21 19h-5" /><path d="M21 5h-7" /><path d="M8 10v4" /><path d="M8 12H3" />',
		'square-arrow-out-up-right' => '<path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6" /><path d="m21 3-9 9" /><path d="M15 3h6v6" />',
		'mail' => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7" /><rect x="2" y="4" width="20" height="16" rx="2" />',
		'arrow-right' => '<path d="M5 12h14" /><path d="m12 5 7 7-7 7" />',
		'copy' => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2" /><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" />',
		'download' => '<path d="M12 15V3" /><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="m7 10 5 5 5-5" />',
		'globe' => '<circle cx="12" cy="12" r="10" /><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" /><path d="M2 12h20" />',
		'briefcase' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /><rect width="20" height="14" x="2" y="6" rx="2" />',
		'pen-line' => '<path d="M13 21h8" /><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" />',
		'rss' => '<path d="M4 11a9 9 0 0 1 9 9" /><path d="M4 4a16 16 0 0 1 16 16" /><circle cx="5" cy="19" r="1" />',
		'search' => '<path d="m21 21-4.34-4.34" /><circle cx="11" cy="11" r="8" />',
		'link-2' => '<path d="M9 17H7A5 5 0 0 1 7 7h2" /><path d="M15 7h2a5 5 0 1 1 0 10h-2" /><line x1="8" x2="16" y1="12" y2="12" />',
		'check' => '<path d="M20 6 9 17l-5-5" />',
		'plus'                      => '<path d="M5 12h14"/><path d="M12 5v14"/>',
		'scan-line' => '<path d="M3 7V5a2 2 0 0 1 2-2h2" /><path d="M17 3h2a2 2 0 0 1 2 2v2" /><path d="M21 17v2a2 2 0 0 1-2 2h-2" /><path d="M7 21H5a2 2 0 0 1-2-2v-2" /><path d="M7 12h10" />',
		'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /><path d="m9 12 2 2 4-4" />',
		'layers' => '<path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /><path d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /><path d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" />',
		'feather' => '<path d="M14.086 18.412A2 2 0 0112.67 19H5v-7.672a2 2 0 01.586-1.414L11.75 3.75a6 6 0 118.49 8.49z" /><path d="M16 8 2 22" /><path d="M17.488 15H9" />',
		'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />',
		'newspaper' => '<path d="M15 18h-5" /><path d="M18 14h-8" /><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-4 0v-9a2 2 0 0 1 2-2h2" /><rect width="8" height="4" x="10" y="6" rx="1" />',
		'map-pin' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" /><circle cx="12" cy="10" r="3" />',
		'calendar' => '<path d="M8 2v3" /><path d="M16 2v3" /><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 9h18" />',
		'heart' => '<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5" />',
		'star' => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z" />',
		'music' => '<path d="M9 18V5l12-2v13" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="16" r="3" />',
		'video' => '<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5" /><rect x="2" y="6" width="14" height="12" rx="2" />',
		'camera' => '<path d="M13.997 4a2 2 0 0 1 1.76 1.05l.486.9A2 2 0 0 0 18.003 7H20a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h1.997a2 2 0 0 0 1.759-1.048l.489-.904A2 2 0 0 1 10.004 4z" /><circle cx="12" cy="13" r="3" />',
		'graduation-cap' => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z" /><path d="M22 10v6" /><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5" />',
		'library' => '<path d="m16 6 4 14" /><path d="M12 6v14" /><path d="M8 8v12" /><path d="M4 4v16" />',
		'scroll-text' => '<path d="M15 12h-5" /><path d="M15 8h-5" /><path d="M19 17V5a2 2 0 0 0-2-2H4" /><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3" />',
		'languages' => '<path d="m5 8 6 6" /><path d="m4 14 6-6 2-3" /><path d="M2 5h12" /><path d="M7 2h1" /><path d="m22 22-5-10-5 10" /><path d="M14 18h6" />',
		'x-logo' => '<path d="M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z" fill="currentColor" stroke="none"/>',
	);
	if ( ! isset( $icons[ $name ] ) ) {
		$name = 'link';
	}
	$size  = isset( $args['size'] ) ? (int) $args['size'] : 24;
	$class = 'menj-icon menj-icon--' . sanitize_html_class( $name ) . ( ! empty( $args['class'] ) ? ' ' . $args['class'] : '' );
	$label = isset( $args['label'] ) ? (string) $args['label'] : '';
	$a11y  = '' !== $label ? 'role="img" aria-label="' . esc_attr( $label ) . '"' : 'aria-hidden="true" focusable="false"';
	return sprintf(
		'<svg class="%1$s" %2$s width="%3$d" height="%3$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">%4$s</svg>',
		esc_attr( $class ),
		$a11y,
		$size,
		$icons[ $name ]
	);
}

/**
 * Icon choices offered in the admin (key => label).
 *
 * @return array
 */
function menj_click_icon_choices() {
	return array(
		'link'               => 'Link',
		'file-text'          => 'Document',
		'user'               => 'Person',
		'book-open'          => 'Book',
		'code-xml'           => 'Code',
		'qr-code'            => 'QR code',
		'globe'              => 'Globe',
		'briefcase'          => 'Briefcase',
		'pen-line'           => 'Pen',
		'feather'            => 'Feather',
		'newspaper'          => 'Newspaper',
		'library'            => 'Library',
		'scroll-text'        => 'Scroll',
		'languages'          => 'Languages',
		'graduation-cap'     => 'Graduation cap',
		'rss'                => 'RSS',
		'mail'               => 'Mail',
		'map-pin'            => 'Map pin',
		'calendar'           => 'Calendar',
		'camera'             => 'Camera',
		'video'              => 'Video',
		'music'              => 'Music',
		'heart'              => 'Heart',
		'star'               => 'Star',
		'layers'             => 'Layers',
		'shield-check'       => 'Shield',
		'sliders-horizontal' => 'Sliders',
	);
}

/**
 * Tile tones (palette slugs without the "tile-" prefix).
 *
 * @return array
 */
function menj_click_tone_choices() {
	return array(
		'green'  => 'Green',
		'blue'   => 'Blue',
		'purple' => 'Purple',
		'amber'  => 'Amber',
		'red'    => 'Red',
		'teal'   => 'Teal',
	);
}

/**
 * Short-link slug rules: lowercase letters, digits, hyphen, underscore.
 *
 * @param string $slug Raw slug.
 * @return string Clean slug or '' when invalid.
 */
function menj_click_clean_slug( $slug ) {
	$slug = strtolower( trim( (string) $slug, " \t\n\r\0\x0B/" ) );
	return preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug ) ? $slug : '';
}

/**
 * Resolve a link field: "/slug" (short link), "/path/" (page) or a full URL.
 *
 * @param string $value Stored value.
 * @return string URL.
 */
function menj_click_resolve_link( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( 0 === strpos( $value, '/' ) ) {
		return home_url( $value );
	}
	return esc_url_raw( $value, array( 'http', 'https', 'mailto' ) );
}

/**
 * The host shown in front of short-link slugs, e.g. "menj.click".
 *
 * @return string
 */
function menj_click_host() {
	$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$port = wp_parse_url( home_url( '/' ), PHP_URL_PORT );
	$path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	return $host . ( $port ? ':' . $port : '' ) . $path;
}

/**
 * Split the site title into its base and ".tld" accent, e.g. menj / .click.
 *
 * @return array{0:string,1:string}
 */
function menj_click_wordmark_parts() {
	$name = get_bloginfo( 'name' );
	$name = '' !== $name ? $name : menj_click_host();
	$dot  = strpos( $name, '.' );
	if ( false === $dot || 0 === $dot ) {
		return array( $name, '' );
	}
	return array( substr( $name, 0, $dot ), substr( $name, $dot ) );
}

/**
 * Hero ridgeline artwork. Every colour is a CSS custom property, so each
 * colour scheme repaints it with no extra requests.
 *
 * @return string
 */
function menj_click_ridges_svg() {
	return '<svg class="menj-ridges" viewBox="0 0 1600 640" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false"><defs><linearGradient id="menj-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="menj-sky-top"/><stop offset=".52" class="menj-sky-mid"/><stop offset=".78" class="menj-sky-glow"/></linearGradient><radialGradient id="menj-glow" cx=".62" cy=".56" r=".55"><stop offset="0" class="menj-glow-core"/><stop offset="1" class="menj-glow-edge"/></radialGradient><linearGradient id="menj-mist" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="menj-mist-clear"/><stop offset="1" class="menj-mist-fog"/></linearGradient></defs><rect width="1600" height="640" fill="url(#menj-sky)"/><rect width="1600" height="640" fill="url(#menj-glow)"/><path class="menj-ridge menj-ridge-1" d="M0 640V296L0 296L20 298L40 300L60 299L80 305L100 310L120 316L140 318L160 325L180 328L200 334L220 332L240 325L260 325L280 319L300 311L320 306L340 301L360 300L380 306L400 307L420 312L440 317L460 318L480 322L500 322L520 322L540 319L560 318L580 317L600 320L620 322L640 325L660 334L680 337L700 337L720 334L740 326L760 318L780 309L800 298L820 292L840 282L860 279L880 272L900 273L920 271L940 265L960 264L980 265L1000 258L1020 256L1040 248L1060 243L1080 245L1100 247L1120 248L1140 253L1160 262L1180 274L1200 280L1220 282L1240 286L1260 285L1280 283L1300 285L1320 279L1340 279L1360 279L1380 279L1400 285L1420 284L1440 290L1460 289L1480 291L1500 288L1520 285L1540 286L1560 281L1580 277L1600 281V640Z"/><rect class="menj-mist" x="0" y="330" width="1600" height="98" fill="url(#menj-mist)"/><path class="menj-ridge menj-ridge-2" d="M0 640V385L0 385L16 379L32 366L48 356L64 344L80 339L96 338L112 335L128 339L144 337L160 339L176 345L192 343L208 342L224 343L240 339L256 337L272 346L288 348L304 353L320 358L336 365L352 362L368 365L384 358L400 354L416 348L432 338L448 330L464 330L480 331L496 332L512 335L528 342L544 345L560 350L576 356L592 364L608 362L624 364L640 368L656 372L672 381L688 391L704 402L720 406L736 412L752 415L768 415L784 415L800 407L816 397L832 387L848 385L864 379L880 376L896 370L912 372L928 376L944 374L960 376L976 369L992 372L1008 362L1024 363L1040 358L1056 365L1072 367L1088 373L1104 376L1120 381L1136 380L1152 384L1168 378L1184 368L1200 365L1216 350L1232 339L1248 330L1264 328L1280 322L1296 327L1312 328L1328 323L1344 323L1360 327L1376 324L1392 327L1408 329L1424 326L1440 331L1456 341L1472 346L1488 355L1504 369L1520 378L1536 387L1552 390L1568 389L1584 390L1600 388V640Z"/><rect class="menj-mist" x="0" y="395" width="1600" height="86" fill="url(#menj-mist)"/><path class="menj-ridge menj-ridge-3" d="M0 640V451L0 451L14 451L28 445L42 443L56 436L70 433L84 427L98 433L112 435L126 439L140 448L154 461L168 466L182 476L196 479L210 476L224 464L238 459L252 455L266 451L280 452L294 452L308 449L322 450L336 449L350 440L364 441L378 431L392 426L406 416L420 419L434 421L448 415L462 426L476 428L490 440L504 444L518 455L532 455L546 454L560 443L574 443L588 438L602 433L616 436L630 433L644 430L658 433L672 429L686 422L700 419L714 405L728 401L742 396L756 387L770 393L784 391L798 403L812 407L826 412L840 428L854 422L868 423L882 425L896 420L910 420L924 417L938 418L952 419L966 418L980 419L994 424L1008 419L1022 415L1036 412L1050 395L1064 390L1078 387L1092 380L1106 388L1120 389L1134 396L1148 403L1162 418L1176 415L1190 426L1204 426L1218 428L1232 425L1246 421L1260 424L1274 431L1288 426L1302 438L1316 439L1330 435L1344 441L1358 429L1372 419L1386 414L1400 410L1414 400L1428 404L1442 405L1456 409L1470 421L1484 427L1498 438L1512 442L1526 444L1540 446L1554 445L1568 442L1582 439L1596 446L1610 453V640Z"/><rect class="menj-mist" x="0" y="455" width="1600" height="74" fill="url(#menj-mist)"/><path class="menj-ridge menj-ridge-4" d="M0 640V472L0 472L12 468L24 473L36 479L48 485L60 498L72 502L84 509L96 509L108 511L120 506L132 504L144 508L156 520L168 519L180 530L192 531L204 522L216 526L228 510L240 513L252 507L264 497L276 504L288 507L300 504L312 513L324 508L336 517L348 512L360 513L372 501L384 505L396 513L408 519L420 521L432 534L444 535L456 527L468 519L480 518L492 508L504 505L516 499L528 496L540 491L552 492L564 493L576 492L588 482L600 479L612 471L624 471L636 469L648 468L660 473L672 479L684 485L696 498L708 499L720 496L732 486L744 490L756 484L768 495L780 496L792 500L804 501L816 498L828 500L840 495L852 479L864 476L876 476L888 476L900 476L912 482L924 495L936 492L948 495L960 502L972 497L984 505L996 500L1008 512L1020 512L1032 526L1044 532L1056 537L1068 533L1080 530L1092 523L1104 519L1116 519L1128 507L1140 508L1152 512L1164 509L1176 512L1188 510L1200 512L1212 501L1224 506L1236 497L1248 498L1260 498L1272 503L1284 515L1296 521L1308 524L1320 516L1332 521L1344 521L1356 512L1368 506L1380 505L1392 499L1404 506L1416 507L1428 497L1440 498L1452 492L1464 481L1476 476L1488 472L1500 468L1512 461L1524 463L1536 477L1548 479L1560 486L1572 488L1584 481L1596 483L1608 479V640Z"/><rect class="menj-mist" x="0" y="515" width="1600" height="62" fill="url(#menj-mist)"/><path class="menj-ridge menj-ridge-5" d="M0 640V591L0 591L8 602L16 596L24 594L32 584L40 580L48 567L56 576L64 597L72 591L80 574L88 576L96 567L104 578L112 589L120 590L128 585L136 585L144 585L152 584L160 599L168 611L176 592L184 588L192 576L200 585L208 585L216 591L224 586L232 587L240 575L248 564L256 572L264 568L272 573L280 580L288 558L296 550L304 549L312 569L320 580L328 568L336 571L344 563L352 560L360 565L368 577L376 577L384 588L392 566L400 565L408 567L416 574L424 575L432 574L440 572L448 549L456 545L464 551L472 567L480 564L488 559L496 545L504 542L512 556L520 558L528 571L536 569L544 566L552 559L560 574L568 578L576 585L584 585L592 593L600 580L608 568L616 572L624 593L632 587L640 586L648 571L656 577L664 564L672 575L680 586L688 584L696 576L704 566L712 572L720 569L728 586L736 600L744 600L752 589L760 584L768 579L776 599L784 610L792 602L800 591L808 591L816 586L824 596L832 601L840 598L848 594L856 583L864 577L872 575L880 581L888 578L896 588L904 567L912 562L920 564L928 561L936 580L944 580L952 578L960 566L968 567L976 574L984 573L992 595L1000 597L1008 581L1016 573L1024 575L1032 576L1040 582L1048 580L1056 573L1064 559L1072 547L1080 560L1088 564L1096 557L1104 568L1112 564L1120 546L1128 555L1136 550L1144 567L1152 575L1160 565L1168 557L1176 552L1184 565L1192 571L1200 582L1208 584L1216 570L1224 567L1232 560L1240 578L1248 586L1256 579L1264 570L1272 568L1280 560L1288 570L1296 576L1304 576L1312 573L1320 568L1328 563L1336 563L1344 573L1352 580L1360 590L1368 582L1376 571L1384 580L1392 582L1400 593L1408 604L1416 607L1424 591L1432 585L1440 591L1448 601L1456 606L1464 608L1472 583L1480 581L1488 574L1496 577L1504 583L1512 590L1520 588L1528 577L1536 561L1544 571L1552 586L1560 586L1568 585L1576 581L1584 579L1592 573L1600 579V640Z"/></svg>';
}

/**
 * Show only this theme's colour schemes in Appearance → Editor → Styles.
 * Twenty Twenty-Five's own variations would swap in its fonts and a palette
 * without the tile colours; block style variations are kept.
 *
 * @param WP_HTTP_Response $response Response.
 * @param WP_REST_Server   $server   Server.
 * @param WP_REST_Request  $request  Request.
 * @return WP_HTTP_Response
 */
function menj_click_filter_style_variations( $response, $server, $request ) {
	if ( ! preg_match( '#^/wp/v2/global-styles/themes/[^/]+/variations$#', $request->get_route() ) || get_stylesheet() !== $request->get_param( 'stylesheet' ) ) {
		return $response;
	}
	$data = $response->get_data();
	if ( is_array( $data ) ) {
		$response->set_data( array_values( array_filter( $data, 'menj_click_is_own_variation' ) ) );
	}
	return $response;
}
add_filter( 'rest_post_dispatch', 'menj_click_filter_style_variations', 10, 3 );

/**
 * Whether a style variation belongs to this theme (it carries the tile colours).
 */
function menj_click_is_own_variation( $variation ) {
	if ( ! empty( $variation['blockTypes'] ) ) {
		return true;
	}
	$palette = isset( $variation['settings']['color']['palette']['theme'] ) ? $variation['settings']['color']['palette']['theme'] : array();
	return in_array( 'tile-green', wp_list_pluck( (array) $palette, 'slug' ), true );
}
