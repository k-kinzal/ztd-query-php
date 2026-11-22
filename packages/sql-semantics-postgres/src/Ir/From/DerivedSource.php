<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An independent derived table of ordered signed integer fields.
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
     * Establishes the independently printable query and its signed slot domain.
     */
    public function __construct(public readonly Select $definition)
    {
        Check::input(!$definition->source instanceof Join, 'A derived join input must be independent of any joined input.');
        Check::input($definition->orderBy === [] && $definition->limit === null, 'A derived join input must be unordered and unbounded.');
        foreach ($definition->columns as $column) {
            Check::input(
                $column->expression instanceof IntegerLiteral || $column->expression instanceof ColumnReference,
                'A derived join field requires a signed literal or a signed column.'
            );
        }
    }
}
