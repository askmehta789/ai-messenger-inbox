<?php
/** OpenAI Chat Completions adapter. Returns raw text (expected to be a JSON string per the shared prompt contract). */

function ai_openai_complete(array $cfg, array $business, string $system, array $history, string $userMessage): string
{
    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($history as $turn) {
        $messages[] = ['role' => $turn['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $turn['text']];
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $business['ai_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $business['ai_model'] ?: 'gpt-4o-mini',
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.7,
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $code >= 400) {
        throw new RuntimeException("OpenAI request failed ($code) $err " . substr((string)$raw, 0, 500));
    }
    $json = json_decode((string)$raw, true);
    return (string)($json['choices'][0]['message']['content'] ?? '');
}
