<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A native text result with an explicit builtin collation.
 *
 * @visibility public
 * @example Applying byte ordering to text
 *     $term = new \SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral('value');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText($term, \SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation::C))->collation->value // => 'C'
 */
final class CollatedText
{
    use Snapshot;

    /**
     * Retains the exact value and the resolved collation identity.
     */
    public function __construct(
        public readonly TextLiteral|TextColumnReference|NullLiteral $operand,
        public readonly TextCollation $collation,
    ) {
    }
}
