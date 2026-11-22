<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;

/**
 * Resolves the operands of a SQLite VALUES statement.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ValuesLowering
{
    /**
     * Lowers rows with an empty local relation scope.
     */
    public function lower(BoundStatement $operation, ValuesClause $statement): ValuesQuery
    {
        $expressions = new ExpressionLowering();
        $scope = new SourceBinding($operation, null);
        $rows = [];
        foreach ($statement->rows as $row) {
            $values = [];
            foreach ($row->values as $value) {
                $values[] = $expressions->lower($value, $scope);
            }
            $rows[] = new ValueRow($values);
        }

        return new ValuesQuery($rows);
    }
}
