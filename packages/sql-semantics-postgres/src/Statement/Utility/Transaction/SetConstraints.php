<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;

/**
 * `SET CONSTRAINTS { ALL | name [, ...] } { DEFERRED | IMMEDIATE }`: when the constraints are checked in the current transaction.
 *
 * Rule: PG-SET-CONSTRAINTS-001. Mirrors PostgreSQL's `ConstraintsSetStmt`:
 * the constraint names, where no name stands for ALL, and whether checking
 * is deferred to the end of the transaction. Facts: none; a constraint is
 * looked up by name among the constraints of the database when the command runs.
 * Source: https://www.postgresql.org/docs/17/sql-set-constraints.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SetConstraints implements Statement
{
    use Snapshot;

    /**
     * @var list<QualifiedName> The constraint names; none stands for ALL
     */
    public readonly array $constraints;

    /**
     * @param list<QualifiedName> $constraints The constraint names; none stands for ALL
     * @param bool $deferred True for DEFERRED, false for IMMEDIATE
     */
    public function __construct(array $constraints, public readonly bool $deferred)
    {
        $this->constraints = Check::listOf($constraints, QualifiedName::class, 'SET CONSTRAINTS takes constraint names.');
    }

    /**
     * Derives nothing: constraints are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET', 'CONSTRAINTS');
        if ($this->constraints === []) {
            $out->keyword('ALL');
        }
        foreach ($this->constraints as $position => $constraint) {
            if ($position > 0) {
                $out->symbol(',');
            }
            (new Spelling())->qualified($out, $constraint, NameUse::Column);
        }
        $out->keyword($this->deferred ? 'DEFERRED' : 'IMMEDIATE');
    }
}
