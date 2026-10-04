<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A parent table named by INHERITS or PARTITION OF.
 *
 * The name is resolved (PG-TABLE-TARGET-001) and its resolution is the
 * relation fact of this node.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Resolving a parent table
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t () INHERITS (p)', []);
 *     $create->facts->relation($create->statement->definition->parents[0])->table::class // => 'SqlSemantics\Statement\Reference\Table\MissingTable'
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
