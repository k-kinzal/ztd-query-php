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
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;

/**
 * Resolves the ordered fields read from one mutation target.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ReturningLowering
{
    /**
     * Allocates the checked output fields in their observed order.
     *
     * @param list<ResultColumn|Star|TableStar> $returning The written result columns
     * @return list<ReturnedColumn>
     * @throws InvalidConstruction When a returned operand is outside the checked target column domain
     */
    public function lower(BoundStatement $operation, array $returning, SourceBinding $binding): array
    {
        if ($returning === []) {
            return [];
        }
        foreach ($returning as $operand) {
            Check::input(!$operand instanceof TableStar, 'RETURNING requires an unqualified wildcard.');
        }
        $fields = $operation->facts->output?->fields();
        Check::input($fields !== null, 'RETURNING needs a closed target result layout.');
        $result = [];
        foreach ($fields as $field) {
            $operand = $field->expression;
            Check::input(
                $operand === null || ($operand instanceof ColumnUse && $operand->qualifier === null) || $operand instanceof DoubleQuotedWord,
                'RETURNING requires an unqualified target column.',
            );
            $resolution = $operand === null ? $field->resolution : $operation->facts->scalar($operand)->resolution;
            Check::input($resolution instanceof ResolvedColumn && $field->name !== null, 'RETURNING needs a resolved named target column.');
            $result[] = new ReturnedColumn($binding->resolved($resolution), new Identifier($field->name->value));
        }
        Check::input($result !== [], 'RETURNING needs at least one resolved result field.');

        return $result;
    }
}
