<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Rendering\Output;

/**
 * A structural value of a statement that is written back as SQL.
 *
 * A node describes what the SQL requests: an operation, an operand, an input
 * relation. It holds decoded names and exact literals and no binding. The same
 * node instance occurs at most once in a statement.
 *
 * @visibility public
 * @example Reading the structure of an analyzed statement
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1');
 *     $operation->statement instanceof \SqlSemantics\Statement\Node // => true
 */
interface Node
{
    /**
     * Writes the typed output pieces of this node; it cannot write free SQL text.
     */
    public function render(Output $out): void;
}
