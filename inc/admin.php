<?php
/**
 * menj.click settings: one page, organised in tabs.
 *
 * @package menj-click
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------- */
/* Page, tabs, assets                                                          */
/* -------------------------------------------------------------------------- */

function menj_click_admin_tabs() {
	return array(
		'hub'         => __( 'Link Hub', 'menj-click' ),
		'links'       => __( 'Short Links', 'menj-click' ),
		'qr'          => __( 'QR Codes', 'menj-click' ),
		'directory'   => __( 'Directory', 'menj-click' ),
		'notes'       => __( 'Notes', 'menj-click' ),
		'footer'      => __( 'Footer', 'menj-click' ),
		'appearance'  => __( 'Appearance', 'menj-click' ),
		'diagnostics' => __( 'Diagnostics', 'menj-click' ),
	);
}

function menj_click_admin_menu() {
	add_menu_page( 'menj.click', 'menj.click', 'manage_options', 'menj-click', 'menj_click_render_admin', 'dashicons-admin-links', 59 );
}
add_action( 'admin_menu', 'menj_click_admin_menu' );

function menj_click_admin_assets( $hook ) {
	if ( 'toplevel_page_menj-click' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'menj-click-admin', MENJ_CLICK_URI . '/assets/css/admin.css', array(), MENJ_CLICK_VERSION );
	wp_enqueue_media();
	wp_enqueue_script( 'menj-click-admin', MENJ_CLICK_URI . '/assets/js/admin.js', array(), MENJ_CLICK_VERSION, true );
	wp_localize_script(
		'menj-click-admin',
		'menjClickAdmin',
		array(
			'copied'      => __( 'Copied', 'menj-click' ),
			'chooseImage' => __( 'Choose a hero image', 'menj-click' ),
			'useImage'    => __( 'Use this image', 'menj-click' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'menj_click_admin_assets' );

function menj_click_current_tab() {
	$tabs = menj_click_admin_tabs();
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'hub'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( $tabs[ $tab ] ) ? $tab : 'hub';
}

function menj_click_render_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tab                   = menj_click_current_tab();
	list( $base, $accent ) = menj_click_wordmark_parts();
	?>
	<div class="wrap menj-admin">
		<header class="menj-admin__header">
			<h1 class="menj-admin__title"><?php echo esc_html( $base ); ?><span><?php echo esc_html( $accent ); ?></span></h1>
			<span class="menj-admin__version"><?php echo esc_html( 'v' . MENJ_CLICK_VERSION ); ?></span>
			<a class="menj-admin__view" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'View site', 'menj-click' ); ?></a>
		</header>
		<nav class="menj-admin__tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'menj-click' ); ?>">
			<?php foreach ( menj_click_admin_tabs() as $key => $label ) : ?>
				<a class="menj-admin__tab<?php echo $key === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( menj_click_admin_url( $key ) ); ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php menj_click_admin_render_flash(); ?>
		<div class="menj-admin__panel">
			<?php call_user_func( 'menj_click_tab_' . $tab ); ?>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------- */
/* Helpers                                                                     */
/* -------------------------------------------------------------------------- */

function menj_click_admin_url( $tab = 'hub', array $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'menj-click', 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
}

function menj_click_admin_redirect( $tab, array $args = array() ) {
	wp_safe_redirect( menj_click_admin_url( $tab, $args ) );
	exit;
}

/**
 * Queue a message for the next page load.
 *
 * @param string   $type    success|error|warning.
 * @param string   $message Message.
 * @param string[] $details Optional list shown under the message.
 */
function menj_click_admin_flash( $type, $message, array $details = array() ) {
	set_transient( 'menj_click_flash_' . get_current_user_id(), compact( 'type', 'message', 'details' ), 120 );
}

function menj_click_admin_render_flash() {
	$key   = 'menj_click_flash_' . get_current_user_id();
	$flash = get_transient( $key );
	if ( ! $flash ) {
		return;
	}
	delete_transient( $key );
	echo '<div class="menj-flash is-' . esc_attr( $flash['type'] ) . '" role="status"><p>' . esc_html( $flash['message'] ) . '</p>';
	if ( ! empty( $flash['details'] ) ) {
		echo '<ul>';
		foreach ( array_slice( $flash['details'], 0, 20 ) as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

function menj_click_admin_guard( $action ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You don’t have permission to do that.', 'menj-click' ), 403 );
	}
	check_admin_referer( $action );
}

/**
 * Hidden fields every settings form posts.
 */
function menj_click_form_fields( $action, $section = '' ) {
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	if ( '' !== $section ) {
		echo '<input type="hidden" name="section" value="' . esc_attr( $section ) . '">';
	}
	wp_nonce_field( $action );
}

function menj_click_field( $type, $name, $value, $label, array $args = array() ) {
	$id = isset( $args['id'] ) ? $args['id'] : 'menj-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
	echo '<div class="menj-field' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '">';
	echo '<label class="menj-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
	$attrs = '';
	foreach ( array( 'placeholder', 'min', 'max', 'step', 'maxlength', 'pattern' ) as $attr ) {
		if ( isset( $args[ $attr ] ) ) {
			$attrs .= ' ' . $attr . '="' . esc_attr( $args[ $attr ] ) . '"';
		}
	}
	if ( ! empty( $args['data'] ) ) {
		foreach ( $args['data'] as $k => $v ) {
			$attrs .= ' data-' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
	}
	if ( 'textarea' === $type ) {
		echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . esc_attr( isset( $args['rows'] ) ? $args['rows'] : 3 ) . '"' . $attrs . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput
	} elseif ( 'select' === $type ) {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $args['options'] as $k => $v ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select>';
	} else {
		if ( ! empty( $args['prefix'] ) ) {
			echo '<span class="menj-input-group"><span class="menj-input-group__prefix">' . esc_html( $args['prefix'] ) . '</span>';
		}
		echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! empty( $args['prefix'] ) ) {
			echo '</span>';
		}
	}
	if ( ! empty( $args['help'] ) ) {
		echo '<p class="menj-field__help">' . esc_html( $args['help'] ) . '</p>';
	}
	echo '</div>';
}

function menj_click_toggle( $name, $checked, $label, $help = '' ) {
	$id = 'menj-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
	echo '<div class="menj-toggle"><input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
	echo '<input type="checkbox" class="menj-switch" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $checked ), true, false ) . '>';
	echo '<label for="' . esc_attr( $id ) . '"><span class="menj-toggle__label">' . esc_html( $label ) . '</span>';
	if ( '' !== $help ) {
		echo '<span class="menj-toggle__help">' . esc_html( $help ) . '</span>';
	}
	echo '</label></div>';
}

/**
 * Repeating rows (add, reorder, remove) for lists such as hub items.
 *
 * @param string $name   Base input name.
 * @param array  $rows   Current rows.
 * @param array  $fields key => { label, type, options, placeholder, prefix }.
 * @param string $add    Add-button label.
 */
function menj_click_repeater( $name, array $rows, array $fields, $add ) {
	echo '<div class="menj-repeater" data-repeater>';
	echo '<div class="menj-repeater__rows" data-repeater-rows>';
	foreach ( array_values( $rows ) as $i => $row ) {
		menj_click_repeater_row( $name, $i, $row, $fields );
	}
	echo '</div><template data-repeater-template>';
	menj_click_repeater_row( $name, '__i__', array(), $fields );
	echo '</template>';
	echo '<button type="button" class="button menj-repeater__add" data-repeater-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html( $add ) . '</button></div>';
}

function menj_click_repeater_row( $name, $i, array $row, array $fields ) {
	echo '<div class="menj-repeater__row" data-repeater-row>';
	foreach ( $fields as $key => $f ) {
		$id    = sanitize_html_class( $name . '-' . $i . '-' . $key );
		$input = $name . '[' . $i . '][' . $key . ']';
		$value = isset( $row[ $key ] ) ? $row[ $key ] : ( isset( $f['default'] ) ? $f['default'] : '' );
		echo '<div class="menj-repeater__cell menj-repeater__cell--' . esc_attr( $key ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
		if ( 'select' === $f['type'] ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $input ) . '">';
			foreach ( $f['options'] as $k => $v ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $v ) . '</option>';
			}
			echo '</select>';
		} else {
			if ( ! empty( $f['prefix'] ) ) {
				echo '<span class="menj-input-group"><span class="menj-input-group__prefix">' . esc_html( $f['prefix'] ) . '</span>';
			}
			echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $input ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ) . '">';
			if ( ! empty( $f['prefix'] ) ) {
				echo '</span>';
			}
		}
		echo '</div>';
	}
	echo '<div class="menj-repeater__controls">'
		. '<button type="button" class="menj-icon-button" data-repeater-up aria-label="' . esc_attr__( 'Move up', 'menj-click' ) . '"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>'
		. '<button type="button" class="menj-icon-button" data-repeater-down aria-label="' . esc_attr__( 'Move down', 'menj-click' ) . '"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>'
		. '<button type="button" class="menj-icon-button is-destructive" data-repeater-remove aria-label="' . esc_attr__( 'Remove', 'menj-click' ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>'
		. '</div></div>';
}

function menj_click_card_open( $title, $desc = '', $class = '' ) {
	echo '<section class="menj-card' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '"><h2 class="menj-card__title">' . esc_html( $title ) . '</h2>';
	if ( '' !== $desc ) {
		echo '<p class="menj-card__desc">' . esc_html( $desc ) . '</p>';
	}
}

function menj_click_card_close() {
	echo '</section>';
}

function menj_click_submit( $label ) {
	echo '<div class="menj-actions"><button type="submit" class="button button-primary">' . esc_html( $label ) . '</button></div>';
}

/* -------------------------------------------------------------------------- */
/* Sanitisers                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * A link field: "/slug", "/page/", or a full URL.
 */
function menj_click_sanitize_link_field( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( 0 === strpos( $value, '/' ) ) {
		return '/' . ltrim( preg_replace( '#[^A-Za-z0-9/_\-.~%?=&\#]#', '', $value ), '/' );
	}
	return esc_url_raw( $value, array( 'http', 'https', 'mailto' ) );
}

function menj_click_sanitize_rows( $rows, array $schema ) {
	$out = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$clean = array();
		foreach ( $schema as $key => $cb ) {
			$clean[ $key ] = call_user_func( $cb, isset( $row[ $key ] ) ? wp_unslash( $row[ $key ] ) : '' );
		}
		if ( '' !== implode( '', array_map( 'strval', $clean ) ) ) {
			$out[] = $clean;
		}
	}
	return $out;
}

function menj_click_sanitize_section( $section, array $in ) {
	$icons = menj_click_icon_choices();
	$tones = menj_click_tone_choices();
	switch ( $section ) {
		case 'hero':
			return array(
				'eyebrow'    => sanitize_text_field( isset( $in['eyebrow'] ) ? $in['eyebrow'] : '' ),
				'headline'   => sanitize_textarea_field( isset( $in['headline'] ) ? $in['headline'] : '' ),
				'lede'       => sanitize_textarea_field( isset( $in['lede'] ) ? $in['lede'] : '' ),
				'background' => ( isset( $in['background'] ) && in_array( $in['background'], array( 'ridges', 'image', 'plain' ), true ) ) ? $in['background'] : 'ridges',
				'image_id'   => isset( $in['image_id'] ) ? absint( $in['image_id'] ) : 0,
				'shade'      => isset( $in['shade'] ) ? max( 0, min( 90, (int) $in['shade'] ) ) : 55,
			);
		case 'hub':
			$rows = menj_click_sanitize_rows(
				$in,
				array(
					'label'       => 'sanitize_text_field',
					'description' => 'sanitize_text_field',
					'link'        => 'menj_click_sanitize_link_field',
					'icon'        => 'sanitize_key',
					'tone'        => 'sanitize_key',
				)
			);
			foreach ( $rows as &$row ) {
				$row['icon'] = isset( $icons[ $row['icon'] ] ) ? $row['icon'] : 'link';
				$row['tone'] = isset( $tones[ $row['tone'] ] ) ? $row['tone'] : 'blue';
			}
			return $rows;
		case 'featured':
			return array_values(
				array_filter(
					menj_click_sanitize_rows( $in, array( 'slug' => 'menj_click_clean_slug', 'label' => 'sanitize_text_field' ) ),
					function ( $r ) {
						return '' !== $r['slug'];
					}
				)
			);
		case 'qr':
			$cards = array_values(
				array_filter(
					menj_click_sanitize_rows( isset( $in['cards'] ) ? $in['cards'] : array(), array( 'slug' => 'menj_click_clean_slug', 'label' => 'sanitize_text_field', 'caption' => 'sanitize_text_field' ) ),
					function ( $r ) {
						return '' !== $r['slug'];
					}
				)
			);
			$fg = sanitize_hex_color( isset( $in['foreground'] ) ? $in['foreground'] : '' );
			$bg = sanitize_hex_color( isset( $in['background'] ) ? $in['background'] : '' );
			return array(
				'cards'      => $cards,
				'foreground' => $fg ? $fg : '#000000',
				'background' => ! empty( $in['transparent'] ) ? 'transparent' : ( $bg ? $bg : '#FFFFFF' ),
				'ecc'        => ( isset( $in['ecc'] ) && in_array( $in['ecc'], array( 'L', 'M', 'Q', 'H' ), true ) ) ? $in['ecc'] : 'M',
				'margin'     => isset( $in['margin'] ) ? max( 0, min( 10, (int) $in['margin'] ) ) : 4,
			);
		case 'notes':
			$title = sanitize_text_field( isset( $in['title'] ) ? $in['title'] : '' );
			return array(
				'title'      => '' !== $title ? $title : 'Notes',
				'intro'      => sanitize_text_field( isset( $in['intro'] ) ? $in['intro'] : '' ),
				'typewriter' => empty( $in['typewriter'] ) ? 0 : 1,
				'home_strip' => empty( $in['home_strip'] ) ? 0 : 1,
				'home_count' => isset( $in['home_count'] ) ? max( 1, min( 6, (int) $in['home_count'] ) ) : 3,
			);
		case 'footer':
			$email = sanitize_email( isset( $in['email'] ) ? $in['email'] : '' );
			return array(
				'website' => esc_url_raw( isset( $in['website'] ) ? trim( $in['website'] ) : '', array( 'http', 'https' ) ),
				'email'   => is_email( $email ) ? $email : '',
				'x'       => sanitize_text_field( isset( $in['x'] ) ? $in['x'] : '' ),
			);
		case 'links':
			$type = isset( $in['default_redirect'] ) ? (int) $in['default_redirect'] : 307;
			return array(
				'default_redirect' => in_array( $type, array( 301, 302, 307 ), true ) ? $type : 307,
				'retention_days'   => isset( $in['retention_days'] ) ? max( 0, min( 3650, (int) $in['retention_days'] ) ) : 365,
				'count_bots'       => empty( $in['count_bots'] ) ? 0 : 1,
			);
	}
	return array();
}

/* -------------------------------------------------------------------------- */
/* Save handler (all simple sections)                                          */
/* -------------------------------------------------------------------------- */

function menj_click_handle_save() {
	menj_click_admin_guard( 'menj_click_save' );
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per section below.
	$section = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
	$post    = wp_unslash( $_POST );
	// phpcs:enable
	$tab = $section;

	switch ( $section ) {
		case 'hub':
			menj_click_update_section( 'hero', menj_click_sanitize_section( 'hero', isset( $post['hero'] ) ? (array) $post['hero'] : array() ) );
			menj_click_update_section( 'hub', menj_click_sanitize_section( 'hub', isset( $_POST['hub'] ) ? (array) $_POST['hub'] : array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			menj_click_update_section( 'featured', menj_click_sanitize_section( 'featured', isset( $_POST['featured'] ) ? (array) $_POST['featured'] : array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			break;
		case 'qr':
			$qr          = isset( $post['qr'] ) ? (array) $post['qr'] : array();
			$qr['cards'] = isset( $_POST['qr_cards'] ) ? (array) $_POST['qr_cards'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			menj_click_update_section( 'qr', menj_click_sanitize_section( 'qr', $qr ) );
			wp_cache_flush_group( 'menj_qr' );
			break;
		case 'notes':
			menj_click_update_section( 'notes', menj_click_sanitize_section( 'notes', isset( $post['notes'] ) ? (array) $post['notes'] : array() ) );
			break;
		case 'footer':
			menj_click_update_section( 'footer', menj_click_sanitize_section( 'footer', isset( $post['footer'] ) ? (array) $post['footer'] : array() ) );
			if ( isset( $post['tagline'] ) ) {
				update_option( 'blogdescription', sanitize_text_field( $post['tagline'] ) );
			}
			break;
		case 'links':
			menj_click_update_section( 'links', menj_click_sanitize_section( 'links', isset( $post['links'] ) ? (array) $post['links'] : array() ) );
			break;
		case 'directory':
			$tab = menj_click_dir_save_settings( $post );
			break;
		default:
			menj_click_admin_redirect( 'hub' );
	}
	menj_click_admin_flash( 'success', __( 'Changes saved.', 'menj-click' ) );
	menj_click_admin_redirect( $tab );
}
add_action( 'admin_post_menj_click_save', 'menj_click_handle_save' );

/* -------------------------------------------------------------------------- */
/* Tab: Link Hub                                                               */
/* -------------------------------------------------------------------------- */

function menj_click_tab_hub() {
	$hero     = menj_click_settings( 'hero' );
	$image    = ! empty( $hero['image_id'] ) ? wp_get_attachment_image_url( (int) $hero['image_id'], 'medium' ) : '';
	$host     = menj_click_host() . '/';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="menj-form">
		<?php menj_click_form_fields( 'menj_click_save', 'hub' ); ?>

		<?php menj_click_card_open( __( 'Hero', 'menj-click' ), __( 'The first thing visitors see. The large wordmark always comes from your site title.', 'menj-click' ) ); ?>
			<div class="menj-grid-2">
				<?php menj_click_field( 'text', 'hero[eyebrow]', $hero['eyebrow'], __( 'Line above the wordmark', 'menj-click' ) ); ?>
				<?php menj_click_field( 'textarea', 'hero[headline]', $hero['headline'], __( 'Headline', 'menj-click' ), array( 'rows' => 2, 'help' => __( 'Each line break starts a new line on the page.', 'menj-click' ) ) ); ?>
			</div>
			<?php menj_click_field( 'textarea', 'hero[lede]', $hero['lede'], __( 'Introduction', 'menj-click' ), array( 'rows' => 2 ) ); ?>
			<fieldset class="menj-choice" data-hero-background>
				<legend class="menj-field__label"><?php esc_html_e( 'Background', 'menj-click' ); ?></legend>
				<?php
				$choices = array(
					'ridges' => array( __( 'Ridgeline artwork', 'menj-click' ), __( 'Drawn in code; repaints itself with each colour scheme.', 'menj-click' ) ),
					'image'  => array( __( 'Photo', 'menj-click' ), __( 'Any image from the media library, at least 2400px wide.', 'menj-click' ) ),
					'plain'  => array( __( 'Plain colour', 'menj-click' ), __( 'The scheme’s sky colour, nothing else.', 'menj-click' ) ),
				);
				foreach ( $choices as $value => $text ) :
					?>
					<label class="menj-choice__item">
						<input type="radio" name="hero[background]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $hero['background'], $value ); ?>>
						<span><strong><?php echo esc_html( $text[0] ); ?></strong><small><?php echo esc_html( $text[1] ); ?></small></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<div class="menj-media" data-media data-hero-image<?php echo 'image' === $hero['background'] ? '' : ' hidden'; ?>>
				<input type="hidden" name="hero[image_id]" value="<?php echo esc_attr( (int) $hero['image_id'] ); ?>" data-media-input>
				<img src="<?php echo esc_url( $image ); ?>" alt="" class="menj-media__preview" data-media-preview<?php echo $image ? '' : ' hidden'; ?>>
				<button type="button" class="button" data-media-pick><?php esc_html_e( 'Choose image', 'menj-click' ); ?></button>
				<button type="button" class="button-link is-destructive" data-media-clear<?php echo $image ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'menj-click' ); ?></button>
			</div>
			<div class="menj-field">
				<label class="menj-field__label" for="menj-hero-shade"><?php esc_html_e( 'Shade behind the text', 'menj-click' ); ?> <output data-range-output for="menj-hero-shade"><?php echo esc_html( (int) $hero['shade'] ); ?>%</output></label>
				<input type="range" id="menj-hero-shade" name="hero[shade]" min="0" max="90" step="5" value="<?php echo esc_attr( (int) $hero['shade'] ); ?>" data-range>
				<p class="menj-field__help"><?php esc_html_e( 'Darkens the left side so the text stays readable on busy photos.', 'menj-click' ); ?></p>
			</div>
		<?php menj_click_card_close(); ?>

		<?php
		menj_click_card_open( __( 'Link panel', 'menj-click' ), __( 'The list beside the hero, also shown on the Links page. Use /slug for a short link, /page/ for a page on this site, or a full URL.', 'menj-click' ) );
		menj_click_repeater(
			'hub',
			menj_click_settings( 'hub' ),
			array(
				'label'       => array( 'label' => __( 'Title', 'menj-click' ), 'type' => 'text' ),
				'description' => array( 'label' => __( 'Description', 'menj-click' ), 'type' => 'text' ),
				'link'        => array( 'label' => __( 'Link', 'menj-click' ), 'type' => 'text', 'placeholder' => '/slug' ),
				'icon'        => array( 'label' => __( 'Icon', 'menj-click' ), 'type' => 'select', 'options' => menj_click_icon_choices(), 'default' => 'link' ),
				'tone'        => array( 'label' => __( 'Colour', 'menj-click' ), 'type' => 'select', 'options' => menj_click_tone_choices(), 'default' => 'blue' ),
			),
			__( 'Add link', 'menj-click' )
		);
		menj_click_card_close();

		menj_click_card_open( __( 'Short URL examples', 'menj-click' ), __( 'The short links listed on the home page. Rows only appear to visitors once the short link is active.', 'menj-click' ) );
		menj_click_repeater(
			'featured',
			menj_click_settings( 'featured' ),
			array(
				'slug'  => array( 'label' => __( 'Short link', 'menj-click' ), 'type' => 'text', 'prefix' => $host ),
				'label' => array( 'label' => __( 'Label', 'menj-click' ), 'type' => 'text' ),
			),
			__( 'Add row', 'menj-click' )
		);
		menj_click_card_close();

		menj_click_submit( __( 'Save changes', 'menj-click' ) );
		?>
	</form>
	<?php
}

/* -------------------------------------------------------------------------- */
/* Tab: Short Links                                                            */
/* -------------------------------------------------------------------------- */

function menj_click_tab_links() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['stats'] ) ) {
		menj_click_links_stats_view( absint( $_GET['stats'] ) );
		return;
	}
	$editing = isset( $_GET['edit'] ) ? menj_click_get_link( absint( $_GET['edit'] ) ) : null;
	$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
	// phpcs:enable
	$draft   = get_transient( 'menj_click_link_form_' . get_current_user_id() );
	if ( $draft ) {
		delete_transient( 'menj_click_link_form_' . get_current_user_id() );
	}
	$form     = is_array( $draft ) ? $draft : ( $editing ? $editing : array() );
	$defaults = menj_click_settings( 'links' );
	$result   = menj_click_query_links( array( 'search' => $search, 'paged' => $paged, 'per_page' => 50, 'orderby' => $orderby ) );
	$host     = menj_click_host() . '/';
	$v        = function ( $key, $fallback = '' ) use ( $form ) {
		return isset( $form[ $key ] ) ? $form[ $key ] : $fallback;
	};
	$open = $editing || $draft || 0 === $result['total'];
	?>
	<details class="menj-card menj-disclosure"<?php echo $open ? ' open' : ''; ?>>
		<summary class="menj-card__title"><?php echo $editing ? esc_html__( 'Edit short link', 'menj-click' ) : esc_html__( 'Add a short link', 'menj-click' ); ?></summary>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="menj-form menj-link-form">
			<?php menj_click_form_fields( 'menj_click_link_save' ); ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( $editing ? (int) $editing['id'] : (int) $v( 'id', 0 ) ); ?>">
			<div class="menj-grid-2">
				<?php
				menj_click_field( 'text', 'link[slug]', $v( 'slug' ), __( 'Short link', 'menj-click' ), array( 'prefix' => $host, 'placeholder' => 'book', 'maxlength' => 64, 'data' => array( 'slug-input' => '1' ) ) );
				menj_click_field( 'url', 'link[target]', $v( 'target' ), __( 'Destination', 'menj-click' ), array( 'placeholder' => 'https://' ) );
				menj_click_field( 'text', 'link[title]', $v( 'title' ), __( 'Title', 'menj-click' ), array( 'help' => __( 'Shown on the Shorts page.', 'menj-click' ) ) );
				menj_click_field( 'text', 'link[description]', $v( 'description' ), __( 'Note', 'menj-click' ), array( 'help' => __( 'For you; also used as a caption on the QR page.', 'menj-click' ) ) );
				?>
			</div>
			<fieldset class="menj-choice menj-choice--inline">
				<legend class="menj-field__label"><?php esc_html_e( 'Redirect', 'menj-click' ); ?></legend>
				<?php
				$current = (int) $v( 'redirect_type', $defaults['default_redirect'] );
				$types   = array(
					307 => array( '307', __( 'Temporary — recommended; safe to change later', 'menj-click' ) ),
					302 => array( '302', __( 'Temporary — older equivalent of 307', 'menj-click' ) ),
					301 => array( '301', __( 'Permanent — browsers cache it', 'menj-click' ) ),
				);
				foreach ( $types as $code => $text ) :
					?>
					<label class="menj-choice__item">
						<input type="radio" name="link[redirect_type]" value="<?php echo esc_attr( $code ); ?>" <?php checked( $current, $code ); ?> data-redirect-type>
						<span><strong><?php echo esc_html( $text[0] ); ?></strong><small><?php echo esc_html( $text[1] ); ?></small></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="menj-warning" data-warn-301<?php echo 301 === $current ? '' : ' hidden'; ?>><?php esc_html_e( 'Browsers remember 301s. If this link is ever printed as a QR code and you later change the destination, people who scanned it before may keep landing on the old page.', 'menj-click' ); ?></p>
			<div class="menj-toggles">
				<?php
				menj_click_toggle( 'link[active]', 'draft' !== $v( 'status', 'active' ), __( 'Active', 'menj-click' ), __( 'Drafts don’t redirect and stay hidden from visitors.', 'menj-click' ) );
				menj_click_toggle( 'link[forward_query]', (int) $v( 'forward_query', 0 ), __( 'Pass query parameters through', 'menj-click' ), __( 'Adds ?utm_… and similar parameters to the destination.', 'menj-click' ) );
				?>
			</div>
			<div class="menj-actions">
				<button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update link', 'menj-click' ) : esc_html__( 'Add link', 'menj-click' ); ?></button>
				<?php if ( $editing ) : ?>
					<a class="button-link" href="<?php echo esc_url( menj_click_admin_url( 'links' ) ); ?>"><?php esc_html_e( 'Cancel', 'menj-click' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
	</details>

	<section class="menj-card">
		<div class="menj-card__head">
			<h2 class="menj-card__title">
				<?php
				/* translators: %d: number of links */
				echo esc_html( sprintf( _n( '%d short link', '%d short links', $result['total'], 'menj-click' ), $result['total'] ) );
				?>
			</h2>
			<form method="get" class="menj-search" role="search">
				<input type="hidden" name="page" value="menj-click"><input type="hidden" name="tab" value="links">
				<label class="screen-reader-text" for="menj-link-search"><?php esc_html_e( 'Search short links', 'menj-click' ); ?></label>
				<input type="search" id="menj-link-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search links', 'menj-click' ); ?>">
			</form>
		</div>
		<?php if ( ! $result['items'] ) : ?>
			<p class="menj-empty-state"><?php echo '' !== $search ? esc_html__( 'No links match that search.', 'menj-click' ) : esc_html__( 'No short links yet. Add one above, import from URL Shortify, or add the starter links below.', 'menj-click' ); ?></p>
		<?php else : ?>
			<div class="menj-table-wrap">
			<table class="menj-table">
				<thead><tr>
					<th scope="col"><a href="<?php echo esc_url( menj_click_admin_url( 'links', array( 'orderby' => 'slug' ) ) ); ?>"><?php esc_html_e( 'Short link', 'menj-click' ); ?></a></th>
					<th scope="col"><?php esc_html_e( 'Destination', 'menj-click' ); ?></th>
					<th scope="col" class="is-num"><a href="<?php echo esc_url( menj_click_admin_url( 'links', array( 'orderby' => 'clicks' ) ) ); ?>"><?php esc_html_e( 'Clicks', 'menj-click' ); ?></a></th>
					<th scope="col"><?php esc_html_e( 'Status', 'menj-click' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'menj-click' ); ?></span></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $result['items'] as $l ) : ?>
					<?php $short = home_url( '/' . $l['slug'] ); ?>
					<tr>
						<td class="menj-table__slug">
							<code><span class="menj-muted"><?php echo esc_html( $host ); ?></span><?php echo esc_html( $l['slug'] ); ?></code>
							<button type="button" class="menj-icon-button" data-copy="<?php echo esc_attr( $short ); ?>" aria-label="<?php esc_attr_e( 'Copy short link', 'menj-click' ); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
							<?php if ( '' !== $l['title'] ) : ?><span class="menj-table__sub"><?php echo esc_html( $l['title'] ); ?></span><?php endif; ?>
						</td>
						<td class="menj-table__target"><?php echo '' !== $l['target'] ? '<a href="' . esc_url( $l['target'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( preg_replace( '#^https?://#', '', $l['target'] ) ) . '</a>' : '<span class="menj-muted">' . esc_html__( 'No destination yet', 'menj-click' ) . '</span>'; ?></td>
						<td class="is-num"><?php echo esc_html( number_format_i18n( (int) $l['clicks'] ) ); ?></td>
						<td><span class="menj-pill <?php echo 'active' === $l['status'] ? 'is-ok' : 'is-muted'; ?>"><?php echo 'active' === $l['status'] ? esc_html__( 'Active', 'menj-click' ) : esc_html__( 'Draft', 'menj-click' ); ?></span> <?php echo 301 === (int) $l['redirect_type'] ? '<span class="menj-pill is-warn">301</span>' : ''; ?></td>
						<td class="menj-table__actions">
							<a href="<?php echo esc_url( menj_click_admin_url( 'links', array( 'edit' => (int) $l['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'menj-click' ); ?></a>
							<a href="<?php echo esc_url( menj_click_admin_url( 'links', array( 'stats' => (int) $l['id'] ) ) ); ?>"><?php esc_html_e( 'Stats', 'menj-click' ); ?></a>
							<?php if ( 'active' === $l['status'] && '' !== $l['target'] ) : ?>
								<a href="<?php echo esc_url( home_url( '/qr/' . $l['slug'] . '.svg?download=1' ) ); ?>"><?php esc_html_e( 'QR', 'menj-click' ); ?></a>
							<?php endif; ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php echo esc_attr( sprintf( /* translators: %s: slug */ __( 'Delete %s and its click history?', 'menj-click' ), $host . $l['slug'] ) ); ?>">
								<?php menj_click_form_fields( 'menj_click_link_delete' ); ?>
								<input type="hidden" name="id" value="<?php echo esc_attr( (int) $l['id'] ); ?>">
								<button type="submit" class="button-link is-destructive"><?php esc_html_e( 'Delete', 'menj-click' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<?php
			$pages = (int) ceil( $result['total'] / 50 );
			if ( $pages > 1 ) {
				echo '<nav class="menj-pages">' . paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => $pages ) ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		<?php endif; ?>
	</section>

	<?php
	menj_click_card_open( __( 'Import and export', 'menj-click' ) );
	$shortify = menj_click_shortify_table();
	echo '<div class="menj-tools">';
	if ( $shortify ) {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$shortify}" ); // phpcs:ignore WordPress.DB
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-tool">';
		menj_click_form_fields( 'menj_click_links_import_shortify' );
		/* translators: %d: number of links */
		echo '<p><strong>' . esc_html__( 'URL Shortify', 'menj-click' ) . '</strong>' . esc_html( sprintf( _n( '%d link found. Slugs, destinations and click totals come across; existing slugs are skipped.', '%d links found. Slugs, destinations and click totals come across; existing slugs are skipped.', $count, 'menj-click' ), $count ) ) . '</p>';
		echo '<button type="submit" class="button">' . esc_html__( 'Import from URL Shortify', 'menj-click' ) . '</button></form>';
	}
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-tool">';
	menj_click_form_fields( 'menj_click_links_import_csv' );
	echo '<p><strong>' . esc_html__( 'CSV file', 'menj-click' ) . '</strong>' . esc_html__( 'Columns: slug, target, title, description, redirect_type, forward_query, status. URL Shortify’s export works too.', 'menj-click' ) . '</p>';
	echo '<input type="file" name="csv" accept=".csv,text/csv" required> <button type="submit" class="button">' . esc_html__( 'Import CSV', 'menj-click' ) . '</button></form>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-tool">';
	menj_click_form_fields( 'menj_click_links_export_csv' );
	echo '<p><strong>' . esc_html__( 'Export', 'menj-click' ) . '</strong>' . esc_html__( 'Every link as a CSV you can re-import here.', 'menj-click' ) . '</p>';
	echo '<button type="submit" class="button">' . esc_html__( 'Download CSV', 'menj-click' ) . '</button></form>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="menj-tool">';
	menj_click_form_fields( 'menj_click_links_seed' );
	echo '<p><strong>' . esc_html__( 'Starter links', 'menj-click' ) . '</strong>' . esc_html__( 'Adds seo, blog, bio, buzz, book, apo, projects, contact and work as drafts, with suggested destinations where known. Nothing goes live until you make it Active.', 'menj-click' ) . '</p>';
	echo '<button type="submit" class="button">' . esc_html__( 'Add starter links', 'menj-click' ) . '</button></form>';
	echo '</div>';
	menj_click_card_close();
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="menj-form">
		<?php
		menj_click_form_fields( 'menj_click_save', 'links' );
		menj_click_card_open( __( 'Link settings', 'menj-click' ) );
		echo '<div class="menj-grid-2">';
		menj_click_field( 'select', 'links[default_redirect]', $defaults['default_redirect'], __( 'Default redirect for new links', 'menj-click' ), array( 'options' => array( 307 => '307 Temporary', 302 => '302 Temporary', 301 => '301 Permanent' ) ) );
		menj_click_field( 'number', 'links[retention_days]', $defaults['retention_days'], __( 'Keep click details for (days)', 'menj-click' ), array( 'min' => 0, 'max' => 3650, 'help' => __( '0 keeps them forever. Totals are never deleted.', 'menj-click' ) ) );
		echo '</div>';
		menj_click_toggle( 'links[count_bots]', $defaults['count_bots'], __( 'Count bots and link previews', 'menj-click' ), __( 'Off by default, so chat-app previews and crawlers don’t inflate your numbers.', 'menj-click' ) );
		menj_click_submit( __( 'Save link settings', 'menj-click' ) );
		menj_click_card_close();
		?>
	</form>
	<?php
}

function menj_click_links_stats_view( $id ) {
	$link = menj_click_get_link( $id );
	echo '<p><a class="menj-back" href="' . esc_url( menj_click_admin_url( 'links' ) ) . '">' . esc_html__( '← All short links', 'menj-click' ) . '</a></p>';
	if ( ! $link ) {
		echo '<p class="menj-empty-state">' . esc_html__( 'That link no longer exists.', 'menj-click' ) . '</p>';
		return;
	}
	$s   = menj_click_link_stats( $id, 30 );
	$max = max( 1, max( $s['series'] ) );
	?>
	<section class="menj-card">
		<h2 class="menj-card__title"><code><?php echo esc_html( menj_click_host() . '/' . $link['slug'] ); ?></code></h2>
		<p class="menj-card__desc"><?php echo '' !== $link['target'] ? esc_html( $link['target'] ) : esc_html__( 'No destination yet', 'menj-click' ); ?></p>
		<div class="menj-stats">
			<div class="menj-stat"><span class="menj-stat__value"><?php echo esc_html( number_format_i18n( $s['total'] ) ); ?></span><span class="menj-stat__label"><?php esc_html_e( 'Clicks, last 30 days', 'menj-click' ); ?></span></div>
			<div class="menj-stat"><span class="menj-stat__value"><?php echo esc_html( number_format_i18n( $s['uniques'] ) ); ?></span><span class="menj-stat__label"><?php esc_html_e( 'Unique visitors (per day)', 'menj-click' ); ?></span></div>
			<div class="menj-stat"><span class="menj-stat__value"><?php echo esc_html( number_format_i18n( $s['qr'] ) ); ?></span><span class="menj-stat__label"><?php esc_html_e( 'From QR scans', 'menj-click' ); ?></span></div>
			<div class="menj-stat"><span class="menj-stat__value"><?php echo esc_html( number_format_i18n( (int) $link['clicks'] ) ); ?></span><span class="menj-stat__label"><?php esc_html_e( 'All-time clicks', 'menj-click' ); ?></span></div>
		</div>
		<figure class="menj-chart">
			<div class="menj-bars" role="img" aria-label="<?php esc_attr_e( 'Clicks per day for the last 30 days', 'menj-click' ); ?>">
				<?php foreach ( $s['series'] as $day => $count ) : ?>
					<span style="height:<?php echo esc_attr( round( 100 * $count / $max, 1 ) ); ?>%" title="<?php echo esc_attr( date_i18n( get_option( 'date_format' ), strtotime( $day ) ) . ': ' . $count ); ?>"></span>
				<?php endforeach; ?>
			</div>
			<figcaption class="menj-chart__axis"><span><?php echo esc_html( date_i18n( 'j M', strtotime( array_key_first( $s['series'] ) ) ) ); ?></span><span><?php esc_html_e( 'Today', 'menj-click' ); ?></span></figcaption>
		</figure>
	</section>
	<div class="menj-grid-2">
		<?php
		menj_click_card_open( __( 'Devices', 'menj-click' ) );
		menj_click_stat_list( $s['devices'], 'device', $s['total'] );
		menj_click_card_close();
		menj_click_card_open( __( 'Top referrers', 'menj-click' ), __( 'Where clicks came from, when the browser shared it.', 'menj-click' ) );
		menj_click_stat_list( $s['referrers'], 'host', $s['total'] );
		menj_click_card_close();
		?>
	</div>
	<?php
}

function menj_click_stat_list( array $rows, $key, $total ) {
	if ( ! $rows ) {
		echo '<p class="menj-muted">' . esc_html__( 'Nothing recorded yet.', 'menj-click' ) . '</p>';
		return;
	}
	echo '<ul class="menj-statlist">';
	foreach ( $rows as $r ) {
		$pct = $total ? round( 100 * $r['c'] / $total ) : 0;
		echo '<li><span class="menj-statlist__name">' . esc_html( ucfirst( $r[ $key ] ) ) . '</span><span class="menj-statlist__bar"><span style="width:' . esc_attr( $pct ) . '%"></span></span><span class="menj-statlist__num">' . esc_html( number_format_i18n( (int) $r['c'] ) ) . '</span></li>';
	}
	echo '</ul>';
}

/* Link handlers -------------------------------------------------------------- */

function menj_click_handle_link_save() {
	menj_click_admin_guard( 'menj_click_link_save' );
	$raw  = isset( $_POST['link'] ) ? (array) wp_unslash( $_POST['link'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$data = array(
		'slug'          => isset( $raw['slug'] ) ? (string) $raw['slug'] : '',
		'target'        => isset( $raw['target'] ) ? (string) $raw['target'] : '',
		'title'         => isset( $raw['title'] ) ? (string) $raw['title'] : '',
		'description'   => isset( $raw['description'] ) ? (string) $raw['description'] : '',
		'redirect_type' => isset( $raw['redirect_type'] ) ? (int) $raw['redirect_type'] : 307,
		'forward_query' => ! empty( $raw['forward_query'] ),
		'status'        => ! empty( $raw['active'] ) ? 'active' : 'draft',
	);
	$saved = menj_click_save_link( $data, $id );
	if ( is_wp_error( $saved ) ) {
		set_transient( 'menj_click_link_form_' . get_current_user_id(), array_merge( $data, array( 'id' => $id ) ), 120 );
		menj_click_admin_flash( 'error', $saved->get_error_message() );
		menj_click_admin_redirect( 'links', $id ? array( 'edit' => $id ) : array() );
	}
	/* translators: %s: short URL */
	menj_click_admin_flash( 'success', sprintf( $id ? __( '%s updated.', 'menj-click' ) : __( '%s is ready.', 'menj-click' ), menj_click_host() . '/' . menj_click_clean_slug( $data['slug'] ) ) );
	menj_click_admin_redirect( 'links' );
}
add_action( 'admin_post_menj_click_link_save', 'menj_click_handle_link_save' );

function menj_click_handle_link_delete() {
	menj_click_admin_guard( 'menj_click_link_delete' );
	$n = menj_click_delete_links( array( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 ) );
	menj_click_admin_flash( $n ? 'success' : 'error', $n ? __( 'Link deleted.', 'menj-click' ) : __( 'That link was already gone.', 'menj-click' ) );
	menj_click_admin_redirect( 'links' );
}
add_action( 'admin_post_menj_click_link_delete', 'menj_click_handle_link_delete' );

function menj_click_report_import( $result, $source ) {
	if ( is_wp_error( $result ) ) {
		menj_click_admin_flash( 'error', $result->get_error_message() );
		return;
	}
	/* translators: 1: number imported, 2: source name */
	$message = sprintf( _n( '%1$d link imported from %2$s.', '%1$d links imported from %2$s.', $result['imported'], 'menj-click' ), $result['imported'], $source );
	if ( $result['skipped'] ) {
		/* translators: %d: number skipped */
		$message .= ' ' . sprintf( _n( '%d skipped:', '%d skipped:', count( $result['skipped'] ), 'menj-click' ), count( $result['skipped'] ) );
	}
	menj_click_admin_flash( $result['skipped'] ? 'warning' : 'success', $message, $result['skipped'] );
}

function menj_click_handle_import_shortify() {
	menj_click_admin_guard( 'menj_click_links_import_shortify' );
	menj_click_report_import( menj_click_import_from_shortify(), 'URL Shortify' );
	menj_click_admin_redirect( 'links' );
}
add_action( 'admin_post_menj_click_links_import_shortify', 'menj_click_handle_import_shortify' );

function menj_click_handle_import_csv() {
	menj_click_admin_guard( 'menj_click_links_import_csv' );
	$file = isset( $_FILES['csv'] ) ? $_FILES['csv'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $file || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		menj_click_admin_flash( 'error', __( 'Choose a CSV file to import.', 'menj-click' ) );
	} elseif ( 'csv' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) || $file['size'] > 2 * MB_IN_BYTES ) {
		menj_click_admin_flash( 'error', __( 'Upload a .csv file under 2 MB.', 'menj-click' ) );
	} else {
		menj_click_report_import( menj_click_import_csv( $file['tmp_name'] ), 'CSV' );
	}
	menj_click_admin_redirect( 'links' );
}
add_action( 'admin_post_menj_click_links_import_csv', 'menj_click_handle_import_csv' );

function menj_click_handle_export_csv() {
	menj_click_admin_guard( 'menj_click_links_export_csv' );
	menj_click_export_csv();
}
add_action( 'admin_post_menj_click_links_export_csv', 'menj_click_handle_export_csv' );

function menj_click_handle_seed() {
	menj_click_admin_guard( 'menj_click_links_seed' );
	$n = menj_click_seed_starter_links();
	/* translators: %d: number of links */
	menj_click_admin_flash( 'success', $n ? sprintf( _n( '%d starter link added as a draft. Check each destination, then switch it to Active.', '%d starter links added as drafts. Check each destination, then switch them to Active.', $n, 'menj-click' ), $n ) : __( 'All starter links already exist.', 'menj-click' ) );
	menj_click_admin_redirect( 'links' );
}
add_action( 'admin_post_menj_click_links_seed', 'menj_click_handle_seed' );

/* -------------------------------------------------------------------------- */
/* Tab: QR Codes                                                               */
/* -------------------------------------------------------------------------- */

function menj_click_tab_qr() {
	$qr   = menj_click_settings( 'qr' );
	$map  = menj_click_link_map();
	$host = menj_click_host() . '/';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="menj-form">
		<?php
		menj_click_form_fields( 'menj_click_save', 'qr' );
		menj_click_card_open( __( 'Codes on the home page', 'menj-click' ), __( 'Each code points at a short link, so you can change where it goes without reprinting it. The QR page lists every active link.', 'menj-click' ) );
		menj_click_repeater(
			'qr_cards',
			$qr['cards'],
			array(
				'slug'    => array( 'label' => __( 'Short link', 'menj-click' ), 'type' => 'text', 'prefix' => $host ),
				'label'   => array( 'label' => __( 'Label', 'menj-click' ), 'type' => 'text' ),
				'caption' => array( 'label' => __( 'Caption', 'menj-click' ), 'type' => 'text' ),
			),
			__( 'Add code', 'menj-click' )
		);
		menj_click_card_close();

		menj_click_card_open( __( 'Style', 'menj-click' ), __( 'Dark codes on a light background scan best. Keep good contrast if you change the colours.', 'menj-click' ) );
		echo '<div class="menj-grid-4">';
		menj_click_field( 'color', 'qr[foreground]', $qr['foreground'], __( 'Code colour', 'menj-click' ) );
		menj_click_field( 'color', 'qr[background]', 'transparent' === $qr['background'] ? '#FFFFFF' : $qr['background'], __( 'Background', 'menj-click' ) );
		menj_click_field(
			'select',
			'qr[ecc]',
			$qr['ecc'],
			__( 'Error correction', 'menj-click' ),
			array(
				'options' => array(
					'L' => __( 'Low (7%) — smallest code', 'menj-click' ),
					'M' => __( 'Medium (15%) — recommended', 'menj-click' ),
					'Q' => __( 'Quartile (25%)', 'menj-click' ),
					'H' => __( 'High (30%) — survives damage', 'menj-click' ),
				),
			)
		);
		menj_click_field( 'number', 'qr[margin]', (int) $qr['margin'], __( 'Quiet zone (modules)', 'menj-click' ), array( 'min' => 0, 'max' => 10, 'help' => __( '4 is the standard. Don’t go below 2 for print.', 'menj-click' ) ) );
		echo '</div>';
		menj_click_toggle( 'qr[transparent]', 'transparent' === $qr['background'], __( 'Transparent background', 'menj-click' ), __( 'SVG only; PNG downloads always get a solid background.', 'menj-click' ) );
		menj_click_submit( __( 'Save QR settings', 'menj-click' ) );
		menj_click_card_close();
		?>
	</form>

	<?php menj_click_card_open( __( 'Preview and downloads', 'menj-click' ), __( 'SVG is vector and prints sharp at any size. PNG is 1,000px or more.', 'menj-click' ) ); ?>
		<div class="menj-qr-preview">
			<?php
			foreach ( $qr['cards'] as $card ) :
				$slug   = menj_click_clean_slug( $card['slug'] );
				$active = '' !== $slug && isset( $map[ $slug ] );
				?>
				<figure class="menj-qr-preview__item<?php echo $active ? '' : ' is-missing'; ?>">
					<div class="menj-qr-preview__code"><?php echo $active ? menj_click_qr_svg( menj_click_qr_url( $slug ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<figcaption>
						<strong><?php echo esc_html( $card['label'] ); ?></strong>
						<code><?php echo esc_html( $host . $slug ); ?></code>
						<?php if ( $active ) : ?>
							<span class="menj-qr-preview__links"><a href="<?php echo esc_url( home_url( '/qr/' . $slug . '.svg?download=1' ) ); ?>">SVG</a><a href="<?php echo esc_url( home_url( '/qr/' . $slug . '.png?download=1' ) ); ?>">PNG</a></span>
						<?php else : ?>
							<span class="menj-muted"><?php esc_html_e( 'Make this short link active to generate its code.', 'menj-click' ); ?></span>
						<?php endif; ?>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	<?php menj_click_card_close(); ?>

	<?php menj_click_card_open( __( 'Code for any URL', 'menj-click' ), __( 'A one-off code that doesn’t go through a short link, so it can’t be redirected or tracked later.', 'menj-click' ) ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="menj-inline-form">
			<?php menj_click_form_fields( 'menj_click_qr_download' ); ?>
			<label class="screen-reader-text" for="menj-qr-text"><?php esc_html_e( 'URL or text', 'menj-click' ); ?></label>
			<input type="text" id="menj-qr-text" name="qr_text" placeholder="https://" required>
			<button type="submit" class="button" name="format" value="svg"><?php esc_html_e( 'Download SVG', 'menj-click' ); ?></button>
			<button type="submit" class="button" name="format" value="png"><?php esc_html_e( 'Download PNG', 'menj-click' ); ?></button>
		</form>
	<?php
	menj_click_card_close();
}

/* -------------------------------------------------------------------------- */
/* Tab: Notes                                                                  */
/* -------------------------------------------------------------------------- */

function menj_click_tab_notes() {
	$n      = menj_click_settings( 'notes' );
	$status = menj_click_notes_status();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="menj-form">
		<?php
		menj_click_form_fields( 'menj_click_save', 'notes' );
		menj_click_card_open( __( 'Section', 'menj-click' ), __( 'Notes is the site’s blog: ordinary WordPress posts, set in Sabon Next.', 'menj-click' ) );
		echo '<div class="menj-grid-2">';
		menj_click_field( 'text', 'notes[title]', $n['title'], __( 'Title', 'menj-click' ) );
		menj_click_field( 'text', 'notes[intro]', $n['intro'], __( 'Introduction', 'menj-click' ) );
		echo '</div>';
		menj_click_toggle( 'notes[typewriter]', $n['typewriter'], __( 'Typewriter masthead', 'menj-click' ), __( 'Sets the title in Special Elite.', 'menj-click' ) );
		menj_click_toggle( 'notes[home_strip]', $n['home_strip'], __( 'Show the latest notes on the home page', 'menj-click' ) );
		menj_click_field( 'number', 'notes[home_count]', (int) $n['home_count'], __( 'How many', 'menj-click' ), array( 'min' => 1, 'max' => 6, 'class' => 'menj-field--short' ) );
		menj_click_submit( __( 'Save changes', 'menj-click' ) );
		menj_click_card_close();
		?>
	</form>
	<?php
	menj_click_card_open( __( 'Setup', 'menj-click' ), __( 'Notes needs a static front page, a Notes page for the posts, and post URLs under /notes/ so they never compete with short links.', 'menj-click' ) );
	$checks = array(
		array( $status['front'], __( 'Home is the static front page', 'menj-click' ) ),
		array( $status['posts_page'], __( 'The Notes page lists your posts', 'menj-click' ) ),
		array( $status['permalinks'], __( 'Posts live under /notes/', 'menj-click' ) ),
		array( ! $status['pages'], $status['pages'] ? sprintf( /* translators: %s: page titles */ __( 'Missing pages: %s', 'menj-click' ), implode( ', ', $status['pages'] ) ) : __( 'My Sites, Shorts and QR Codes pages exist', 'menj-click' ) ),
	);
	echo '<ul class="menj-checklist">';
	foreach ( $checks as $c ) {
		echo '<li class="' . ( $c[0] ? 'is-ok' : 'is-warn' ) . '"><span class="menj-checklist__mark" aria-hidden="true"></span>' . esc_html( $c[1] ) . '</li>';
	}
	echo '</ul>';
	if ( in_array( false, array_column( $checks, 0 ), true ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		menj_click_form_fields( 'menj_click_setup' );
		echo '<input type="hidden" name="task" value="all"><input type="hidden" name="return" value="notes">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Set up Notes and pages', 'menj-click' ) . '</button></form>';
	}
	menj_click_card_close();
}

/* -------------------------------------------------------------------------- */
/* Tab: Footer                                                                 */
/* -------------------------------------------------------------------------- */

function menj_click_tab_footer() {
	$f = menj_click_settings( 'footer' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="menj-form">
		<?php
		menj_click_form_fields( 'menj_click_save', 'footer' );
		menj_click_card_open( __( 'Footer', 'menj-click' ), __( 'The tagline beside the wordmark, and the icons on the right. Leave a field empty to hide its icon.', 'menj-click' ) );
		menj_click_field( 'text', 'tagline', get_option( 'blogdescription' ), __( 'Tagline', 'menj-click' ), array( 'help' => __( 'This is also your site tagline in Settings → General.', 'menj-click' ) ) );
		echo '<div class="menj-grid-3">';
		menj_click_field( 'url', 'footer[website]', $f['website'], __( 'Website (link icon)', 'menj-click' ), array( 'placeholder' => 'https://' ) );
		menj_click_field( 'email', 'footer[email]', $f['email'], __( 'Email (mail icon)', 'menj-click' ), array( 'help' => __( 'Shown encoded to deter spam bots.', 'menj-click' ) ) );
		menj_click_field( 'text', 'footer[x]', $f['x'], __( 'X handle or profile URL', 'menj-click' ), array( 'placeholder' => '@handle' ) );
		echo '</div>';
		menj_click_submit( __( 'Save changes', 'menj-click' ) );
		menj_click_card_close();
		?>
	</form>
	<?php
}

/* -------------------------------------------------------------------------- */
/* Tab: Appearance                                                             */
/* -------------------------------------------------------------------------- */

/**
 * Colour schemes: the theme default plus this theme's own files in /styles.
 * (Twenty Twenty-Five's variations are left out; they belong to its design.)
 *
 * @return array slug => { title, palette, config }
 */
function menj_click_schemes() {
	$flat = function ( $palette ) {
		return isset( $palette['theme'] ) ? $palette['theme'] : ( is_array( $palette ) ? $palette : array() );
	};
	$read = function ( $file ) {
		$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_array( $json ) ? $json : array();
	};
	$base    = $read( MENJ_CLICK_DIR . '/theme.json' );
	$schemes = array(
		'default' => array(
			'title'   => isset( $base['title'] ) ? $base['title'] : 'Dusk',
			'palette' => $flat( isset( $base['settings']['color']['palette'] ) ? $base['settings']['color']['palette'] : array() ),
			'config'  => array(),
		),
	);
	foreach ( (array) glob( MENJ_CLICK_DIR . '/styles/*.json' ) as $file ) {
		$variation = $read( $file );
		if ( empty( $variation['title'] ) ) {
			continue;
		}
		$schemes[ sanitize_title( $variation['title'] ) ] = array(
			'title'   => $variation['title'],
			'palette' => $flat( isset( $variation['settings']['color']['palette'] ) ? $variation['settings']['color']['palette'] : array() ),
			'config'  => $variation,
		);
	}
	return $schemes;
}

function menj_click_active_scheme() {
	$post_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
	$config  = json_decode( (string) get_post_field( 'post_content', $post_id ), true );
	if ( ! empty( $config['title'] ) ) {
		return sanitize_title( $config['title'] );
	}
	return empty( $config['settings']['color'] ) ? 'default' : 'custom';
}

function menj_click_tab_appearance() {
	$schemes = menj_click_schemes();
	$active  = menj_click_active_scheme();
	menj_click_card_open( __( 'Colour scheme', 'menj-click' ), __( 'Each scheme recolours the whole site, including the hero artwork. Applying one replaces colour changes made in Appearance → Editor → Styles; fine-tune there afterwards.', 'menj-click' ) );
	echo '<div class="menj-schemes">';
	foreach ( $schemes as $slug => $scheme ) {
		$colors = array();
		foreach ( $scheme['palette'] as $p ) {
			$colors[ $p['slug'] ] = $p['color'];
		}
		$show = array( 'base', 'contrast', 'accent-1', 'accent-3', 'tile-green', 'tile-purple', 'tile-amber', 'tile-teal' );
		echo '<div class="menj-scheme' . ( $slug === $active ? ' is-active' : '' ) . '">';
		echo '<div class="menj-scheme__swatches" style="background:' . esc_attr( isset( $colors['base'] ) ? $colors['base'] : '#fff' ) . '">';
		foreach ( $show as $key ) {
			if ( isset( $colors[ $key ] ) ) {
				echo '<span style="background:' . esc_attr( $colors[ $key ] ) . '" title="' . esc_attr( $key ) . '"></span>';
			}
		}
		echo '</div><div class="menj-scheme__foot"><strong>' . esc_html( $scheme['title'] ) . '</strong>';
		if ( $slug === $active ) {
			echo '<span class="menj-pill is-ok">' . esc_html__( 'Active', 'menj-click' ) . '</span>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-confirm="' . esc_attr__( 'Apply this scheme? It replaces colour changes made in the Site Editor.', 'menj-click' ) . '">';
			menj_click_form_fields( 'menj_click_apply_scheme' );
			echo '<input type="hidden" name="scheme" value="' . esc_attr( $slug ) . '"><button type="submit" class="button">' . esc_html__( 'Apply', 'menj-click' ) . '</button></form>';
		}
		echo '</div></div>';
	}
	echo '</div>';
	if ( 'custom' === $active ) {
		echo '<p class="menj-muted">' . esc_html__( 'You’re using custom colours from the Site Editor.', 'menj-click' ) . '</p>';
	}
	echo '<p><a class="button" href="' . esc_url( admin_url( 'site-editor.php' ) ) . '">' . esc_html__( 'Open the Site Editor', 'menj-click' ) . '</a></p>';
	menj_click_card_close();

	menj_click_card_open( __( 'Typefaces', 'menj-click' ), __( 'Script fonts download only on pages that use them. Tag text with the Language button in the editor toolbar, or a block’s Language setting.', 'menj-click' ) );
	$fonts = array(
		array( 'Inter', __( 'Interface, headings, navigation', 'menj-click' ) ),
		array( 'Sabon Next LT', __( 'Notes: articles, excerpts, titles', 'menj-click' ) ),
		array( 'EB Garamond', __( 'Fills in characters Sabon lacks: transliteration marks, polytonic Greek', 'menj-click' ) ),
		array( 'Special Elite', __( 'Notes masthead (optional)', 'menj-click' ) ),
		array( 'SBL Hebrew', __( 'Hebrew', 'menj-click' ) ),
		array( 'SBL Greek', __( 'Greek', 'menj-click' ) ),
		array( 'SBL BibLit', __( 'Transliteration', 'menj-click' ) ),
		array( 'Noto Naskh Arabic', __( 'Arabic prose and hadith', 'menj-click' ) ),
		array( 'KFGQPC HAFS Uthmanic Script', __( 'Qur’an', 'menj-click' ) ),
		array( 'Dubidam Arabic', __( 'Arabic headings', 'menj-click' ) ),
		array( 'Arslan Wessam A / B', __( 'Arabic calligraphy', 'menj-click' ) ),
		array( 'Noto Sans Syriac', __( 'Syriac (Unicode Estrangela)', 'menj-click' ) ),
		array( 'Evangelion CPA', __( 'Christian Palestinian Aramaic', 'menj-click' ) ),
		array( 'Estrangelo (legacy)', __( 'Pre-Unicode Syriac, typed as Latin letters', 'menj-click' ) ),
		array( 'Noto Sans Cuneiform', __( 'Cuneiform', 'menj-click' ) ),
	);
	echo '<table class="menj-table menj-table--compact"><tbody>';
	foreach ( $fonts as $f ) {
		echo '<tr><th scope="row">' . esc_html( $f[0] ) . '</th><td>' . esc_html( $f[1] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	menj_click_card_close();
}

function menj_click_apply_scheme( $slug ) {
	$schemes = menj_click_schemes();
	if ( ! isset( $schemes[ $slug ] ) ) {
		return new WP_Error( 'scheme', __( 'That colour scheme doesn’t exist.', 'menj-click' ) );
	}
	$config = array( 'version' => WP_Theme_JSON::LATEST_SCHEMA, 'isGlobalStylesUserThemeJSON' => true );
	if ( 'default' !== $slug ) {
		$variation       = $schemes[ $slug ]['config'];
		$config['title'] = $variation['title'];
		foreach ( array( 'settings', 'styles' ) as $key ) {
			if ( ! empty( $variation[ $key ] ) ) {
				$config[ $key ] = $variation[ $key ];
			}
		}
	}
	$post_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
	$result  = wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => wp_json_encode( $config ) ) ), true );
	WP_Theme_JSON_Resolver::clean_cached_data();
	if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
		wp_clean_theme_json_cache();
	}
	return is_wp_error( $result ) ? $result : true;
}

function menj_click_handle_apply_scheme() {
	menj_click_admin_guard( 'menj_click_apply_scheme' );
	$slug   = isset( $_POST['scheme'] ) ? sanitize_title( wp_unslash( $_POST['scheme'] ) ) : '';
	$result = menj_click_apply_scheme( $slug );
	menj_click_admin_flash( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Colour scheme applied.', 'menj-click' ) );
	menj_click_admin_redirect( 'appearance' );
}
add_action( 'admin_post_menj_click_apply_scheme', 'menj_click_handle_apply_scheme' );
