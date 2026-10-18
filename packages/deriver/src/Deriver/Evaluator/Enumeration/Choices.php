<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Enumeration;

use Closure;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Generator;
use WeakMap;

/**
 * Keeps branch and call-site correlations inside shared expression choices.
 * @visibility parent
 */
final class Choices
{
    /**
     * Shares proofs when distributing and folding alternatives.
     */
    public function __construct(private readonly \Deriver\Deriver\Evaluator\Evidence\Provenance $provenance = new \Deriver\Deriver\Evaluator\Evidence\Provenance(), private readonly ?\Deriver\Deriver\Evaluator\Search\Search $search = null)
    {
    }

    /**
     * @param list<array{Term, array<string, bool|string>}> $alternatives
     */
    public function make(array $alternatives): Term
    {
        if (count($alternatives) === 1 && $alternatives[0][1] === [] && $alternatives[0][0]->kind !== TermKind::Choice) {
            return $alternatives[0][0];
        }
        $items = [];
        foreach ($alternatives as [$value, $guard]) {
            $attributes = [];
            foreach ($guard as $condition => $selection) {
                $attributes['guard:' . $condition] = $selection;
            }
            $items[] = new Term(TermKind::Alternative, operands: [$value], attributes: $attributes);
        }
        return new Term(TermKind::Choice, operands: $items);
    }

    /**

     * @return Generator<int, array{Term, array<string, bool|string>}, void, void>

     */
    public function alternatives(Term $value): Generator
    {
        $cursor = new Enumerator($value);
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
                        return new Term(TermKind::Operation, $operation, $operands, ['reason' => 'ENUMERATION_LIMIT']);
                    }
                }
            }
            $rows = $next;
        }
        $branches = array_map(fn (array $row): Closure => fn (): Term => $this->provenance->operation($evaluate($row[0]), $row[0], $operation), $rows);
        $values = $this->search?->all($branches) ?? array_map(static fn (Closure $branch): Term => $branch(), $branches);
        return $this->make(array_map(static fn (Term $value, array $row): array => [$value, $row[1]], $values, $rows));
    }
    /**
     * Transforms each shared choice once per operation, preserving branch guards and distinct proofs.
     * @param Closure(Term): Term $transform
     */
    public function map(Term $value, Closure $transform): Term
    {
        /** @var WeakMap<Term, Term> $memo */
        $memo = new WeakMap();
        /** @var list<array{Term, bool}> $pending */
        $pending = [[$value, false]];
        while ($pending !== []) {
            [$node, $ready] = array_pop($pending);
            if (isset($memo[$node])) {
                continue;
            }
            if ($node->kind !== TermKind::Choice) {
                $memo[$node] = $transform($node);
            } elseif (!$ready) {
                $pending[] = [$node, true];
                foreach ($node->operands as $item) {
                    $pending[] = [$item->operands[0], false];
                }
            } else {
                $items = [];
                foreach ($node->operands as $key => $item) {
                    $items[$key] = new Term(TermKind::Alternative, operands: [$memo[$item->operands[0]]], attributes: $item->attributes);
                }
                $memo[$node] = new Term(TermKind::Choice, operands: $items);
            }
        }
        return $memo[$value];
    }
}
