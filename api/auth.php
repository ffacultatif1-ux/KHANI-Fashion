<?php
/**
 * Endpoint : Authentification administrateur
 * Méthodes : POST (login), DELETE (logout), GET (check)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/functions.php';

handleCors();

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST': // Login
            $input = getJsonInput();
            $username = sanitize($input['username'] ?? '');
            $password = $input['password'] ?? '';

            if (!$username || !$password) {
                jsonError('Identifiant et mot de passe requis', 400);
            }

            $stmt = $db->prepare('SELECT id, username, password, nom_complet, role FROM admin WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($password, $admin['password'])) {
                jsonError('Identifiant ou mot de passe incorrect', 401);
            }

            $_SESSION['admin_id']       = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_nom']      = $admin['nom_complet'];
            $_SESSION['admin_role']     = $admin['role'];

            jsonSuccess('Connexion réussie', [
                'admin' => [
                    'id'       => (int)$admin['id'],
                    'username' => $admin['username'],
                    'nom'      => $admin['nom_complet'],
                    'role'     => $admin['role'],
                ],
            ]);
            break;

        case 'DELETE': // Logout
            $_SESSION = [];
            session_destroy();
            jsonSuccess('Déconnecté');
            break;

        case 'GET': // Vérifier la session
            if (!isset($_SESSION['admin_id'])) {
                jsonError('Non authentifié', 401);
            }
            jsonSuccess('Authentifié', [
                'admin' => [
                    'id'       => $_SESSION['admin_id'],
                    'username' => $_SESSION['admin_username'],
                    'nom'      => $_SESSION['admin_nom'] ?? '',
                    'role'     => $_SESSION['admin_role'] ?? 'admin',
                ],
            ]);
            break;

        default:
            jsonError('Méthode non autorisée', 405);
    }
} catch (Exception $e) {
    if (DEBUG_MODE) jsonError('Erreur serveur : ' . $e->getMessage(), 500);
    jsonError('Erreur serveur', 500);
}
