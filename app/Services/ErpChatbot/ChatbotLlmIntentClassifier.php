<?php

namespace App\Services\ErpChatbot;

use App\Models\ErpChatParserRule;
use App\Models\ErpSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotLlmIntentClassifier
{
    public const DEFAULT_API_URL = 'https://api.deepseek.com/v1/chat/completions';

    public const DEFAULT_MODEL = 'deepseek-chat';

    /**
     * Classify user message into a whitelisted intent_key, or null if uncertain / disabled.
     *
     * @param  list<array{role: string, text: string}>  $history
     */
    public function classify(string $message, array $history = []): ?string
    {
        $setting = ErpSetting::query()->first();
        if (! $setting || ! $setting->chatbot_llm_enabled) {
            return null;
        }

        $apiUrl = trim((string) ($setting->chatbot_llm_api_url ?: self::DEFAULT_API_URL));
        $apiKey = trim((string) ($setting->chatbot_llm_api_key ?? ''));
        $model = trim((string) ($setting->chatbot_llm_model ?: self::DEFAULT_MODEL));

        if ($apiUrl === '' || $apiKey === '') {
            return null;
        }

        $intents = ErpChatParserRule::validIntentKeys();
        $systemPrompt = $this->systemPrompt($intents);
        $userPayload = $this->userPayload($message, $history);

        try {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->post($apiUrl, [
                    'model' => $model,
                    'temperature' => 0,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPayload],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Chatbot LLM connection failed', ['message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Chatbot LLM API error', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return null;
        }

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        $intent = $this->extractIntent($content, $intents);

        return $intent;
    }

    /**
     * @param  list<string>  $intents
     */
    private function systemPrompt(array $intents): string
    {
        $list = implode(', ', $intents);

        return <<<PROMPT
You are an intent classifier for an Indonesian ERP chatbot.
Return ONLY valid JSON: {"intent_key":"<key>"} or {"intent_key":null}.
Choose intent_key from this whitelist only: {$list}.
If the message is unclear or not about ERP data/actions, return null.
Do not invent SQL, numbers, or answers. Classification only.
Examples:
- "berapa sisa stok kabel lan" -> stock_lookup
- "harga standing pouch" -> product_price_lookup
- "penjualan hari ini" / "pos hari ini" -> pos_sales_today
- "cashflow bulan ini" -> cashflow_month
- "invoice belum dibayar" -> invoice_unpaid_list
- "project yang sedang jalan" -> project_active_list
- "bantuan" -> help
PROMPT;
    }

    /**
     * @param  list<array{role: string, text: string}>  $history
     */
    private function userPayload(string $message, array $history): string
    {
        $recent = collect($history)
            ->slice(-4)
            ->map(fn (array $item): string => ($item['role'] ?? 'user').': '.($item['text'] ?? ''))
            ->implode("\n");

        if ($recent === '') {
            return "Message:\n{$message}";
        }

        return "Recent chat:\n{$recent}\n\nCurrent message:\n{$message}";
    }

    /**
     * @param  list<string>  $intents
     */
    private function extractIntent(string $content, array $intents): ?string
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && array_key_exists('intent_key', $decoded)) {
                $key = $decoded['intent_key'];
                if ($key === null || $key === '' || $key === 'null') {
                    return null;
                }
                $key = (string) $key;

                return in_array($key, $intents, true) ? $key : null;
            }
        }

        $normalized = Str::of($content)->trim()->trim('"')->trim("'")->toString();

        return in_array($normalized, $intents, true) ? $normalized : null;
    }
}
