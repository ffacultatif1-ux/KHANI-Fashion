<?php
/**
 * Endpoint : Commandes
 * GET    /api/commandes.php           : toutes les commandes (admin)
 * GET    /api/commandes.php?id=X      : une commande (admin)
 * GET    /api/commandes.php?statut=X : filtrer par statut (admin)
 * POST   /api/commandes.php           : créer une commande (public)
 * PUT    /api/commandes.php?id=X      : changer statut (admin)
 * DELETE /api/commandes.php?id=X      : supprimer (admin)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/functions.php';

handleCors();

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            requireAdmin();
            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $stmt = $db->prepare('SELECT * FROM commandes WHERE id = ?');
                $stmt->execute([$id]);
                $commande = $stmt->fetch();
                if (!$commande) jsonError('Commande introuvable', 404);
                if ($commande['produits_json']) {
                    $commande['produits'] = json_decode($commande['produits_json'], true);
                }
                jsonSuccess('', ['commande' => $commande]);
            }
            $sql = 'SELECT * FROM commandes';
            $params = [];
            if (!empty($_GET['statut'])) {
                $sql .= ' WHERE statut = ?';
                $params[] = $_GET['statut'];
            }
            $sql .= ' ORDER BY created_at DESC';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $commandes = $stmt->fetchAll();
            foreach ($commandes as &$c) {
                if ($c['produits_json']) {
                    $c['produits'] = json_decode($c['produits_json'], true);
                }
            }
            jsonSuccess('', ['commandes' => $commandes]);
            break;

        case 'POST':
            $input = getJsonInput();
            $nom       = sanitize($input['client']['nom'] ?? ($input['nom'] ?? ''));
            $tel       = sanitize($input['client']['telephone'] ?? ($input['telephone'] ?? ''));
            $email     = sanitize($input['client']['email'] ?? ($input['email'] ?? ''));
            $adresse   = sanitize($input['client']['adresse'] ?? ($input['adresse'] ?? ''));
            $ville     = sanitize($input['client']['ville'] ?? ($input['ville'] ?? ''));
            $paiement  = sanitize($input['paiement'] ?? 'livraison');
            $produits  = $input['produits'] ?? [];
            $total     = (float)($input['total'] ?? 0);
            $notes     = sanitize($input['notes'] ?? '');

            if (!$nom || !$tel || empty($produits) || $total <= 0) {
                jsonError('Données de commande incomplètes', 400);
            }

            $validPaiements = ['livraison', 'mobile_money', 'carte'];
            if (!in_array($paiement, $validPaiements)) $paiement = 'livraison';

            $ref = genererReference();
            $commandeId = Database::getInstance()->insertAndGetId(
                'INSERT INTO commandes (reference, client_nom, client_telephone, client_email, client_adresse, ville, mode_paiement, produits_json, total, notes) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [
                    $ref, $nom, $tel, $email, $adresse, $ville, $paiement,
                    json_encode($produits, JSON_UNESCAPED_UNICODE),
                    $total, $notes
                ]
            );

            // Générer lien WhatsApp
            $settings = getParametres();
            $wa = preg_replace('/\D/', '', $settings['whatsapp'] ?? '242061763204');

            $msg = "Bonjour KHANI Fashion 👑\n\n";
            $msg .= "Je souhaite commander :\n\n";
            foreach ($produits as $p) {
                $qte = $p['quantite'] ?? 1;
                $msg .= "- {$p['nom']} x {$qte} = " . number_format(((float)$p['prix'])*$qte, 0, ',', ' ') . " FCFA\n";
            }
            $msg .= "\nTotal : " . number_format($total, 0, ',', ' ') . " FCFA";
            $msg .= "\n\nClient : {$nom}\nTél : {$tel}";
            if ($adresse) $msg .= "\nAdresse : {$adresse}";
            $msg .= "\n\nRéf : {$ref}";

            jsonSuccess('Commande enregistrée', [
                'commande' => [
                    'id' => $commandeId,
                    'reference' => $ref,
                ],
                'whatsapp_url' => "https://wa.me/{$wa}?text=" . rawurlencode($msg),
            ], 201);
            break;

        case 'PUT':
            requireAdmin();
            // Champs depuis $_POST (FormData + X-HTTP-Method-Override) sinon corps de la requête
            $put = $_POST;
            if (!$put) {
                parse_str(file_get_contents('php://input'), $put);
            }
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonError('ID requis', 400);
            $statut = sanitize($put['statut'] ?? '');
            $valid = ['en_attente', 'confirmee', 'en_livraison', 'livree', 'annulee'];
            if (!in_array($statut, $valid)) jsonError('Statut invalide', 400);
            $db->prepare('UPDATE commandes SET statut = ? WHERE id = ?')->execute([$statut, $id]);
            jsonSuccess('Statut mis à jour');
            break;

        case 'DELETE':
            requireAdmin();
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonError('ID requis', 400);
            $db->prepare('DELETE FROM commandes WHERE id = ?')->execute([$id]);
            jsonSuccess('Commande supprimée');
            break;

        default:
            jsonError('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    if (DEBUG_MODE) jsonError('Erreur : ' . $e->getMessage(), 500);
    jsonError('Erreur serveur', 500);
}

function getParametres(): array {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query('SELECT cle, valeur FROM parametres');
    $out = [];
    foreach ($stmt->fetchAll() as $row) $out[$row['cle']] = $row['valeur'];
    return $out;
}
