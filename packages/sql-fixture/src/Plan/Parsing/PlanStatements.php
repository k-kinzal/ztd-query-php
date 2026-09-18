<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Parsing;

use SqlFixture\Plan\Exception\UnbalancedBracketsException;

/**
 * Splits plans outside quoted identifiers, literals, groups and choice blocks.
 * @visibility root
 */
final class PlanStatements
{
    /**
     * @return array<int, string>
     * @throws UnbalancedBracketsException
     */
    public function split(string $plan): array
    {
        $statements = [];
        $start = 0;
        $stack = [];
        for ($offset = 0; $offset < strlen($plan); $offset++) {
            $character = $plan[$offset];
            if (in_array($character, ["'", '"', '`'], true)) {
                $offset = $this->quotedEnd($plan, $offset);
                continue;
            }
            if (in_array($character, ['[', '(', '{'], true)) {
                $stack[] = $character;
            } elseif (in_array($character, [']', ')', '}'], true)) {
                $expected = match ($character) {
                    ']' => '[', ')' => '(', '}' => '{'
                };
                if (array_pop($stack) !== $expected) {
                    throw new UnbalancedBracketsException($plan);
                }
            }
            if ($stack === [] && in_array($character, [',', "\n", ';'], true)) {
                $statements[] = trim(substr($plan, $start, $offset - $start));
                $start = $offset + 1;
            }
        }
        if ($stack !== []) {
            throw new UnbalancedBracketsException($plan);
        }
        $statements[] = trim(substr($plan, $start));
        return array_filter($statements, static fn (string $statement): bool => $statement !== '');
    }

    /**
     * Finds a closing quote, allowing doubled quotes inside its content.
     * @throws UnbalancedBracketsException
     */
    public function quotedEnd(string $source, int $start): int
    {
        $quote = $source[$start];
        for ($offset = $start + 1; $offset < strlen($source); $offset++) {
            if ($source[$offset] !== $quote) {
                continue;
            }
            if (($source[$offset + 1] ?? null) === $quote) {
                $offset++;
                continue;
            }
            return $offset;
        }
        throw new UnbalancedBracketsException($source);
    }
}
