<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\Value\Term;
use Generator;

/**
 * Keeps branch and call-site correlations inside shared expression choices.
 * @visibility root
 */
final class Choices
{
    /**
     * @param list<array{Term, array<string, bool|string>}> $alternatives
     */
    public function make(array $alternatives): Term
    {
        if (count($alternatives) === 1 && $alternatives[0][1] === [] && $alternatives[0][0]->kind !== 'choice') {
            return $alternatives[0][0];
        }
        $items = [];
        foreach ($alternatives as [$value, $guard]) {
            $attributes = [];
            foreach ($guard as $condition => $selection) {
                $attributes['guard:' . $condition] = $selection;
            }
            $items[] = new Term('alternative', operands: [$value], attributes: $attributes);
        }
        return new Term('choice', operands: $items);
    }

    /**

     * @return Generator<int, array{Term, array<string, bool|string>}, void, void>

     */
    public function alternatives(Term $value): Generator
    {
        $cursor = new Enumeration\Cursor($value);
        while (($next = $cursor->next()) !== null) {
            yield $next;
        }
    }

    /**
     * @param array<string, bool|string> $left
     * @param array<string, bool|string> $right
     * @return array<string, bool|string>|null
     */
    public function merge(array $left, array $right): ?array
    {
        foreach ($right as $key => $value) {
            if (isset($left[$key]) && $left[$key] !== $value) {
                return null;
            }
        }
        return $left + $right;
    }

    /**
     * Distributes small choices and retains large products as an expression DAG.
     * @param list<Term> $operands
     * @param callable(list<Term>): Term $evaluate
     */
    public function apply(string $operation, array $operands, callable $evaluate, int $limit): Term
    {
        $rows = [[[], []]];
        foreach ($operands as $operand) {
            $next = [];
            foreach ($rows as [$values, $guard]) {
                foreach ($this->alternatives($operand) as [$value, $conditions]) {
                    $merged = $this->merge($guard, $conditions);
                    if ($merged !== null) {
                        $next[] = [[...$values, $value], $merged];
                    }
                    if (count($next) > $limit) {
                        return new Term('operation', $operation, $operands, ['reason' => 'ENUMERATION_LIMIT']);
                    }
                }
            }
            $rows = $next;
        }
        return $this->make(array_map(static fn (array $row): array => [Evidence\Provenance::operation($evaluate($row[0]), $row[0], $operation), $row[1]], $rows));
    }
}
