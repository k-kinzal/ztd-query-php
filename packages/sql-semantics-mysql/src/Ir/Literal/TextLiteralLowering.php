<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Literal;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

/**
 * Lowers the plain decoded string role of a native projection.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TextLiteralLowering
{
    /**
     * Concatenates the decoded segments at the completed projection boundary.
     */
    public function lower(Scalar $expression): ?TextLiteral
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if (!$expression instanceof StringLiteral) {
            return null;
        }
        Check::input(
            $expression->introducer === null && !$expression->national,
            'A text literal requires the plain utf8mb4 projection role.'
        );

        return new TextLiteral($expression->value());
    }
}
