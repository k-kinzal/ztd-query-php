<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use JsonException;
use SqlSemantics\Statement\Fact\Facts as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;
use Tests\Generator\Semantics\Statement\Operation;

/**
 * Facts retain the node and warning identities established by one generated operation.
 */
final class Facts implements Inhabitant
{
    /**
     * Generates the statement first so every retained fact belongs to the same graph.
     *
     * @throws JsonException When the semantic value inventory cannot be read
     */
    public function sample(Choices $choices): Model
    {
        return (new Operation())->sample($choices)->facts;
    }
}
