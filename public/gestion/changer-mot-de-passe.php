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

$userFile = '/home/polymay/private/gestion-user.json';

$error = '';
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!is_file($userFile)) {
        $error = 'Le compte de gestion est introuvable.';
    } else {
        $userData = json_decode(
            file_get_contents($userFile),
            true
        );

        if (!is_array($userData)) {
            $error = 'Le compte de gestion est invalide.';
        } else {
            $storedHash =
                (string) ($userData['password_hash'] ?? '');

            if (
                $storedHash === '' ||
                !password_verify($currentPassword, $storedHash)
            ) {
                $error = 'Le mot de passe actuel est incorrect.';
            } elseif (strlen($newPassword) < 10) {
                $error = 'Le nouveau mot de passe doit contenir au moins 10 caractères.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Les deux nouveaux mots de passe ne correspondent pas.';
            } elseif (password_verify($newPassword, $storedHash)) {
                $error = 'Choisissez un mot de passe différent de l’actuel.';
            } else {
                $userData['password_hash'] =
                    password_hash($newPassword, PASSWORD_DEFAULT);

                $json = json_encode(
                    $userData,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

                if (
                    $json === false ||
                    file_put_contents(
                        $userFile,
                        $json,
                        LOCK_EX
                    ) === false
                ) {
                    $error = 'Impossible d’enregistrer le nouveau mot de passe.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $success = 'Votre mot de passe a bien été modifié.';
                }
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
    <title>Changer le mot de passe - Polymaintenance</title>
    <link rel="stylesheet" href="./admin.css?v=20260915-3">
</head>

<body>

<div class="admin-shell">
    <header class="admin-header">
        <div class="admin-header__inner">
            <a class="admin-brand" href="./" aria-label="Accueil de la gestion Polymaintenance">
                <img
                    src="./logo-polymaintenance.png"
                    alt="Polymaintenance"
                    width="250"
                    height="64"
                >
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
            </div>
        </div>
    </header>

    <main class="admin-main admin-main--narrow">
        <a class="admin-backlink" href="./">← Retour aux annonces</a>

        <div class="admin-pagehead">
            <div>
                <h1>Changer mon mot de passe</h1>

                <p>
                    Choisissez un mot de passe d’au moins 10 caractères.
                </p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="admin-alert admin-alert--error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="admin-alert admin-alert--success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <section class="admin-card">
            <form method="post" class="admin-form admin-form--compact">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                >

                <div class="admin-field">
                    <label for="current_password">Mot de passe actuel</label>

            <div class="password-field">
                    <input
                        id="current_password"
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >
                <button
                    class="password-toggle"
                    type="button"
                    data-password-toggle
                    aria-controls="current_password"
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

                <div class="admin-field">
                    <label for="new_password">Nouveau mot de passe</label>

            <div class="password-field">
                    <input
                        id="new_password"
                        type="password"
                        name="new_password"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >
                <button
                    class="password-toggle"
                    type="button"
                    data-password-toggle
                    aria-controls="new_password"
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

                <div class="admin-field">
                    <label for="confirm_password">
                        Confirmer le nouveau mot de passe
                    </label>

            <div class="password-field">
                    <input
                        id="confirm_password"
                        type="password"
                        name="confirm_password"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >
                <button
                    class="password-toggle"
                    type="button"
                    data-password-toggle
                    aria-controls="confirm_password"
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

                <div class="admin-form__footer">
                    <a class="admin-button admin-button--ghost" href="./">
                        Annuler
                    </a>

                    <button class="admin-button admin-button--green" type="submit">
                        Modifier le mot de passe
                    </button>
                </div>
            </form>
        </section>
    </main>
</div>

    <script src="./password-toggle.js" defer></script>
</body>
</html>
