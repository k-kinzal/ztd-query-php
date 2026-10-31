<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Natural-language terms, quoted phrases and the second query-expansion pass.
 *
 * The input is the evaluated AGAINST value, not SQL or a parser node. Phrase
 * matching applies to InnoDB. MyISAM treats natural-language punctuation as word
 * boundaries. Expansion retains the original query and adds words from matching
 * documents, with MyISAM counting their occurrences and InnoDB adding unique terms.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/fulltext-query-expansion.html.
 *
 * @visibility MySqlMemory
 */
final class Natural
{
    /**
     * @param list<list<string>> $terms Word or phrase alternatives in their observed order
     */
    public function __construct(public readonly array $terms, public readonly Words $words, public readonly bool $myIsam)
    {
    }

    /**
     * Decodes the runtime search value before assigning positions to quoted phrases.
     */
    public static function read(string $text, Charset $charset, Words $words, bool $myIsam): self
    {
        $decoded = \MySqlMemory\Value\Encoding::convert($text, $charset, Charset::known('utf8mb4'));
        preg_match_all('/"([^"\\n]*)(?:"|$)|([^"\\n]+)/u', $decoded, $parts, PREG_SET_ORDER);
        $terms = [];
        foreach ($parts as $part) {
            $phrase = str_starts_with($part[0], '"') && !$myIsam;
            $tokens = $words->split($part[2] ?? $part[1] ?? '', Charset::known('utf8mb4'));
            if ($phrase) {
                $terms[] = $tokens;
            } else {
                foreach ($tokens as $word) {
                    $terms[] = [$word];
                }
            }
        }
        return new self($terms, $words, $myIsam);
    }

    /**
     * @return array<string, int> Original search occurrence counts
     */
    public function frequencies(): array
    {
        return $this->words->frequencies(array_merge([], ...$this->terms));
    }

    /**
     * @return array<string, int> Occurrence counts for alternatives matching this document
     */
    public function matching(Document $document): array
    {
        $all = $this->frequencies();
        $matching = [];
        foreach ($this->terms as $term) {
            if (count($term) === 1 || $document->phrase($term)) {
                foreach ($this->words->frequencies($term) as $word => $count) {
                    $matching[$word] = $all[$word];
                }
            }
        }
        return $matching;
    }

    /**
     * Scores the same query against the complete visible collection.
     */
    public function score(Document $document, Collection $collection, bool $expand): float
    {
        $query = $this->matching($document);
        if ($expand) {
            foreach ($this->expansion($collection) as $word => $count) {
                $query[$word] = ($query[$word] ?? 0) + $count;
            }
        }
        return (new Ranking())->natural($document->frequencies(), $collection->frequencies(), count($collection->documents), $query, $this->myIsam);
    }

    /**
     * @return array<string, int> Additional terms from the first pass's matching documents
     */
    public function expansion(Collection $collection): array
    {
        $extra = [];
        $original = $this->frequencies();
        $ranked = [];
        foreach ($collection->documents as $index => $document) {
            if (array_intersect_key($document->frequencies(), $this->matching($document)) !== []) {
                $ranked[$index] = $this->score($document, $collection, false);
            }
        }
        arsort($ranked, SORT_NUMERIC);
        foreach (array_slice(array_keys($ranked), 0, 20) as $index) {
            foreach ($collection->documents[$index]->frequencies() as $word => $count) {
                if ($this->myIsam) {
                    $extra[$word] = ($extra[$word] ?? 0) + $count;
                } elseif (!isset($original[$word])) {
                    $extra[$word] = 1;
                }
            }
        }
        ksort($extra, SORT_STRING);
        return $extra;
    }
}
