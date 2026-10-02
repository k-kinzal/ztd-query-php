<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A CASE expression: the result of the first branch that matches, in written order.
 *
 * Rule: SQLITE-CASE-001. With a base expression each WHEN value is compared
 * with it; without one each WHEN is a condition. The result is one of the
 * THEN results or the ELSE result, so its type is the choice over their
 * types. Without ELSE the result is NULL when no branch matches; otherwise it
 * can be NULL when a result can.
 * Source: https://sqlite.org/lang_expr.html#the_case_expression. Status: Implemented.
 *
 * @visibility public
 * @example Reading the type of a CASE
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT CASE a WHEN 1 THEN 'x' ELSE 2 END FROM t");
 *     [count($query->field(0)->type->alternatives), $query->field(0)->nullability] // => [2, \SqlSemantics\Statement\Type\Nullability::NotNull]
 */
final class CaseExpression implements Scalar
{
    use Snapshot;

    /**
     * @var non-empty-list<CaseBranch> The branches in written order
     */
    public readonly array $branches;

    /**
     * @param Scalar|null $base The expression every WHEN value is compared with
     * @param list<CaseBranch> $branches The branches in written order; at least one
     * @param Scalar|null $otherwise The ELSE result
     */
    public function __construct(public readonly ?Scalar $base, array $branches, public readonly ?Scalar $otherwise = null)
    {
        $this->branches = Check::listOf($branches, CaseBranch::class, 'A CASE expression has at least one branch.', 1);
    }

    /**
     * Derives every part and the choice over the results.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->base !== null) {
            $derivation->scalar($this->base, $environment);
        }
        $types = [];
        $nullability = $this->otherwise === null ? Nullability::Nullable : Nullability::NotNull;
        foreach ($this->branches as $branch) {
            $derivation->scalar($branch->when, $environment);
            $then = $derivation->scalar($branch->then, $environment);
            $types[] = $then->type;
            $nullability = $nullability->propagate($then->nullability);
        }
        if ($this->otherwise !== null) {
            $otherwise = $derivation->scalar($this->otherwise, $environment);
            $types[] = $otherwise->type;
            $nullability = $nullability->propagate($otherwise->nullability);
        }

        return new ScalarFact((new Storages())->either($types), $nullability);
    }

    /**
     * Writes the expression.
     */
    public function render(Output $out): void
    {
        $out->keyword('CASE')->node($this->base);
        foreach ($this->branches as $branch) {
            $out->node($branch);
        }
        if ($this->otherwise !== null) {
            $out->keyword('ELSE')->node($this->otherwise);
        }
        $out->keyword('END');
    }
}
