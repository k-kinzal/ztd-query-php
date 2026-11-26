<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate\Membership;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\Literal\IntegerTokenLowering;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Binds each native signed operation input in the owning scope.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IntegerMembershipLowering
{
    /**
     * Binds the tested value followed by all alternatives in written order.
     */
    public function lower(InList $test, SourceBinding|JoinBinding $binding): IntegerMembership
    {
        $subject = $this->operand($test->operand, $binding);
        $alternatives = [];
        foreach ($test->elements as $element) {
            $alternatives[] = $this->operand($element, $binding);
        }

        return new IntegerMembership($subject, $alternatives, $test->negated);
    }

    /**
     * Resolves a pure integer literal, NULL or physical column.
     * @throws InvalidConstruction When the operand is outside the pure signed domain
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
            default => throw new InvalidConstruction('A membership operand requires a pure signed integer or NULL.'),
        };
    }
}
