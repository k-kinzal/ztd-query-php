<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Query;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Rules\Expression\RowValueUse;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

/**
 * A membership test against the rows of a query, optionally negated.
 *
 * Rule: SQLITE-IN-QUERY-001. The query is derived inside the environment of
 * the expression. The result is INTEGER; it can be NULL when the operand or
 * a compared column can be, and it depends on the missing inputs when the
 * shape of the query is open. The query returns as many columns as the
 * operand has values (SQLITE-ROW-VALUE-USE-001). The operand may not end in an operator weaker
 * than the equality group (SQLITE-PRECEDENCE-001).
 * Source: https://sqlite.org/lang_expr.html#the_in_and_not_in_operators.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InQuery implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested expression
     * @param Query $query The query
     * @param bool $negated Whether NOT is written before IN
     */
    public function __construct(public readonly Scalar $operand, public readonly Query $query, public readonly bool $negated = false)
    {
        Check::input((new Precedence())->closing($operand) >= Precedence::EQUALITY, 'The operand needs parentheses to keep its place.');
    }

    /**
     * Derives the operand, the query and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $nullability = $operand->nullability;
        $fact = $derivation->query($this->query, $environment);
        if ($fact->shape->complete()) {
            (new RowValueUse())->membership($operand, count($fact->shape->slots), $derivation);
        } else {
            $nullability = $nullability->propagate(Nullability::Dependent);
        }
        foreach ($fact->shape->slots as $slot) {
            $nullability = $nullability->propagate($slot->nullability);
        }

        return new ScalarFact(new Known(Storage::Integer), $nullability);
    }

    /**
     * Writes the test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('IN')->symbol('(')->node($this->query)->symbol(')');
    }
}
