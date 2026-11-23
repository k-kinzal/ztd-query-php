<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression;

use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteralLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves an explicit collation over a known native text operand.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CollatedTextLowering
{
    /**
     * Checks the stock collation and resolves the exact text leaf.
     */
    public function lower(Collated $expression, SourceBinding|JoinBinding $binding): CollatedText
    {
        $collation = TextCollation::tryFrom(strtolower($expression->collation->value));
        Check::input($collation !== null, 'The explicit MySQL text collation must be utf8mb4_bin.');
        $operand = $expression->operand;
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        $value = $operand instanceof ColumnUse ? $binding->field($operand) : (new TextLiteralLowering())->lower($operand);
        Check::input($value instanceof TextColumnReference || $value instanceof TextLiteral, 'An explicit MySQL text collation requires a native VARCHAR field or text literal.');

        return new CollatedText($value, $collation);
    }
}
