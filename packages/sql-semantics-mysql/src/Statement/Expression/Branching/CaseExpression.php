<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Branching;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\TypeAggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\BranchInference;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A CASE expression in its simple form, `CASE operand WHEN value THEN result … [ELSE result] END`, or its searched form, `CASE WHEN condition THEN result … [ELSE result] END` (`Item_func_case`).
 *
 * The branches are tried in order and the first that matches gives the
 * result; the order is kept as written.
 *
 * Rule: MYSQL-CASE-001. Facts: the type is the aggregate of the results
 * (MYSQL-TYPE-AGGREGATION-001); the value is NULL when a result can be
 * NULL or when there is no ELSE. The compared operand, conditions and results
 * all take single values (MYSQL-OPERAND-COLUMNS-001), including the simple form.
 * WHEN/result pairs are resolved before the compared operand and ELSE.
 * Verified on MySQL 8.0, 8.4 and 9.1. Terminates: the parts are
 * strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html#operator_case.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing a CASE by its results
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE CASE a WHEN 1 THEN 'x' ELSE 'y' END");
 *     [$query->facts->scalar($query->statement->query->where)->type->descriptor->name(), $query->facts->scalar($query->statement->query->where)->nullability] // => ['VARCHAR', \SqlSemantics\Statement\Type\Nullability::NotNull]
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
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operand, every branch and the ELSE result, and aggregates the result types.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $types = [];
        $results = [];
        $nullability = $this->else === null ? Nullability::Nullable : Nullability::NotNull;
        foreach ($this->branches as $branch) {
            $operands->single($derivation->scalar($branch->condition, $environment), $derivation);
            $result = $operands->single($derivation->scalar($branch->result, $environment), $derivation);
            $types[] = $result->type;
            $results[] = $branch->result;
            $nullability = $nullability->propagate($result->nullability);
        }
        if ($this->operand !== null) {
            $operands->single($derivation->scalar($this->operand, $environment), $derivation);
        }
        if ($this->else !== null) {
            $result = $operands->single($derivation->scalar($this->else, $environment), $derivation);
            $types[] = $result->type;
            $results[] = $this->else;
            $nullability = $nullability->propagate($result->nullability);
        }

        $domains = (new Precision())->all($types);
        $settings = Settings::of($derivation->context);
        $inferred = $domains === null ? null : (new BranchInference())->resolve($domains, $results, $derivation);
        $domain = $inferred === null ? null : (new Aggregation(new Collations($settings->connection)))->of($inferred, 'case', $derivation);

        return new ScalarFact($domain === null ? (new TypeAggregation())->aggregate($types) : new Known($domain), $nullability);
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
