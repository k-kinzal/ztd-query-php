<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Expression\Binding\NumberedRegistrations;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;

/**
 * Reads the lexical registrations of the discarded operand before publishing its decision.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class FoldedMembershipLowering
{
    /**
     * Preserves marker order without resolving the discarded operand.
     * @throws InvalidConstruction When a discarded marker is outside the numbered profile
     */
    public function lower(Scalar $expression, bool $matches): FoldedMembership
    {
        $bindings = (new NumberedRegistrations())->collect($expression, 'An empty membership requires numbered bindings.');

        return new FoldedMembership($matches, $bindings);
    }
}
