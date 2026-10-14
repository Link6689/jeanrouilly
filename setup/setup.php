<?php
/**
 * Configuration du site, exécutée par setup/install.sh via `wp eval-file`.
 * Idempotent : peut être relancé sans dupliquer les pages, menus ou formulaires.
 *
 * Variables d'environnement lues :
 *   JR_CONTACT_EMAIL  destinataire du formulaire de contact (défaut : e-mail admin)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "À lancer avec : wp eval-file setup/setup.php\n" );
}

$content_dir = __DIR__ . '/content';

function jr_log( $msg ) {
	WP_CLI::log( '  ' . $msg );
}

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
$recipient  = getenv( 'JR_CONTACT_EMAIL' ) ?: get_option( 'admin_email' );

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
	$content = str_replace(
		'{{CONTACT_FORM}}',
		sprintf( '[contact-form-7 id="%s" title="%s"]', $form->hash(), $form_title ),
		$content
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
