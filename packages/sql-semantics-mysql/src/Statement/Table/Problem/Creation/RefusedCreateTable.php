<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete CREATE TABLE whose written definition the server executes only to refuse.
 *
 * The analysis refuses the standalone statement with the problem; a stored program keeps
 * the definition as its stored form and reports the problem when the routine runs.
 * @visibility public
 * @example Reading the refused definition and its problem
 *     $definition = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTable($definition, new \SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns()))->message() // => 'A table must have at least 1 column.'
 */
final class RefusedCreateTable implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredCreateTable $definition The complete written definition
     * @param Diagnostic $problem The problem the server reports when it executes the definition
     */
    public function __construct(public readonly DeferredCreateTable $definition, public readonly Diagnostic $problem)
    {
    }

    /**
     * Describes the problem of the definition.
     */
    public function message(): string
    {
        return $this->problem->message();
    }
}
