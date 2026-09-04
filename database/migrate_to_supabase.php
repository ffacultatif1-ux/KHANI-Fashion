<?php
/**
 * ============================================================
 * KHANI Fashion - Migration MySQL local → Supabase (PostgreSQL)
 * ============================================================
 *
 * Usage (ligne de commande) :
 *   C:\xampp\php\php.exe database\migrate_to_supabase.php
 *
 * Prérequis :
 *   - MySQL local démarré (XAMPP) avec la base khani_fashion
 *   - Un fichier .env à la racine contenant les identifiants
 *     Supabase (PGSQL_HOST, PGSQL_USER, PGSQL_PASSWORD, ...)
 *
 * Garanties :
 *   - La base MySQL locale n'est JAMAIS modifiée (lecture seule)
 *   - Le schéma PostgreSQL est créé s'il est absent (idempotent)
 *   - Les données sont copiées en conservant les identifiants
 *   - Ré-exécutable sans créer de doublons (ON CONFLICT DO NOTHING)
 *   - Aucun secret n'apparaît dans ce fichier : tout vient de .env
 */

require_once __DIR__ . '/../api/includes/env.php';
loadEnv(__DIR__ . '/../.env');

echo "============================================================\n";
echo "  KHANI Fashion - Migration MySQL local -> Supabase\n";
echo "============================================================\n\n";

// ------------------------------------------------------------------
// 1. Connexion MySQL locale (LECTURE SEULE)
// ------------------------------------------------------------------
$myHost = env('MYSQL_HOST', '127.0.0.1');
$myPort = env('MYSQL_PORT', '3306');
$myName = env('MYSQL_DBNAME', 'khani_fashion');
$myUser = env('MYSQL_USER', 'root');
$myPass = env('MYSQL_PASSWORD', '');

try {
    $mysql = new PDO(
        "mysql:host={$myHost};port={$myPort};dbname={$myName};charset=utf8mb4",
        $myUser,
        $myPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
    );
    echo "[OK] Connexion MySQL local ({$myName}) — lecture seule\n";
} catch (PDOException $e) {
    fwrite(STDERR, "[ECHEC] MySQL local : " . $e->getMessage() . "\n");
    fwrite(STDERR, "Verifiez que MySQL est demarre dans XAMPP et que la section MYSQL_* du .env est correcte.\n");
    exit(1);
}

// ------------------------------------------------------------------
// 2. Connexion Supabase (PostgreSQL)
// ------------------------------------------------------------------
$pgHost = env('PGSQL_HOST');
$pgPort = env('PGSQL_PORT', '5432');
$pgName = env('PGSQL_DBNAME', 'postgres');
$pgUser = env('PGSQL_USER');
$pgPass = env('PGSQL_PASSWORD');
$pgSsl  = env('PGSQL_SSLMODE', 'require');

if (!$pgHost || !$pgUser || $pgPass === null) {
    fwrite(STDERR, "[ECHEC] Identifiants Supabase manquants.\n");
    fwrite(STDERR, "Copiez .env.example en .env puis remplissez les champs PGSQL_HOST, PGSQL_USER, PGSQL_PASSWORD.\n");
    exit(1);
}

try {
    $pg = new PDO(
        "pgsql:host={$pgHost};port={$pgPort};dbname={$pgName};sslmode={$pgSsl}",
        $pgUser,
        $pgPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 20,
        ]
    );
    echo "[OK] Connexion Supabase (PostgreSQL) — host masque\n";
} catch (PDOException $e) {
    fwrite(STDERR, "[ECHEC] Supabase : " . $e->getMessage() . "\n");
    fwrite(STDERR, "Verifiez les valeurs PGSQL_* dans le fichier .env (Project Settings > Database > Session pooler).\n");
    exit(1);
}

// ------------------------------------------------------------------
// 3. Création du schéma PostgreSQL (idempotent)
// ------------------------------------------------------------------
$schemaFile = __DIR__ . '/schema_postgres.sql';
$schema = file_get_contents($schemaFile);
if (!$schema) {
    fwrite(STDERR, "[ECHEC] Fichier schema_postgres.sql introuvable.\n");
    exit(1);
}
$schema = preg_replace('/^\xEF\xBB\xBF/', '', $schema);

