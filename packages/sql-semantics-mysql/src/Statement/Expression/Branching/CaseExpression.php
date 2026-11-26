<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Branching;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\TypeAggregation;

/**
 * A CASE expression in its simple form, `CASE operand WHEN value THEN result … [ELSE result] END`, or its searched form, `CASE WHEN condition THEN result … [ELSE result] END` (`Item_func_case`).
 *
 * The branches are tried in order and the first that matches gives the
 * result; the order is kept as written.
 *
 * Rule: MYSQL-CASE-001. Facts: the type is the aggregate of the results
 * (MYSQL-TYPE-AGGREGATION-001); the value is NULL when a result can be
 * NULL or when there is no ELSE. In the simple form the operand and every
 * value must have the same number of columns (MYSQL-OPERAND-COLUMNS-001);
 * conditions and results take single values. Terminates: the parts are
 * strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html#operator_case.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CaseExpression implements Scalar
{
    use Snapshot;

    /**
     * @var non-empty-list<CaseBranch> The branches in order
     */
    public readonly array $branches;

    /**
     * @param Scalar|null $operand The compared value of the simple form; null for the searched form
     * @param list<CaseBranch> $branches The branches in order; at least one
     * @param Scalar|null $else The result after ELSE, when written
     */
    public function __construct(public readonly ?Scalar $operand, array $branches, public readonly ?Scalar $else = null)
    {
        $this->branches = Check::listOf($branches, CaseBranch::class, 'A CASE expression has at least one WHEN branch.', 1);
    }

    /**
     * Derives the operand, every branch and the ELSE result, and aggregates the result types.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $compared = $this->operand === null ? [] : [$derivation->scalar($this->operand, $environment)];
        $types = [];
        $nullability = $this->else === null ? Nullability::Nullable : Nullability::NotNull;
        foreach ($this->branches as $branch) {
            $condition = $derivation->scalar($branch->condition, $environment);
            if ($this->operand === null) {
                $operands->single($condition, $derivation);
            } else {
                $compared[] = $condition;
            }
            $result = $operands->single($derivation->scalar($branch->result, $environment), $derivation);
            $types[] = $result->type;
            $nullability = $nullability->propagate($result->nullability);
        }
        $operands->comparable($compared, $derivation);
        if ($this->else !== null) {
            $result = $operands->single($derivation->scalar($this->else, $environment), $derivation);
            $types[] = $result->type;
            $nullability = $nullability->propagate($result->nullability);
        }

        return new ScalarFact((new TypeAggregation())->aggregate($types), $nullability);
    }

    /**
     * Writes CASE, the operand, the branches, the ELSE result and END.
     */
    public function render(Output $out): void
    {
        $out->keyword('CASE')->node($this->operand);
        foreach ($this->branches as $branch) {
            $out->node($branch);
        }
        if ($this->else !== null) {
            $out->keyword('ELSE')->node($this->else);
        }
        $out->keyword('END');
    }
}
