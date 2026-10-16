<?php
/**
 * Thème enfant Blocksy – Jean Rouilly, mentorat personnalisé.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JR_VERSION', '0.6.0' );

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
 * Pied de page : copyright, coordonnées et liens légaux.
 * Les coordonnées sont enregistrées par setup/setup.php (option jr_contact).
 */
function jr_footer_copyright() {
	$contact = (array) get_option( 'jr_contact', [] );
	$parts   = [ '© {current_year} {site_title} · Mentorat personnalisé' . ( ! empty( $contact['city'] ) ? ' · ' . esc_html( $contact['city'] ) : '' ) ];

	if ( ! empty( $contact['phone'] ) ) {
		$parts[] = sprintf( '<a href="tel:+%s">%s</a>', esc_attr( $contact['phone_intl'] ?? '' ), esc_html( $contact['phone'] ) );
	}
	if ( ! empty( $contact['linkedin'] ) ) {
		$parts[] = sprintf( '<a href="%s" target="_blank" rel="noreferrer noopener">LinkedIn</a>', esc_url( $contact['linkedin'] ) );
	}
	$parts[] = sprintf( '<a href="%s">Mentions légales</a>', esc_url( home_url( '/mentions-legales/' ) ) );
	$parts[] = sprintf( '<a href="%s">Confidentialité</a>', esc_url( home_url( '/politique-de-confidentialite/' ) ) );

	return implode( ' · ', $parts );
}
add_filter( 'blocksy:footer:copyright:default-value', 'jr_footer_copyright' );

/**
 * En-tête : pas de recherche (inutile sur un site de quelques pages).
 * Ne s'applique qu'à la structure par défaut de Blocksy : un en-tête modifié
 * dans le Customizer reste tel quel.
 */
function jr_strip_header_item( $node, $item ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	if ( isset( $node['items'] ) && is_array( $node['items'] ) ) {
		$node['items'] = array_values( array_diff( $node['items'], [ $item ] ) );
	}
	foreach ( $node as $key => $child ) {
		if ( 'items' !== $key ) {
			$node[ $key ] = jr_strip_header_item( $child, $item );
		}
	}
	return $node;
}
add_filter(
	'blocksy:header:default_value',
	function ( $value ) {
		return jr_strip_header_item( $value, 'search' );
	}
);

/**
 * Bouton WhatsApp flottant (lien wa.me vers le numéro de Jean).
 */
function jr_whatsapp_button() {
	$contact = (array) get_option( 'jr_contact', [] );
	if ( empty( $contact['whatsapp'] ) ) {
		return;
	}
	printf(
		'<a class="jr-whatsapp" href="%s" target="_blank" rel="noreferrer noopener" aria-label="%s">%s<span class="jr-whatsapp__label">%s</span></a>',
		esc_url( $contact['whatsapp'] ),
		esc_attr__( 'Écrire à Jean Rouilly sur WhatsApp', 'jeanrouilly' ),
		'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
		esc_html__( 'WhatsApp', 'jeanrouilly' )
	);
}
add_action( 'wp_footer', 'jr_whatsapp_button' );

/**
 * Référencement : description, Open Graph (aperçus LinkedIn, WhatsApp…) et
 * données structurées. Désactivé si une extension SEO dédiée est installée.
 */
function jr_has_seo_plugin() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'All_in_One_SEO_Pack' ) || defined( 'AIOSEO_VERSION' );
}

function jr_seo_head() {
	if ( jr_has_seo_plugin() ) {
		return;
	}

	$description = is_singular() ? get_post_field( 'post_excerpt', get_queried_object_id() ) : '';
	if ( ! $description ) {
		$description = get_bloginfo( 'description' );
	}
	$share_id = (int) get_option( 'jr_share_image_id' );
	$image    = $share_id ? wp_get_attachment_image_url( $share_id, 'full' ) : get_site_icon_url( 512 );
	$url      = is_singular() ? get_permalink() : home_url( '/' );

	$tags = [
		'description'    => $description,
		'og:type'        => 'website',
		'og:locale'      => 'fr_FR',
		'og:site_name'   => get_bloginfo( 'name' ),
		'og:title'       => wp_get_document_title(),
		'og:description' => $description,
		'og:url'         => $url,
		'og:image'       => $image,
		'twitter:card'   => 'summary_large_image',
	];
	foreach ( $tags as $name => $content ) {
		if ( ! $content ) {
			continue;
		}
		$attr = 0 === strpos( $name, 'og:' ) ? 'property' : 'name';
		printf( '<meta %s="%s" content="%s">' . "\n", $attr, esc_attr( $name ), esc_attr( $content ) );
	}

	if ( is_front_page() ) {
		$contact = (array) get_option( 'jr_contact', [] );
		$person  = array_filter(
			[
				'@context'    => 'https://schema.org',
				'@type'       => 'Person',
				'name'        => 'Jean Rouilly',
				'jobTitle'    => 'Mentor',
				'description' => $description,
				'url'         => home_url( '/' ),
				'image'       => $image,
				'email'       => ! empty( $contact['email'] ) ? 'mailto:' . $contact['email'] : null,
				'telephone'   => ! empty( $contact['phone_intl'] ) ? '+' . $contact['phone_intl'] : null,
				'sameAs'      => ! empty( $contact['linkedin'] ) ? [ $contact['linkedin'] ] : null,
				'address'     => ! empty( $contact['city'] ) ? [ '@type' => 'PostalAddress', 'addressLocality' => $contact['city'], 'addressCountry' => 'FR' ] : null,
				'areaServed'  => ! empty( $contact['city'] ) ? [ $contact['city'], 'France' ] : null,
				'award'       => 'Chevalier de l’Ordre national du Mérite',
				'alumniOf'    => [ '@type' => 'CollegeOrUniversity', 'name' => 'Sciences Po Bordeaux' ],
				'knowsLanguage' => [ 'fr', 'en' ],
				'knowsAbout'  => [ 'Mentorat', 'Management', 'Orientation professionnelle', 'Développement personnel', 'Entrepreneuriat' ],
			]
		);
		printf( '<script type="application/ld+json">%s</script>' . "\n", wp_json_encode( $person, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}
}
add_action( 'wp_head', 'jr_seo_head', 2 );

/**
 * Anti-spam : le champ « jr-website » du formulaire est invisible pour les
 * visiteurs ; s'il est rempli, c'est un robot et le message est rejeté.
 */
function jr_honeypot_spam( $spam ) {
	if ( $spam ) {
		return $spam;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 vérifie la soumission.
	return ! empty( $_POST['jr-website'] );
}
add_filter( 'wpcf7_spam', 'jr_honeypot_spam' );

/**
 * Confidentialité : pas de script emoji ni de XML-RPC, inutiles ici.
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
add_filter( 'xmlrpc_enabled', '__return_false' );
