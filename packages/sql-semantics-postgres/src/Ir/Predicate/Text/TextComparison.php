<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A nullable Boolean comparison under an exact native text collation.
 *
 * @visibility public
 * @example Comparing a text slot with a literal by UTF-8 bytes
 *     $operator = \SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextOperator::Less;
 *     $left = new \SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference(0);
 *     $right = new \SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral('A');
 *     $collation = \SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation::C;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison($operator, $left, $right, $collation))->operator->value // => '<'
 */
final class TextComparison
{
    use Snapshot;

    /**
     * Owns the exact operator, ordered operands and effective collation.
     */
    public function __construct(
        public readonly TextOperator $operator,
        public readonly TextLiteral|TextColumnReference|NullLiteral $left,
        public readonly TextLiteral|TextColumnReference|NullLiteral $right,
        public readonly TextCollation $collation,
    ) {
    }
}
