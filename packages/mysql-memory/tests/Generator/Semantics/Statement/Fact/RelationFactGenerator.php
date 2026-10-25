<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use SqlSemantics\Statement\Fact\RelationFact as Model;
use SqlSemantics\Statement\Operation;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Generates a RelationFact together with the published operation that owns it.
 */
final class RelationFactGenerator implements Inhabitant
{
    /**
     * The original analyzed operation retained for execution.
     */
    public ?Operation $operation = null;

    /**
     * Samples a complete semantic graph, retaining the identities used by its facts.
     */
    public function sample(Choices $choices): Model
    {
        $value = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $choices->integer(0, 100000));
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($value)]);
        $relation = new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable($query, new \SqlSemantics\Statement\Identifier\Name('input_' . $choices->integer(0, 100000)));
        $select = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral($choices->integer(0, 1) === 1))], from: $relation);
        $this->operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from(new \SqlSemantics\Platform\MySql\Statement\QueryRoot($select), $choices);
        return $this->operation->facts->relation($relation);
    }
}
