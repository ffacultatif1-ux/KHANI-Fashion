<?php
/**
 * Endpoint : Statistiques
 * GET /api/stats.php : retourne les stats du tableau de bord (admin)
 * POST /api/stats.php : incrémente le compteur de visiteurs (public)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/functions.php';

handleCors();

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        requireAdmin();
        $ca = (float)$db->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE statut != 'annulee'")->fetchColumn();
        $nbCmd = (int)$db->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
        $nbProd = (int)$db->query("SELECT COUNT(*) FROM produits WHERE actif = 1")->fetchColumn();
        $nbVis = (int)$db->query("SELECT COUNT(*) FROM visiteurs")->fetchColumn();

        // Top produit
        $top = $db->query("SELECT produits_json, COUNT(*) as c FROM commandes GROUP BY produits_json ORDER BY c DESC LIMIT 1")->fetch();
        $topName = '-';
        if ($top && $top['produits_json']) {
            $prods = json_decode($top['produits_json'], true);
            if (!empty($prods[0]['nom'])) $topName = $prods[0]['nom'];
        }

        // 7 derniers jours (syntaxe compatible MySQL et PostgreSQL)
        $sinceSql = $db->getDriver() === 'pgsql'
            ? "created_at >= NOW() - INTERVAL '7 days'"
            : 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        $ventes7j = $db->query("SELECT DATE(created_at) as jour, COALESCE(SUM(total),0) as total FROM commandes WHERE {$sinceSql} GROUP BY DATE(created_at) ORDER BY jour")->fetchAll();

        // Répartition par statut
        $repartition = $db->query("SELECT statut, COUNT(*) as nb FROM commandes GROUP BY statut")->fetchAll();

        jsonSuccess('', [
            'stats' => [
                'chiffre_affaires' => $ca,
                'nb_commandes'    => $nbCmd,
                'nb_produits'     => $nbProd,
                'nb_visiteurs'    => $nbVis,
                'top_produit'     => $topName,
                'ventes_7j'       => $ventes7j,
                'repartition'     => $repartition,
            ],
        ]);
    }
    elseif ($method === 'POST') {
        $page = sanitize($_GET['page'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $db->prepare('INSERT INTO visiteurs (ip, page, user_agent) VALUES (?,?,?)');
        $stmt->execute([$ip, $page, $ua]);
        jsonSuccess('Visite enregistrée');
    }
    else {
        jsonError('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    if (DEBUG_MODE) jsonError('Erreur : ' . $e->getMessage(), 500);
    jsonError('Erreur serveur', 500);
}
