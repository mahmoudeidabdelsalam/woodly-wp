<?php
/**
 * WP Headless — functions.php
 *
 * Backend-only theme. Alomar content fields and content-type registration
 * are theme-managed; the core plugin supplies the remaining business logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Theme-owned ACF configuration. */


/**
 * Enable featured images for posts and pages.
 */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'post-thumbnails', array( 'post', 'page', 'projects' ) );
} );

/**
 * Warn in wp-admin if required plugins are missing, instead of failing
 * silently (no fields, no GraphQL type, no error message anywhere).
 */
add_action( 'admin_notices', function () {
	$missing = array();

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		$missing[] = 'Advanced Custom Fields (Pro)';
	}
	if ( ! function_exists( 'register_graphql_field' ) && ! class_exists( 'WPGraphQL' ) ) {
		$missing[] = 'WPGraphQL';
	}
	if ( ! class_exists( 'WPGraphQL_ACF' ) && ! function_exists( 'wpgraphql_acf_init' ) ) {
		// Best-effort check only — plugin class/function names vary by version.
		$missing[] = 'WPGraphQL for ACF (verify manually if this warning is a false positive)';
	}
	if ( ! class_exists( 'Alomar_REST_API' ) ) {
		$missing[] = 'Alomar Holding Core contact integration';
	}

	if ( ! empty( $missing ) ) {
		printf(
			'<div class="notice notice-warning"><p><strong>WP Headless:</strong> missing required plugin(s): %s.</p></div>',
			esc_html( implode( ', ', $missing ) )
		);
	}
} );

/**
 * This theme serves no frontend templates. Public WordPress-rendered pages
 * must not compete with the headless frontend in search results.
 * If configured, redirect public visitors to the React frontend.
 *
 * Set FRONTEND_URL to the deployed frontend's URL to enable redirects.
 */
add_action( 'template_redirect', function () {
	// Never redirect REST, AJAX, feeds, or login requests. The headless theme
	// must leave WordPress API routes available to the React application.
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$is_rest_path = 0 === strpos( $request_uri, '/wp-json/' ) || false !== strpos( $request_uri, 'rest_route=' );
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || $is_rest_path || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	header( 'X-Robots-Tag: noindex, follow', true );

	$frontend_url = defined( 'FRONTEND_URL' ) ? FRONTEND_URL : ( defined( 'REACT_FRONTEND_URL' ) ? REACT_FRONTEND_URL : '' );

	if ( $frontend_url ) {
		wp_redirect( $frontend_url, 302 );
		exit;
	}
} );

/**
 * Keep the WordPress backend hostname out of search results. The frontend
 * owns public indexing and publishes its own canonical sitemap.
 */
add_filter( 'robots_txt', function ( $output ) {
	$output = preg_replace( '/^Sitemap:.*(?:\R|$)/mi', '', $output );
	$output = rtrim( $output );

	return $output . "\nUser-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
}, 100000 );

/**
 * Allow the React frontend's origin to call WPGraphQL directly from
 * the browser if needed (e.g. for previews). If GraphQL requests are
 * always made server-side, this is not required.
 *
 * ASSUMPTION: set FRONTEND_URL as above.
 */
add_action( 'graphql_response_headers_to_send', function ( $headers ) {
	$frontend_url = defined( 'FRONTEND_URL' ) ? FRONTEND_URL : ( defined( 'REACT_FRONTEND_URL' ) ? REACT_FRONTEND_URL : '' );
	$origin = $frontend_url ? wp_parse_url( $frontend_url, PHP_URL_SCHEME ) . '://' . wp_parse_url( $frontend_url, PHP_URL_HOST ) : '';
	$request_origin = get_http_origin();

	if ( $origin && $request_origin && $origin === $request_origin ) {
		$headers['Access-Control-Allow-Origin']  = $origin;
		$headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS';
		$headers['Access-Control-Allow-Headers'] = 'Content-Type';
		$headers['Vary']                         = 'Origin';
	}

	return $headers;
} );

/**
 * Trim unused frontend weight since no theme templates are rendered.
 */
add_action( 'init', function () {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
} );
