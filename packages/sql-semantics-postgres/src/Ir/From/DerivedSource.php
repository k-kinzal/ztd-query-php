<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An independent derived table of ordered signed integer, native text and Boolean fields.
 *
 * @visibility public
 * @example Constructing a signed input without an external relation
 *     $field = new \SqlSemantics\Platform\PostgreSql\Ir\ResultColumn(new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('3'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('value'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\From\DerivedSource(new \SqlSemantics\Platform\PostgreSql\Ir\Select([$field])))->definition->columns[0]->name->value // => 'value'
 */
final class DerivedSource
{
    use Snapshot;

    /**
     * Establishes the independently printable query and its native slot roles.
     */
    public function __construct(public readonly Select $definition)
    {
        Check::input(!$definition->source instanceof Join, 'A derived join input must be independent of any joined input.');
        Check::input($definition->orderBy === [] && $definition->limit === null, 'A derived join input must be unordered and unbounded.');
        foreach (array_keys($definition->columns) as $slot) {
            $this->columnKind($slot);
        }
    }

    /**
     * Derives the native kind of an exported ordinal from its checked expression.
     */
    public function columnKind(int $slot): ?ColumnKind
    {
        $column = $this->definition->columns[$slot] ?? null;
        if ($column === null) {
            return null;
        }

        $expression = $column->expression;
        Check::input(
            $expression instanceof IntegerLiteral || $expression instanceof ColumnReference
                || $expression instanceof TextColumnReference || $expression instanceof BooleanColumnReference
                || $expression instanceof WideIntegerLiteral || $expression instanceof WideIntegerColumnReference,
            'A derived join field requires a signed literal or a supported native column.'
        );

        return match (true) {
            $expression instanceof TextColumnReference => ColumnKind::Text,
            $expression instanceof BooleanColumnReference => ColumnKind::Boolean,
            $expression instanceof WideIntegerColumnReference, $expression instanceof WideIntegerLiteral => ColumnKind::Int8,
            $expression instanceof ColumnReference, $expression instanceof IntegerLiteral => ColumnKind::Int4,
        };
    }
}
