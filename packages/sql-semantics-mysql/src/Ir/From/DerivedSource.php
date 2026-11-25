<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\LocalProjectionAliases;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An independent derived input with ordered signed INT and native VARCHAR fields.
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
     * Establishes the independently printable query and its native slot types.
     */
    public function __construct(public readonly Select $definition)
    {
        (new LocalProjectionAliases())->check($definition);
        Check::input(!$definition->source instanceof Join, 'A derived join input must be independent of any joined input.');
        Check::input($definition->orderBy === [] && $definition->limit === null, 'A derived join input must be unordered and unbounded.');
        foreach ($definition->columns as $column) {
            Check::input(
                $column->expression instanceof IntegerLiteral || $column->expression instanceof ColumnReference
                    || $column->expression instanceof TextColumnReference,
                'A derived join field requires a signed literal or a signed column, or a native VARCHAR column.'
            );
        }
    }

    /**
     * Derives the exported ordinal's type from its checked input and expression.
     */
    public function columnType(int $slot): ColumnKind|Varchar|null
    {
        $expression = $this->definition->columns[$slot]->expression ?? null;
        if ($expression === null) {
            return null;
        }
        if ($expression instanceof TextColumnReference) {
            $source = $this->definition->source;
            Check::invariant($source instanceof TableSource, 'A derived VARCHAR read has an independent physical source.');
            $type = $source->columnTypes[$expression->slot] ?? null;
            Check::invariant($type instanceof Varchar, 'A checked derived VARCHAR field has its declared type.');

            return $type;
        }
        if ($expression instanceof IntegerLiteral) {
            return ColumnKind::RequiredSignedInt;
        }
        Check::invariant($expression instanceof ColumnReference, 'A derived signed field has a checked expression.');
        $source = $this->definition->source;
        Check::invariant($source instanceof TableSource, 'A derived signed read has an independent physical source.');
        $type = $source->columnTypes[$expression->slot] ?? null;
        Check::invariant($type instanceof ColumnKind, 'A checked derived signed field has its declared role.');

        return $type;
    }
}
