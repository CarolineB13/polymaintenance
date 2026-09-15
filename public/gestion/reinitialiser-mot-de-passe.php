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
$resetFile = '/home/polymay/private/gestion-reset.json';

$error = '';
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenValid = false;
$resetData = null;

if (
    preg_match('/^[a-f0-9]{64}$/', $token) &&
    is_file($resetFile)
) {
    $resetData = json_decode(
        file_get_contents($resetFile),
        true
    );

    if (
        is_array($resetData) &&
        !empty($resetData['token_hash']) &&
        !empty($resetData['expires_at']) &&
        time() <= (int) $resetData['expires_at']
    ) {
        $tokenHash = hash('sha256', $token);

        $tokenValid = hash_equals(
            (string) $resetData['token_hash'],
            $tokenHash
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    if (!$tokenValid) {
        $error =
            'Ce lien de réinitialisation est invalide ou a expiré.';
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 10) {
            $error =
                'Le nouveau mot de passe doit contenir au moins 10 caractères.';
        } elseif ($newPassword !== $confirmPassword) {
            $error =
                'Les deux mots de passe ne correspondent pas.';
        } elseif (!is_file($userFile)) {
            $error =
                'Le compte de gestion est introuvable.';
        } else {
            $userData = json_decode(
                file_get_contents($userFile),
                true
            );

            if (!is_array($userData)) {
                $error =
                    'Le compte de gestion est invalide.';
            } else {
                $storedEmail = strtolower(
                    trim((string) ($userData['email'] ?? ''))
                );

                $resetEmail = strtolower(
                    trim((string) ($resetData['email'] ?? ''))
                );

                if (
                    $storedEmail === '' ||
                    $resetEmail === '' ||
                    !hash_equals($storedEmail, $resetEmail)
                ) {
                    $error =
                        'Ce lien de réinitialisation n’est plus valide.';
                } else {
                    $userData['password_hash'] =
                        password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        );

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
                        $error =
                            'Impossible d’enregistrer le nouveau mot de passe.';
                    } else {
                        @unlink($resetFile);

                        $_SESSION = [];
                        session_regenerate_id(true);
                        $_SESSION['csrf_token'] =
                            bin2hex(random_bytes(32));

                        $success =
                            'Votre mot de passe a bien été réinitialisé. Vous pouvez maintenant vous connecter.';
                        $tokenValid = false;
                    }
                }
            }
        }
    }
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    !$tokenValid
) {
    $error =
        'Ce lien de réinitialisation est invalide ou a expiré.';
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Réinitialiser le mot de passe - Polymaintenance</title>
    <link rel="stylesheet" href="./admin.css?v=20260915-3">
</head>

<body class="login-page">

<main class="login-card">
    <a class="login-card__logo" href="/" aria-label="Accéder au site Polymaintenance">
        <img
            src="./logo-polymaintenance.png"
            alt="Polymaintenance"
            width="250"
            height="64"
        >
    </a>

    <h1>Choisir un nouveau mot de passe</h1>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert--error admin-alert--spaced">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="admin-alert admin-alert--success admin-alert--spaced">
            <?= htmlspecialchars($success) ?>
        </div>

        <a class="admin-button admin-button--full" href="./">
            Revenir à la connexion
        </a>

    <?php elseif ($tokenValid): ?>

        <p class="login-card__intro">
            Le nouveau mot de passe doit contenir au moins 10 caractères.
        </p>

        <form method="post" class="login-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >

            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars($token) ?>"
            >

            <div class="admin-field">
                <label for="new_password">Nouveau mot de passe</label>

            <div class="password-field">
                <input
                    id="new_password"
                    type="password"
                    name="new_password"
                    minlength="10"
                    autocomplete="new-password"
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
                <label for="confirm_password">Confirmer le mot de passe</label>

            <div class="password-field">
                <input
                    id="confirm_password"
                    type="password"
                    name="confirm_password"
                    minlength="10"
                    autocomplete="new-password"
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

            <button class="admin-button" type="submit">
                Enregistrer le nouveau mot de passe
            </button>
        </form>

    <?php else: ?>

        <a class="admin-button admin-button--full" href="./mot-de-passe-oublie.php">
            Demander un nouveau lien
        </a>

    <?php endif; ?>
</main>

    <script src="./password-toggle.js" defer></script>
</body>
</html>
