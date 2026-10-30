# Jean Rouilly – Mentorat personnalisé

Site vitrine WordPress, thème **Blocksy** + thème enfant `jeanrouilly`.
Préproduction : **https://jean.conquerantsduweb.com** (non indexée par les moteurs de recherche).

## Contenu

| Page | URL | Source |
|---|---|---|
| Accueil : conviction, ambition, objectif, engagement | `/` | `setup/content/accueil.html` |
| Mon accompagnement : méthodologie et modalités | `/accompagnement/` | `setup/content/accompagnement.html` |
| Contact (formulaire Contact Form 7) | `/contact/` | `setup/content/contact.html` |
| Mentions légales *(à compléter)* | `/mentions-legales/` | `setup/content/mentions-legales.html` |
| Politique de confidentialité *(à compléter)* | `/politique-de-confidentialite/` | `setup/content/confidentialite.html` |

Les pages sont en blocs Gutenberg natifs : elles restent modifiables dans l'éditeur WordPress.

## Organisation du dépôt

```
wp-content/themes/jeanrouilly/   thème enfant Blocksy (styles, polices auto-hébergées)
setup/install.sh                 installation + configuration complète (WP-CLI)
setup/setup.php                  pages, menus, formulaire, palette Blocksy
setup/content/                   contenu des pages
docker-compose.yml               environnement local
```

## Installer la préprod sur jean.conquerantsduweb.com

Prérequis sur le serveur : PHP 8.1+ (Contact Form 7 est installé en 6.1.4 si PHP < 8.3), MySQL/MariaDB, [WP-CLI](https://wp-cli.org), accès SSH.

```bash
git clone <ce dépôt> ~/jeanrouilly && cd ~/jeanrouilly
cp .env.example .env    # renseigner base de données, admin, e-mail de contact
set -a; . ./.env; set +a
WP_PATH=/chemin/du/document-root/jean.conquerantsduweb.com ./setup/install.sh
```

Le script :

1. télécharge WordPress en français et l'installe (si ce n'est pas déjà fait) ;
2. installe Blocksy, Blocksy Companion et Contact Form 7, active le thème enfant ;
3. bloque l'indexation (`JR_PREPROD`) ;
4. crée les pages, les menus, le formulaire de contact et applique la palette de couleurs.

Il est **relançable** : il met à jour les pages et réglages sans rien dupliquer.
⚠️ Relancer le script écrase les modifications faites aux pages dans l'admin : une fois
que Jean édite les textes directement dans WordPress, ne plus relancer `setup.php`
(ou reporter d'abord les changements dans `setup/content/`).

Si WordPress est déjà installé via le panneau de l'hébergeur, le script le détecte et
passe directement à la configuration.

## Production : jeanrouilly.fr

La production est une **copie de la préprod** sur le même compte o2switch
(domaine `jeanrouilly.fr`, déjà déclaré dans le cPanel), avec sa propre base.

Mise en production (une seule fois), dans le Terminal du cPanel :

```bash
curl -sSL https://codeload.github.com/Link6689/jeanrouilly/tar.gz/refs/heads/claude/pensive-feynman-gpmy83 | tar -xzOf - --wildcards '*/setup/prod.sh' | bash
```

`setup/prod.sh` sauvegarde la base de préprod (`~/sauvegardes-jeanrouilly/`),
crée la base `<compte>_jeanprod`, copie les fichiers, remplace l'adresse et
ouvre le site aux moteurs de recherche. Il refuse de tourner si jeanrouilly.fr
contient déjà un WordPress (`FORCE=1` pour écraser volontairement).

Ensuite :

- les textes se modifient **directement dans l'admin** de jeanrouilly.fr ;
- pour reporter une évolution du dépôt en production :
  `… tar -xzOf - --wildcards '*/setup/update-prod.sh' | bash` (sauvegarde la base,
  refuse d'écraser une page modifiée dans l'admin sauf `FORCE=1`) ;
- la commande `setup/o2switch.sh` ne touche **que la préprod** (utile pour
  tester une évolution avant de la reporter en production).

## En local (Docker)

```bash
docker compose up -d
docker compose run --rm cli     # lance setup/install.sh
```

Site sur http://localhost:8080, admin : `admin` / `admin`.

## Charte graphique

Sobre, moderne, lumineuse, couleurs positives.

| Rôle | Couleur |
|---|---|
| Principal (boutons, liens) | `#0E7C7B` bleu-vert |
| Accents (soleil → corail) | `#F6B23C` → `#EF7B5A` |
| Fond | `#FFFAF4` crème clair / `#FFF3E4` crème |
| Titres / texte | `#1E293B` / `#475569` |

Polices : *Plus Jakarta Sans* (titres) et *Inter* (texte), auto-hébergées dans le thème
(aucun appel à Google Fonts, conforme RGPD).
La palette est modifiable dans **Apparence › Personnaliser › Couleurs** (Blocksy).

## Avant la mise en production

- [x] Mentions légales (éditeur IMC Partners, hébergeur o2switch) et politique de confidentialité
- [ ] Vérifier l'envoi des e-mails du formulaire (installer un plugin SMTP, ex. *WP Mail SMTP*, si besoin)
- [ ] Ajouter une photo de Jean et éventuellement un logo
- [ ] Ajouter un anti-spam au formulaire (ex. Cloudflare Turnstile ou reCAPTCHA, intégrés à Contact Form 7)
- [ ] Mettre en production avec `setup/prod.sh` (voir ci-dessus)
