<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;

/**
 * Resolves the ordered old-row fields exposed by DELETE RETURNING.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DeleteReturningLowering
{
    /**
     * Allocates the checked output fields in their observed order.
     *
     * @return list<ReturnedColumn>
     * @throws InvalidConstruction When a returned operand is outside the checked target column domain
     */
    public function lower(BoundStatement $operation, Delete $statement, SourceBinding $binding): array
    {
        if ($statement->returning === []) {
            return [];
        }
        foreach ($statement->returning as $operand) {
            Check::input(!$operand instanceof TableStar, 'DELETE RETURNING requires an unqualified wildcard.');
        }
        $fields = $operation->facts->output?->fields();
        Check::input($fields !== null, 'DELETE RETURNING needs a closed target result layout.');
        $result = [];
        foreach ($fields as $field) {
            $operand = $field->expression;
            Check::input(
                $operand === null || ($operand instanceof ColumnUse && $operand->qualifier === null) || $operand instanceof DoubleQuotedWord,
                'DELETE RETURNING requires an unqualified target column.',
            );
            $resolution = $operand === null ? $field->resolution : $operation->facts->scalar($operand)->resolution;
            Check::input($resolution instanceof ResolvedColumn && $field->name !== null, 'DELETE RETURNING needs a resolved named target column.');
            $result[] = new ReturnedColumn($binding->resolved($resolution), new Identifier($field->name->value));
        }
        Check::input($result !== [], 'DELETE RETURNING needs at least one resolved result field.');

        return $result;
    }
}
