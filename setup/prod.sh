#!/usr/bin/env bash
# Mise en production : copie la préprod (jean.conquerantsduweb.com) vers
# jeanrouilly.fr sur le même compte cPanel (o2switch).
#
# À coller dans le Terminal du cPanel :
#   curl -sSL https://codeload.github.com/Link6689/jeanrouilly/tar.gz/refs/heads/claude/pensive-feynman-gpmy83 | tar -xzOf - --wildcards '*/setup/prod.sh' | bash
#
# Le script :
#   1. sauvegarde la base de la préprod dans ~/sauvegardes-jeanrouilly/ ;
#   2. crée une base MySQL dédiée à la production (mot de passe généré) ;
#   3. copie les fichiers de la préprod dans le dossier de jeanrouilly.fr ;
#   4. importe la base et remplace l'adresse de préprod par https://jeanrouilly.fr ;
#   5. ouvre le site aux moteurs de recherche (JR_PREPROD=false, indexation activée).
# La préprod n'est pas modifiée : elle reste disponible pour tester les évolutions.
#
# Par sécurité, le script refuse de s'exécuter si jeanrouilly.fr contient déjà
# un WordPress (il écraserait la production). FORCE=1 pour passer outre.
set -euo pipefail

main() {
	SRC_HOST="jean.conquerantsduweb.com"
	DST_HOST="${DST_HOST:-jeanrouilly.fr}"
	SECRETS="$HOME/.jeanrouilly-prod.env"
	BACKUP_DIR="$HOME/sauvegardes-jeanrouilly"
	CPUSER="$(id -un)"
	STAMP="$(date +%Y%m%d-%H%M%S)"

	genpass() { head -c 512 /dev/urandom | tr -dc 'A-Za-z0-9' | cut -c1-24; }
	uapi_json() { uapi --output=json "$@" 2>/dev/null || true; }
	uapi_ok() { local out; out="$(uapi_json "$@")"; grep -Eq '"status" ?: ?1' <<<"$out"; }
	docroot_of() {
		uapi_json DomainInfo single_domain_data domain="$1" \
			| grep -Eo '"documentroot" ?: ?"[^"]*"' | cut -d'"' -f4 | sed 's#\\/#/#g' || true
	}

	if ! command -v wp >/dev/null; then
		mkdir -p "$HOME/bin"
		curl -sSLo "$HOME/bin/wp" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
		chmod +x "$HOME/bin/wp"
		export PATH="$HOME/bin:$PATH"
	fi

	# --- Dossiers --------------------------------------------------------
	SRC="$(docroot_of "$SRC_HOST")"
	DST="$(docroot_of "$DST_HOST")"
	[ -n "$SRC" ] && [ -f "$SRC/wp-config.php" ] || { echo "Préprod introuvable ($SRC_HOST)." >&2; exit 1; }
	[ -n "$DST" ] || { echo "Le domaine $DST_HOST n'existe pas dans ce cPanel (Domaines › Créer un domaine)." >&2; exit 1; }
	echo "==> Préprod     : $SRC"
	echo "==> Production  : $DST"

	if [ -f "$DST/wp-config.php" ] && [ "${FORCE:-0}" != "1" ]; then
		echo "Un WordPress existe déjà dans $DST : arrêt pour ne pas écraser la production." >&2
		echo "(Relancer avec FORCE=1 uniquement si vous voulez la remplacer par la préprod.)" >&2
		exit 1
	fi
	OTHER="$(find "$DST" -mindepth 1 -maxdepth 1 ! -name cgi-bin ! -name .well-known ! -name '.htaccess' -print -quit)"
	if [ -n "$OTHER" ] && [ ! -f "$DST/wp-config.php" ]; then
		echo "Le dossier $DST n'est pas vide ($OTHER) : vérifiez son contenu avant de continuer." >&2
		exit 1
	fi

	# --- Sauvegarde de la préprod ---------------------------------------
	mkdir -p "$BACKUP_DIR"
	chmod 700 "$BACKUP_DIR"
	DUMP="$BACKUP_DIR/preprod-$STAMP.sql"
	echo "==> Sauvegarde de la base de préprod : $DUMP"
	wp --path="$SRC" db export "$DUMP" --quiet

	# --- Base de production ---------------------------------------------
	if [ -f "$SECRETS" ]; then
		. "$SECRETS"
	else
		DB_NAME="${CPUSER}_jeanprod"
		DB_USER="${CPUSER}_jeanprod"
		DB_PASSWORD="$(genpass)"
		umask 077
		printf 'DB_NAME=%s\nDB_USER=%s\nDB_PASSWORD=%s\n' "$DB_NAME" "$DB_USER" "$DB_PASSWORD" >"$SECRETS"
		umask 022
	fi
	echo "==> Base de production $DB_NAME"
	if ! grep -Eq "\"database\" ?: ?\"$DB_NAME\"" <<<"$(uapi_json Mysql list_databases)"; then
		uapi_ok Mysql create_database name="$DB_NAME" || { echo "Échec création base" >&2; exit 1; }
	fi
	if ! grep -Eq "\"user\" ?: ?\"$DB_USER\"" <<<"$(uapi_json Mysql list_users)"; then
		uapi_ok Mysql create_user name="$DB_USER" password="$DB_PASSWORD" || echo "   (utilisateur MySQL non créé : il existe peut-être déjà)"
	fi
	uapi_ok Mysql set_privileges_on_database user="$DB_USER" database="$DB_NAME" privileges="ALL%20PRIVILEGES" \
		|| { echo "Échec attribution des droits MySQL" >&2; exit 1; }

	# --- Fichiers ------------------------------------------------------
	echo "==> Copie des fichiers"
	cp -a "$SRC/." "$DST/"

	wp() { command wp --path="$DST" "$@"; }
	wp config set DB_NAME "$DB_NAME" --quiet
	wp config set DB_USER "$DB_USER" --quiet
	wp config set DB_PASSWORD "$DB_PASSWORD" --quiet
	wp config set JR_PREPROD false --raw --quiet
	wp config shuffle-salts --quiet

	# --- Base : import + changement d'adresse --------------------------
	echo "==> Import de la base"
	wp db import "$DUMP" --quiet
	echo "==> Adresse : https://$SRC_HOST → https://$DST_HOST"
	wp search-replace "https://$SRC_HOST" "https://$DST_HOST" --skip-columns=guid --report-changed-only
	wp search-replace "$SRC_HOST" "$DST_HOST" --skip-columns=guid --report-changed-only

	# --- Ouverture aux moteurs de recherche -----------------------------
	wp option update blog_public 1 --quiet
	wp rewrite flush --quiet
	wp eval 'add_filter("got_rewrite", "__return_true"); require_once ABSPATH . "wp-admin/includes/misc.php"; save_mod_rewrite_rules();'
	wp eval 'delete_transient("blocksy_dynamic_styles_descriptor"); do_action("blocksy:dynamic-css:refresh-caches");'
	wp cache flush --quiet || true

	cat <<EOF

========================================================
 Production en ligne : $(wp option get home)
 Administration      : $(wp option get home)/wp-admin
 (mêmes identifiants que la préprod)
 Base de production  : $DB_NAME (identifiants dans $SECRETS)
 Sauvegarde préprod  : $DUMP
========================================================
EOF
}

main "$@" </dev/null
