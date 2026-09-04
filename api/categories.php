<?php
/**
 * Endpoint : Catégories
 * GET    /api/categories.php           : liste publique
 * POST   /api/categories.php           : créer (admin)
 * DELETE /api/categories.php?id=X      : supprimer (admin)
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
            $stmt = $db->query('SELECT * FROM categories ORDER BY nom');
            jsonSuccess('', ['categories' => $stmt->fetchAll()]);
            break;

        case 'POST':
            requireAdmin();
            $data = $_POST ?: getJsonInput();
            $nom = sanitize($data['nom'] ?? '');
            $description = sanitize($data['description'] ?? '');
            if (!$nom) jsonError('Nom requis', 400);

            $image = null;
            if (!empty($_FILES['image'])) {
                $image = uploadImage($_FILES['image'], 'categories');
            }

            $id = Database::getInstance()->insertAndGetId(
                'INSERT INTO categories (nom, image, description) VALUES (?,?,?)',
                [$nom, $image, $description]
            );
            jsonSuccess('Catégorie ajoutée', ['id' => $id], 201);
            break;

        case 'DELETE':
            requireAdmin();
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) jsonError('ID requis', 400);
            $stmt = $db->prepare('SELECT image FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $cat = $stmt->fetch();
            if ($cat) {
                $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                deleteUploadedFile($cat['image'], 'categories');
            }
            jsonSuccess('Catégorie supprimée');
            break;

        default:
            jsonError('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    if (DEBUG_MODE) jsonError('Erreur : ' . $e->getMessage(), 500);
    jsonError('Erreur serveur', 500);
}
