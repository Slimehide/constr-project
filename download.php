<?php
/**
 * H-CLEER document download handler.
 *
 * Forces the browser to download a file (instead of opening it inline)
 * by sending a Content-Disposition: attachment header.
 *
 * Usage:  /download.php?file=letter-of-intent
 */

declare(strict_types=1);

$files = [
    'letter-of-intent'         => ['path' => 'letter-of-intent.pdf',         'type' => 'application/pdf'],
    'request-for-quotation'    => ['path' => 'request-for-quotation.pdf',    'type' => 'application/pdf'],
    'commercial-invoice'       => ['path' => 'commercial-invoice.pdf',       'type' => 'application/pdf'],
    'supply-agreement'         => ['path' => 'supply-agreement.pdf',         'type' => 'application/pdf'],
    'monthly-supply-agreement' => ['path' => 'monthly-supply-agreement.pdf', 'type' => 'application/pdf'],
    'all'                      => ['path' => 'hcleer-documents-all.zip',     'type' => 'application/zip'],
];

$key = isset($_GET['file']) ? (string)$_GET['file'] : '';
if (!isset($files[$key])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "File not found.\n";
    exit;
}

$entry = $files[$key];
$absolute = __DIR__ . '/documents/' . $entry['path'];

if (!is_file($absolute) || !is_readable($absolute)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "File not available.\n";
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $entry['type']);
header('Content-Disposition: attachment; filename="' . basename($entry['path']) . '"');
header('Content-Length: ' . filesize($absolute));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

readfile($absolute);
exit;
