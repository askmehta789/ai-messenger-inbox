<?php
/** Anthropic Messages API adapter. Returns raw text (expected to be a JSON string per the shared prompt contract). */

function ai_anthropic_complete(array $cfg, array $business, string $system, array $history, string $userMessage): string
{
    $messages = [];
    foreach ($history as $turn) {
        $messages[] = ['role' => $turn['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $turn['text']];
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $business['ai_api_key'],
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $business['ai_model'] ?: 'claude-3-5-haiku-20241022',
            'system' => $system,
            'messages' => $messages,
            'max_tokens' => 500,
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $code >= 400) {
        throw new RuntimeException("Anthropic request failed ($code) $err " . substr((string)$raw, 0, 500));
    }
    $json = json_decode((string)$raw, true);
    return (string)($json['content'][0]['text'] ?? '');
}
