<?php
/**
 * Provider-agnostic AI reply generation.
 * Every adapter returns the same shape: ['reply'=>string, 'name'=>?string, 'phone'=>?string, 'address'=>?string, 'product'=>?string]
 */

require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/anthropic.php';
require_once __DIR__ . '/gemini.php';
require_once __DIR__ . '/../graph.php'; // ai_log()

function ai_build_system_prompt(array $business, array $known): string
{
    $missing = [];
    foreach (['name' => 'full name', 'phone' => 'phone number', 'address' => 'delivery address', 'product' => 'which product they want'] as $field => $label) {
        if (empty($known[$field])) $missing[] = $label;
    }
    $knownLines = [];
    foreach (['name', 'phone', 'address', 'product'] as $field) {
        if (!empty($known[$field])) $knownLines[] = "- $field: {$known[$field]}";
    }

    $lines = [
        'You are a helpful sales assistant chatting with a customer over Facebook Messenger.',
        'Always reply in Nepali (Devanagari script), in a warm, natural, conversational tone. Keep replies short (1-3 sentences).',
        'Your goal is to collect exactly these four pieces of information from the customer, one at a time, without being pushy: full name, phone number, delivery address, and which product they want.',
        $knownLines ? "Already known about this customer:\n" . implode("\n", $knownLines) : 'Nothing is known about this customer yet.',
        $missing ? 'Still missing: ' . implode(', ', $missing) . '. Ask for ONE missing item at a time, in the next reply.' : 'All four fields are known — thank the customer and confirm their order naturally, do not ask for the same info again.',
    ];
    if (!empty($business['instructions'])) {
        $lines[] = "Business context and instructions from the store owner:\n" . $business['instructions'];
    }
    $lines[] = 'Respond with ONLY a single JSON object, no markdown fences, no extra text, in exactly this shape: '
        . '{"reply": "<Nepali text to send the customer>", "name": "<string or null>", "phone": "<string or null>", "address": "<string or null>", "product": "<string or null>"}. '
        . 'Only fill a field if the customer just gave you that exact piece of information in this message; otherwise use null for it.';

    return implode("\n\n", $lines);
}

/** Extract the first {...} JSON object from a model's raw text output, tolerating markdown fences. */
function ai_parse_json_reply(string $raw): array
{
    $text = trim($raw);
    $text = preg_replace('/^```(?:json)?/i', '', $text);
    $text = preg_replace('/```$/', '', $text);
    $text = trim($text);
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end < $start) {
        return ['reply' => $raw, 'name' => null, 'phone' => null, 'address' => null, 'product' => null];
    }
    $json = json_decode(substr($text, $start, $end - $start + 1), true);
    if (!is_array($json)) {
        return ['reply' => $raw, 'name' => null, 'phone' => null, 'address' => null, 'product' => null];
    }
    return [
        'reply'   => (string)($json['reply'] ?? ''),
        'name'    => $json['name'] ?? null,
        'phone'   => $json['phone'] ?? null,
        'address' => $json['address'] ?? null,
        'product' => $json['product'] ?? null,
    ];
}

/**
 * @param array $business Decrypted business row: ai_provider, ai_api_key, ai_model, instructions
 * @param array $history  [['role'=>'user'|'assistant','text'=>string], ...] chronological, most recent last
 * @param array $known     Currently known lead fields: name/phone/address/product
 */
function ai_generate_reply(array $cfg, array $business, array $history, string $userMessage, array $known): array
{
    $system = ai_build_system_prompt($business, $known);
    $provider = $business['ai_provider'] ?? '';

    try {
        $raw = match ($provider) {
            'openai'    => ai_openai_complete($cfg, $business, $system, $history, $userMessage),
            'anthropic' => ai_anthropic_complete($cfg, $business, $system, $history, $userMessage),
            'gemini'    => ai_gemini_complete($cfg, $business, $system, $history, $userMessage),
            default     => throw new RuntimeException("Unknown AI provider: $provider"),
        };
    } catch (Throwable $e) {
        ai_log($cfg, 'AI call failed: ' . $e->getMessage());
        return ['reply' => null, 'name' => null, 'phone' => null, 'address' => null, 'product' => null];
    }

    return ai_parse_json_reply($raw);
}
