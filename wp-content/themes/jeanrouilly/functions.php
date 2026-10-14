<?php
/**
 * Thème enfant Blocksy – Jean Rouilly, mentorat personnalisé.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JR_VERSION', '0.1.0' );

/**
 * Styles du site, chargés après ceux de Blocksy (front et éditeur).
 */
function jr_enqueue_assets() {
	wp_enqueue_style(
		'jeanrouilly',
		get_stylesheet_directory_uri() . '/assets/css/site.css',
		[ 'ct-main-styles' ],
		JR_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'jr_enqueue_assets', 20 );

function jr_editor_assets() {
	add_editor_style( 'assets/css/site.css' );
}
add_action( 'after_setup_theme', 'jr_editor_assets' );

/**
 * Précharge les polices auto-hébergées (pas d'appel à Google Fonts : RGPD).
 */
function jr_preload_fonts() {
	$fonts = [ 'plus-jakarta-sans-latin-wght-normal.woff2', 'inter-latin-wght-normal.woff2' ];
	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_stylesheet_directory_uri() . '/assets/fonts/' . $font )
		);
	}
}
add_action( 'wp_head', 'jr_preload_fonts', 1 );

/**
 * Préproduction : tant que JR_PREPROD est vrai (défini dans wp-config.php),
 * le site n'est pas indexé, même si l'option « Visibilité » est modifiée par erreur.
 */
function jr_preprod_noindex( $robots ) {
	if ( defined( 'JR_PREPROD' ) && JR_PREPROD ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'jr_preprod_noindex' );

/**
 * Pied de page : copyright et liens légaux.
 */
function jr_footer_copyright() {
	return sprintf(
		'© {current_year} {site_title} · Mentorat personnalisé · <a href="%s">Mentions légales</a> · <a href="%s">Confidentialité</a>',
		esc_url( home_url( '/mentions-legales/' ) ),
		esc_url( home_url( '/politique-de-confidentialite/' ) )
	);
}
add_filter( 'blocksy:footer:copyright:default-value', 'jr_footer_copyright' );

/**
 * Confidentialité : pas de script emoji ni de XML-RPC, inutiles ici.
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
add_filter( 'xmlrpc_enabled', '__return_false' );
