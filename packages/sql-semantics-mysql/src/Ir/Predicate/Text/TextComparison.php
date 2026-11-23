<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate\Text;

use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A comparison whose character collation is selected by a native VARCHAR field.
 *
 * @visibility public
 * @example Comparing a typed field to a string
 *     $field = new \SqlSemantics\Platform\MySql\Ir\From\TextColumnReference(0);
 *     $literal = new \SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral('a');
 *     $op = \SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextOperator::Equal;
 *     (new \SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison($op, $field, $literal))->collation->value // => 'utf8mb4_bin'
 */
final class TextComparison
{
    use Snapshot;

    /**
     * Requires a physical or explicitly collated operand to fix the stock collation.
     */
    public function __construct(
        public readonly TextOperator $operator,
        public readonly CollatedText|TextColumnReference|TextLiteral|NullLiteral $left,
        public readonly CollatedText|TextColumnReference|TextLiteral|NullLiteral $right,
        public readonly TextCollation $collation = TextCollation::Utf8mb4Bin,
    ) {
        Check::input(
            $left instanceof TextColumnReference || $left instanceof CollatedText || $right instanceof TextColumnReference || $right instanceof CollatedText,
            'A text comparison needs a native VARCHAR field or explicit collation.',
        );
    }
}
