<?php
/**
 * KHANI Fashion - Configuration de la base de données
 *
 * Les identifiants sont lus dans le fichier ".env" à la racine du projet.
 * Ne place JAMAIS de mot de passe directement dans ce fichier.
 *
 * - DB_DRIVER=pgsql → PostgreSQL (Supabase)
 * - DB_DRIVER=mysql → MySQL local (XAMPP)
 *
 * Modèle fourni : .env.example  (copiez-le en ".env" et remplissez vos valeurs)
 */

require_once __DIR__ . '/../includes/env.php';
loadEnv(__DIR__ . '/../../.env');

define('DB_DRIVER', strtolower(env('DB_DRIVER', 'mysql')));

if (DB_DRIVER === 'pgsql') {
    // ----- PostgreSQL / Supabase -----
    define('DB_HOST',     env('PGSQL_HOST', 'localhost'));
    define('DB_PORT',     env('PGSQL_PORT', '5432'));
    define('DB_NAME',     env('PGSQL_DBNAME', 'postgres'));
    define('DB_USER',     env('PGSQL_USER', ''));
    define('DB_PASS',     env('PGSQL_PASSWORD', ''));
    define('DB_SSLMODE',  env('PGSQL_SSLMODE', 'require'));
    define('DB_CHARSET',  'UTF8');
} else {
    // ----- MySQL local (XAMPP) -----
    define('DB_HOST',     env('MYSQL_HOST', 'localhost'));
    define('DB_PORT',     env('MYSQL_PORT', '3306'));
    define('DB_NAME',     env('MYSQL_DBNAME', 'khani_fashion'));
    define('DB_USER',     env('MYSQL_USER', 'root'));
    define('DB_PASS',     env('MYSQL_PASSWORD', ''));
    define('DB_SSLMODE',  '');
    define('DB_CHARSET',  'utf8mb4');
}

// Chemin de base du site (auto-détection)
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\') . '/');

// Fuseau horaire
date_default_timezone_set('Africa/Brazzaville');

// Affichage des erreurs (DEBUG_MODE=0 dans .env en production)
define('DEBUG_MODE', env('DEBUG_MODE', '1') === '1');
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

