<?php

declare(strict_types=1);

const CONTACT_RECIPIENT = 'contact@polymaintenance.fr';
const CONTACT_FROM = 'contact@polymaintenance.fr';
const MAX_NAME_LENGTH = 120;
const MAX_COMPANY_LENGTH = 160;
const MAX_PHONE_LENGTH = 50;
const MAX_MESSAGE_LENGTH = 6000;

function redirectTo(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/contact');
}

// Champ leurre : un visiteur humain ne le voit pas. Les robots le remplissent souvent.
if (!empty($_POST['website'] ?? '')) {
    redirectTo('/merci');
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
