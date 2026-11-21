<?php

return [

    'documents_disk' => env('EIRA_DOCUMENTS_DISK', 'local'),

    'upload' => [
        'max_kb' => (int) env('EIRA_UPLOAD_MAX_KB', 10240),
        'max_image_pixels' => 20_000_000,
        'max_image_side' => 8000,
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => (int) env('EIRA_OTP_TTL_MINUTES', 10080),
        'max_attempts' => (int) env('EIRA_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('EIRA_OTP_RESEND_COOLDOWN', 60),
    ],

    'patient_session_minutes' => (int) env('EIRA_PATIENT_SESSION_MINUTES', 20),

    'portal_url' => env('EIRA_PORTAL_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/consulta'),

    'email_driver' => env('EIRA_EMAIL_DRIVER', 'fake'),

    'sms_driver' => env('SMS_DRIVER', 'fake'),

    'clicksend' => [
        'username' => env('CLICKSEND_USERNAME'),
        'key' => env('CLICKSEND_API_KEY'),
        'from' => env('CLICKSEND_FROM'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from' => env('RESEND_FROM'),
    ],

    'demo_outbox' => (bool) env('EIRA_DEMO_OUTBOX', false),

    'demo_quick_login' => (bool) env('EIRA_DEMO_QUICK_LOGIN', false),

    // true: confirmar la revisión publica y notifica en un solo paso (sin cuenta de publicación separada).
    'publish_on_review' => (bool) env('EIRA_PUBLISH_ON_REVIEW', false),

    'demo_accounts' => [
        'operator' => 'operador@eira.example',
        'publisher' => 'publicador@eira.example',
        'admin' => 'admin@eira.example',
    ],

    'display_timezone' => env('EIRA_DISPLAY_TIMEZONE', 'America/Mexico_City'),

];
