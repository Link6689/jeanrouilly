#!/usr/bin/env bash
# Installe et configure le site (WordPress + Blocksy + thème enfant + contenu).
# Idempotent : peut être relancé pour réappliquer le contenu et les réglages.
#
# Usage (sur le serveur de préprod, depuis la racine du dépôt) :
#   WP_PATH=/chemin/vers/wordpress ./setup/install.sh
#
# Variables (voir .env.example) :
#   WP_PATH           dossier d'installation WordPress        (défaut : ./wordpress)
#   SITE_URL          URL du site                             (défaut : https://jean.conquerantsduweb.com)
#   ADMIN_USER, ADMIN_EMAIL, ADMIN_PASSWORD   compte admin, si WordPress n'est pas encore installé
#   DB_NAME, DB_USER, DB_PASSWORD, DB_HOST    base de données, si wp-config.php n'existe pas encore
#   JR_CONTACT_EMAIL  destinataire du formulaire de contact   (défaut : e-mail admin)
#   JR_PREPROD        1 = site non indexé (défaut), 0 = production
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_PATH="${WP_PATH:-$REPO_DIR/wordpress}"
SITE_URL="${SITE_URL:-https://jean.conquerantsduweb.com}"
JR_PREPROD="${JR_PREPROD:-1}"

wp() { command wp --path="$WP_PATH" "$@"; }

command -v wp >/dev/null || { echo "WP-CLI est requis : https://wp-cli.org" >&2; exit 1; }

# Contact Form 7 récent exige PHP 8.3 : sur un PHP plus ancien, on installe
# la dernière version compatible (6.1.4, PHP 7.4+).
CF7_VERSION=""
if ! php -r 'exit(version_compare(PHP_VERSION, "8.3", ">=") ? 0 : 1);'; then
	CF7_VERSION="6.1.4"
	echo "PHP $(php -r 'echo PHP_VERSION;') : Contact Form 7 sera installé en version $CF7_VERSION."
fi

echo "==> WordPress dans $WP_PATH ($SITE_URL)"
mkdir -p "$WP_PATH"

if [ ! -f "$WP_PATH/wp-load.php" ]; then
	wp core download --locale=fr_FR
fi

if [ ! -f "$WP_PATH/wp-config.php" ]; then
	: "${DB_NAME:?DB_NAME requis}" "${DB_USER:?DB_USER requis}" "${DB_PASSWORD:?DB_PASSWORD requis}"
	wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" \
		--dbhost="${DB_HOST:-localhost}" --locale=fr_FR
fi

if ! wp core is-installed 2>/dev/null; then
	: "${ADMIN_USER:?ADMIN_USER requis}" "${ADMIN_EMAIL:?ADMIN_EMAIL requis}"
	wp core install --url="$SITE_URL" --title="Jean Rouilly" \
		--admin_user="$ADMIN_USER" --admin_email="$ADMIN_EMAIL" \
		${ADMIN_PASSWORD:+--admin_password="$ADMIN_PASSWORD"} --skip-email
fi

echo "==> Langue"
wp language core install fr_FR --activate || true

echo "==> Thème Blocksy + thème enfant"
# Le thème enfant est copié depuis le dépôt, sauf s'il y est déjà relié
# (lien symbolique, ou montage Docker : JR_THEME_LINKED=1).
THEME_DIR="$WP_PATH/wp-content/themes/jeanrouilly"
if [ "${JR_THEME_LINKED:-0}" != "1" ] && [ "$(realpath -m "$THEME_DIR")" != "$(realpath -m "$REPO_DIR/wp-content/themes/jeanrouilly")" ]; then
	rm -rf "$THEME_DIR"
	cp -R "$REPO_DIR/wp-content/themes/jeanrouilly" "$THEME_DIR"
fi
wp theme is-installed blocksy || wp theme install blocksy
wp theme activate jeanrouilly

echo "==> Extensions"
wp plugin is-installed blocksy-companion || wp plugin install blocksy-companion
wp plugin is-installed contact-form-7 || wp plugin install contact-form-7 ${CF7_VERSION:+--version="$CF7_VERSION"}
wp plugin activate blocksy-companion contact-form-7
wp plugin delete hello akismet 2>/dev/null || true
wp language plugin install --all fr_FR || true
wp language theme install --all fr_FR || true

echo "==> Préproduction : indexation"
if [ "$JR_PREPROD" = "1" ]; then
	wp option update blog_public 0
	wp config set JR_PREPROD true --raw
else
	wp option update blog_public 1
	wp config set JR_PREPROD false --raw
fi

echo "==> Contenu et réglages"
wp eval-file "$REPO_DIR/setup/setup.php"
wp rewrite flush --hard 2>/dev/null || wp rewrite flush

echo "==> Terminé : $(wp option get home)"