foreach (splitSqlStatements($schema) as $statement) {
    // Retire les lignes de commentaires pour ne garder que le SQL réel
    $sqlLines = array_filter(
        explode("\n", $statement),
        fn($l) => trim($l) !== '' && !preg_match('/^\s*--/', $l)
    );
    $sql = trim(implode("\n", $sqlLines));
    if ($sql === '') {
        continue;
    }
    $pg->exec($sql);
}
echo "[OK] Schema PostgreSQL cree ou deja present\n\n";

// ------------------------------------------------------------------
// 4. Copie des données (ordre respectant les clés étrangères)
// ------------------------------------------------------------------
$tables = ['categories', 'produits', 'admin', 'parametres', 'visiteurs', 'commandes'];
$rapport = [];

foreach ($tables as $table) {
    $rows = $mysql->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    $copied = 0;

    if (!empty($rows)) {
        // Liste des colonnes (celles réellement présentes côté MySQL)
        $columns = array_keys($rows[0]);
        $colList = implode(', ', array_map(fn($c) => "\"{$c}\"", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $stmt = $pg->prepare(
            "INSERT INTO {$table} ({$colList}) VALUES ({$placeholders}) ON CONFLICT DO NOTHING"
        );

        $pg->beginTransaction();
        foreach ($rows as $row) {
            $values = array_map(
                fn($v) => is_resource($v) ? stream_get_contents($v) : $v,
                array_values($row)
            );
            $stmt->execute($values);
            $copied += $stmt->rowCount();
        }
        $pg->commit();
    }

    // Réaligne les séquences d'identifiants sur le max existant
    if (in_array($table, ['admin', 'categories', 'produits', 'commandes', 'visiteurs'], true)) {
        $pg->exec(
            "SELECT setval(pg_get_serial_sequence('{$table}', 'id'),
                    COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)"
        );
    }

    $rapport[$table] = ['mysql' => count($rows), 'copies' => $copied];
    echo sprintf("[OK] %-12s : %3d ligne(s) lues, %3d copiee(s) vers Supabase\n", $table, count($rows), $copied);
}

// ------------------------------------------------------------------
// 5. Vérification : comptage dans Supabase
// ------------------------------------------------------------------
echo "\n-------------------- VERIFICATION SUPABASE --------------------\n";
$toutOk = true;
foreach ($tables as $table) {
    $nbPg = (int)$pg->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    $attendu = $rapport[$table]['mysql'];
    $ok = ($nbPg === $attendu);
    if (!$ok) $toutOk = false;
    echo sprintf(
        "%s %-12s : %3d ligne(s) dans Supabase (attendu : %3d)\n",
        $ok ? '[OK]  ' : '[ALERTE]',
        $table,
        $nbPg,
        $attendu
    );
}

// Test de lecture d'un produit réel
$test = $pg->query("SELECT id, nom, prix FROM produits ORDER BY id LIMIT 1")->fetch();
if ($test) {
    echo sprintf("\n[OK] Lecture test produit #%d : %s (%s FCFA)\n", $test['id'], $test['nom'], $test['prix']);
}

// Test d'intégrité des clés étrangères
$orphelins = (int)$pg->query(
    "SELECT COUNT(*) FROM produits p LEFT JOIN categories c ON c.id = p.categorie_id
     WHERE p.categorie_id IS NOT NULL AND c.id IS NULL"
)->fetchColumn();
echo $orphelins === 0
    ? "[OK] Integrite des relations produits -> categories : valide\n"
    : "[ALERTE] {$orphelins} produit(s) avec categorie orpheline\n";

echo "\n============================================================\n";
echo $toutOk
    ? "  MIGRATION TERMINEE AVEC SUCCES — toutes les donnees sont dans Supabase\n"
    : "  MIGRATION TERMINEE AVEC DES ALERTES — verifiez les lignes [ALERTE]\n";
echo "  La base MySQL locale n'a PAS ete modifiee.\n";
echo "============================================================\n";
exit($toutOk ? 0 : 2);

// ------------------------------------------------------------------
// Découpe SQL respectant les blocs $$ ... $$ (fonctions PL/pgSQL)
// ------------------------------------------------------------------
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer     = '';
    $inDollar   = false;
    $inString   = false;
    $length     = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $pair = substr($sql, $i, 2);

        if ($pair === '$$') {
            $inDollar = !$inDollar;
            $buffer .= $pair;
            $i++;
            continue;
        }
        if (!$inDollar && $char === "'") {
            $inString = !$inString;
        }
        if (!$inDollar && !$inString && $char === ';') {
            $statements[] = $buffer;
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }
    return $statements;
}

