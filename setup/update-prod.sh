#!/usr/bin/env bash
# Met à jour la PRODUCTION (jeanrouilly.fr) avec le contenu et le thème du dépôt.
# À coller dans le Terminal du cPanel :
#
#   curl -sSL https://codeload.github.com/Link6689/jeanrouilly/tar.gz/refs/heads/claude/pensive-feynman-gpmy83 | tar -xzOf - --wildcards '*/setup/update-prod.sh' | bash
#
# Les pages sont réécrites depuis setup/content/ : si l'une d'elles a été
# modifiée dans l'admin depuis la dernière mise à jour, le script s'arrête
# (FORCE=1 pour écraser quand même).
set -euo pipefail

main() {
	HOST="jeanrouilly.fr"
	BRANCH="claude/pensive-feynman-gpmy83"
	REPO_TARBALL="https://codeload.github.com/Link6689/jeanrouilly/tar.gz/refs/heads/$BRANCH"

	DOCROOT="$(uapi --output=json DomainInfo single_domain_data domain="$HOST" 2>/dev/null \
		| grep -Eo '"documentroot" ?: ?"[^"]*"' | cut -d'"' -f4 | sed 's#\\/#/#g' || true)"
	[ -n "$DOCROOT" ] && [ -f "$DOCROOT/wp-config.php" ] || { echo "WordPress introuvable pour $HOST." >&2; exit 1; }

	if ! command -v wp >/dev/null; then
		mkdir -p "$HOME/bin"
		curl -sSLo "$HOME/bin/wp" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
		chmod +x "$HOME/bin/wp"
		export PATH="$HOME/bin:$PATH"
	fi

	echo "==> Sauvegarde de la base de production"
	mkdir -p "$HOME/sauvegardes-jeanrouilly" && chmod 700 "$HOME/sauvegardes-jeanrouilly"
	wp --path="$DOCROOT" db export "$HOME/sauvegardes-jeanrouilly/prod-$(date +%Y%m%d-%H%M%S).sql" --quiet

	echo "==> Téléchargement du projet"
	rm -rf "$HOME/jeanrouilly.tmp" && mkdir -p "$HOME/jeanrouilly.tmp"
	curl -sSL "$REPO_TARBALL" | tar -xz -C "$HOME/jeanrouilly.tmp" --strip-components=1
	rm -rf "$HOME/jeanrouilly" && mv "$HOME/jeanrouilly.tmp" "$HOME/jeanrouilly"

	WP_PATH="$DOCROOT" SITE_URL="https://$HOST" JR_PREPROD=0 FORCE="${FORCE:-}" \
		bash "$HOME/jeanrouilly/setup/install.sh"
}

main "$@" </dev/null
