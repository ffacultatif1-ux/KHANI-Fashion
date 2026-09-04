<?php
/**
 * Chargeur minimal de fichier .env
 *
 * Lit le fichier .env situé à la racine du projet et expose
 * les variables via $_ENV / getenv(). Ce fichier ne contient
 * AUCUNE donnée sensible : les secrets restent dans .env,
 * qui ne doit jamais être publié (voir .gitignore).
 */

function loadEnv(string $path): void
{
    static $loaded = false;
    if ($loaded || !is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $i => $line) {
        // Retire le BOM UTF-8 éventuel sur la première ligne
        if ($i === 0) {
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
        }
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        // Retire les guillemets englobants éventuels
        $len = strlen($value);
        if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    $loaded = true;
}

/**
 * Récupère une variable d'environnement avec valeur par défaut.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}
