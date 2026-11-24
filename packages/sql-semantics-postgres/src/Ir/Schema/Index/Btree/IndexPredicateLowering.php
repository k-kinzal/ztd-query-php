<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;

/**
 * Resolves the scalar predicate of a partial B-tree to an indexed column.
 *
 * @visibility SqlSemantics
 */
final class IndexPredicateLowering
{
    /**
     * Finds the tested column by the exact declared owner and occurrence.
     *
     * @throws InvalidConstruction When the predicate does not use a physical column
     */
    public function lower(BoundStatement $operation, CreateIndex $statement): ?NullIndexPredicate
    {
        $condition = $statement->where;
        if ($condition === null) {
            return null;
        }
        while ($condition instanceof Grouped) {
            $condition = $condition->operand;
        }
        Check::input($condition instanceof NullTest, 'A partial B-tree needs a direct scalar column NULL test.');
        $operand = $condition->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        Check::input($operand instanceof ColumnReference, 'A partial B-tree needs a direct scalar column NULL test.');
        $table = $operation->facts->relation($statement)->table;
        $resolved = $operation->facts->scalar($operand)->resolution;
        Check::input($table instanceof DeclaredTable && $resolved instanceof ResolvedColumn
            && $resolved->depth === 0 && $resolved->relation === $statement, 'The partial B-tree operand needs an exact local column.');
        $column = $resolved->declaration();
        foreach ($table->table->columns as $slot => $candidate) {
            if ($candidate === $column) {
                return new NullIndexPredicate($slot, $condition->negated);
            }
        }
        throw new InvalidConstruction('The partial B-tree operand must be a declared physical column.');
    }
}
