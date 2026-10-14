<?php
/**
 * Configuration du site, exécutée par setup/install.sh via `wp eval-file`.
 * Idempotent : peut être relancé sans dupliquer les pages, menus ou formulaires.
 *
 * Variables d'environnement lues :
 *   JR_CONTACT_EMAIL  destinataire du formulaire de contact (défaut : e-mail de Jean ci-dessous)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "À lancer avec : wp eval-file setup/setup.php\n" );
}

$content_dir = __DIR__ . '/content';

function jr_log( $msg ) {
	WP_CLI::log( '  ' . $msg );
}

/* -------------------------------------------------------------------------
 * Coordonnées de Jean (affichées sur le site et destinataire du formulaire)
 * ---------------------------------------------------------------------- */

$contact = [
	'email'    => 'jean.rouilly@imcpartners.fr',
	'phone'    => '06 86 18 55 34',
	'linkedin' => 'https://www.linkedin.com/in/jean-rouilly-08329019/',
];
// Lu par le thème (pied de page).
update_option( 'jr_contact', $contact );

/* -------------------------------------------------------------------------
 * Réglages généraux
 * ---------------------------------------------------------------------- */

update_option( 'blogname', 'Jean Rouilly' );
update_option( 'blogdescription', 'Mentorat personnalisé' );
update_option( 'timezone_string', 'Europe/Paris' );
update_option( 'date_format', 'j F Y' );
update_option( 'time_format', 'G\hi' );
update_option( 'default_comment_status', 'closed' );
update_option( 'default_ping_status', 'closed' );
update_option( 'permalink_structure', '/%postname%/' );
jr_log( 'Réglages généraux appliqués' );

// Contenu de démonstration de WordPress.
foreach ( [ 'bonjour-tout-le-monde', 'hello-world' ] as $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $post ) {
		wp_delete_post( $post->ID, true );
	}
}
foreach ( [ 'page-d-exemple', 'sample-page' ] as $slug ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		wp_delete_post( $page->ID, true );
	}
}

/* -------------------------------------------------------------------------
 * Formulaire de contact (Contact Form 7)
 * ---------------------------------------------------------------------- */

$form_title = 'Formulaire de contact – Mentorat';
$recipient  = getenv( 'JR_CONTACT_EMAIL' ) ?: $contact['email'];

$form_markup = <<<'CF7'
<div class="jr-form-row">
<p><label>Nom et prénom *[text* your-name autocomplete:name]</label></p>
<p><label>E-mail *[email* your-email autocomplete:email]</label></p>
</div>
<div class="jr-form-row">
<p><label>Téléphone (facultatif)[tel your-phone autocomplete:tel]</label></p>
<p><label>Votre demande concerne[select your-topic "Développement personnel" "Vie professionnelle, management" "Orientation : études, carrière, entrepreneuriat" "Autre"]</label></p>
</div>
<p><label>Votre message *[textarea* your-message x6 placeholder "Décrivez en quelques mots votre situation et ce que vous attendez d’un accompagnement."]</label></p>
<p>[acceptance rgpd] J’accepte que les informations saisies soient utilisées pour me recontacter. <a href="/politique-de-confidentialite/">En savoir plus</a>[/acceptance]</p>
<p>[submit "Envoyer mon message"]</p>
CF7;

$mail_body = <<<'MAIL'
Nouveau message depuis le site de mentorat.

Nom : [your-name]
E-mail : [your-email]
Téléphone : [your-phone]
Sujet : [your-topic]

Message :
[your-message]

--
Envoyé depuis [_site_url]
MAIL;

if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
	WP_CLI::error( 'Contact Form 7 doit être activé avant ce script.' );
}

$existing = WPCF7_ContactForm::find( [ 'title' => $form_title ] );
$form     = $existing ? $existing[0] : WPCF7_ContactForm::get_template( [ 'title' => $form_title ] );

