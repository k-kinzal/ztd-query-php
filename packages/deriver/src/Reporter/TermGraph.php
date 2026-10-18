<?php

declare(strict_types=1);

namespace Deriver\Reporter;

use Deriver\Deriver\Evaluator\Term;
use SplObjectStorage;

/**
 * Represents shared residual expressions once, using report-local node references.
 * @visibility namespace
 */
final class TermGraph
{
    /**
     * @return array{root: int, nodes: list<array{kind: string, literal: scalar|null, operands: array<int|string, int>, attributes: array<string, scalar|null>}>}
     */
    public function encode(Term $root): array
    {
        /** @var SplObjectStorage<Term, int> $ids */
        $ids = new SplObjectStorage();
        $ids[$root] = 0;
        $terms = [$root];
        $nodes = [];
        for ($index = 0; $index < count($terms); ++$index) {
            $term = $terms[$index];
            $operands = [];
            foreach ($term->operands as $key => $operand) {
                if (!isset($ids[$operand])) {
                    $ids[$operand] = count($terms);
                    $terms[] = $operand;
                }
                $operands[$key] = $ids[$operand];
            }
            $nodes[] = ['kind' => $term->kind->value, 'literal' => $term->literal, 'operands' => $operands, 'attributes' => $term->attributes];
        }
        return ['root' => 0, 'nodes' => $nodes];
    }
}
