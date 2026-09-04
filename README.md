# 👑 KHANI Fashion - Site e-commerce

Site e-commerce moderne pour la vente de pagnes africains, robes, ensembles et accessoires de mode féminine.
Construit en **HTML/CSS/JavaScript** côté frontend, **PHP/MySQL** côté backend.

---

## 🚀 Installation

### Pré-requis
- **XAMPP** (ou WAMP / MAMP / LAMP) — https://www.apachefriends.org/
- PHP 7.4+ avec extension PDO MySQL
- MySQL 5.7+ ou MariaDB 10+

### Étapes

1. **Copier le dossier** dans `htdocs/` (XAMPP) :
   ```
   C:\xampp\htdocs\KHANI Fashion.com\
   ```

2. **Démarrer** Apache et MySQL dans le panneau XAMPP

3. **Lancer l'installation** automatique :
   ```
   http://localhost/KHANI%20Fashion.com/install.php
   ```
   Ce script crée la base de données, importe les tables, hash le mot de passe admin
   et importe vos images existantes comme produits.

4. **Supprimer `install.php`** après installation (sécurité)

5. **Accéder au site** :
   - Boutique : `http://localhost/KHANI%20Fashion.com/`
   - Admin : `http://localhost/KHANI%20Fashion.com/admin/login.html`

### Identifiants admin
- **Utilisateur** : `admin`
- **Mot de passe** : defini dans le fichier `.env` (`ADMIN_DEFAULT_PASSWORD`) lors de l'installation, ou genere aleatoirement et affiche une seule fois par `install.php` — jamais stocke en clair dans le code (`.env` n'est pas publie).

⚠️ **Changez le mot de passe** après la première connexion (page Parametres du back-office).

---

## 📁 Structure

```
KHANI Fashion.com/
├── index.html              # Page d'accueil
├── boutique.html           # Catalogue produits
├── panier.html             # Panier
├── commande.html           # Finalisation commande
│
├── admin/                  # Back-office
│   ├── login.html
│   ├── dashboard.html
│   ├── produits.html       # CRUD produits + upload image
│   ├── categories.html
│   ├── commandes.html
│   ├── statistiques.html
│   └── parametres.html
│
├── api/                    # Backend PHP/MySQL
│   ├── auth.php            # Login / logout / check session
│   ├── produits.php        # CRUD produits
│   ├── categories.php      # CRUD catégories
│   ├── commandes.php       # Commandes + génération lien WhatsApp
│   ├── parametres.php      # Infos boutique
│   ├── stats.php           # Statistiques + compteur visiteurs
│   ├── config/
│   │   └── database.php    # Configuration BDD
│   ├── includes/
│   │   ├── Database.php    # Singleton PDO
│   │   └── functions.php   # Utilitaires
│   └── uploads/            # Images uploadées
│       └── produits/
│
├── css/
│   ├── style.css           # Frontend
│   └── admin.css           # Backend
│
├── js/
│   ├── script.js           # Frontend (panier, produits, API)
│   └── admin.js            # Backend (CRUD, upload, auth)
│
├── Images/                 # Assets d'origine
│
├── database/
│   └── schema.sql          # Schéma BDD
│
├── install.php             # Script d'installation (à supprimer après usage)
├── .htaccess               # Configuration Apache
└── README.md
```

---

## 🛠️ Configuration production

### 1. Hébergement
- Compatible avec tout hébergeur supportant PHP 7.4+ et MySQL
- Pas besoin de Node.js ni de bundler

### 2. Paramètres BDD
Les identifiants sont lus dans le fichier `.env` à la racine (copiez `.env.example` puis remplissez vos valeurs).
**Le fichier `.env` ne doit jamais être publié** (il est listé dans `.gitignore`). En production, passez `DEBUG_MODE=0` dans `.env`.

### 3. Sécurité
- Changer le mot de passe admin par défaut
- Passer `DEBUG_MODE` à `false`
- Vérifier que `install.php` est supprimé
- Utiliser HTTPS
- Limiter l'accès au dossier `api/config/` via `.htaccess`

---

## 📞 Contact

- Téléphone : +242 06 176 32 04 / +242 06 526 92 13
- Email : khanihenoc8@gmail.com

---

**Version 2.0** - Architecture PHP/MySQL avec upload d'images et back-office complet.
