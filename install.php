<?php
/**
 * Script d'installation KHANI Fashion
 * À exécuter UNE SEULE FOIS : http://localhost/KHANI%20Fashion.com/install.php
 *
 * Crée la base de données, importe le schéma, hash le mot de passe admin,
 * crée un dossier produits/ et copie les images existantes comme produits par défaut.
 */

require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/includes/Database.php';

$messages = [];
$erreurs = [];

try {
    // Installation locale = toujours MySQL (XAMPP), indépendamment du driver actif.
    $localHost = env('MYSQL_HOST', 'localhost');
    $localName = env('MYSQL_DBNAME', 'khani_fashion');
    $localUser = env('MYSQL_USER', 'root');
    $localPass = env('MYSQL_PASSWORD', '');

    // Connexion sans sélectionner la BDD pour créer la base si nécessaire.
    $pdo = new PDO(
        'mysql:host=' . $localHost . ';charset=utf8mb4',
        $localUser,
        $localPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $localName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    // Reconnexion explicitement sur la base créée.
    $pdo = new PDO(
        'mysql:host=' . $localHost . ';dbname=' . $localName . ';charset=utf8mb4',
        $localUser,
        $localPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    if (!$schema) throw new Exception('Fichier database/schema.sql introuvable');

    // Supprime le BOM UTF-8 éventuel au début du fichier SQL,
    // puis exécute les requêtes une par une pour éviter les erreurs MariaDB
    // sur les commentaires et les instructions multi-lignes.
    $schema = preg_replace('/^\xEF\xBB\xBF/', '', $schema);
    $schema = str_replace(["\r\n", "\r"], "\n", $schema);

    $queries = array_filter(array_map('trim', preg_split('/;\s*\n/', $schema)), fn($q) => $q !== '');
    foreach ($queries as $query) {
        $query = trim($query);
        if ($query === '' || preg_match('/^(--|\/\*)/', $query)) continue;
        if (preg_match('/^USE\s+/i', $query)) continue;
        if (preg_match('/^CREATE\s+DATABASE\s+/i', $query)) continue;
        $pdo->exec($query . ';');
    }
    $messages[] = '✅ Schéma de base de données importé';

    // Maintenant on se connecte à la BDD locale (MySQL déjà connectée ci-dessus)
    $db = $pdo;

    // Mot de passe admin initial : lu dans .env (ADMIN_DEFAULT_PASSWORD),
    // sinon généré aléatoirement et affiché une seule fois ci-dessous.
    // Aucun mot de passe n'est jamais stocké en clair dans le code.
    $adminPass = env('ADMIN_DEFAULT_PASSWORD');
    $genere = false;
    if (!$adminPass) {
        $adminPass = bin2hex(random_bytes(8));
        $genere = true;
    }
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO admin (username, password, nom_complet, email, role)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE password = VALUES(password)');
    $stmt->execute(['admin', $hash, 'Administrateur KHANI', 'khanihenoc8@gmail.com', 'superadmin']);
    $messages[] = $genere
        ? "✅ Compte admin créé — mot de passe généré (affiché une seule fois, notez-le) : {$adminPass}"
        : '✅ Compte admin créé/mis à jour (mot de passe défini dans .env)';

    // Vérifier si la table produits est vide
    $count = (int)$db->query('SELECT COUNT(*) FROM produits')->fetchColumn();

    if ($count === 0) {
        // Importer les images existantes comme produits par défaut
        $imagesDir = __DIR__ . '/Images';
        $uploadDir = __DIR__ . '/api/uploads/produits';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $prix = [
            'Pagne' => 15000,
            'Robe' => 30000,
            'Ensemble' => 25000,
        ];

        $files = glob($imagesDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG}', GLOB_BRACE);
        $catStmt = $db->query('SELECT id, nom FROM categories');
        $categories = [];
        foreach ($catStmt->fetchAll() as $c) $categories[$c['nom']] = (int)$c['id'];

        $stmt = $db->prepare('INSERT INTO produits (nom, description, prix, image, categorie_id, stock) VALUES (?,?,?,?,?,?)');
        $imported = 0;
        foreach ($files as $file) {
            $basename = basename($file);
            $newName = 'init_' . uniqid() . '.' . strtolower(pathinfo($file, PATHINFO_EXTENSION));
            copy($file, $uploadDir . '/' . $newName);

            $nom = pathinfo($file, PATHINFO_FILENAME);
            $nomClean = preg_replace('/\s+/', ' ', $nom);
            $catId = null;
            foreach ($prix as $key => $p) {
                if (stripos($nom, $key) !== false) {
                    foreach ($categories as $cnom => $cid) {
                        if (stripos($cnom, substr($key, 0, -1)) !== false || stripos($key, $cnom) !== false) {
                            $catId = $cid;
                            break;
                        }
                    }
                    break;
                }
            }
            // fallback catégorie par défaut
            if (!$catId && !empty($categories)) $catId = reset($categories);

            $prixProduit = 15000;
            if (stripos($nom, 'Robe') !== false) $prixProduit = 30000;
            elseif (stripos($nom, 'Ensemble') !== false) $prixProduit = 25000;
            elseif (stripos($nom, 'Pagne') !== false) $prixProduit = 15000;

            $stmt->execute([
                ucwords(strtolower($nomClean)),
                'Magnifique article de la collection KHANI Fashion.',
                $prixProduit,
                $newName,
                $catId,
                10,
            ]);
            $imported++;
        }
        $messages[] = "✅ $imported produits importés depuis le dossier Images/";
    } else {
        $messages[] = "ℹ️ La table produits contient déjà $count entrées (import ignoré)";
    }

    // Créer un fichier .htaccess pour protéger les uploads
    $htaccess = __DIR__ . '/api/uploads/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\.(php|phtml|phar)$\">\nDeny from all\n</FilesMatch>\n");
        $messages[] = '✅ Fichier .htaccess créé dans uploads/';
    }

} catch (Exception $e) {
    $erreurs[] = '❌ ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Installation KHANI Fashion</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', system-ui, sans-serif;
            background: linear-gradient(135deg, #E91E63, #6A1B9A);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            margin: 0; padding: 20px;
        }
        .box {
            background: white; padding: 40px; border-radius: 20px;
            max-width: 700px; width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
        }
        h1 { color: #6A1B9A; margin-bottom: 30px; }
        .ok { background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 10px; margin: 8px 0; }
        .err { background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 10px; margin: 8px 0; }
        .btn { display: inline-block; margin-top: 20px; padding: 14px 28px;
               background: #E91E63; color: white; text-decoration: none;
               border-radius: 30px; font-weight: bold; margin-right: 10px; }
        .btn:hover { background: #6A1B9A; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="box">
        <h1><i class="fa-solid fa-crown"></i> Installation KHANI Fashion</h1>
        <?php foreach ($messages as $m) echo "<div class='ok'>$m</div>"; ?>
        <?php foreach ($erreurs as $e) echo "<div class='err'>$e</div>"; ?>
        <?php if (empty($erreurs)): ?>
            <p style="margin-top:20px">L'installation est terminée. Vous pouvez maintenant :</p>
            <a href="index.html" class="btn"><i class="fa-solid fa-house"></i> Voir le site</a>
            <a href="admin/login.html" class="btn"><i class="fa-solid fa-lock"></i> Admin</a>
            <p style="margin-top:20px; color:#666; font-size:14px">
                <strong>Identifiant admin :</strong> <code>admin</code><br>
                <strong>Mot de passe :</strong> celui défini dans <code>.env</code>
                (ADMIN_DEFAULT_PASSWORD) ou généré lors de l'installation — voir message ci-dessus.<br>
                <strong>Pensez à supprimer</strong> <code>install.php</code> après installation.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
