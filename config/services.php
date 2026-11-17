<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI'),
    ],

    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
        'redirect' => env('TWITCH_REDIRECT_URI'),
    ],

    'steam' => [
        'client_id' => null,
        'client_secret' => env('STEAM_API_KEY'),
        'redirect' => env('STEAM_REDIRECT_URI'),
        'allowed_hosts' => [parse_url(env('APP_URL'), PHP_URL_HOST)],
    ],

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
    ],

    'demome' => [
        'api_token' => env('DEMOME_API_TOKEN'),
    ],

    'q3df' => [
        'key' => env('Q3DF_API_KEY'),
    ],

    'defraglive' => [
        // Shared bearer token the DefragLive WebSocket bridge uses to POST
        // chat / serverstate into the web (DefragliveIngestController).
        'ingest_token' => env('DEFRAGLIVE_INGEST_TOKEN'),
        // Filesystem dir the public tw.defrag.racing/{console,serverstate}.json
        // are served from. defraglive:write-json rewrites those two files here
        // from the DB every minute, replacing the old cron `docker cp` out of
        // the bridge container. Empty = writer disabled (so it can't fight the
        // legacy cron until the VPS cutover).
        'public_path' => env('DEFRAGLIVE_PUBLIC_PATH', ''),
        // How many recent messages console.json keeps (parity with the
        // bridge's old last-100 behaviour the extension expects).
        'console_limit' => (int) env('DEFRAGLIVE_CONSOLE_LIMIT', 100),
    ],

    'storage_vps' => [
        'host'     => env('STORAGE_VPS_HOST'),
        'port'     => (int) env('STORAGE_VPS_PORT', 2258),
        'user'     => env('STORAGE_VPS_USER', 'provisioner'),
        'key_path' => env('STORAGE_VPS_KEY_PATH'),
    ],

    'storage_vps_dl' => [
        'host'     => env('STORAGE_VPS_DL_HOST'),
        'port'     => (int) env('STORAGE_VPS_DL_PORT', 2258),
        'user'     => env('STORAGE_VPS_DL_USER', 'dlbrowser'),
        'key_path' => env('STORAGE_VPS_DL_KEY_PATH'),
        'root'     => env('STORAGE_VPS_DL_ROOT', '/www/downloads'),
    ],

];
