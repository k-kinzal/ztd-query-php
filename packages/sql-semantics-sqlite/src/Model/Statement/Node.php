<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement;

use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;

/**
 * A structural value of a statement that is written back as SQL.
 *
 * A node describes what the SQL requests: an operation, an operand, an input
 * relation. It holds decoded names and exact literals and no binding. The same
 * node instance occurs at most once in a statement.
 *
 * @visibility SqlSemantics
 */
interface Node
{
    /**
     * Writes the typed output pieces of this node; it cannot write free SQL text.
     */
    public function render(Output $out): void;
}
