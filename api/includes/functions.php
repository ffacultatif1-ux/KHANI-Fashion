<?php
/**
 * Fonctions utilitaires globales pour l'API KHANI Fashion
 */

/**
 * Envoie une réponse JSON et termine le script
 */
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonSuccess(string $message = 'OK', array $data = [], int $status = 200): void {
    jsonResponse(['success' => true, 'message' => $message] + $data, $status);
}

function jsonError(string $message, int $status = 400, array $extra = []): void {
    jsonResponse(['success' => false, 'message' => $message] + $extra, $status);
}

/**
 * Lit le corps JSON de la requête
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    if (!$input) return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * Vérifie l'authentification admin
 */
function requireAdmin(): array {
    if (!isset($_SESSION['admin_id'])) {
        jsonError('Non authentifié', 401);
    }
    return [
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? '',
        'role' => $_SESSION['admin_role'] ?? 'admin',
    ];
}

/**
 * Nettoie une chaîne
 */
function sanitize(?string $str): string {
    if ($str === null) return '';
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

/**
 * Génère une référence de commande (ex: KHN-20260828-AB12)
 */
function genererReference(): string {
    $date = date('Ymd');
    $rand = strtoupper(bin2hex(random_bytes(2)));
    return "KHN-{$date}-{$rand}";
}

/**
 * Upload d'une image - retourne le nom du fichier ou null
 */
function uploadImage(array $file, string $subdir = 'produits'): ?string {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return null;
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5 Mo max
        return null;
    }

    $ext = $allowed[$mime];
    $filename = uniqid('prod_', true) . '.' . $ext;
    $destDir = __DIR__ . '/../uploads/' . $subdir;
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }
    $dest = $destDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }

    return $filename;
}

/**
 * Supprime un fichier uploadé
 */
function deleteUploadedFile(?string $filename, string $subdir = 'produits'): void {
    if (!$filename) return;
    $path = __DIR__ . '/../uploads/' . $subdir . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Gère les requêtes OPTIONS (CORS preflight)
 */
function handleCors(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        http_response_code(204);
        exit;
    }

    // Support override de méthode via _method (utile pour FormData/PUT)
    // ou via l'en-tête X-HTTP-Method-Override (utilisé par js/admin.js)
    $override = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_method'])) {
        $override = strtoupper($_POST['_method']);
    } elseif (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
        $override = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    }
    if (in_array($override, ['PUT', 'DELETE', 'PATCH'], true)) {
        $_SERVER['REQUEST_METHOD'] = $override;
    }
}
