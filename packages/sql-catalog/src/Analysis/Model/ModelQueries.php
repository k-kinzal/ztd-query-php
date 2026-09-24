<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Model;

use PhpParser\Node\Expr;
use SqlCatalog\Analysis\Derivation\Deriver;
use SqlCatalog\Analysis\Derivation\Solution;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Extension\Model\QueryModelInterface;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Derives extension-requested expressions and keeps their evidence on compiled statements.
 *
 * @visibility root
 */
final class ModelQueries
{
    /**
     * @return list<Solution> SQL and bindings with the ordinary derivation provenance.
     */
    public function solve(Expr\CallLike $call, ?QueryModelInterface $model, Deriver $deriver): array
    {
        if ($model === null) {
            return [new Solution([Domain::opaque(TypeShape::of(['string']), Origin::Call, 'Statement model is not registered'), Domain::unknown()], [])];
        }
        $solutions = [];
        foreach ($deriver->solve($call, $model->inputs($call)) as $solution) {
            foreach ($model->statements($call, $solution->values) as $output) {
                $solutions[] = new Solution([$output->sql, $output->bindings], $solution->through, $solution->truncated || $output->truncated, $solution->combined || $output->combined);
            }
        }

        return $solutions;
    }
}
