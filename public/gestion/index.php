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

$userFile = '/home/polymay/private/gestion-user.json';
$annoncesFile = '/home/polymay/private/annonces.json';

$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* -------------------------------------------------------
   Déconnexion
------------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'logout'
) {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: ./');
    exit;
}

/* -------------------------------------------------------
   Connexion
------------------------------------------------------- */

if (
    empty($_SESSION['gestion_connected']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'login'
) {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!is_file($userFile)) {
        $error = 'Le compte de gestion n’est pas configuré.';
    } else {
        $userData = json_decode(
            file_get_contents($userFile),
            true
        );

        $storedEmail = strtolower(
            trim((string) ($userData['email'] ?? ''))
        );

        $storedHash =
            (string) ($userData['password_hash'] ?? '');

        $emailOk =
            $storedEmail !== '' &&
            hash_equals($storedEmail, $email);

        $passwordOk =
            $storedHash !== '' &&
            password_verify($password, $storedHash);

        if ($emailOk && $passwordOk) {
            session_regenerate_id(true);

            $_SESSION['gestion_connected'] = true;
            $_SESSION['gestion_email'] = $storedEmail;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: ./');
            exit;
        }

        $error = 'Adresse e-mail ou mot de passe incorrect.';
    }
}

/* -------------------------------------------------------
   Écran de connexion
------------------------------------------------------- */

if (empty($_SESSION['gestion_connected'])):
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion Polymaintenance</title>
    <link rel="stylesheet" href="./admin.css?v=20260915-3">
</head>

<body class="login-page">

<main class="login-card">
    <a class="login-card__logo" href="/" aria-label="Accéder au site Polymaintenance">
        <img src="./logo-polymaintenance.png" alt="Polymaintenance" width="250" height="64">
    </a>

    <h1>Espace de gestion</h1>

    <p class="login-card__intro">
        Connectez-vous pour gérer le matériel à vendre sur le site
        Polymaintenance.
    </p>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert--error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="login-form">
        <input type="hidden" name="action" value="login">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
        >

        <div class="admin-field">
            <label for="email">Adresse e-mail</label>

            <input
                id="email"
                type="email"
                name="email"
                autocomplete="username"
                required
            >
        </div>

        <div class="admin-field">
            <label for="password">Mot de passe</label>

<div class="password-field">
            <input
                id="password"
                type="password"
                name="password"
                autocomplete="current-password"
                required
            >
                <button
                    class="password-toggle"
                    type="button"
                    data-password-toggle
                    aria-controls="password"
                    aria-label="Afficher le mot de passe"
                    title="Afficher le mot de passe"
                >
                    <svg class="password-toggle__show" aria-hidden="true" viewBox="0 0 24 24">
                        <path
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                        />
                        <circle
                            cx="12"
                            cy="12"
                            r="3"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        />
                    </svg>

                    <svg class="password-toggle__hide" aria-hidden="true" viewBox="0 0 24 24">
                        <path
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m3 3 18 18M10.6 10.7a2 2 0 0 0 2.7 2.7M9.9 4.3A10 10 0 0 1 12 4c6.5 0 10 8 10 8a16.6 16.6 0 0 1-3.1 4.3M6.6 6.6C3.7 8.4 2 12 2 12s3.5 8 10 8a9.8 9.8 0 0 0 4.1-.9"
                        />
                    </svg>
                </button>
            </div>
        </div>

        <button class="admin-button" type="submit">
            Se connecter
        </button>

        <a class="login-card__forgot" href="./mot-de-passe-oublie.php">
            Mot de passe oublié ?
        </a>
    </form>

    <p class="login-card__footer">
        Accès réservé à Polymaintenance.
    </p>
</main>

    <script src="./password-toggle.js" defer></script>
</body>
</html>
<?php
exit;
endif;

/* -------------------------------------------------------
   Tableau de bord
------------------------------------------------------- */

$contenu = is_file($annoncesFile)
    ? file_get_contents($annoncesFile)
    : '[]';

$annonces = json_decode($contenu ?: '[]', true);

if (!is_array($annonces)) {
    $annonces = [];
}

usort(
    $annonces,
    static function (array $a, array $b): int {
        $dateA = strtotime(
            (string) ($a['updated_at'] ?? $a['created_at'] ?? '')
        ) ?: 0;

        $dateB = strtotime(
            (string) ($b['updated_at'] ?? $b['created_at'] ?? '')
        ) ?: 0;

        return $dateB <=> $dateA;
    }
);

$categoryLabels = [
    'air-comprime' => 'Air comprimé',
    'manutention' => 'Manutention',
    'bois' => 'Machines à bois',
    'ferronnerie' => 'Ferronnerie',
    'soudage' => 'Soudage',
    'emballage' => 'Emballage',
    'autre' => 'Autre matériel',
];

$statusLabels = [
    'available' => 'Disponible',
    'reserved' => 'Réservé',
    'sold' => 'Vendu',
];

$conditionLabels = [
    'neuf' => 'Neuf',
    'occasion' => 'Occasion',
    'reconditionne' => 'Reconditionné',
];

$total = count($annonces);
$published = 0;
$drafts = 0;
$available = 0;

foreach ($annonces as $item) {
    if (($item['publication'] ?? '') === 'publiee') {
        $published++;
    } else {
        $drafts++;
    }

    if (($item['status'] ?? '') === 'available') {
        $available++;
    }
}

function formatAdminPrice(array $annonce): string
{
    if (
        ($annonce['priceType'] ?? '') !== 'fixed' ||
        !isset($annonce['prix'])
    ) {
        return 'Prix sur demande';
    }

    return number_format(
        (float) $annonce['prix'],
        2,
        ',',
        ' '
    ) . ' €';
}

