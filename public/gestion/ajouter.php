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
    array $annonces
): string {
    $base = slugify($title);
    $slug = $base;
    $suffix = 2;

    $existing = [];

    foreach ($annonces as $annonce) {
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
   Valeurs par défaut
------------------------------------------------------- */

$titre = '';
$description = '';
$categorie = 'autre';
$condition = 'occasion';
$priceType = 'on-request';
$prix = '';
$status = 'available';
$marque = '';
$modele = '';
$annee = '';
$details = '';
$publication = 'brouillon';

/* -------------------------------------------------------
   Protection CSRF
------------------------------------------------------- */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* -------------------------------------------------------
   Traitement du formulaire
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
    }

    if ($error === '') {

        $contenu = is_file($annoncesFile)
            ? file_get_contents($annoncesFile)
            : '[]';

        $annonces = json_decode($contenu, true);

        if (!is_array($annonces)) {
            $annonces = [];
        }

        $id = bin2hex(random_bytes(8));
        $slug = uniqueSlug($titre, $annonces);

        $photo = null;
        $targetPath = null;

        /* ------------------------------------------------
           Photo facultative
        ------------------------------------------------ */

        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {

                $error = 'Une erreur est survenue lors de l’envoi de la photo.';

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

                            $error = 'Impossible de créer le dossier des photos.';

                        } else {

                            $filename = $id . '.webp';
                            $targetPath = $uploadDir . '/' . $filename;

                            if (
                                !imagewebp(
                                    $destination,
                                    $targetPath,
                                    82
                                )
                            ) {

                                $error = 'Impossible d’enregistrer la photo.';

                            } else {

                                $photo =
                                    '/uploads/annonces/' . $filename;
                            }
                        }

                        imagedestroy($source);
                        imagedestroy($destination);
                    }
                }
            }
        }

        /* ------------------------------------------------
           Enregistrement
        ------------------------------------------------ */

        if ($error === '') {

            $annonce = [
                'id' => $id,
                'slug' => $slug,
                'titre' => $titre,
                'description' => $description,
                'categorie' => $categorie,
                'condition' => $condition,
                'priceType' => $priceType,
                'prix' => $priceType === 'fixed'
                    ? (float) $prix
                    : null,
                'status' => $status,
                'marque' => $marque !== ''
                    ? $marque
                    : null,
                'modele' => $modele !== ''
                    ? $modele
                    : null,
                'annee' => $annee !== ''
                    ? (int) $annee
                    : null,
                'photo' => $photo,
                'details' => $details,
                'publication' => $publication,
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];

            $annonces[] = $annonce;

            $json = json_encode(
                $annonces,
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

                if (
                    $targetPath !== null &&
                    is_file($targetPath)
                ) {
                    @unlink($targetPath);
                }

                $error = 'Impossible d’enregistrer l’annonce.';

            } else {

                header('Location: ./?created=1');
                exit;
            }
        }
    }
}

?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ajouter une annonce - Polymaintenance</title>
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
                <h1>Ajouter une annonce</h1>

                <p>
                    Les champs marqués d’un astérisque sont obligatoires.
                    L’annonce peut être enregistrée en brouillon avant publication.
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

                <section class="admin-form__section">
                    <h2>Informations principales</h2>

                    <p class="admin-form__hint">
                        Ce sont les informations visibles dès la liste des annonces.
                    </p>

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

                            <small>
                                Quelques lignes pour présenter rapidement le matériel.
                            </small>
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
                                value="<?= htmlspecialchars($prix) ?>"
                            >

                            <small>
                                Utilisé uniquement si « Afficher un prix » est sélectionné.
                            </small>
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
                        Elles sont facultatives. Une information vide ne sera pas affichée sur la fiche.
                    </p>

                    <div class="admin-grid">
                        <div class="admin-field">
                            <label for="marque">Marque</label>

                            <input
                                id="marque"
                                type="text"
                                name="marque"
                                maxlength="100"
                                value="<?= htmlspecialchars($marque) ?>"
                            >
                        </div>

                        <div class="admin-field">
                            <label for="modele">Modèle</label>

                            <input
                                id="modele"
                                type="text"
                                name="modele"
                                maxlength="100"
                                value="<?= htmlspecialchars($modele) ?>"
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
                                value="<?= htmlspecialchars($annee) ?>"
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

                            <small>
                                Caractéristiques, équipements, travaux effectués, informations utiles, etc.
                            </small>
                        </div>
                    </div>
                </section>

                <section class="admin-form__section">
                    <h2>Photo</h2>

                    <div class="admin-field">
                        <label for="photo">Ajouter une photo</label>

                        <input
                            id="photo"
                            type="file"
                            name="photo"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <small>
                            JPG, PNG ou WebP — 8 Mo maximum. La photo sera automatiquement convertie et optimisée.
                            Si aucune photo n’est ajoutée, « Photo à venir » sera affiché.
                        </small>
                    </div>
                </section>

                <div class="admin-form__footer">
                    <a class="admin-button admin-button--ghost" href="./">
                        Annuler
                    </a>

                    <button class="admin-button admin-button--green" type="submit">
                        Enregistrer l’annonce
                    </button>
                </div>
            </form>
        </section>
    </main>
</div>

</body>
</html>
