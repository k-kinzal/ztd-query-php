<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An independent derived table of ordered signed integer fields.
 *
 * @visibility public
 * @example Constructing a signed input without an external relation
 *     $field = new \SqlSemantics\Platform\MySql\Ir\ResultColumn(new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('3'), new \SqlSemantics\Platform\MySql\Ir\Identifier('value'));
 *     (new \SqlSemantics\Platform\MySql\Ir\From\DerivedSource(new \SqlSemantics\Platform\MySql\Ir\Select([$field])))->definition->columns[0]->name->value // => 'value'
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
