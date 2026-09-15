<?php

header('X-Robots-Tag: noindex, nofollow', true);

ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();

if (empty($_SESSION['gestion_connected'])) {
    header('Location: ./');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Requête invalide.');
}

$id = $_POST['id'] ?? '';

if (strlen($id) !== 16 || !ctype_xdigit($id)) {
    http_response_code(400);
    exit('Annonce invalide.');
}

$annoncesFile = '/home/polymay/private/annonces.json';
$uploadDir = '/home/polymay/www/uploads/annonces';

if (!is_file($annoncesFile)) {
    http_response_code(404);
    exit('Fichier des annonces introuvable.');
}

$contenu = file_get_contents($annoncesFile);
$annonces = json_decode($contenu, true);

if (!is_array($annonces)) {
    http_response_code(500);
    exit('Données invalides.');
}

$annonceASupprimer = null;
$nouvellesAnnonces = [];

foreach ($annonces as $annonce) {
    if (($annonce['id'] ?? '') === $id) {
        $annonceASupprimer = $annonce;
        continue;
    }

    $nouvellesAnnonces[] = $annonce;
}

if ($annonceASupprimer === null) {
    http_response_code(404);
    exit('Annonce introuvable.');
}

$json = json_encode(
    $nouvellesAnnonces,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

if (
    $json === false ||
    file_put_contents(
        $annoncesFile,
        $json,
        LOCK_EX
    ) === false
) {
    http_response_code(500);
    exit('Impossible de supprimer l’annonce.');
}

/* Suppression de la photo uniquement après enregistrement du JSON. */
$photo = $annonceASupprimer['photo'] ?? null;

if ($photo) {
    $filename = basename((string) $photo);

    if (
        preg_match(
            '/^[a-f0-9]{16}\.webp$/',
            $filename
        )
    ) {
        $photoPath = $uploadDir . '/' . $filename;

        if (is_file($photoPath)) {
            @unlink($photoPath);
        }
    }
}

header('Location: ./?deleted=1');
exit;
