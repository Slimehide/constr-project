<?php
/**
 * H-CLEER contact form handler.
 *
 * Receives a POST submission from contact.html, validates the input,
 * and delivers it to the configured recipient via SMTP using the locally
 * vendored PHPMailer (lib/phpmailer/).
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require __DIR__ . '/lib/phpmailer/src/Exception.php';
require __DIR__ . '/lib/phpmailer/src/PHPMailer.php';
require __DIR__ . '/lib/phpmailer/src/SMTP.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$config = require __DIR__ . '/lib/config.php';

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Method not allowed']);
}

$post = $_POST;

$required = ['company', 'contact_name', 'email', 'product_interest', 'message'];
$missing = [];
foreach ($required as $key) {
    if (empty(trim((string)($post[$key] ?? '')))) {
        $missing[] = $key;
    }
}
if (!empty($missing)) {
    respond(422, [
        'ok' => false,
        'error' => 'Please complete all required fields.',
        'missing' => $missing,
    ]);
}

$email = trim((string)$post['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['ok' => false, 'error' => 'Please enter a valid email address.']);
}

$fields = [
    'company'          => 'Company Name',
    'contact_name'     => 'Contact Name',
    'email'            => 'Email Address',
    'phone'            => 'Phone Number',
    'product_interest' => 'Product Interest',
    'quantity'         => 'Quantity (MT)',
    'delivery_terms'   => 'Delivery Terms',
    'destination_port' => 'Destination Port',
    'message'          => 'Message',
];

$data = [];
foreach ($fields as $key => $label) {
    $value = isset($post[$key]) ? trim((string)$post[$key]) : '';
    $data[$key] = ['label' => $label, 'value' => $value];
}

$attachments = [];
$maxTotalBytes = isset($config['max_total_upload_bytes'])
    ? (int)$config['max_total_upload_bytes']
    : 25 * 1024 * 1024;
$maxPerFile = (int)$config['max_upload_bytes'];

$collectFile = static function (string $name, int $error, int $size, string $tmp) use ($config, $maxPerFile, &$attachments) {
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['error' => 'File upload failed. Please try again.', 'status' => 400];
    }
    if ($size <= 0 || $size > $maxPerFile) {
        $maxMb = (int)($maxPerFile / (1024 * 1024));
        return ['error' => "Each file must be smaller than {$maxMb} MB.", 'status' => 413];
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $config['allowed_upload_extensions'], true)) {
        return ['error' => 'Unsupported file type. Allowed: PDF, DOC, DOCX, ODT, RTF, JPG, PNG, GIF, TIFF, WEBP.', 'status' => 415];
    }
    if (!is_uploaded_file($tmp)) {
        return ['error' => 'Invalid upload.', 'status' => 400];
    }
    $detectedMime = function_exists('mime_content_type') ? @mime_content_type($tmp) : null;
    if ($detectedMime && !in_array($detectedMime, $config['allowed_upload_mime'], true)) {
        return ['error' => 'File content does not match an allowed document type.', 'status' => 415];
    }
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: ('document.' . $ext);
    $attachments[] = ['path' => $tmp, 'name' => $safeName, 'size' => $size];
    return null;
};

$inputs = [];
if (!empty($_FILES['signed_documents']) && is_array($_FILES['signed_documents'])) {
    $f = $_FILES['signed_documents'];
    if (is_array($f['name'] ?? null)) {
        $count = count($f['name']);
        for ($i = 0; $i < $count; $i++) {
            $inputs[] = [
                'name'  => (string)($f['name'][$i] ?? ''),
                'error' => (int)($f['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size'  => (int)($f['size'][$i] ?? 0),
                'tmp'   => (string)($f['tmp_name'][$i] ?? ''),
            ];
        }
    } else {
        $inputs[] = [
            'name'  => (string)($f['name'] ?? ''),
            'error' => (int)($f['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'  => (int)($f['size'] ?? 0),
            'tmp'   => (string)($f['tmp_name'] ?? ''),
        ];
    }
}
if (!empty($_FILES['signed_document']) && is_array($_FILES['signed_document'])) {
    $f = $_FILES['signed_document'];
    $inputs[] = [
        'name'  => (string)($f['name'] ?? ''),
        'error' => (int)($f['error'] ?? UPLOAD_ERR_NO_FILE),
        'size'  => (int)($f['size'] ?? 0),
        'tmp'   => (string)($f['tmp_name'] ?? ''),
    ];
}

foreach ($inputs as $entry) {
    $err = $collectFile($entry['name'], $entry['error'], $entry['size'], $entry['tmp']);
    if ($err !== null) {
        respond((int)$err['status'], ['ok' => false, 'error' => $err['error']]);
    }
}

$totalBytes = 0;
foreach ($attachments as $a) { $totalBytes += (int)$a['size']; }
if ($totalBytes > $maxTotalBytes) {
    $totalMb = (int)($maxTotalBytes / (1024 * 1024));
    respond(413, ['ok' => false, 'error' => "Combined attachment size exceeds the {$totalMb} MB limit. Please reduce the total size."]);
}

$plainLines = [
    'New inquiry from the H-CLEER website',
    str_repeat('-', 40),
    '',
];
$htmlRows = [];
foreach ($data as $entry) {
    if ($entry['value'] === '') {
        continue;
    }
    $plainLines[] = $entry['label'] . ': ' . $entry['value'];
    $htmlRows[] = '<tr>'
        . '<td style="padding:6px 12px 6px 0;color:#6F5F44;font-weight:600;white-space:nowrap;vertical-align:top;">'
        . htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td style="padding:6px 0;color:#2A241B;vertical-align:top;">'
        . nl2br(htmlspecialchars($entry['value'], ENT_QUOTES, 'UTF-8'))
        . '</td></tr>';
}
$plainLines[] = '';
$plainLines[] = str_repeat('-', 40);
if (!empty($attachments)) {
    $plainLines[] = 'Attachments (' . count($attachments) . '):';
    foreach ($attachments as $a) {
        $plainLines[] = '  - ' . $a['name'];
    }
}
$plainLines[] = 'Submitted: ' . gmdate('Y-m-d H:i:s') . ' UTC';

$plainBody = implode("\n", $plainLines);

$htmlBody = '<div style="font-family:Arial,Helvetica,sans-serif;background:#F4E9C9;padding:24px;color:#2A241B;">'
    . '<div style="max-width:640px;margin:0 auto;background:#FBF6E2;border:1px solid #D9B872;border-radius:6px;padding:24px;">'
    . '<h2 style="margin:0 0 6px 0;font-family:Georgia,serif;color:#B98B3F;letter-spacing:1px;">New Inquiry</h2>'
    . '<p style="margin:0 0 16px 0;color:#6F5F44;font-style:italic;">Submitted via the H-CLEER website contact form.</p>'
    . '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . implode('', $htmlRows) . '</table>';

if (!empty($attachments)) {
    $htmlBody .= '<p style="margin:18px 0 6px 0;color:#6F5F44;font-weight:600;">Attached documents (' . count($attachments) . '):</p>';
    $htmlBody .= '<ul style="margin:0;padding-left:18px;color:#2A241B;">';
    foreach ($attachments as $a) {
        $htmlBody .= '<li>' . htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $htmlBody .= '</ul>';
}

$htmlBody .= '<p style="margin:18px 0 0 0;color:#9a8a6c;font-size:12px;">Submitted ' . gmdate('Y-m-d H:i:s') . ' UTC</p>'
    . '</div></div>';

$mailer = new PHPMailer(true);

try {
    $mailer->isSMTP();
    $mailer->Host       = $config['smtp_host'];
    $mailer->Port       = (int)$config['smtp_port'];
    $mailer->SMTPAuth   = (bool)$config['smtp_auth'];
    $mailer->Username   = $config['smtp_user'];
    $mailer->Password   = $config['smtp_pass'];
    $mailer->SMTPSecure = $config['smtp_secure'] === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->SMTPDebug  = (int)$config['smtp_debug'];
    $mailer->CharSet    = 'UTF-8';
    $mailer->XMailer    = 'H-CLEER Website Mailer';

    $mailer->setFrom($config['mail_from'], $config['mail_from_name']);
    $mailer->addAddress($config['mail_to'], $config['mail_to_name']);
    $mailer->addReplyTo($email, $data['contact_name']['value'] ?: $data['company']['value']);

    $subject = 'Quote Request from ' . ($data['company']['value'] !== ''
        ? $data['company']['value']
        : $data['contact_name']['value']);
    $mailer->Subject = $subject;
    $mailer->isHTML(true);
    $mailer->Body    = $htmlBody;
    $mailer->AltBody = $plainBody;

    foreach ($attachments as $a) {
        $mailer->addAttachment($a['path'], $a['name']);
    }

    $mailer->send();
} catch (PHPMailerException $e) {
    error_log('[H-CLEER mail] ' . $mailer->ErrorInfo);
    respond(502, [
        'ok' => false,
        'error' => 'Could not send your message right now. Please email hc@hcleerimportex.com directly.',
    ]);
}

respond(200, [
    'ok' => true,
    'message' => 'Your message has been sent. Our team will reply shortly.',
]);
