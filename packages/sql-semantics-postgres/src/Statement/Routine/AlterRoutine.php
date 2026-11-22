<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\RoutineChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineOption;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;

/**
 * `ALTER FUNCTION`, `PROCEDURE` or `ROUTINE ... action ...`: changes options of a routine.
 *
 * Mirrors PostgreSQL's `AlterFunctionStmt`. Only the options that CREATE and
 * ALTER share can be changed; the optional trailing RESTRICT is ignored by
 * the server and not kept.
 * Source: https://www.postgresql.org/docs/17/sql-alterfunction.html.
 *
 * @visibility SqlSemantics
 */
final class AlterRoutine implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<RoutineOption> The changed options in written order
     */
    public readonly array $options;

    /**
     * @param ObjectKind $kind FUNCTION, PROCEDURE or ROUTINE
     * @param RoutineSignature $signature The routine
     * @param list<RoutineOption> $options The changed options; at least one, each one CREATE and ALTER share
     */
    public function __construct(public readonly ObjectKind $kind, public readonly RoutineSignature $signature, array $options)
    {
        Check::input(in_array($kind, [ObjectKind::Function, ObjectKind::Procedure, ObjectKind::Routine], true), 'ALTER names a function, a procedure or a routine.');
        $this->options = Check::listOf($options, RoutineOption::class, 'ALTER changes at least one routine option.', 1);
        foreach ($this->options as $option) {
            Check::input($option->alterable(), 'ALTER changes only the options CREATE and ALTER share.');
        }
    }

    /**
     * Derives the signature and the options and reports options the server rejects.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        $this->signature->deriveClause($derivation, $environment);
        foreach ($this->options as $option) {
            $option->deriveClause($derivation, $environment);
        }
        (new RoutineChecks())->options($this->options, $this->kind === ObjectKind::Procedure, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', ...$this->kind->keywords())->node($this->signature);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
