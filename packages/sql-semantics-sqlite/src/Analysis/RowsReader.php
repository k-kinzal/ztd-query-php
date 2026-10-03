<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Lowers explicit input rows into scoped expressions without evaluating them.
 * @visibility SqlSemantics
 */
final class RowsReader
{
    /**
     * An explicit row has no table scan; its expressions do not resolve against insertion destinations.
     */
    public function read(Node $source, Catalog|Scope|SqliteAliasScope $catalog): Rows
    {
        assert(in_array($source->name, ['values', 'mvalues'], true), 'A row constructor has one or more explicit tuples.');
        $scope = new Scope($catalog);
        $rows = [];
        foreach (Tree::outer($source, ['nexprlist']) as $tuple) {
            $expressions = array_map(static fn (Node $expression): ScalarExpression => (new ExpressionReader())->read($expression, $scope), Tree::outer($tuple, ['expr']));
            assert($expressions !== [], 'Each VALUES tuple has at least one expression.');
            $rows[] = new Row($scope, ...$expressions);
        }
        assert($rows !== [], 'A row constructor contains at least one row.');
        return new Rows(...$rows);
    }
}