$form->set_properties(
	[
		'form' => $form_markup,
		'mail' => [
			'active'             => true,
			'subject'            => '[_site_title] Nouveau contact : [your-name]',
			'sender'             => '[_site_title] <wordpress@[_site_domain]>',
			'recipient'          => $recipient,
			'body'               => $mail_body,
			'additional_headers' => 'Reply-To: [your-email]',
			'attachments'        => '',
			'use_html'           => false,
			'exclude_blank'      => true,
		],
		'messages' => array_merge(
			$form->prop( 'messages' ) ?: [],
			[
				'mail_sent_ok'         => 'Merci, votre message a bien été envoyé. Je vous réponds personnellement dans les meilleurs délais.',
				'accept_terms'         => 'Merci d’accepter l’utilisation de vos données pour être recontacté(e).',
				'invalid_required'     => 'Ce champ est obligatoire.',
				'validation_error'     => 'Un ou plusieurs champs sont incomplets. Merci de les vérifier.',
			]
		),
	]
);
$form_id = $form->save();
jr_log( "Formulaire de contact #$form_id (destinataire : $recipient)" );

// Contact Form 7 crée un formulaire par défaut à l'activation : on le retire.
foreach ( WPCF7_ContactForm::find() as $other ) {
	if ( $other->id() !== $form_id && in_array( $other->title(), [ 'Formulaire de contact 1', 'Contact form 1' ], true ) ) {
		$other->delete();
	}
}

/* -------------------------------------------------------------------------
 * Médias : photo de Jean et icône du site (importées une seule fois)
 * ---------------------------------------------------------------------- */

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/**
 * Importe un fichier de setup/media dans la médiathèque, une seule fois
 * (l'ID est mémorisé dans une option), et renvoie l'ID de la pièce jointe.
 */
function jr_import_media( $filename, $title, $alt, $option ) {
	$id = (int) get_option( $option );
	if ( $id && get_post( $id ) ) {
		return $id;
	}
	$tmp = wp_tempnam( $filename );
	copy( __DIR__ . '/media/' . $filename, $tmp );
	$id = media_handle_sideload( [ 'name' => $filename, 'tmp_name' => $tmp ], 0, $title );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( "$filename : " . $id->get_error_message() );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_option( $option, $id );
	return $id;
}

$photo_id = jr_import_media( 'jean-rouilly.webp', 'Jean Rouilly', 'Jean Rouilly, mentor', 'jr_photo_id' );
$photo_url = wp_get_attachment_url( $photo_id );
jr_log( "Photo #$photo_id" );

// Icône du site (favicon, icône d'écran d'accueil mobile).
$icon_id = jr_import_media( 'site-icon.png', 'Icône du site', '', 'jr_site_icon_id' );
if ( 'site-icon' !== get_post_meta( $icon_id, '_wp_attachment_context', true ) ) {
	// Génère les tailles dédiées (32, 180, 192, 270 px) comme le fait l'outil
	// « Icône du site » du Customizer, au lieu de réutiliser les miniatures.
	require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';
	$site_icon = new WP_Site_Icon();
	add_filter( 'intermediate_image_sizes_advanced', [ $site_icon, 'additional_sizes' ] );
	update_post_meta( $icon_id, '_wp_attachment_context', 'site-icon' );
	wp_update_attachment_metadata( $icon_id, wp_generate_attachment_metadata( $icon_id, get_attached_file( $icon_id ) ) );
	remove_filter( 'intermediate_image_sizes_advanced', [ $site_icon, 'additional_sizes' ] );
}
update_option( 'site_icon', $icon_id );
jr_log( "Icône du site #$icon_id" );

/* -------------------------------------------------------------------------
 * Pages
 * ---------------------------------------------------------------------- */

$pages = [
	'accueil'                      => [ 'Accueil', 'accueil.html' ],
	'accompagnement'               => [ 'Mon accompagnement', 'accompagnement.html' ],
	'contact'                      => [ 'Contact', 'contact.html' ],
	'mentions-legales'             => [ 'Mentions légales', 'mentions-legales.html' ],
	'politique-de-confidentialite' => [ 'Politique de confidentialité', 'confidentialite.html' ],
];

$page_ids = [];

