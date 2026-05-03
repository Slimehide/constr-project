<?php
/**
 * SMTP / mail configuration for the H-CLEER contact form.
 *
 * Edit the values below for your hosting environment, OR set the same keys
 * as environment variables (recommended for production) and they will take
 * precedence over the values defined here.
 *
 *   SMTP_HOST     - SMTP server hostname (e.g. smtp.gmail.com, smtp.office365.com)
 *   SMTP_PORT     - 587 for STARTTLS (recommended), 465 for SMTPS
 *   SMTP_USER     - SMTP authentication username (usually the From address)
 *   SMTP_PASS     - SMTP authentication password / app password
 *   SMTP_SECURE   - "tls" (STARTTLS, port 587) or "ssl" (SMTPS, port 465)
 *   MAIL_FROM     - Address that the email will be sent FROM
 *   MAIL_FROM_NAME - Display name for the From address
 *   MAIL_TO       - Address that messages are delivered TO
 *   MAIL_TO_NAME  - Display name for the To address
 *
 * IMPORTANT: this file should NEVER be committed with real credentials.
 * Keep production values in environment variables on the server.
 */

return [
    'smtp_host'      => getenv('SMTP_HOST')      ?: 'smtp.example.com',
    'smtp_port'      => (int)(getenv('SMTP_PORT') ?: 587),
    'smtp_user'      => getenv('SMTP_USER')      ?: 'hc@hcleerimportex.com',
    'smtp_pass'      => getenv('SMTP_PASS')      ?: '',
    'smtp_secure'    => getenv('SMTP_SECURE')    ?: 'tls',
    'smtp_auth'      => true,
    'smtp_debug'     => 0,

    'mail_from'      => getenv('MAIL_FROM')      ?: 'hc@hcleerimportex.com',
    'mail_from_name' => getenv('MAIL_FROM_NAME') ?: 'H-CLEER Website',
    'mail_to'        => getenv('MAIL_TO')        ?: 'hc@hcleerimportex.com',
    'mail_to_name'   => getenv('MAIL_TO_NAME')   ?: 'H-CLEER Import & Export Inc.',

    'allowed_upload_extensions' => [
        'pdf', 'doc', 'docx', 'odt', 'rtf',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'tif', 'tiff',
    ],
    'allowed_upload_mime' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'application/rtf',
        'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'image/tiff',
    ],
    'max_upload_bytes'       => 10 * 1024 * 1024,
    'max_total_upload_bytes' => 25 * 1024 * 1024,
];
