<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

/**
 * Boolean filtering and relevance over an already resolved document collection.
 *
 * @visibility MySqlMemory
 */
final class BooleanSearch
{
    /**
     * @param list<Term> $terms The runtime Boolean search expression
     */
    public function __construct(public readonly array $terms, public readonly Words $words, public readonly bool $myIsam)
    {
    }

    /**
     * @return array<string, int> Search occurrences across all Boolean levels
     * @param list<Term> $terms
     */
    public function frequencies(array $terms, Collection $collection): array
    {
        $result = [];
        foreach ($terms as $term) {
            foreach ($term->children === [] ? $term->frequencies($this->words, $collection) : $this->frequencies($term->children, $collection) as $word => $count) {
                $result[$word] = ($result[$word] ?? 0) + $count;
            }
        }
        return $result;
    }

    /**
     * Returns zero for a rejected document, preserving negative relevance for matches.
     */
    public function score(Document $document, Collection $collection): float
    {
        $seen = [];
        [$matches, $score] = $this->group($this->terms, $document, $collection, $this->frequencies($this->terms, $collection), $seen);
        return $matches ? (new Ranking())->single($score) : 0.0;
    }

    /**
     * @param list<Term> $terms
     * @param array<string, int> $query
     * @param array<string, true> $seen Terms whose relevance was already accumulated
     * @return array{bool, float} Membership and relevance, kept separate at zero score
     */
    public function group(array $terms, Document $document, Collection $collection, array $query, array &$seen): array
    {
        $score = 0.0;
        $adjustment = 0.0;
        $values = [];
        $positive = false;
        $required = [];
        $optional = 0.0;
        foreach ($terms as $term) {
            $noise = str_contains($term->operator, '~');
            if ($noise && !$positive && !$this->myIsam) {
                continue;
            }
            [$matches, $value] = $this->value($term, $document, $collection, $query, $seen);
            $exclude = str_contains($term->operator, '-');
            $require = str_contains($term->operator, '+');
            if (($exclude && $matches) || ($require && !$matches)) {
                return [false, 0.0];
            }
            if (!$matches) {
                continue;
            }
            if ($this->myIsam) {
                $value = $this->weighted($value, $term->operator);
            } else {
                $adjustment = $this->weighted($adjustment, $term->operator);
            }
            $values[] = $value;
            $score = (new Ranking())->single($score + $value);
            $positive = $positive || !$noise;
            if ($require) {
                $required[] = $value;
            } else {
                $optional += $value;
            }
        }
        if ($this->myIsam && $required !== []) {
            $score = max($required) + $optional / 3;
        }
        return [$positive, $this->myIsam ? $score : array_reduce($values, static fn (float $score, float $value): float => (new Ranking())->single($score + $value), $adjustment)];
    }

    /**
     * @param array<string, int> $query
     * @param array<string, true> $seen
     * @return array{bool, float} A matched term or nested group's unmodified relevance
     */
    public function value(Term $term, Document $document, Collection $collection, array $query, array &$seen): array
    {
        if ($term->children !== []) {
            [$matches, $score] = $this->group($term->children, $document, $collection, $query, $seen);
            if ($this->myIsam && str_contains($term->operator, '+') && $score > 1) {
                $score = 1 + ($score - 1) / 3;
            }
            return [$matches, $score];
        }
        if (!$term->matches($document, $collection)) {
            return [false, 0.0];
        }
        if ($this->myIsam) {
            return [true, 1.0];
        }
        $terms = array_intersect_key($query, $term->frequencies($this->words, $collection));
        $terms = array_diff_key($terms, $seen);
        foreach ($terms as $word => $count) {
            $seen[$word] = true;
        }
        return [true, (new Ranking())->natural($document->frequencies(), $collection->frequencies(), count($collection->documents), $terms, false)];
    }

    /**
     * Applies the documented increase, decrease and negative contribution operators.
     */
    public function weighted(float $score, string $operator): float
    {
        $increase = substr_count($operator, '>') - substr_count($operator, '<');
        if ($this->myIsam) {
            return (new Ranking())->single($score * (1.5 ** $increase) * (str_contains($operator, '~') ? -0.5 : 1.0));
        }
        return (new Ranking())->single($score + $increase - (str_contains($operator, '~') ? 1 : 0));
    }
}
