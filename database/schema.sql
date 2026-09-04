-- ====================================================================
-- KHANI Fashion - Schéma de base de données MySQL
-- À importer via phpMyAdmin ou en ligne de commande MySQL
-- ====================================================================

CREATE DATABASE IF NOT EXISTS khani_fashion
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE khani_fashion;

-- --------------------------------------------------------------------
-- Table : admin
-- Stocke les comptes administrateurs (mot de passe hashé bcrypt)
-- Le compte admin est créé par install.php (mot de passe défini dans .env
-- via ADMIN_DEFAULT_PASSWORD, ou généré aléatoirement).
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom_complet VARCHAR(100),
    email VARCHAR(150),
    role ENUM('superadmin','admin') DEFAULT 'admin',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Compte admin : créé par install.php avec un mot de passe défini
-- dans .env (ADMIN_DEFAULT_PASSWORD) ou généré aléatoirement.
-- Aucun identifiant n'est stocké en clair dans ce fichier.

-- --------------------------------------------------------------------
-- Table : categories
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    UNIQUE KEY uk_nom (nom),
    image VARCHAR(255),
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categories (nom, description) VALUES
    ('Robes', 'Robes africaines élégantes'),
    ('Pagnes', 'Pagnes wax de qualité'),
    ('Ensembles', 'Ensembles modernes'),
    ('Accessoires', 'Accessoires de mode')
ON DUPLICATE KEY UPDATE nom = nom;

-- --------------------------------------------------------------------
-- Table : produits
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    prix DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    categorie_id INT,
    stock INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_categorie (categorie_id),
    INDEX idx_actif (actif)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- Table : commandes
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(20) NOT NULL UNIQUE,
    client_nom VARCHAR(150) NOT NULL,
    client_telephone VARCHAR(30) NOT NULL,
    client_email VARCHAR(150),
    client_adresse TEXT,
    ville VARCHAR(100),
    mode_paiement ENUM('livraison','mobile_money','carte') DEFAULT 'livraison',
    produits_json JSON NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    statut ENUM('en_attente','confirmee','en_livraison','livree','annulee') DEFAULT 'en_attente',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_statut (statut),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- Table : parametres
-- Stockage clé/valeur pour les infos boutique
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS parametres (
    cle VARCHAR(50) PRIMARY KEY,
    valeur TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO parametres (cle, valeur) VALUES
    ('nom_boutique', 'KHANI Fashion'),
    ('email', 'khanihenoc8@gmail.com'),
    ('telephone1', '+242 06 176 32 04'),
    ('telephone2', '+242 06 526 92 13'),
    ('adresse', 'Brazzaville, Congo'),
    ('whatsapp', '242061763204'),
    ('devise', 'FCFA')
ON DUPLICATE KEY UPDATE valeur = VALUES(valeur);

-- --------------------------------------------------------------------
-- Table : visiteurs (compteur)
-- --------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visiteurs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45),
    page VARCHAR(100),
    user_agent TEXT,
    visited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (visited_at)
) ENGINE=InnoDB;
