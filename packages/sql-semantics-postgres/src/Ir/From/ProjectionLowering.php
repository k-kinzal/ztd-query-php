<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\ParameterLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;

/**
 * Closes the result layout over the resolved input scope.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ProjectionLowering
{
    /**
     * Preserves result positions, names, native roles and binding types.
     *
     * @param array<int, CastDestination> $inputTypes Native input types inferred in statement order
     * @return non-empty-list<ResultColumn>
     * @throws InvalidConstruction When the result cannot be represented
     */
    public function lower(
        BoundStatement $operation,
        ParsedSelect $statement,
        SourceBinding|JoinBinding $binding,
        bool $catalogFirst,
        array &$inputTypes,
        ?Destination $assignment,
        bool $simpleOutput,
    ): array {
        $expressions = new ExpressionLowering();
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'A complete projection requires a resolved output shape.');
        $results = [];
        $parameters = new ParameterLowering();
        foreach ($fields as $field) {
            $name = $field->name;
            Check::input($name !== null, 'The result must have a resolved name.');
            $label = new Identifier($name->value);
            $expression = $field->expression;
            while ($expression instanceof Grouped) {
                $expression = $expression->operand;
            }
            if ($expression === null || $expression instanceof ParsedColumn) {
                $resolution = $field->resolution;
                Check::input($resolution instanceof ResolvedColumn, 'A projected field must resolve to one visible input slot.');
                $value = $binding instanceof JoinBinding ? $binding->projected($resolution) : $binding->resolved($resolution);
                $results[] = new ResultColumn($value, $label);
                continue;
            }
            $parameter = $parameters->lower($expression, $operation->context, $inputTypes);
            $kind = $assignment?->columnKind(count($results));
            $value = $parameter ?? $expressions->result($expression, $binding, $catalogFirst, $kind, $statement->distinct !== null, $simpleOutput);
            $results[] = new ResultColumn($value, $label);
        }

        Check::input($results !== [], 'A complete projection requires at least one resolved output field.');

        return $results;
    }
}
