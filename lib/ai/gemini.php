<?php
/** Google Gemini (generateContent) adapter. Returns raw text (expected to be a JSON string per the shared prompt contract). */

function ai_gemini_complete(array $cfg, array $business, string $system, array $history, string $userMessage): string
{
    $contents = [];
    foreach ($history as $turn) {
        $contents[] = ['role' => $turn['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $turn['text']]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

    $model = $business['ai_model'] ?: 'gemini-1.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model)
        . ':generateContent?key=' . urlencode($business['ai_api_key']);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $code >= 400) {
        throw new RuntimeException("Gemini request failed ($code) $err " . substr((string)$raw, 0, 500));
    }
    $json = json_decode((string)$raw, true);
    return (string)($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
}
