<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Evidence;

use Deriver\Deriver\Evaluator\Term;
use WeakMap;

/**
 * Collects roots from an unenumerated value DAG without enumerating its combinations.
 * @visibility parent
 * @visibility Deriver\Deriver\Publication
 */
final class Forest
{
    /**
     * Receives the proof ledger belonging to this analysis.
     */
    public function __construct(private readonly Provenance $provenance)
    {
    }

    /**
     * Builds a shared proof for a residual expression without enumerating its choices.
     */
    public function root(Term $value): Node
    {
        /**
         * @var WeakMap<Term, Node> $memo
         */
        $memo = $this->provenance->roots;
        /**
         * @var list<array{Term, bool}> $pending
         */
        $pending = [[$value, false]];
        while ($pending !== []) {
            [$node, $ready] = array_pop($pending);
            if (isset($memo[$node])) {
                continue;
            }
            if ($this->provenance->get($node) !== null) {
                $memo[$node] = $this->provenance->get($node);
            } elseif (!$ready) {
                $pending[] = [$node, true];
                foreach ($node->operands as $child) {
                    $pending[] = [$child, false];
                }
            } else {
                $inputs = [];
                foreach ($node->operands as $key => $child) {
                    $inputs['operand:' . $key] = $memo[$child];
                }
                $choice = in_array($node->kind, [\Deriver\Deriver\Evaluator\TermKind::Choice, \Deriver\Deriver\Evaluator\TermKind::UnexpandedChoice], true);
                $memo[$node] = new Node($choice ? EvidenceKind::Choice : EvidenceKind::Operation, $inputs, attributes: $choice ? ['selector' => 'residual-alternatives'] : ['operation' => $node->kind->value, ...($node->kind === \Deriver\Deriver\Evaluator\TermKind::Alternative ? $node->attributes : [])]);
            }
        }
        return $memo[$value];
    }

}
