<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnNaming;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\CaseTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A conditional expression: `CASE [operand] WHEN … THEN … [ELSE …] END`.
 *
 * Mirrors PostgreSQL's `CaseExpr` node. The branches are tried in order and
 * only the taken branch's result is evaluated.
 *
 * Rule: PG-CASE-001. Facts follow PG-CASE-TYPING-001: the result is of the
 * common type of the results (PG-UNIFICATION-001), NULL when a result can be
 * or no ELSE is written. An unaliased result column is named after the ELSE
 * result when it names one firmly, and otherwise `case`.
 * Source: https://www.postgresql.org/docs/17/functions-conditional.html#FUNCTIONS-CASE,
 * https://www.postgresql.org/docs/17/typeconv-union-case.html. Status: Implemented.
 *
 * @visibility public
 * @example Typing a CASE without ELSE
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT CASE 1 WHEN 1 THEN 2 WHEN 2 THEN 3.5 END');
 *     [$query->field(0)->type->descriptor->name(), $query->field(0)->nullability, $query->field(0)->name->value] // => ['numeric', \SqlSemantics\Statement\Type\Nullability::Nullable, 'case']
 */
final class CaseExpression implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<CaseBranch> The branches in order
     */
    public readonly array $branches;

    /**
     * @param Scalar|null $operand The value the branches compare with, or null for a searched CASE
     * @param list<CaseBranch> $branches The branches in order; at least one
     * @param Scalar|null $default The ELSE result, or null when none is written
     */
    public function __construct(public readonly ?Scalar $operand, array $branches, public readonly ?Scalar $default = null)
    {
        $this->branches = Check::listOf($branches, CaseBranch::class, 'A CASE expression has at least one WHEN branch.', 1);
    }

    /**
     * Names an unaliased result column after the ELSE result, or `case`.
     */
    public function outputName(): ?Name
    {
        return (new ColumnNaming())->name($this);
    }

    /**
     * Derives the operand, the branches and the ELSE result, and the type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new CaseTyping())->derive($derivation, $environment, $this);
    }

    /**
     * Writes CASE, the operand, the branches, ELSE and END.
     */
    public function render(Output $out): void
    {
        $out->keyword('CASE')->node($this->operand);
        foreach ($this->branches as $branch) {
            $out->node($branch);
        }
        if ($this->default !== null) {
            $out->keyword('ELSE')->node($this->default);
        }
        $out->keyword('END');
    }
}
