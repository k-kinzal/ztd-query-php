<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Evidence;

use Deriver\Result\Evidence\Node;
use Deriver\Value\Term;
use WeakMap;

/**
 * Collects roots from an unenumerated value DAG without enumerating its combinations.
 * @visibility root
 */
final class Forest
{
    /**
     * Builds a shared proof for a residual expression without enumerating its choices.
     */
    public function root(Term $value): Node
    {
        /** @var WeakMap<Term, Node> $memo */
        $memo = new WeakMap();
        /** @var list<array{Term, bool}> $pending */
        $pending = [[$value, false]];
        while ($pending !== []) {
            [$node, $ready] = array_pop($pending);
            if (isset($memo[$node])) {
                continue;
            }
            if ($node->evidence !== null) {
                $memo[$node] = $node->evidence;
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
                $choice = in_array($node->kind, ['choice', 'unexpanded-choice'], true);
                $memo[$node] = new Node($choice ? 'choice' : 'operation', $inputs, attributes: $choice ? ['selector' => 'residual-alternatives'] : ['operation' => $node->kind]);
            }
        }
        return $memo[$value];
    }
}
