<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression;

use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Native text with an explicit builtin collation.
 *
 * @visibility public
 * @example Applying the stock binary collation
 *     (new \SqlSemantics\Platform\MySql\Ir\Expression\CollatedText(new \SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral('value')))->collation->value // => 'utf8mb4_bin'
 */
final class CollatedText
{
    use Snapshot;

    /**
     * Retains the text operand and the explicit collation.
     */
    public function __construct(
        public readonly TextColumnReference|TextLiteral $operand,
        public readonly TextCollation $collation = TextCollation::Utf8mb4Bin,
    ) {
    }
}
