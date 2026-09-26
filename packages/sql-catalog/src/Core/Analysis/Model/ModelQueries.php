<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Model;

use PhpParser\Node\Expr;
use SqlCatalog\Core\Analysis\Derivation\Deriver;
use SqlCatalog\Core\Analysis\Derivation\Solution;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Extension\Model\QueryModelInterface;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Type\TypeShape;

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
