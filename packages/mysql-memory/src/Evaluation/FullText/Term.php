<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

/**
 * A term of an evaluated Boolean search value, independent of SQL syntax.
 *
 * @visibility MySqlMemory
 */
final class Term
{
    /**
     * @param list<string> $words Decoded search words or a quoted phrase
     * @param list<Term> $children Parenthesized alternatives
     * @param string $operator Required, excluded, noise or relevance modifiers
     */
    public function __construct(public readonly array $words = [], public readonly array $children = [], public readonly string $operator = '', public readonly bool $prefix = false, public readonly ?int $distance = null, public readonly bool $phrase = false)
    {
    }

    /**
     * @return array<string, int> Indexed terms, expanding a trailing wildcard against the dictionary
     */
    public function frequencies(Words $words, Collection $collection): array
    {
        if (!$this->prefix) {
            return $words->frequencies($this->words);
        }
        $prefix = $this->words[0] ?? '';
        $key = $words->key($prefix);
        $terms = [];
        foreach ($collection->documents as $document) {
            foreach ($document->tokens as $token) {
                if ($words->indexed($token) && $words->key(mb_substr($token, 0, mb_strlen($prefix, 'UTF-8'), 'UTF-8')) === $key) {
                    $terms[$words->key($token)] = 1;
                }
            }
        }
        return $terms;
    }

    /**
     * Tests word presence and optional phrase or proximity constraints.
     */
    public function matches(Document $document, Collection $collection): bool
    {
        if (array_intersect_key($document->frequencies(), $this->frequencies($document->words, $collection)) === []) {
            return false;
        }
        if ($this->distance !== null) {
            return $document->proximity($this->words, $this->distance);
        }
        return !$this->phrase || $document->phrase($this->words);
    }
}
