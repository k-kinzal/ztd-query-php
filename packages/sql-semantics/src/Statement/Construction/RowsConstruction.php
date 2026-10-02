<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Constructs each row in one fresh namespace, without importing bound columns.
 * @visibility SqlSemantics
 */
final class RowsConstruction
{
    /**
     * Unequal row widths are semantic SQL diagnostics, not a construction rejection.
     */
    public function derive(Query\RowsDefinition $input, Catalog|Scope|SqliteAliasScope $context): Rows
    {
        $scope = new Scope($context);
        $expressions = new ExpressionConstruction();
        $rows = array_map(static fn (Query\RowDefinition $row): Row => new Row($scope, ...array_map(static fn (ScalarInput $item): ScalarExpression => $expressions->derive($item, $scope), $row->expressions)), $input->rows);
        return new Rows(...$rows);
    }
}