foreach ( $pages as $slug => list( $title, $file ) ) {
	$content = file_get_contents( "$content_dir/$file" );
	$content = strtr(
		$content,
		[
			'{{CONTACT_FORM}}' => sprintf( '[contact-form-7 id="%s" title="%s"]', $form->hash(), $form_title ),
			'{{PHOTO_ID}}'     => $photo_id,
			'{{PHOTO_URL}}'    => esc_url( $photo_url ),
			'{{EMAIL}}'        => $contact['email'],
			'{{PHONE}}'        => $contact['phone'],
			'{{PHONE_LINK}}'   => 'tel:+33' . substr( preg_replace( '/\D/', '', $contact['phone'] ), 1 ),
			'{{LINKEDIN}}'     => $contact['linkedin'],
		]
	);

	$existing = get_page_by_path( $slug );
	$postarr  = [
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => wp_slash( $content ),
	];
	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
	}

	$id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( "Page $slug : " . $id->get_error_message() );
	}
	$page_ids[ $slug ] = $id;

	// Options de page Blocksy : pas de bandeau titre (chaque page a son propre héro),
	// pleine largeur pour les sections, pas d'espacement vertical autour du contenu.
	$is_legal = in_array( $slug, [ 'mentions-legales', 'politique-de-confidentialite' ], true );
	update_post_meta(
		$id,
		'blocksy_post_meta_options',
		[
			'has_hero_section'        => 'disabled',
			'page_structure_type'     => $is_legal ? 'type-3' : 'type-4',
			'content_style_source'    => 'custom',
			'content_style'           => 'wide',
			'vertical_spacing_source' => $is_legal ? 'inherit' : 'custom',
			'content_area_spacing'    => 'none',
		]
	);

	jr_log( "Page « $title » #$id" );
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_ids['accueil'] );
update_option( 'wp_page_for_privacy_policy', $page_ids['politique-de-confidentialite'] );

/* -------------------------------------------------------------------------
 * Menus
 * ---------------------------------------------------------------------- */

function jr_sync_menu( $name, array $items ) {
	$menu = wp_get_nav_menu_object( $name );
	$id   = $menu ? $menu->term_id : wp_create_nav_menu( $name );

	foreach ( wp_get_nav_menu_items( $id ) ?: [] as $item ) {
		wp_delete_post( $item->ID, true );
	}
	foreach ( $items as $position => $page_id ) {
		wp_update_nav_menu_item(
			$id,
			0,
			[
				'menu-item-object-id' => $page_id,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $position + 1,
			]
		);
	}
	return $id;
}

$main_menu = jr_sync_menu(
	'Menu principal',
	[ $page_ids['accueil'], $page_ids['accompagnement'], $page_ids['contact'] ]
);
$legal_menu = jr_sync_menu(
	'Menu légal',
	[ $page_ids['mentions-legales'], $page_ids['politique-de-confidentialite'] ]
);

$locations                = get_theme_mod( 'nav_menu_locations', [] );
$locations['menu_1']      = $main_menu;
$locations['menu_mobile'] = $main_menu;
$locations['footer']      = $legal_menu;
set_theme_mod( 'nav_menu_locations', $locations );
jr_log( 'Menus assignés' );

/* -------------------------------------------------------------------------
 * Blocksy : palette de couleurs (lumineuse, couleurs positives)
 * ---------------------------------------------------------------------- */

set_theme_mod(
	'colorPalette',
	[
		'color1' => [ 'color' => '#0e7c7b' ], // Principal : bleu-vert
		'color2' => [ 'color' => '#0a5f5e' ], // Principal foncé (survol)
		'color3' => [ 'color' => '#475569' ], // Texte
		'color4' => [ 'color' => '#1e293b' ], // Titres
		'color5' => [ 'color' => '#f1e4d3' ], // Bordures
		'color6' => [ 'color' => '#fff3e4' ], // Fond clair (crème)
		'color7' => [ 'color' => '#fffaf4' ], // Fond du site
		'color8' => [ 'color' => '#ffffff' ], // Blanc
	]
);
// Blocksy met en cache ses styles dynamiques : on force leur régénération.
delete_transient( 'blocksy_dynamic_styles_descriptor' );
do_action( 'blocksy:dynamic-css:refresh-caches' );
jr_log( 'Palette Blocksy appliquée' );

flush_rewrite_rules();
WP_CLI::success( 'Site configuré.' );
