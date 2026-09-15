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

$message = '';
$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        exit('Requête invalide.');
    }

    $email = strtolower(trim($_POST['email'] ?? ''));

    /*
     * Message volontairement identique dans tous les cas :
     * on ne révèle pas si une adresse possède un compte.
     */
    $message =
        'Si cette adresse correspond au compte de gestion, un e-mail de réinitialisation vient d’être envoyé.';

    if (
        filter_var($email, FILTER_VALIDATE_EMAIL) &&
        is_file($userFile)
    ) {
        $userData = json_decode(
            file_get_contents($userFile),
            true
        );

        if (is_array($userData)) {
            $storedEmail = strtolower(
                trim((string) ($userData['email'] ?? ''))
            );

            if (
                $storedEmail !== '' &&
                hash_equals($storedEmail, $email)
            ) {
                $token = bin2hex(random_bytes(32));

                $resetData = [
                    'email' => $storedEmail,
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => time() + 1800,
                    'created_at' => date('c'),
                ];

                $json = json_encode(
                    $resetData,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

                if (
                    $json !== false &&
                    file_put_contents(
                        $resetFile,
                        $json,
                        LOCK_EX
                    ) !== false
                ) {
                    $resetUrl =
                        'https://polymaintenance.fr/gestion/reinitialiser-mot-de-passe.php?token=' .
                        rawurlencode($token);

                    $subject =
                        'Réinitialisation du mot de passe Polymaintenance';

                    $body =
                        "Bonjour,\n\n" .
                        "Une demande de réinitialisation du mot de passe de l’espace de gestion Polymaintenance a été effectuée.\n\n" .
                        "Pour choisir un nouveau mot de passe, utilisez ce lien dans les 30 prochaines minutes :\n\n" .
                        $resetUrl .
                        "\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail.\n\n" .
                        "Polymaintenance";

                    $headers = [
                        'From: Polymaintenance <contact@polymaintenance.fr>',
                        'Reply-To: contact@polymaintenance.fr',
                        'Content-Type: text/plain; charset=UTF-8',
                        'X-Mailer: PHP/' . phpversion(),
                    ];

                    @mail(
                        $storedEmail,
                        $subject,
                        $body,
                        implode("\r\n", $headers)
                    );
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
    <title>Mot de passe oublié - Polymaintenance</title>
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

    <h1>Mot de passe oublié</h1>

    <p class="login-card__intro">
        Indiquez l’adresse e-mail utilisée pour l’espace de gestion.
    </p>

    <?php if ($message): ?>
        <div class="admin-alert admin-alert--success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert--error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="login-form">
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
                autocomplete="email"
                required
            >
        </div>

        <button class="admin-button" type="submit">
            Envoyer le lien
        </button>

        <a class="login-card__forgot" href="./">
            ← Retour à la connexion
        </a>
    </form>
</main>

</body>
</html>
