<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use JsonException;
use SqlSemantics\Statement\Fact\Facts as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;
use Tests\Generator\Semantics\Statement\OperationGenerator as Operation;

/**
 * Facts retain the node and warning identities established by one generated operation.
 */
final class FactsGenerator implements Inhabitant
{
    /**
     * The published owner of the generated facts, retained for direct execution.
     */
    public ?\SqlSemantics\Statement\Operation $operation = null;

    /**
     * Generates the statement first so every retained fact belongs to the same graph.
     *
     * @throws JsonException When the semantic value inventory cannot be read
     */
    public function sample(Choices $choices): Model
    {
        $this->operation = (new Operation())->sample($choices);
        return $this->operation->facts;
    }
}
