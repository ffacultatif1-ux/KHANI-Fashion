<?php
/**
 * Endpoint : Paramètres de la boutique
 * GET   /api/parametres.php : récupérer tous les paramètres (public)
 * POST  /api/parametres.php : mettre à jour (admin)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/functions.php';

handleCors();

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $db->query('SELECT cle, valeur FROM parametres');
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[$row['cle']] = $row['valeur'];
        }
        jsonSuccess('', ['parametres' => $out]);
    }
    elseif ($method === 'POST') {
        requireAdmin();
        $data = $_POST ?: getJsonInput();
        $allowed = ['nom_boutique','email','telephone1','telephone2','adresse','whatsapp','devise'];
        // Upsert compatible MySQL et PostgreSQL
        if (Database::getInstance()->getDriver() === 'pgsql') {
            $stmt = $db->prepare('INSERT INTO parametres (cle, valeur) VALUES (?,?)
                ON CONFLICT (cle) DO UPDATE SET valeur = EXCLUDED.valeur, updated_at = CURRENT_TIMESTAMP');
        } else {
            $stmt = $db->prepare('INSERT INTO parametres (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)');
        }
        foreach ($allowed as $cle) {
            if (isset($data[$cle])) {
                $stmt->execute([$cle, sanitize($data[$cle])]);
            }
        }
        jsonSuccess('Paramètres enregistrés');
    }
    else {
        jsonError('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    if (DEBUG_MODE) jsonError('Erreur : ' . $e->getMessage(), 500);
    jsonError('Erreur serveur', 500);
}
