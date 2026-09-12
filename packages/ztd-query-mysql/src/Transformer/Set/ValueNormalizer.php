<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Set;

/**
 * Value Normalizer.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ValueNormalizer
{
    /**
     * Normalize Set Value for the supplied MySQL input.
     */
    public function normalizeSetValue(string $value, string $mysqlType): string
    {
        if ($value === '') {
            return $value;
        }

        $declared = $this->extractSetMembers($mysqlType);

        if ($declared === []) {
            return $value;
        }

        $rank = [];
        foreach ($declared as $index => $name) {
            $rank[$name] = $index;
        }

        $parts = explode(',', $value);
        $normalized = [];
        foreach ($parts as $part) {
            $candidate = trim($part);
            if ($candidate === '' || !array_key_exists($candidate, $rank)) {
                continue;
            }
            $normalized[$candidate] = true;
        }

        if ($normalized === []) {
            return $value;
        }

        $members = array_keys($normalized);
        usort(
            $members,
            static fn (string $a, string $b): int => $rank[$a] <=> $rank[$b]
        );

        return implode(',', $members);
    }

    /**
     * @return list<string>
     */
    public function extractSetMembers(string $type): array
    {
        if (preg_match('/^SET\((.*)\)$/i', trim($type), $matches) !== 1) {
            return [];
        }

        $definition = $matches[1];
        $members = [];

        if (preg_match_all('/\'((?:\'\'|[^\'])*)\'|"((?:""|[^"])*)"/', $definition, $valueMatches, PREG_SET_ORDER) > 0) {
            foreach ($valueMatches as $tokenMatch) {
                $singleQuoted = $tokenMatch[1] ?? '';
                $doubleQuoted = $tokenMatch[2] ?? '';
                if ($singleQuoted !== '') {
                    $members[] = str_replace("''", "'", $singleQuoted);
                    continue;
                }
                $members[] = str_replace('""', '"', $doubleQuoted);
            }

            return $members;
        }

        foreach (explode(',', $definition) as $token) {
            $trimmed = trim($token, " \t\n\r\0\x0B'\"");
            if ($trimmed !== '') {
                $members[] = $trimmed;
            }
        }

        return $members;
    }
}
