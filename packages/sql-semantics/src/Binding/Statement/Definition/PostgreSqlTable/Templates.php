<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\PostgreSqlTable;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Relation\ForeignTables;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Table\TemplatePlacement;

/**
 * Reads the LIKE clauses of a PostgreSQL table declaration together with their places among the declared columns.
 * @visibility SqlSemantics
 */
final class Templates
{
    /**
     * Counts the column definitions written before each LIKE clause.
     *
     * @return list<TemplatePlacement>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $declaration, QueryContext $context): array
    {
        $templates = [];
        $position = 0;
        foreach (Tree::outer($declaration, ['columnDef', 'TableLikeClause']) as $element) {
            if ($element->name === 'columnDef') {
                ++$position;
                continue;
            }
            $templates[] = new TemplatePlacement(ForeignTables::template($element, $context), $position);
        }
        return $templates;
    }
}
