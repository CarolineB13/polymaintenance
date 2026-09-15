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

$annoncesFile = '/home/polymay/private/annonces.json';
$uploadDir = '/home/polymay/www/uploads/annonces';

$error = '';

/* -------------------------------------------------------
   Fonctions slug
------------------------------------------------------- */

function slugify(string $text): string
{
    $text = trim($text);

    if (function_exists('iconv')) {
        $converted = @iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $text
        );

        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');

    return $text !== '' ? $text : 'annonce';
}

function uniqueSlug(
    string $title,
    array $annonces,
    string $currentId
): string {
    $base = slugify($title);
    $slug = $base;
    $suffix = 2;

    $existing = [];

    foreach ($annonces as $annonce) {

        if (($annonce['id'] ?? '') === $currentId) {
            continue;
        }

        $existingSlug = $annonce['slug'] ?? '';

        if ($existingSlug === '') {
            $existingSlug = slugify(
                (string) ($annonce['titre'] ?? '')
            );
        }

        if ($existingSlug !== '') {
            $existing[] = $existingSlug;
        }
    }

    while (in_array($slug, $existing, true)) {
        $slug = $base . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

/* -------------------------------------------------------
   Identification annonce
------------------------------------------------------- */

$id = $_GET['id'] ?? $_POST['id'] ?? '';

if (strlen($id) !== 16 || !ctype_xdigit($id)) {
    http_response_code(400);
    exit('Annonce invalide.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!is_file($annoncesFile)) {
    http_response_code(500);
    exit('Fichier des annonces introuvable.');
}

$contenu = file_get_contents($annoncesFile);
$annonces = json_decode($contenu, true);

if (!is_array($annonces)) {
    $annonces = [];
}

$indexAnnonce = null;

foreach ($annonces as $index => $item) {
    if (($item['id'] ?? '') === $id) {
        $indexAnnonce = $index;
        break;
    }
}

if ($indexAnnonce === null) {
    http_response_code(404);
    exit('Annonce introuvable.');
}

$annonce = $annonces[$indexAnnonce];

/* -------------------------------------------------------
   Valeurs autorisées
------------------------------------------------------- */

$categoriesAutorisees = [
    'air-comprime',
    'manutention',
    'bois',
    'ferronnerie',
    'soudage',
    'emballage',
    'autre',
];

$conditionsAutorisees = [
    'neuf',
    'occasion',
    'reconditionne',
];

$priceTypesAutorises = [
    'fixed',
    'on-request',
];

$statusAutorises = [
    'available',
    'reserved',
    'sold',
];

$publicationsAutorisees = [
    'brouillon',
    'publiee',
];

/* -------------------------------------------------------
   Valeurs actuelles
------------------------------------------------------- */

$titre = $annonce['titre'] ?? '';
$description = $annonce['description'] ?? '';
$categorie = $annonce['categorie'] ?? 'autre';
$condition = $annonce['condition'] ?? 'occasion';
$priceType = $annonce['priceType'] ?? 'on-request';
$prix = $annonce['prix'] ?? '';
$status = $annonce['status'] ?? 'available';
$marque = $annonce['marque'] ?? '';
$modele = $annonce['modele'] ?? '';
$annee = $annonce['annee'] ?? '';
$details = $annonce['details'] ?? '';
$publication = $annonce['publication'] ?? 'brouillon';

/* -------------------------------------------------------
   Traitement
------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie = $_POST['categorie'] ?? 'autre';
    $condition = $_POST['condition'] ?? 'occasion';
    $priceType = $_POST['priceType'] ?? 'on-request';
    $prix = trim($_POST['prix'] ?? '');
    $status = $_POST['status'] ?? 'available';
    $marque = trim($_POST['marque'] ?? '');
    $modele = trim($_POST['modele'] ?? '');
    $annee = trim($_POST['annee'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $publication = $_POST['publication'] ?? 'brouillon';

    $supprimerPhoto =
        isset($_POST['supprimer_photo']) &&
        $_POST['supprimer_photo'] === '1';

    $nouvellePhotoEnvoyee =
        isset($_FILES['photo']) &&
        $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($titre === '') {

        $error = 'Le titre est obligatoire.';

    } elseif ($description === '') {

        $error = 'La description courte est obligatoire.';

    } elseif (!in_array($categorie, $categoriesAutorisees, true)) {

        $error = 'Catégorie invalide.';

    } elseif (!in_array($condition, $conditionsAutorisees, true)) {

        $error = 'État invalide.';

    } elseif (!in_array($priceType, $priceTypesAutorises, true)) {

        $error = 'Type de prix invalide.';

    } elseif (
        $priceType === 'fixed' &&
        (
            $prix === '' ||
            !is_numeric($prix) ||
            (float) $prix < 0
        )
    ) {

        $error = 'Veuillez indiquer un prix valide.';

    } elseif (!in_array($status, $statusAutorises, true)) {

        $error = 'Disponibilité invalide.';

    } elseif (
        $annee !== '' &&
        (
            !ctype_digit($annee) ||
            (int) $annee < 1900 ||
            (int) $annee > ((int) date('Y') + 1)
        )
    ) {

        $error = 'Année invalide.';

    } elseif (!in_array($publication, $publicationsAutorisees, true)) {

        $error = 'Statut de publication invalide.';

    } elseif ($supprimerPhoto && $nouvellePhotoEnvoyee) {

        $error =
            'Choisissez soit une nouvelle photo, soit la suppression de la photo actuelle.';
    }

    /*
     * Le slug est immuable :
     * - s'il existe déjà, on le conserve
     * - s'il manque (ancienne annonce), on le crée
     */
    $slug = trim((string) ($annonce['slug'] ?? ''));

    if ($slug === '') {
        $slug = uniqueSlug(
            $titre,
            $annonces,
            $id
        );
    }

    /* ---------------------------------------------------
       Photo
    --------------------------------------------------- */

    $anciennePhoto = $annonce['photo'] ?? null;
    $nouvellePhoto = $anciennePhoto;

    $tempPath = null;
    $finalPath = $uploadDir . '/' . $id . '.webp';
    $backupPath = null;

    if ($error === '' && $supprimerPhoto) {
        $nouvellePhoto = null;
    }

    if ($error === '' && $nouvellePhotoEnvoyee) {

        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {

            $error =
                'Une erreur est survenue lors de l’envoi de la photo.';

        } elseif ($_FILES['photo']['size'] > 8 * 1024 * 1024) {

            $error = 'La photo ne doit pas dépasser 8 Mo.';

        } else {

            $imageInfo = @getimagesize(
                $_FILES['photo']['tmp_name']
            );

            $allowedTypes = [
                IMAGETYPE_JPEG,
                IMAGETYPE_PNG,
                IMAGETYPE_WEBP,
            ];

            if (
                !$imageInfo ||
                !in_array($imageInfo[2], $allowedTypes, true)
            ) {

                $error = 'Format de photo non autorisé.';

            } else {

                $imageData = @file_get_contents(
                    $_FILES['photo']['tmp_name']
                );

                $source = $imageData !== false
                    ? @imagecreatefromstring($imageData)
                    : false;

                if (!$source) {

                    $error = 'Impossible de lire cette photo.';

                } else {

                    $width = imagesx($source);
                    $height = imagesy($source);

                    $maxSize = 1400;

                    $ratio = min(
                        1,
                        $maxSize / $width,
                        $maxSize / $height
                    );

                    $newWidth = max(
                        1,
                        (int) round($width * $ratio)
                    );

                    $newHeight = max(
                        1,
                        (int) round($height * $ratio)
                    );

                    $destination = imagecreatetruecolor(
                        $newWidth,
                        $newHeight
                    );

                    imagealphablending($destination, false);
                    imagesavealpha($destination, true);

                    imagecopyresampled(
                        $destination,
                        $source,
                        0,
                        0,
                        0,
                        0,
                        $newWidth,
                        $newHeight,
                        $width,
                        $height
                    );

                    if (
                        !is_dir($uploadDir) &&
                        !mkdir($uploadDir, 0755, true)
                    ) {

                        $error =
                            'Impossible de créer le dossier des photos.';

                    } else {

                        $tempPath =
                            $uploadDir .
                            '/.' .
                            $id .
                            '-' .
                            bin2hex(random_bytes(4)) .
                            '.webp.tmp';

                        if (
                            !imagewebp(
                                $destination,
                                $tempPath,
                                82
                            )
                        ) {

                            $error =
                                'Impossible d’enregistrer la nouvelle photo.';

                        } else {

                            $nouvellePhoto =
                                '/uploads/annonces/' .
                                $id .
                                '.webp';
                        }
                    }

                    imagedestroy($source);
                    imagedestroy($destination);
                }
            }
        }
    }

    /* ---------------------------------------------------
       Préparation JSON
    --------------------------------------------------- */

    if ($error === '') {

        $annonces[$indexAnnonce]['slug'] = $slug;
        $annonces[$indexAnnonce]['titre'] = $titre;
        $annonces[$indexAnnonce]['description'] = $description;
        $annonces[$indexAnnonce]['categorie'] = $categorie;
        $annonces[$indexAnnonce]['condition'] = $condition;
        $annonces[$indexAnnonce]['priceType'] = $priceType;

        $annonces[$indexAnnonce]['prix'] =
            $priceType === 'fixed'
                ? (float) $prix
                : null;

        $annonces[$indexAnnonce]['status'] = $status;

        $annonces[$indexAnnonce]['marque'] =
            $marque !== ''
                ? $marque
                : null;

        $annonces[$indexAnnonce]['modele'] =
            $modele !== ''
                ? $modele
                : null;

        $annonces[$indexAnnonce]['annee'] =
            $annee !== ''
                ? (int) $annee
                : null;

        $annonces[$indexAnnonce]['photo'] =
            $nouvellePhoto;

        $annonces[$indexAnnonce]['details'] =
            $details;

        $annonces[$indexAnnonce]['publication'] =
            $publication;

        $annonces[$indexAnnonce]['updated_at'] =
            date('c');

        $json = json_encode(
            $annonces,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            $error =
                'Impossible de préparer les modifications.';
        }
    }

    /* ---------------------------------------------------
       Remplacement physique photo
    --------------------------------------------------- */

    if (
        $error === '' &&
        $tempPath !== null
    ) {

        if (is_file($finalPath)) {

            $backupPath =
                $uploadDir .
                '/.' .
                $id .
                '-backup-' .
                bin2hex(random_bytes(4)) .
                '.webp';

            if (!@rename($finalPath, $backupPath)) {
                $error =
                    'Impossible de préparer le remplacement de la photo.';
            }
        }

        if (
            $error === '' &&
            !@rename($tempPath, $finalPath)
        ) {

            if (
                $backupPath !== null &&
                is_file($backupPath)
            ) {
                @rename(
                    $backupPath,
                    $finalPath
                );
            }

            $error =
                'Impossible de remplacer la photo.';
        }
    }

    /* ---------------------------------------------------
       Enregistrement JSON
    --------------------------------------------------- */

    if ($error === '') {

        if (
            file_put_contents(
                $annoncesFile,
                $json,
                LOCK_EX
            ) === false
        ) {

            if ($tempPath !== null) {

                if (is_file($finalPath)) {
                    @unlink($finalPath);
                }

                if (
                    $backupPath !== null &&
                    is_file($backupPath)
                ) {
                    @rename(
                        $backupPath,
                        $finalPath
                    );
                }
            }

            $error =
                'Impossible d’enregistrer les modifications.';

        } else {

            if (
                $backupPath !== null &&
                is_file($backupPath)
            ) {
                @unlink($backupPath);
            }

            if (
                $supprimerPhoto &&
                $anciennePhoto !== null
            ) {

                $filename =
                    basename($anciennePhoto);

                if (
                    preg_match(
                        '/^[a-f0-9]{16}\.webp$/',
                        $filename
                    )
                ) {

                    $photoPath =
                        $uploadDir .
                        '/' .
                        $filename;

                    if (is_file($photoPath)) {
                        @unlink($photoPath);
                    }
                }
            }

            header('Location: ./?updated=1');
            exit;
        }
    }

    if (
        $error !== '' &&
        $tempPath !== null &&
        is_file($tempPath)
    ) {
        @unlink($tempPath);
    }
}

?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modifier une annonce - Polymaintenance</title>
    <link rel="stylesheet" href="./admin.css">
</head>

<body>

<div class="admin-shell">
    <header class="admin-header">
        <div class="admin-header__inner">
            <a class="admin-brand" href="./" aria-label="Accueil de la gestion Polymaintenance">
                <img src="./logo-polymaintenance.png" alt="Polymaintenance">
            </a>

            <div class="admin-header__actions">
                <a
                    class="admin-header__link"
                    href="/"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Accéder au site
                </a>

                <form method="post" action="./">
                    <input type="hidden" name="action" value="logout">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                    >

                    <button class="admin-logout" type="submit">
                        Se déconnecter
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="admin-main admin-main--narrow">
        <a class="admin-backlink" href="./">← Retour aux annonces</a>

        <div class="admin-pagehead">
            <div>
                <h1>Modifier l’annonce</h1>

                <p>
                    Les modifications d’une annonce publiée sont visibles
                    immédiatement sur le site.
                </p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="admin-alert admin-alert--error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <section class="admin-card">
            <form
                method="post"
                enctype="multipart/form-data"
                class="admin-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="id"
                    value="<?= htmlspecialchars($id) ?>"
                >

                <section class="admin-form__section">
                    <h2>Informations principales</h2>

                    <div class="admin-grid">
                        <div class="admin-field admin-field--full">
                            <label for="titre">Titre *</label>

                            <input
                                id="titre"
                                type="text"
                                name="titre"
                                required
                                maxlength="120"
                                value="<?= htmlspecialchars($titre) ?>"
                            >
                        </div>

                        <div class="admin-field admin-field--full">
                            <label for="description">Description courte *</label>

                            <textarea
                                id="description"
                                name="description"
                                rows="3"
                                required
                                maxlength="350"
                            ><?= htmlspecialchars($description) ?></textarea>
                        </div>

                        <div class="admin-field">
                            <label for="categorie">Catégorie *</label>

                            <select id="categorie" name="categorie">
                                <option value="air-comprime" <?= $categorie === 'air-comprime' ? 'selected' : '' ?>>Air comprimé</option>
                                <option value="manutention" <?= $categorie === 'manutention' ? 'selected' : '' ?>>Manutention</option>
                                <option value="bois" <?= $categorie === 'bois' ? 'selected' : '' ?>>Machines à bois</option>
                                <option value="ferronnerie" <?= $categorie === 'ferronnerie' ? 'selected' : '' ?>>Ferronnerie</option>
                                <option value="soudage" <?= $categorie === 'soudage' ? 'selected' : '' ?>>Soudage</option>
                                <option value="emballage" <?= $categorie === 'emballage' ? 'selected' : '' ?>>Emballage</option>
                                <option value="autre" <?= $categorie === 'autre' ? 'selected' : '' ?>>Autre matériel</option>
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="condition">État *</label>

                            <select id="condition" name="condition">
                                <option value="neuf" <?= $condition === 'neuf' ? 'selected' : '' ?>>Neuf</option>
                                <option value="occasion" <?= $condition === 'occasion' ? 'selected' : '' ?>>Occasion</option>
                                <option value="reconditionne" <?= $condition === 'reconditionne' ? 'selected' : '' ?>>Reconditionné</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="admin-form__section">
                    <h2>Prix et disponibilité</h2>

                    <div class="admin-grid">
                        <div class="admin-field">
                            <label for="priceType">Affichage du prix *</label>

                            <select id="priceType" name="priceType">
                                <option value="on-request" <?= $priceType === 'on-request' ? 'selected' : '' ?>>Prix sur demande</option>
                                <option value="fixed" <?= $priceType === 'fixed' ? 'selected' : '' ?>>Afficher un prix</option>
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="prix">Montant en €</label>

                            <input
                                id="prix"
                                type="number"
                                name="prix"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars((string) $prix) ?>"
                            >
                        </div>

                        <div class="admin-field">
                            <label for="status">Disponibilité *</label>

                            <select id="status" name="status">
                                <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Disponible</option>
                                <option value="reserved" <?= $status === 'reserved' ? 'selected' : '' ?>>Réservé</option>
                                <option value="sold" <?= $status === 'sold' ? 'selected' : '' ?>>Vendu</option>
                            </select>
                        </div>

                        <div class="admin-field">
                            <label for="publication">Publication *</label>

                            <select id="publication" name="publication">
                                <option value="brouillon" <?= $publication === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                                <option value="publiee" <?= $publication === 'publiee' ? 'selected' : '' ?>>Publiée</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="admin-form__section">
                    <h2>Informations complémentaires</h2>

                    <p class="admin-form__hint">
                        Une information vide n’apparaîtra pas sur la fiche publique.
                    </p>

                    <div class="admin-grid">
                        <div class="admin-field">
                            <label for="marque">Marque</label>

                            <input
                                id="marque"
                                type="text"
                                name="marque"
                                maxlength="100"
                                value="<?= htmlspecialchars((string) $marque) ?>"
                            >
                        </div>

                        <div class="admin-field">
                            <label for="modele">Modèle</label>

                            <input
                                id="modele"
                                type="text"
                                name="modele"
                                maxlength="100"
                                value="<?= htmlspecialchars((string) $modele) ?>"
                            >
                        </div>

                        <div class="admin-field">
                            <label for="annee">Année</label>

                            <input
                                id="annee"
                                type="number"
                                name="annee"
                                min="1900"
                                max="<?= (int) date('Y') + 1 ?>"
                                value="<?= htmlspecialchars((string) $annee) ?>"
                            >
                        </div>

                        <div class="admin-field admin-field--full">
                            <label for="details">Description détaillée</label>

                            <textarea
                                id="details"
                                name="details"
                                rows="8"
                                maxlength="5000"
                            ><?= htmlspecialchars($details) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="admin-form__section">
                    <h2>Photo</h2>

                    <?php if (!empty($annonce['photo'])): ?>
                        <div class="admin-photo">
                            <img
                                src="<?= htmlspecialchars($annonce['photo']) ?>?v=<?= urlencode($annonce['updated_at'] ?? '') ?>"
                                alt="<?= htmlspecialchars($titre) ?>"
                            >

                            <label class="admin-checkbox">
                                <input
                                    type="checkbox"
                                    name="supprimer_photo"
                                    value="1"
                                >

                                <span>Supprimer la photo actuelle</span>
                            </label>
                        </div>
                    <?php else: ?>
                        <p class="admin-form__hint">
                            Aucune photo actuellement. « Photo à venir » est affiché sur le site.
                        </p>
                    <?php endif; ?>

                    <div class="admin-field">
                        <label for="photo">
                            <?= !empty($annonce['photo'])
                                ? 'Remplacer la photo'
                                : 'Ajouter une photo'
                            ?>
                        </label>

                        <input
                            id="photo"
                            type="file"
                            name="photo"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <small>
                            JPG, PNG ou WebP — 8 Mo maximum. La photo sera automatiquement optimisée.
                        </small>
                    </div>
                </section>

                <div class="admin-form__footer">
                    <a class="admin-button admin-button--ghost" href="./">
                        Annuler
                    </a>

                    <button class="admin-button admin-button--green" type="submit">
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </section>
    </main>
</div>

</body>
</html>
