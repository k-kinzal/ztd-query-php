<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use JsonException;
use LogicException;
use SqlSemantics\Statement\Fact\QueryFact as Model;
use SqlSemantics\Statement\Operation;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Generates a QueryFact together with the published operation that owns it.
 */
final class QueryFactGenerator implements Inhabitant
{
    /**
     * The original analyzed operation retained for execution.
     */
    public ?Operation $operation = null;

    /**
     * Samples a complete semantic graph, retaining the identities used by its facts.
     * @throws LogicException When a generated operand has the wrong semantic type
     * @throws JsonException When the semantic inventory cannot be read
     */
    public function sample(Choices $choices): Model
    {
        $expression = (new \Tests\Generator\Construction($choices, new \Tests\Generator\Catalogue(), new \Tests\Generator\Metadata()))->object(\SqlSemantics\Statement\Scalar::class, 6);
        if (!$expression instanceof \SqlSemantics\Statement\Scalar) {
            throw new LogicException('The scalar generator returned another type.');
        }
        $statement = (new \Tests\Generator\Embedding())->statement($expression);
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot) {
            throw new LogicException('A scalar must be embedded in a read query.');
        }
        $this->operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, $choices);
        return $this->operation->facts->query($statement->query);
    }
}