$message = '';

if (isset($_GET['created'])) {
    $message = 'L’annonce a bien été créée.';
} elseif (isset($_GET['updated'])) {
    $message = 'L’annonce a bien été modifiée.';
} elseif (isset($_GET['deleted'])) {
    $message = 'L’annonce a bien été supprimée.';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des annonces - Polymaintenance</title>
    <link rel="stylesheet" href="./admin.css?v=20260915-3">
</head>

<body>

<div class="admin-shell">
    <header class="admin-header">
        <div class="admin-header__inner">
            <a class="admin-brand" href="./" aria-label="Accueil de la gestion Polymaintenance">
                <img src="./logo-polymaintenance.png" alt="Polymaintenance" width="250" height="64">
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

                <form method="post">
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

    <main class="admin-main">
        <div class="admin-pagehead">
            <div>
                <h1>Matériel à vendre</h1>

                <p>
                    Créez, modifiez et publiez les annonces affichées
                    automatiquement sur le site.
                    <a class="admin-inline-link" href="./changer-mot-de-passe.php">
                        Changer mon mot de passe
                    </a>
                </p>
            </div>

            <a class="admin-button admin-button--green" href="./ajouter.php">
                + Ajouter une annonce
            </a>
        </div>

        <?php if ($message): ?>
            <div class="admin-alert admin-alert--success">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="admin-stats">
            <div class="admin-stat">
                <strong><?= $total ?></strong>
                <span>Annonce<?= $total > 1 ? 's' : '' ?> au total</span>
            </div>

            <div class="admin-stat">
                <strong><?= $published ?></strong>
                <span>Publiée<?= $published > 1 ? 's' : '' ?></span>
            </div>

            <div class="admin-stat">
                <strong><?= $drafts ?></strong>
                <span>Brouillon<?= $drafts > 1 ? 's' : '' ?></span>
            </div>

            <div class="admin-stat">
                <strong><?= $available ?></strong>
                <span>Disponible<?= $available > 1 ? 's' : '' ?></span>
            </div>
        </div>

        <?php if (!$annonces): ?>

            <section class="admin-empty">
                <h2>Aucune annonce pour l’instant</h2>

                <p>
                    Ajoutez le premier matériel que vous souhaitez présenter
                    sur le site.
                </p>

                <a class="admin-button admin-button--green" href="./ajouter.php">
                    Ajouter une annonce
                </a>
            </section>

        <?php else: ?>

            <section class="admin-list" aria-label="Liste des annonces">

                <?php foreach ($annonces as $annonce): ?>
                    <?php
                    $id = (string) ($annonce['id'] ?? '');
                    $slug = (string) ($annonce['slug'] ?? '');
                    $photo = $annonce['photo'] ?? null;
                    $updatedAt = (string) ($annonce['updated_at'] ?? '');
                    $publication = ($annonce['publication'] ?? '') === 'publiee'
                        ? 'publiee'
                        : 'brouillon';
                    $status = (string) ($annonce['status'] ?? 'available');
                    ?>

                    <article class="admin-item">
                        <div class="admin-item__visual">
                            <?php if ($photo): ?>
                                <img
                                    src="<?= htmlspecialchars($photo) ?>?v=<?= urlencode($updatedAt) ?>"
                                    alt="<?= htmlspecialchars((string) ($annonce['titre'] ?? '')) ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <div class="admin-item__placeholder">
                                    Photo à venir
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="admin-item__body">
                            <div class="admin-item__top">
                                <div>
                                    <h2>
                                        <?= htmlspecialchars((string) ($annonce['titre'] ?? 'Sans titre')) ?>
                                    </h2>

                                    <p class="admin-item__description">
                                        <?= htmlspecialchars((string) ($annonce['description'] ?? '')) ?>
                                    </p>
                                </div>

                                <strong class="admin-item__price">
                                    <?= htmlspecialchars(formatAdminPrice($annonce)) ?>
                                </strong>
                            </div>

                            <div class="admin-item__meta">
                                <span class="admin-pill">
                                    <?= htmlspecialchars(
                                        $categoryLabels[$annonce['categorie'] ?? 'autre']
                                        ?? 'Autre matériel'
                                    ) ?>
                                </span>

                                <span class="admin-pill">
                                    <?= htmlspecialchars(
                                        $conditionLabels[$annonce['condition'] ?? 'occasion']
                                        ?? 'Occasion'
                                    ) ?>
                                </span>

                                <span class="admin-pill admin-pill--<?= htmlspecialchars($status) ?>">
                                    <?= htmlspecialchars(
                                        $statusLabels[$status] ?? 'Disponible'
                                    ) ?>
                                </span>

                                <span class="admin-pill admin-pill--<?= $publication === 'publiee' ? 'published' : 'draft' ?>">
                                    <?= $publication === 'publiee' ? 'Publiée' : 'Brouillon' ?>
                                </span>
                            </div>

                            <div class="admin-item__bottom">
                                <div>
                                    <?php if ($publication === 'publiee' && $slug !== ''): ?>
                                        <a
                                            href="/materiel-industriel/<?= rawurlencode($slug) ?>/"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Voir l’annonce sur le site
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="admin-actions">
                                    <a
                                        class="admin-button admin-button--ghost"
                                        href="./modifier.php?id=<?= urlencode($id) ?>"
                                    >
                                        Modifier
                                    </a>

                                    <form
                                        method="post"
                                        action="./supprimer.php"
                                        onsubmit="return confirm('Supprimer définitivement cette annonce ?');"
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

                                        <button
                                            class="admin-button admin-button--danger"
                                            type="submit"
                                        >
                                            Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>

            </section>

        <?php endif; ?>
    </main>
</div>

</body>
</html>
