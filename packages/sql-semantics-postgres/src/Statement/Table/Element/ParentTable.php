<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;

/**
 * A parent table named by INHERITS or PARTITION OF.
 *
 * The name is resolved (PG-TABLE-TARGET-001) and its resolution is the
 * relation fact of this node.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
 */
final class ParentTable implements Node
{
    use Snapshot;

    /**
     * @param QualifiedName $name The parent table
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        (new Spelling())->qualified($out, $this->name);
    }
}
