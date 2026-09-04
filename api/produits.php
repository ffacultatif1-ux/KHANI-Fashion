<?php
/**
 * Endpoint : Produits
 * GET    /api/produits.php            : liste publique (actifs seulement)
 * GET    /api/produits.php?id=X       : un produit
 * GET    /api/produits.php?all=1      : tous (admin)
 * POST   /api/produits.php            : créer (admin + multipart pour image)
 * PUT    /api/produits.php?id=X       : modifier (admin)
 * DELETE /api/produits.php?id=X       : supprimer (admin)
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
            $isAdmin = isset($_SESSION['admin_id']);
            $all = isset($_GET['all']) && $isAdmin;

            if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $sql = 'SELECT p.*, c.nom AS categorie_nom FROM produits p
                        LEFT JOIN categories c ON c.id = p.categorie_id
                        WHERE p.id = ?' . ($all ? '' : ' AND p.actif = 1');
                $stmt = $db->prepare($sql);
                $stmt->execute([$id]);
                $produit = $stmt->fetch();
                if (!$produit) jsonError('Produit introuvable', 404);
                jsonSuccess('', ['produit' => $produit]);
            }

            $sql = 'SELECT p.*, c.nom AS categorie_nom FROM produits p
                    LEFT JOIN categories c ON c.id = p.categorie_id'
                . ($all ? '' : ' WHERE p.actif = 1')
                . ' ORDER BY p.created_at DESC';
            $stmt = $db->query($sql);
            jsonSuccess('', ['produits' => $stmt->fetchAll()]);
            break;

        case 'POST':
            requireAdmin();
            $data = $_POST;

            $nom         = sanitize($data['nom'] ?? '');
            $description = sanitize($data['description'] ?? '');
            $prix        = (float)($data['prix'] ?? 0);
            $stock       = (int)($data['stock'] ?? 0);
            $categorieId = !empty($data['categorie_id']) ? (int)$data['categorie_id'] : null;

            if (!$nom || $prix <= 0) {
                jsonError('Nom et prix requis', 400);
            }

            $image = null;
            if (!empty($_FILES['image'])) {
                $image = uploadImage($_FILES['image'], 'produits');
            }

            $id = Database::getInstance()->insertAndGetId(
                'INSERT INTO produits (nom, description, prix, image, categorie_id, stock) VALUES (?,?,?,?,?,?)',
                [$nom, $description, $prix, $image, $categorieId, $stock]
            );
            jsonSuccess('Produit ajouté', ['id' => $id], 201);
            break;

        case 'PUT':
            requireAdmin();
            // Champs depuis $_POST (FormData multipart) sinon corps de la requête
            $put = $_POST;
            if (!$put) {
                parse_str(file_get_contents('php://input'), $put);
            }
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonError('ID requis', 400);

            $stmt = $db->prepare('SELECT image FROM produits WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch();
            if (!$old) jsonError('Produit introuvable', 404);

            $nom         = sanitize($put['nom'] ?? '');
            $description = sanitize($put['description'] ?? '');
            $prix        = (float)($put['prix'] ?? 0);
            $stock       = (int)($put['stock'] ?? 0);
            $categorieId = !empty($put['categorie_id']) ? (int)$put['categorie_id'] : null;
            // Case à cocher "actif" : absente = décochée (0), 'on'/'1'/'true' = active (1)
            $actif       = isset($put['actif']) ? (in_array(strtolower((string)$put['actif']), ['1', 'on', 'true'], true) ? 1 : 0) : 1;

            $image = $old['image'];
            if (!empty($_FILES['image'])) {
                $newImage = uploadImage($_FILES['image'], 'produits');
                if ($newImage) {
                    deleteUploadedFile($old['image'], 'produits');
                    $image = $newImage;
                }
            }

            $stmt = $db->prepare('UPDATE produits SET nom=?, description=?, prix=?, image=?, categorie_id=?, stock=?, actif=? WHERE id=?');
            $stmt->execute([$nom, $description, $prix, $image, $categorieId, $stock, $actif, $id]);
            jsonSuccess('Produit modifié');
            break;

        case 'DELETE':
            requireAdmin();
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonError('ID requis', 400);

            $stmt = $db->prepare('SELECT image FROM produits WHERE id = ?');
            $stmt->execute([$id]);
            $prod = $stmt->fetch();
            if (!$prod) jsonError('Produit introuvable', 404);

            $db->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);
            deleteUploadedFile($prod['image'], 'produits');
            jsonSuccess('Produit supprimé');
            break;

        default:
            jsonError('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    if (DEBUG_MODE) jsonError('Erreur : ' . $e->getMessage(), 500);
    jsonError('Erreur serveur', 500);
}
