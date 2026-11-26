<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate\Membership;

use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerAtomLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\InList;

/**
 * Resolves pure membership operands under PostgreSQL's stock equality rules.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class IntegerMembershipLowering
{
    /**
     * Publishes the selected comparison and exact signed leaves without retaining the binding.
     */
    public function lower(InList $test, SourceBinding|JoinBinding $binding, bool $catalogFirst): IntegerMembership
    {
        Check::input($catalogFirst, 'A membership predicate requires the pg_catalog comparison profile.');
        $resolver = new IntegerAtomLowering();
        [$subject] = $resolver->lower($test->operand, $binding, $catalogFirst);
        $alternatives = [];
        foreach ($test->values as $value) {
            [$alternative] = $resolver->lower($value, $binding, $catalogFirst);
            $alternatives[] = $alternative;
        }

        return new IntegerMembership($subject, $alternatives, $test->negated);
    }
}
