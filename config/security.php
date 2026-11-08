<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return [
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Content-Security-Policy' => implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self'",
            // Google sign-in starts as a same-origin form POST; the server
            // answers with a redirect to Google's authorization endpoint, and
            // form-action also governs that redirect.
            "form-action 'self' https://accounts.google.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]),
    ],
];
