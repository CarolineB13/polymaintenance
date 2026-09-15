<?php

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$annoncesFile = '/home/polymay/private/annonces.json';

/* -------------------------------------------------------
   Lecture du fichier privé
------------------------------------------------------- */

if (!is_file($annoncesFile)) {
    echo json_encode([]);
    exit;
}

$contenu = file_get_contents($annoncesFile);

if ($contenu === false) {
    http_response_code(500);

    echo json_encode([
        'error' => 'Impossible de lire les annonces.'
    ]);

    exit;
}

$annonces = json_decode($contenu, true);

if (!is_array($annonces)) {
    http_response_code(500);

    echo json_encode([
        'error' => 'Les données des annonces sont invalides.'
    ]);

    exit;
}

/* -------------------------------------------------------
   On ne conserve que les annonces publiées
------------------------------------------------------- */

$annoncesPubliques = [];

foreach ($annonces as $annonce) {

    if (($annonce['publication'] ?? '') !== 'publiee') {
        continue;
    }

    /*
     * Une annonce sans slug n'est pas exposée comme fiche détail.
     * Les anciennes annonces recevront leur slug lors de leur
     * prochaine modification dans l'espace de gestion.
     */
    $slug = trim((string) ($annonce['slug'] ?? ''));

    $annoncesPubliques[] = [

        'id' => $annonce['id'] ?? '',

        'slug' => $slug,

        'titre' => $annonce['titre'] ?? '',

        'description' =>
            $annonce['description'] ?? '',

        'categorie' =>
            $annonce['categorie'] ?? 'autre',

        'condition' =>
            $annonce['condition'] ?? 'occasion',

        'priceType' =>
            $annonce['priceType'] ?? 'on-request',

        'prix' =>
            $annonce['prix'] ?? null,

        'status' =>
            $annonce['status'] ?? 'available',

        'marque' =>
            $annonce['marque'] ?? null,

        'modele' =>
            $annonce['modele'] ?? null,

        'annee' =>
            $annonce['annee'] ?? null,

        'photo' =>
            $annonce['photo'] ?? null,

        'details' =>
            $annonce['details'] ?? '',

        'created_at' =>
            $annonce['created_at'] ?? '',

        'updated_at' =>
            $annonce['updated_at'] ?? '',
    ];
}

/* -------------------------------------------------------
   Tri :
   1. disponible
   2. réservé
   3. vendu
   puis les plus récents
------------------------------------------------------- */

$statusOrder = [
    'available' => 0,
    'reserved' => 1,
    'sold' => 2,
];

usort(
    $annoncesPubliques,
    function ($a, $b) use ($statusOrder) {

        $statusA =
            $statusOrder[$a['status']] ?? 99;

        $statusB =
            $statusOrder[$b['status']] ?? 99;

        if ($statusA !== $statusB) {
            return $statusA <=> $statusB;
        }

        $dateA =
            strtotime($a['created_at'] ?? '') ?: 0;

        $dateB =
            strtotime($b['created_at'] ?? '') ?: 0;

        return $dateB <=> $dateA;
    }
);

/* -------------------------------------------------------
   Réponse JSON
------------------------------------------------------- */

$json = json_encode(
    $annoncesPubliques,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    http_response_code(500);

    echo json_encode([
        'error' => 'Impossible de générer les annonces.'
    ]);

    exit;
}

echo $json;
