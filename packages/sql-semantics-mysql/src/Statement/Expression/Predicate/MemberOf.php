<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Predicate;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;

/**
 * A JSON array membership test: `value MEMBER OF (json_array)` (`Item_func_member_of`, MySQL 8.0.17 and later).
 *
 * The keyword OF is optional in the grammar and does not change the test;
 * whether it is written is kept, since it is part of the text MySQL names
 * an unaliased select list expression after. The value is a bit_expr and the array a
 * simple_expr (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-MEMBER-OF-001. Facts: 1, 0 or NULL, an integer; it can be
 * NULL when an operand can. Terminates: the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#operator_member-of.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class MemberOf implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The value searched for
     * @param Scalar $array The JSON array searched
     * @param OptionalWords $of Whether the optional OF is written
     */
    public function __construct(public readonly Scalar $operand, public readonly Scalar $array, public readonly OptionalWords $of = OptionalWords::Written)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The operand of MEMBER OF needs a grouping to keep its place.');
        Check::input($precedence->admits($array, Precedence::SIMPLE_EXPR), 'The array of MEMBER OF needs a grouping to keep its place.');
    }

    /**
     * Derives both operands and combines their NULL facts; the test needs MySQL 8.0 or later.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $grammar = $derivation->context->profile->grammar;
        Check::input($grammar !== GrammarRelease::MySql5651 && $grammar !== GrammarRelease::MySql5744, 'MEMBER OF needs MySQL 8.0 or later.');
        $operands = new Operands();
        $operand = $operands->single($derivation->scalar($this->operand, $environment), $derivation);
        $array = $operands->single($derivation->scalar($this->array, $environment), $derivation);

        return $operands->truth($operand->nullability->propagate($array->nullability));
    }

    /**
     * Writes the value, MEMBER, OF when it is written, and the array in parentheses.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('MEMBER');
        if ($this->of === OptionalWords::Written) {
            $out->keyword('OF');
        }
        $out->symbol('(')->node($this->array)->symbol(')');
    }
}
