<?php
/**
 * Vérificateur de compatibilité hébergeur — KHANI Fashion
 * À déposer à la racine du site chez l'hébergeur puis à visiter :
 *   https://votre-site.example/verifier-hebergeur.php
 *
 * 1. Vérifie PHP + extensions (pdo_pgsql requis pour Supabase)
 * 2. Vérifie la connexion sortante vers Supabase (aucun secret requis)
 * 3. Si le .env est présent et rempli : teste la connexion réelle + compte les tables
 * AUCUN secret n'est affiché. SUPPRIMEZ CE FICHIER après vérification !
 */

header('Content-Type: text/plain; charset=utf-8');
echo "==== VERIFICATION HEBERGEUR - KHANI Fashion ====\n\n";

// --- 1. PHP et extensions ---
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
echo ($phpOk ? '[OK]  ' : '[FAIL]') . " PHP " . PHP_VERSION . ($phpOk ? '' : " (8.0+ requis)") . "\n";

foreach (['pdo' => 'PDO (obligatoire)', 'pdo_pgsql' => 'PostgreSQL (obligatoire pour Supabase)', 'pdo_mysql' => 'MySQL (local seulement)', 'curl' => 'HTTP', 'mbstring' => 'Chaines multi-octets', 'openssl' => 'SSL/TLS'] as $ext => $role) {
    echo (extension_loaded($ext) ? '[OK]  ' : '[FAIL]') . " Extension $ext — $role\n";
}

$drivers = PDO::getAvailableDrivers();
echo '[INFO] Drivers PDO disponibles : ' . implode(', ', $drivers) . "\n";
$pgOk = in_array('pgsql', $drivers, true);
echo ($pgOk ? '[OK]  ' : '[FAIL]') . " Driver pgsql " . ($pgOk ? 'disponible — Supabase possible' : 'ABSENT — cet hebergeur NE PEUT PAS se connecter a Supabase') . "\n\n";

if (!$pgOk) {
    echo "==== CONCLUSION : hebergeur incompatible (pdo_pgsql manquant). Essayez un autre hebergeur. ====\n";
    exit;
}

// --- 2. Connexion sortante vers Supabase (TCP, sans secret) ---
require_once __DIR__ . '/api/includes/env.php';
loadEnv(__DIR__ . '/.env');
$host = env('PGSQL_HOST') ?: 'aws-0-eu-central-1.pooler.supabase.com';
$port = (int)env('PGSQL_PORT', '5432');
echo "Test connexion sortante vers le pooler Supabase ($host:$port)...\n";
$conn = @fsockopen($host, $port, $errno, $errstr, 10);
if ($conn) {
    fclose($conn);
    echo "[OK]  Connexion sortante autorisee\n\n";
} else {
    echo "[FAIL] Connexion sortante BLOQUEE (erreur $errno : $errstr)\n";
    echo "==== CONCLUSION : cet hebergeur bloque les connexions sortantes vers Supabase. ====\n";
    exit;
}

// --- 3. Connexion reelle si .env rempli ---
$user = env('PGSQL_USER');
$pass = env('PGSQL_PASSWORD');
if (!$user || $pass === null || str_contains($pass, 'TODO')) {
    echo "[INFO] .env absent ou incomplet : creez-le sur l'hebergeur (copie de .env.example, valeurs PGSQL_* remplies)\n";
    echo "==== HEBERGEUR COMPATIBLE : vous pouvez deployer le site. ====\n";
    exit;
}

try {
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', env('PGSQL_HOST'), $port, env('PGSQL_DBNAME', 'postgres'), env('PGSQL_SSLMODE', 'require')),
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 20]
    );
    $v = $pdo->query('SELECT version()')->fetchColumn();
    echo "[OK]  Connexion Supabase reussie : " . substr($v, 0, 60) . "...\n";
    foreach (['admin','categories','produits','commandes','parametres','visiteurs'] as $t) {
        $n = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "       - $t : $n ligne(s)\n";
    }
    echo "\n==== TOUT EST PRET : le site peut fonctionner sur cet hebergeur. ====\n";
} catch (PDOException $e) {
    echo "[FAIL] Connexion Supabase : " . $e->getMessage() . "\n";
    echo "Verifiez les valeurs PGSQL_* du .env sur l'hebergeur.\n";
}
