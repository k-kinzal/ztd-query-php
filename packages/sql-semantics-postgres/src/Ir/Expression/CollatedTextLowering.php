<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;

/**
 * Resolves the builtin collation and its native text operand at analysis time.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CollatedTextLowering
{
    /**
     * Maps an absolute builtin collation and its typed operand.
     */
    public function lower(Collation $expression, SourceBinding|JoinBinding $binding, bool $catalogFirst): CollatedText
    {
        Check::input(
            array_column($expression->collation->parts, 'value') === ['pg_catalog', 'C'],
            'A text projection requires the explicit pg_catalog.C collation.'
        );
        return new CollatedText($this->operand($expression->operand, $binding, $catalogFirst), TextCollation::C);
    }

    /**
     * Selects a native text field, decoded literal or text NULL.
     */
    public function operand(Scalar $operand, SourceBinding|JoinBinding $binding, bool $catalogFirst): TextLiteral|TextColumnReference|NullLiteral
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        Check::input(!$operand instanceof Collation, 'A checked text collation requires one native text operand.');
        $value = $operand instanceof ParsedColumn ? $binding->field($operand) : (new ExpressionLowering())->lower($operand, $binding, $catalogFirst, true);
        if ($value instanceof BuiltinCast && $value->target === CastDestination::Text && $value->operand instanceof NullLiteral) {
            $value = $value->operand;
        }
        Check::input(
            $value instanceof TextLiteral || $value instanceof TextColumnReference || $value instanceof NullLiteral,
            'A checked text collation requires one native text operand.'
        );

        return $value;
    }
}
