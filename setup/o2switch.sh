#!/usr/bin/env bash
# Mise en ligne en une commande sur un hébergement cPanel (o2switch).
# À coller dans le Terminal du cPanel (ou en SSH) :
#
#   curl -sSL https://codeload.github.com/Link6689/jeanrouilly/tar.gz/refs/heads/claude/pensive-feynman-gpmy83 | tar -xzOf - --wildcards '*/setup/o2switch.sh' | bash -s -- votre@email.fr
#
# Le script, relançable sans risque :
#   1. crée le sous-domaine jean.conquerantsduweb.com s'il n'existe pas ;
#   2. crée la base MySQL et son utilisateur (mot de passe généré) ;
#   3. télécharge ce dépôt dans ~/jeanrouilly ;
#   4. lance setup/install.sh (WordPress, Blocksy, pages, formulaire…).
# Les identifiants générés sont gardés dans ~/.jeanrouilly.env (lisible par vous seul).
set -euo pipefail

main() {

	EMAIL="${1:-}"
	[ -n "$EMAIL" ] || { echo "Usage : bash o2switch.sh votre@email.fr" >&2; exit 1; }

	SUBDOMAIN="jean"
	ROOT_DOMAIN="conquerantsduweb.com"
	SITE_HOST="$SUBDOMAIN.$ROOT_DOMAIN"
	BRANCH="claude/pensive-feynman-gpmy83"
	REPO_TARBALL="https://codeload.github.com/Link6689/jeanrouilly/tar.gz/refs/heads/$BRANCH"
	SECRETS="$HOME/.jeanrouilly.env"
	CPUSER="$(id -un)"

	genpass() { head -c 512 /dev/urandom | tr -dc 'A-Za-z0-9' | cut -c1-24; }
	uapi_json() { uapi --output=json "$@" 2>/dev/null || true; }
	uapi_ok() { local out; out="$(uapi_json "$@")"; grep -Eq '"status" ?: ?1' <<<"$out"; }

	# --- Identifiants (générés une seule fois) --------------------------------
	if [ -f "$SECRETS" ]; then
		. "$SECRETS"
	else
		DB_NAME="${CPUSER}_jean"
		DB_USER="${CPUSER}_jean"
		DB_PASSWORD="$(genpass)"
		ADMIN_USER="admin-jr"
		ADMIN_PASSWORD="$(genpass)"
		umask 077
		cat >"$SECRETS" <<EOF
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
ADMIN_USER=$ADMIN_USER
ADMIN_PASSWORD=$ADMIN_PASSWORD
EOF
		umask 022
	fi

	# --- Sous-domaine ---------------------------------------------------------
	echo "==> Sous-domaine $SITE_HOST"
	DOCROOT="$(uapi_json DomainInfo single_domain_data domain="$SITE_HOST" \
		| grep -Eo '"documentroot" ?: ?"[^"]*"' | cut -d'"' -f4 | sed 's#\\/#/#g' || true)"
	if [ -z "$DOCROOT" ]; then
		uapi_ok SubDomain addsubdomain domain="$SUBDOMAIN" rootdomain="$ROOT_DOMAIN" dir="$SITE_HOST" \
			|| { echo "Impossible de créer le sous-domaine $SITE_HOST" >&2; exit 1; }
		DOCROOT="$HOME/$SITE_HOST"
		echo "   créé : $DOCROOT"
	else
		echo "   existe déjà : $DOCROOT"
	fi

	# --- Base de données ------------------------------------------------------
	echo "==> Base de données $DB_NAME"
	if grep -Eq "\"database\" ?: ?\"$DB_NAME\"" <<<"$(uapi_json Mysql list_databases)"; then
		echo "   existe déjà"
	else
		uapi_ok Mysql create_database name="$DB_NAME" || { echo "Échec création base" >&2; exit 1; }
		echo "   créée"
	fi
	if ! grep -Eq "\"user\" ?: ?\"$DB_USER\"" <<<"$(uapi_json Mysql list_users)"; then
		uapi_ok Mysql create_user name="$DB_USER" password="$DB_PASSWORD" || echo "   (utilisateur MySQL non créé : il existe peut-être déjà)"
	fi
	uapi_ok Mysql set_privileges_on_database user="$DB_USER" database="$DB_NAME" privileges="ALL%20PRIVILEGES" \
		|| { echo "Échec attribution des droits MySQL" >&2; exit 1; }

	# --- WP-CLI ---------------------------------------------------------------
	if ! command -v wp >/dev/null; then
		echo "==> Installation de WP-CLI dans ~/bin"
		mkdir -p "$HOME/bin"
		curl -sSLo "$HOME/bin/wp" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
		chmod +x "$HOME/bin/wp"
		export PATH="$HOME/bin:$PATH"
	fi

	# --- Code du site ---------------------------------------------------------
	echo "==> Téléchargement du projet dans ~/jeanrouilly"
	rm -rf "$HOME/jeanrouilly.tmp"
	mkdir -p "$HOME/jeanrouilly.tmp"
	curl -sSL "$REPO_TARBALL" | tar -xz -C "$HOME/jeanrouilly.tmp" --strip-components=1
	rm -rf "$HOME/jeanrouilly"
	mv "$HOME/jeanrouilly.tmp" "$HOME/jeanrouilly"

	# --- Installation WordPress -----------------------------------------------
	WP_PATH="$DOCROOT" SITE_URL="https://$SITE_HOST" \
		DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASSWORD="$DB_PASSWORD" DB_HOST=localhost \
		ADMIN_USER="$ADMIN_USER" ADMIN_PASSWORD="$ADMIN_PASSWORD" ADMIN_EMAIL="$EMAIL" \
		JR_CONTACT_EMAIL="$EMAIL" JR_PREPROD=1 \
		bash "$HOME/jeanrouilly/setup/install.sh"

	cat <<EOF

========================================================
 Site en ligne : https://$SITE_HOST
 Administration : https://$SITE_HOST/wp-admin
 Identifiant    : $ADMIN_USER
 Mot de passe   : $ADMIN_PASSWORD
 (conservés dans $SECRETS)
========================================================
EOF
}

main "$@" </dev/null
