<?php
/** Encrypt/decrypt AI provider API keys at rest using libsodium (bundled in PHP core). */

function ai_encrypt(string $plaintext, string $base64Key): string
{
    $key = base64_decode($base64Key, true);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
    return base64_encode($nonce . $cipher);
}

function ai_decrypt(?string $encoded, string $base64Key): ?string
{
    if (!$encoded) return null;
    $key = base64_decode($base64Key, true);
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
    return $plain === false ? null : $plain;
}
