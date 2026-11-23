<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\Values;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A standalone VALUES cell with an explicit native operand role.
 *
 * @visibility public
 * @example Reading the type of a numeric cell
 *     $cell = new \SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell(new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('7'));
 *     $cell->kind()?->value // => 'int4'
 */
final class ValuesCell
{
    use Snapshot;

    /**
     * Admits one exact literal or a typed NULL.
     */
    public function __construct(public readonly BooleanLiteral|BuiltinCast|IntegerLiteral|NullLiteral|TextLiteral|WideIntegerLiteral $value)
    {
        Check::input(!$value instanceof BuiltinCast || $value->operand instanceof NullLiteral, 'A VALUES cast cell must cast literal NULL.');
    }

    /**
     * Answers the cell's explicit kind, if it contributes one.
     */
    public function kind(): ?ColumnKind
    {
        $value = $this->value;

        return match (true) {
            $value instanceof BooleanLiteral => ColumnKind::Boolean,
            $value instanceof BuiltinCast => match ($value->target) {
                CastDestination::Boolean => ColumnKind::Boolean,
                CastDestination::Integer => ColumnKind::Int4,
                CastDestination::WideInteger => ColumnKind::Int8,
                CastDestination::Text => ColumnKind::Text,
            },
            $value instanceof IntegerLiteral => ColumnKind::Int4,
            $value instanceof NullLiteral => null,
            $value instanceof TextLiteral => ColumnKind::Text,
            $value instanceof WideIntegerLiteral => ColumnKind::Int8,
        };
    }

    /**
     * Emits the checked literal in the native profile.
     */
    public function toString(): string
    {
        return (new SelectSql())->expression($this->value, null);
    }
}
