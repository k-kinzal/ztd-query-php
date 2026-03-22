<?php

declare(strict_types=1);

namespace Spec\Support;

use JsonException;
use SqlFaker\Contract\Grammar;

final class GrammarFingerprint
{
    public static function sha256(Grammar $grammar): string
    {
        try {
            return hash('sha256', json_encode(self::normalize($grammar), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $e) {
            throw new \InvalidArgumentException('Unable to encode grammar fingerprint payload.', 0, $e);
        }
    }

    /**
     * @return array{
     *     start_symbol: string,
     *     rules: array<string, list<list<string>>>
     * }
     */
    public static function normalize(Grammar $grammar): array
    {
        $rules = [];
        foreach ($grammar->rules as $ruleName => $rule) {
            $rules[$ruleName] = array_map(
                static fn (\SqlFaker\Contract\Production $alternative): array => $alternative->sequence(),
                $rule->alternatives,
            );
        }

        return [
            'start_symbol' => $grammar->startSymbol,
            'rules' => $rules,
        ];
    }
}
