<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\Values;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Erases the analyzed VALUES representation after checking its complete native heading.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ValuesLowering
{
    /**
     * Publishes a standalone typed constant table.
     */
    public function lower(BoundStatement $operation, ValuesList $statement): ValuesQuery
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'VALUES requires the native parameter profile.');
        $fields = $operation->facts->query($statement)->fields();
        Check::input($fields !== null, 'VALUES needs a resolved native result heading.');
        $searchPath = $operation->context->searchPath;
        $operators = $searchPath[0]->value === 'pg_temp' ? ($searchPath[1]->value ?? null) : $searchPath[0]->value;
        $catalogFirst = $operators === 'pg_catalog';
        $binding = new SourceBinding($operation, null);
        $lower = new ExpressionLowering();
        $rows = [];
        foreach ($statement->rows as $row) {
            $cells = [];
            foreach ($row->values as $position => $expression) {
                $type = $fields->at($position)->type;
                Check::input($type instanceof Known, 'Each VALUES column requires a known native type.');
                $text = $type->descriptor === Builtin::Text;
                $value = $lower->lower($expression, $binding, $catalogFirst, $text);
                Check::input(
                    $value instanceof BooleanLiteral || $value instanceof BuiltinCast || $value instanceof IntegerLiteral
                    || $value instanceof NullLiteral || $value instanceof TextLiteral || $value instanceof WideIntegerLiteral,
                    'A standalone VALUES cell needs a supported literal or a typed NULL.',
                );
                $cells[] = new ValuesCell($value);
            }
            $rows[] = new ValuesRow($cells);
        }
        $values = new ValuesQuery($rows, $profile->grammar);
        foreach ($fields as $position => $field) {
            Check::input(
                $field->type instanceof Known && $field->type->descriptor instanceof Builtin
                    && $field->type->descriptor->value === $values->columnKind($position)->value,
                'The resolved VALUES column type is outside the checked domain.',
            );
        }

        return $values;
    }
}
