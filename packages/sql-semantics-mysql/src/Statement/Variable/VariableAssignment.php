<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An assignment to a user-defined variable inside an expression: `@name := value`.
 *
 * The value is a complete expression: everything to the right of `:=`
 * belongs to it. An operator that takes an assignment as its left operand
 * must therefore hold it in a grouping.
 *
 * Rule: MYSQL-VARIABLE-ASSIGNMENT-001. Facts: the assignment yields the
 * assigned value, so its type and NULL fact are those of the value.
 * Diagnostics: none. Terminates: the value is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/assignment-operators.html#operator_assign-value.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading an assignment
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = @n := 5');
 *     [$query->statement->where->right->target->name->value, $query->statement->where->right->value->text] // => ['n', '5']
 */
final class VariableAssignment implements Scalar
{
    use Snapshot;

    /**
     * @param UserVariable $target The variable assigned to
     * @param Scalar $value The assigned expression
     */
    public function __construct(public readonly UserVariable $target, public readonly Scalar $value)
    {
    }

    /**
     * Derives the target and the value; the assignment has the facts of the value.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->target, $environment);
        $fact = (new Operands())->single($derivation->scalar($this->value, $environment), $derivation);

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes the target, the assignment operator and the value.
     */
    public function render(Output $out): void
    {
        $out->node($this->target)->symbol(':=')->node($this->value);
    }
}
