# 🚀 Déploiement du site KHANI Fashion en ligne (InfinityFree — gratuit)

Ce guide met votre boutique **en ligne sur Internet** avec Supabase comme base de données.
Durée : ~30 minutes. Aucune carte bancaire requise.

> ⚠️ **Règle d'or** : le fichier `.env` (identifiants secrets) ne se met QUE sur l'hébergeur,
> **jamais sur GitHub**. Il est déjà protégé par le `.htaccess` du projet.

---

## ⭐ Option GitHub Pages : site statique + Supabase (recommandé pour commencer)

Votre boutique est déjà publiée sur GitHub Pages :
`https://ffacultatif1-ux.github.io/KHANI-Fashion/`

GitHub Pages ne peut **pas** exécuter de PHP. Pour que le site communique
avec la base de données, le JavaScript se connecte **directement** à l'API
REST de Supabase (PostgREST) grâce à :
- `js/config.js`   → URL du projet + clé **anon** (publique, sans danger)
- `js/supabase.js` → appels à l'API (produits, catégories, paramètres, commandes, visites)
- `js/fallback.js` → mode de secours : 18 produits affichés si Supabase indisponible

### Étape A — Récupérer la clé anon 🔑
1. Supabase Dashboard → **Settings** (engrenage) → **API Keys**
2. Copiez la **`anon` public key** (une longue chaîne commençant par `eyJ...`)
3. Collez-la dans `js/config.js` → `supabaseAnonKey`

### Étape B — Autoriser les accès publics (SQL)
1. Supabase Dashboard → **SQL Editor** → New query
2. Ouvrez `database/rls_supabase_site.sql`, copiez le contenu, cliquez sur **Run**
   => autorise la lecture publique (produits actifs, catégories, paramètres)
   et l'écriture publique (commandes, visites).
   L'administration PHP (rôle `postgres`) n'est PAS affectée par ces règles.

### Étape C — Publier sur GitHub
1. `git add . && git commit -m "..." && git push` → GitHub Pages se met à jour seul
2. Visitez votre URL → les vrais produits de Supabase s'affichent 🎉
   (sans clé configurée, le mode de secours affiche les 18 produits de démonstration)

> ⚠️ Le back-office (`admin/`) nécessite du PHP : utilisez l'hébergement
> InfinityFree (guide ci-dessous) pour l'administrer.

---

## Étape 1 — Créer le compte InfinityFree (5 min)

1. Allez sur **https://www.infinityfree.com** → **Register** ( inscription avec votre e-mail,
   sans carte bancaire)
2. Une fois connecté : **Create Account** → choisissez un **sous-domaine gratuit**,
   par exemple : `khanifashion.great-site.net`
3. Notez l'URL de votre site : `https://khanifashion.great-site.net`

## Étape 2 — Récupérer les identifiants FTP (2 min)

Dans le panneau InfinityFree (**FTP Accounts** / **FTP Details**) :

| Information | Exemple |
|---|---|
| **FTP Host** | `ftpupload.net` |
| **FTP User** | `epiz_XXXXXXXX` (affiché dans le panel) |
| **FTP Password** | le mot de passe du compte d'hébergement (pas celui du forum) |
| **Dossier du site** | `htdocs/` |

## Étape 3 — Télécharger et configurer FileZilla (5 min)

1. Téléchargez FileZilla : **https://filezilla-project.org** (gratuit)
2. Connectez-vous : Hôte = `ftpupload.net`, Port = `21`, vos identifiants FTP
3. À droite (site distant) : ouvrez le dossier **`htdocs/`**

## Étape 4 — Tester la compatibilité AVANT tout (2 min) ✅ IMPORTANT

1. Envoyez via FileZilla **un seul fichier** : `verifier-hebergeur.php`
2. Visitez : `https://khanifashion.great-site.net/verifier-hebergeur.php`
3. Vérifiez que tout est **[OK]** (surtout l'extension `pdo_pgsql`)
   - Si `pdo_pgsql` est **[FAIL]** → cet hébergeur ne peut pas joindre Supabase,
     prévenez-moi et on choisira un autre hébergeur

## Étape 5 — Envoyer le site complet (10 min)

Depuis FileZilla, glissez vers `htdocs/` **le contenu du projet** (dossier par dossier) :
`admin/`, `api/`, `assets/`, `css/`, `database/`, `Images/`, `js/`, `.htaccess`,
`index.html`, `boutique.html`, `panier.html`, `commande.html`, `README.md`,
`.env.example`, `.gitignore`

**⚠️ À NE PAS envoyer sur l'hébergeur :**
- `.env` (vous le créerez directement sur l'hébergeur — étape 6)
- le dossier `.git/` (inutile en production)
- `install.php` (réservé à l'installation locale MySQL)
- `verifier-hebergeur.php` (à supprimer à l'étape 8)

## Étape 6 — Créer le fichier `.env` sur l'hébergeur (5 min) 🔐

1. Dans le panneau InfinityFree → **File Manager** (ou via FileZilla) → à la racine `htdocs/`
2. Créez un fichier nommé exactement **`.env`** avec ce contenu :

```
DB_DRIVER=pgsql

PGSQL_HOST=aws-0-eu-central-1.pooler.supabase.com
PGSQL_PORT=5432
PGSQL_DBNAME=postgres
PGSQL_USER=postgres.kvhajluxwvznhudiwfmg
PGSQL_PASSWORD=VOTRE_MOT_DE_PASSE_SUPABASE_ICI
PGSQL_SSLMODE=require

DEBUG_MODE=0
```

- Remplacez `VOTRE_MOT_DE_PASSE_SUPABASE_ICI` par votre vrai mot de passe Supabase
- `DEBUG_MODE=0` : masque les erreurs techniques aux visiteurs (production)
- **Ne mettez PAS** `ADMIN_DEFAULT_PASSWORD` sur l'hébergeur : le compte admin existe
  déjà dans Supabase

## Étape 7 — Test du site en ligne ✅

1. Visitez `https://khanifashion.great-site.net/` → les 18 produits doivent s'afficher
2. Testez une commande, puis le back-office : `/admin/login.html`
3. **Communiquez-moi l'URL** : je lancerai mon analyse complète (43 tests) sur votre site en ligne

## Étape 8 — Nettoyage final de sécurité 🧹

Via FileZilla, supprimez sur l'hébergeur :
- `verifier-hebergeur.php` (déjà servi son rôle)
- `install.php` et `database/migrate_to_supabase.php` si présents
- `README.md` et `DEPLOIEMENT.md` (optionnel, pas nécessaire en ligne)

## Bonus — Domaine personnalisé (optionnel)

Quand vous aurez acheté `khani-fashion.com` (~10-15 €/an) :
1. Sur InfinityFree : **Addon Domains** → ajoutez le domaine
2. Chez le registrar : nameservers d'InfinityFree (affichés dans le panel)
3. Créez le lien court `go.khani-fashion.com` dans Short.io vers votre site

---

## 🔐 Rappel sécurité

| Élément | GitHub | Hébergeur |
|---|---|---|
| Code PHP/HTML/JS | ✅ oui | ✅ oui |
| `.env` (secrets) | ❌ **jamais** | ✅ oui (protégé par `.htaccess`) |
| `install.php` | publié mais bloqué | ❌ ne pas déposer |
