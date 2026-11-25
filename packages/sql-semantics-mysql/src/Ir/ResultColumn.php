<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Binding\Parameter;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\Expression\UnsignedCast;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\IntrinsicIntegerName;
use SqlSemantics\Platform\MySql\Ir\Literal\ProjectedIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
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
        public readonly Arithmetic|CollatedText|ColumnReference|IntegerLiteral|IntegerComparison|IntegerMembership|IntegerRange|BooleanLiteral|NullLiteral|NullTest|Parameter|ProjectedIntegerLiteral|SignedCase|SignedCast|TextColumnReference|TextComparison|TextLiteral|TruthTest|UnsignedCast $expression,
        public readonly Identifier|IntrinsicIntegerName $name,
    ) {
        Check::input($expression instanceof ColumnReference || $expression instanceof TextColumnReference || $name->value === ltrim($name->value, " \t\r\n\v\f"), 'A MySQL result name cannot start with SQL whitespace.');
        Check::input(
            !$name instanceof IntrinsicIntegerName || ($expression instanceof ProjectedIntegerLiteral && $expression->token === $name->value),
            'An intrinsic integer name must match the exact projected integer token.'
        );
    }
}
