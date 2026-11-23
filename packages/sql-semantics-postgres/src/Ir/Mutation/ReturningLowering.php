<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedReturnColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;

/**
 * Allocates the exact target slots of a resolved RETURNING layout.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ReturningLowering
{
    /**
     * Preserves names and positions after resolving reference and wildcard fields.
     *
     * @return non-empty-list<ReturnedColumn>
     * @throws InvalidConstruction When an output field is outside the target column domain
     */
    public function lower(?QueryFact $output, SourceBinding $binding): array
    {
        $fields = $output?->fields();
        Check::input($fields !== null, 'RETURNING needs a closed target result layout.');
        $returning = [];
        foreach ($fields as $field) {
            $operand = $field->expression;
            while ($operand instanceof Grouped) {
                $operand = $operand->operand;
            }
            Check::input($operand === null || $operand instanceof ParsedReturnColumn, 'RETURNING requires a resolved target column.');
            $resolution = $field->resolution;
            Check::input($resolution instanceof ResolvedColumn && $field->name !== null, 'RETURNING needs a resolved named target column.');
            $reference = $binding->resolved($resolution);
            $returning[] = new ReturnedColumn($reference, new Identifier($field->name->value));
        }
        Check::input($returning !== [], 'RETURNING needs at least one resolved target field.');

        return $returning;
    }
}
