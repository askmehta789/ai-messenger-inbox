<?php
/**
 * Copy to config.php and fill in. Never commit config.php.
 */
return [
    // --- Meta app (developers.facebook.com -> your app -> Settings -> Basic) ---
    // This must be a NEW, separate Meta App from any other bot you run.
    'app_id'       => 'PASTE_APP_ID',
    'app_secret'   => 'PASTE_APP_SECRET',              // used to verify X-Hub-Signature-256
    'verify_token' => 'PASTE_A_RANDOM_STRING',          // same string you type in the Webhooks setup
    'graph_version' => 'v23.0',

    // --- Database (cPanel -> MySQL Databases) ---
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=YOURDB_ai_inbox;charset=utf8mb4',
        'user' => 'YOURDB_ai_inbox_user',
        'pass' => 'CHANGE_ME',
    ],

    // --- Dashboard login: generate with  php -r "echo password_hash('yourpass', PASSWORD_DEFAULT);" ---
    // Seed this into the admin_users table (see schema.sql); this config value is unused after seeding.

    // --- Master key to encrypt/decrypt AI provider API keys stored in the businesses table ---
    // Generate once with:  php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"
    // Losing/changing this makes previously-saved API keys undecryptable — back it up.
    'crypto_key' => 'PASTE_BASE64_KEY',

    'timezone' => 'Asia/Kathmandu',
    'log_file' => __DIR__ . '/storage/bot.log',
];
