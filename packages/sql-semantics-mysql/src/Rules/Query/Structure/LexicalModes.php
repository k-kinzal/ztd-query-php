<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;

/**
 * Intrinsic lexical requirements of expression variants.
 *
 * A statement has one lexical mode. Grouping and subquery boundaries cannot
 * make NOT have two precedences or make || mean both OR and concatenation.
 * The enclosing constructor combines these requirements over its whole graph;
 * conflicting requirements cannot describe a statement in any language profile.
 * This check neither selects a session mode nor decides execution eligibility.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LexicalModes
{
    /**
     * Answers a bit set: low NOT (1), high NOT (2), OR pipes (4), concatenate pipes (8).
     */
    public function requirement(object $value): int
    {
        if ($value instanceof LanguageProfile) {
            return ($value->lexical->highNotPrecedence ? 2 : 1) | ($value->lexical->pipesAsConcat ? 8 : 4);
        }
        if ($value instanceof Not) {
            return 1;
        }
        if ($value instanceof Unary && $value->keyword) {
            return 2;
        }
        if ($value instanceof Logical && $value->symbolic && $value->operator === LogicalOperator::Or) {
            return 4;
        }

        return $value instanceof Concatenation ? 8 : 0;
    }

    /**
     * Refuses a composition whose operands require contradictory lexical settings.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the requirements conflict
     */
    public function check(int $requirements): void
    {
        Check::input(($requirements & 3) !== 3, 'A structure cannot require both enabled and disabled HIGH_NOT_PRECEDENCE.');
        Check::input(($requirements & 12) !== 12, 'A structure cannot require both enabled and disabled PIPES_AS_CONCAT.');
    }
}
