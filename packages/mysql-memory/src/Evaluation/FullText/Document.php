<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

/**
 * The word positions and indexed frequencies of one visible full-text document.
 *
 * @visibility MySqlMemory
 */
final class Document
{
    /**
     * @param list<string> $tokens Decoded words, including excluded index words
     */
    public function __construct(public readonly array $tokens, public readonly Words $words)
    {
    }

    /**
     * @return array<string, int> Indexed frequencies under the column collation
     */
    public function frequencies(): array
    {
        return $this->words->frequencies($this->tokens);
    }

    /**
     * Tests consecutive positions, including intervening short words and stopwords.
     *
     * @param list<string> $phrase Decoded phrase words
     */
    public function phrase(array $phrase): bool
    {
        if ($phrase === []) {
            return false;
        }
        $keys = array_map($this->words->key(...), $this->tokens);
        $expected = array_map($this->words->key(...), $phrase);
        for ($position = 0; $position <= count($keys) - count($expected); $position++) {
            if (array_slice($keys, $position, count($expected)) === $expected) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tests an unordered word window while keeping repeated terms at distinct positions.
     *
     * @param list<string> $phrase Decoded search words
     */
    public function proximity(array $phrase, int $distance): bool
    {
        if ($distance <= 0 || $phrase === []) {
            return false;
        }
        $keys = array_map($this->words->key(...), $this->tokens);
        $expected = array_count_values(array_map($this->words->key(...), $phrase));
        foreach (array_keys($keys) as $position) {
            $actual = array_count_values(array_slice($keys, $position, $distance));
            $matched = true;
            foreach ($expected as $word => $count) {
                $matched = $matched && ($actual[$word] ?? 0) >= $count;
            }
            if ($matched) {
                return true;
            }
        }
        return false;
    }
}
