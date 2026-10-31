<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

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
 *     $create->definition->parents[0]->relationInformation()->table::class // => 'SqlSemantics\Statement\Reference\Table\MissingTable'
 */
final class ParentTable implements Node, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;

    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param QualifiedName $name The parent table
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\Fact\RelationFact $relationInformation = null)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        (new Spelling())->qualified($out, $this->name);
    }
}
