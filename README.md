<div align="center">

# 🖥️ Geoventure Panel

**Le panel d'administration de l'écosystème Geoventure-MC**

Crée tes comptes joueurs, gère tes serveurs, tes mods, ta whitelist et toute la
configuration de ton launcher — depuis une seule interface web.

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)

</div>

---

## ✨ Ce que fait le panel

Le panel est le **cerveau** de l'écosystème. Le launcher ne fait que lire ce que
tu configures ici.

- 👥 **Comptes & rôles** — gestion des utilisateurs, rôles et backgrounds par rôle
- 🌍 **Serveurs multiples** — gère plusieurs serveurs (Geoventure, Elandor, Pokeland…)
  depuis **un seul panel**. Ajout manuel ou synchro Azuriom, choix du serveur par défaut,
  upload d'icônes.
- 🧩 **Mods & loader** — version Minecraft, Forge/Fabric, mods optionnels
- 🛡️ **Sécurité** — mode maintenance (toggle rapide), whitelist par joueur ou par rôle
- 📢 **Annonces** — bandeau de notifications poussé dans le launcher
- 🎨 **Interface** — couleur d'accent, splash, alertes, vidéo d'accueil
- 🎮 **Discord RPC** — presence riche entièrement configurable
- 📜 **Journal d'audit** — trace des actions admin
- 🔄 **Mises à jour** — la page « Mise à Jour » vérifie les releases GitHub et
  applique la nouvelle version en un clic

## 🔗 Comment le launcher lit le panel

Le launcher interroge ces endpoints publics (préfixe `/utils`) :

| Endpoint | Contenu |
|----------|---------|
| `GET /utils/api` | Toute la config : maintenance, loader, **liste des serveurs**, RPC, UI, whitelist… |
| `GET /utils/mods` | Mods optionnels |
| `GET /utils/notifications` | Annonces in-app actives |
| `GET /utils/servers-status` | Statut en ligne / hors ligne de chaque serveur (ping) |
| `GET /data` | Liste des fichiers du modpack (hash / taille / url) |

> 💡 La clé `servers` de `/utils/api` contient **tous** les serveurs configurés,
> ce qui permet à un seul panel d'alimenter les différents serveurs du launcher.
> La clé `status` reste le serveur marqué « par défaut » (compatibilité).

## 🚀 Installation

La méthode recommandée est l'**installer Geoventure**, qui télécharge
automatiquement la dernière archive `panel-*.zip` publiée ici et te guide pas à pas.

Installation manuelle :

```bash
# 1. Récupère la dernière release (archive autonome : deps PHP + assets inclus)
#    https://github.com/Geoventure-MC/panel/releases/latest

# 2. Dézippe sur ton serveur web, puis ouvre ton domaine
#    -> l'assistant d'installation se lance automatiquement
```

### Pour développer

```bash
git clone https://github.com/Geoventure-MC/panel.git
cd panel
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev      # assets en mode watch
php artisan serve
```

## 🔄 Mises à jour & versions

Chaque modification poussée sur `main` déclenche automatiquement une **release**.
La version est calculée à partir des messages de commit (versioning sémantique) :

| Préfixe du commit | Effet sur la version |
|-------------------|----------------------|
| `feat: …` | bump **mineur** (1.1.0 → 1.2.0) |
| `fix: …`, `chore: …`, etc. | bump **patch** (1.1.0 → 1.1.1) |
| `feat!: …` ou `BREAKING CHANGE` | bump **majeur** (1.1.0 → 2.0.0) |

Les notes de release sont générées automatiquement et classées en
✨ Nouveautés / 🐛 Corrections / 🔧 Autres. La version est synchronisée entre
`package.json` et `config/app.php`, qui est celle affichée sur la page
« Mise à Jour » du panel.

> 💡 Astuce : écris des messages de commit clairs (`feat:`, `fix:`…) — ils
> deviennent directement les notes de version visibles par les utilisateurs.

## 🧱 Stack technique

- **Laravel 11** / **PHP 8.2**
- **Blade** + **Bootstrap 5** (Bootstrap Icons)
- **Vite** pour le build des assets
- Stockage des options dans des tables `options_*`
- i18n FR / EN (`lang/fr`, `lang/en`)

---

<div align="center">

Fait avec 💚 pour <strong>Geoventure-MC</strong>

</div>
