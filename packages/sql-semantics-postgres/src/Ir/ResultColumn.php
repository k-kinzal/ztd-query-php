<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A projected expression and its observable output name.
 *
 * @visibility public
 * @example Naming an explicit NULL projection
 *     $column = new \SqlSemantics\Platform\PostgreSql\Ir\ResultColumn(new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral(), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('value'));
 *     $column->name->value // => 'value'
 */
final class ResultColumn
{
    use Snapshot;

    /**
     * Pairs a closed expression with its semantic result name.
     */
    public function __construct(
        public readonly Arithmetic|ColumnReference|IntegerCase|IntegerLiteral|IntegerRange|BooleanLiteral|NullLiteral|Parameter $expression,
        public readonly Identifier $name,
    ) {
    }
}
