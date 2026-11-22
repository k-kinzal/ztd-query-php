<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Binding\Parameter;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\Expression\UnsignedCast;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A projected expression and its observable output name.
 *
 * @visibility public
 * @example Naming an explicit NULL projection
 *     $column = new \SqlSemantics\Platform\MySql\Ir\ResultColumn(new \SqlSemantics\Platform\MySql\Ir\NullLiteral(), new \SqlSemantics\Platform\MySql\Ir\Identifier('value'));
 *     $column->name->value // => 'value'
 */
final class ResultColumn
{
    use Snapshot;

    /**
     * Pairs a closed expression with its semantic result name.
     */
    public function __construct(
        public readonly Arithmetic|ColumnReference|IntegerLiteral|IntegerRange|BooleanLiteral|NullLiteral|Parameter|SignedCast|UnsignedCast $expression,
        public readonly Identifier $name,
    ) {
        Check::input($expression instanceof ColumnReference || $name->value === ltrim($name->value, " \t\r\n\v\f"), 'A MySQL result name cannot start with SQL whitespace.');
    }
}
