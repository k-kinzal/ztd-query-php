<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Literal\IntegerTokenLowering;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves the signed comparison category of the three range operands.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IntegerRangeLowering
{
    /**
     * Binds and preserves the order of the three signed or NULL operands.
     */
    public function lower(Between $test, SourceBinding|JoinBinding $binding): IntegerRange
    {
        return new IntegerRange(
            $this->operand($test->operand, $binding),
            $this->operand($test->low, $binding),
            $this->operand($test->high, $binding),
            $test->negated,
        );
    }

    /**
     * Resolves one leaf of the signed comparison role.
     * @throws InvalidConstruction When the leaf has another comparison category
     */
    public function operand(Scalar $expression, SourceBinding|JoinBinding $binding): ColumnReference|IntegralColumnReference|RangeIntegerLiteral|NullLiteral
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        $token = (new IntegerTokenLowering())->lower($expression);
        if ($token !== null) {
            return new RangeIntegerLiteral($token[0], $token[1], $token[2]);
        }

        return match (true) {
            $expression instanceof ParsedNull => new NullLiteral(),
            $expression instanceof ColumnUse => $binding->membershipReference($expression),
            default => throw new InvalidConstruction('A range operand requires a pure signed integer or NULL.'),
        };
    }
}
