<?php

declare(strict_types=1);

const CONTACT_RECIPIENT = 'contact@polymaintenance.fr';
const CONTACT_FROM = 'contact@polymaintenance.fr';
const MAX_NAME_LENGTH = 120;
const MAX_COMPANY_LENGTH = 160;
const MAX_PHONE_LENGTH = 50;
const MAX_MESSAGE_LENGTH = 6000;

// Protection anti-spam silencieuse : aucune gêne pour un visiteur normal.
const MIN_FORM_FILL_SECONDS = 4;
const MAX_FORM_AGE_SECONDS = 7200;
const RATE_LIMIT_WINDOW_SECONDS = 3600;
const RATE_LIMIT_MAX_ATTEMPTS = 5;

function redirectTo(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function rejectSpam(): never
{
    // Ne pas révéler au robot qu'il a été détecté.
    redirectTo('/merci');
}

function cleanSingleLine(string $value, int $maxLength): string
{
    $value = trim(strip_tags($value));
    $value = str_replace(["\r", "\n"], ' ', $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function cleanMessage(string $value): string
{
    $value = trim(strip_tags($value));
    return function_exists('mb_substr') ? mb_substr($value, 0, MAX_MESSAGE_LENGTH) : substr($value, 0, MAX_MESSAGE_LENGTH);
}

function clientIp(): string
{
    // REMOTE_ADDR est volontairement utilisé : les en-têtes X-Forwarded-For sont falsifiables.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}


function verifyTurnstile(string $token, string $ip): bool
{
    if ($token === '') {
        return false;
    }

    $configPath = dirname(__DIR__) . '/turnstile-config.php';
    if (!is_file($configPath)) {
        return false;
    }

    $config = require $configPath;
    $secret = is_array($config) ? (string) ($config['secret_key'] ?? '') : '';
    if ($secret === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ip,
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        false,
        $context
    );
    if ($response === false) {
        return false;
    }

    $result = json_decode($response, true);
    return is_array($result) && ($result['success'] ?? false) === true;
}

function isRateLimited(string $ip): bool
{
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'polymaintenance-contact-rate-limit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        // Si le stockage temporaire est indisponible, ne pas bloquer les vrais visiteurs.
        return false;
    }

    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return false;
    }

    $limited = false;
    if (flock($handle, LOCK_EX)) {
        $raw = stream_get_contents($handle);
        $attempts = json_decode($raw ?: '[]', true);
        if (!is_array($attempts)) {
            $attempts = [];
        }

        $now = time();
        $cutoff = $now - RATE_LIMIT_WINDOW_SECONDS;
        $attempts = array_values(array_filter(
            $attempts,
            static fn ($timestamp): bool => is_int($timestamp) && $timestamp >= $cutoff
        ));

        if (count($attempts) >= RATE_LIMIT_MAX_ATTEMPTS) {
            $limited = true;
        } else {
            $attempts[] = $now;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($attempts, JSON_THROW_ON_ERROR));
            fflush($handle);
        }

        flock($handle, LOCK_UN);
    }

    fclose($handle);
    return $limited;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/contact');
}

// Évite les requêtes anormalement volumineuses avant tout traitement.
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 20000) {
    rejectSpam();
}

// Champ leurre : un visiteur humain ne le voit pas. Les robots le remplissent souvent.
if (!empty($_POST['website'] ?? '')) {
    rejectSpam();
}

// Le navigateur inscrit l'heure d'ouverture du formulaire. Les soumissions instantanées,
// très anciennes ou envoyées directement au script PHP sont rejetées silencieusement.
$formStartedAt = filter_var($_POST['form_started_at'] ?? null, FILTER_VALIDATE_INT);
$now = time();
if (
    $formStartedAt === false ||
    $formStartedAt === null ||
    $formStartedAt > $now ||
    ($now - $formStartedAt) < MIN_FORM_FILL_SECONDS ||
    ($now - $formStartedAt) > MAX_FORM_AGE_SECONDS
) {
    rejectSpam();
}

// Cloudflare Turnstile : le jeton est vérifié côté serveur avant tout envoi d'e-mail.
$ip = clientIp();
$turnstileToken = (string) ($_POST['cf-turnstile-response'] ?? '');
if (!verifyTurnstile($turnstileToken, $ip)) {
    rejectSpam();
}

// Au maximum 5 tentatives par heure et par IP.
if (isRateLimited($ip)) {
    rejectSpam();
}

$name = cleanSingleLine((string) ($_POST['name'] ?? ''), MAX_NAME_LENGTH);
$company = cleanSingleLine((string) ($_POST['company'] ?? ''), MAX_COMPANY_LENGTH);
$email = trim((string) ($_POST['email'] ?? ''));
$phone = cleanSingleLine((string) ($_POST['phone'] ?? ''), MAX_PHONE_LENGTH);
$subjectKey = cleanSingleLine((string) ($_POST['subject'] ?? ''), 40);
$message = cleanMessage((string) ($_POST['message'] ?? ''));

$subjects = [
    'maintenance' => 'Maintenance ou dépannage',
    'installation' => 'Installation ou transfert',
    'formation' => 'Formation professionnelle',
    'negoce' => 'Recherche de matériel',
    'autre' => 'Autre demande',
];

if (
    $name === '' ||
    $message === '' ||
    !isset($subjects[$subjectKey]) ||
    strlen($email) > 254 ||
    preg_match('/[\r\n]/', $email) ||
    filter_var($email, FILTER_VALIDATE_EMAIL) === false
) {
    redirectTo('/contact?error=1');
}

$subjectLabel = $subjects[$subjectKey];
$mailSubject = 'Demande site Polymaintenance - ' . $subjectLabel;

$bodyLines = [
    'Nouvelle demande reçue depuis polymaintenance.fr',
    '',
    'Nom : ' . $name,
    'Société : ' . ($company !== '' ? $company : 'Non renseignée'),
    'E-mail : ' . $email,
    'Téléphone : ' . ($phone !== '' ? $phone : 'Non renseigné'),
    'Objet : ' . $subjectLabel,
    '',
    'Message :',
    $message,
];

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: Polymaintenance <' . CONTACT_FROM . '>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . PHP_VERSION,
];

$sent = mail(
    CONTACT_RECIPIENT,
    $mailSubject,
    implode("\r\n", $bodyLines),
    implode("\r\n", $headers)
);

if (!$sent) {
    redirectTo('/contact?error=1');
}

redirectTo('/merci');
