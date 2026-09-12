<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * Separates column declarations without splitting quoted text or nested expressions.
 *
 * @visibility root
 */
final class DefinitionSegments
{
    /**
     * Returns nonempty declarations in their SQL order.
     *
     * @return list<string>
     */
    public function split(string $sql): array
    {
        $segments = [];
        $current = '';
        $depth = 0;
        $quote = null;
        for ($index = 0; $index < strlen($sql); $index++) {
            $character = $sql[$index];
            if ($quote !== null) {
                $current .= $character;
                if ($character === $quote) {
                    if (($sql[$index + 1] ?? null) === $quote) {
                        $current .= $sql[++$index];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $segments[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $character;
        }
        if (trim($current) !== '') {
            $segments[] = trim($current);
        }
        return $segments;
    }
}
