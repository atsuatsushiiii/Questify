<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Google OpenID Connect sign-in settings.
 *
 * Create an OAuth 2.0 client in Google Cloud Console, add the callback URL as
 * an authorized redirect URI, and set GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET.
 * GOOGLE_REDIRECT_URI is optional; it defaults to APP_URL/auth/google/callback.
 */
return [
    'google' => [
        'client_id' => (string) env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => (string) env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri' => (string) env('GOOGLE_REDIRECT_URI', ''),
        'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_endpoint' => 'https://oauth2.googleapis.com/token',
        'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
        'scopes' => ['openid', 'email', 'profile'],
        'state_ttl' => 600,
        'timeout' => 10,
    ],
];
