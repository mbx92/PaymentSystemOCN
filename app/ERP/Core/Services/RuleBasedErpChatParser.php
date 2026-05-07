<?php

namespace App\ERP\Core\Services;

use App\Models\ErpChatParserRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RuleBasedErpChatParser
{
    public function parse(string $message, ?Collection $rules = null): array
    {
        $normalizedMessage = Str::of($message)->lower()->squish()->toString();
        $activeRules = ($rules ?? $this->activeRules())->sortBy('priority')->values();

        foreach ($activeRules as $rule) {
            $keywords = collect($rule->keywords)
                ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
                ->map(fn ($keyword) => Str::of($keyword)->lower()->trim()->toString())
                ->values();

            if ($keywords->isEmpty()) {
                continue;
            }

            $allKeywordsMatched = $keywords->every(
                fn ($keyword) => Str::contains($normalizedMessage, $keyword)
            );

            if (! $allKeywordsMatched) {
                continue;
            }

            return [
                'matched' => true,
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'intent_key' => $rule->intent_key,
                    'priority' => $rule->priority,
                    'keywords' => $keywords->values()->all(),
                    'response_text' => $rule->response_text,
                ],
            ];
        }

        return [
            'matched' => false,
            'rule' => null,
        ];
    }

    public function activeRules(): Collection
    {
        return ErpChatParserRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }
}
