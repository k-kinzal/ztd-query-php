<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * CALL of a stored procedure with its arguments.
 *
 * Rule: MYSQL-CALL-001. The arguments are derived where no relation is
 * visible. The procedure is a stored routine, which the analysis context
 * does not declare; whether the call returns result sets, and how many,
 * depends on the procedure body, so the statement records no rows. `CALL p`
 * and `CALL p()` are the same call. Terminates: one pass over the
 * arguments. Source: https://dev.mysql.com/doc/refman/8.4/en/call.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ProcedureCall implements Statement
{
    use Snapshot;

    /**
     * @var list<Scalar> The arguments in written order
     */
    public readonly array $arguments;

    /**
     * @param QualifiedName $procedure The procedure name with its optional database
     * @param list<Scalar> $arguments The arguments
     */
    public function __construct(public readonly QualifiedName $procedure, array $arguments = [])
    {
        Check::input($procedure->catalog === null, 'A procedure is qualified by at most a database.');
        $this->arguments = Check::listOf($arguments, Scalar::class, 'The arguments of CALL are expressions.');
    }

    /**
     * Derives the arguments where no relation is visible; the statement records no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument, $derivation->environment());
        }
    }

    /**
     * Writes the statement; the parentheses are written when there are arguments.
     */
    public function render(Output $out): void
    {
        $out->keyword('CALL');
        if ($this->procedure->schema !== null) {
            $out->name($this->procedure->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->procedure->name, NameUse::Routine);
        if ($this->arguments !== []) {
            $out->glue()->symbol('(')->list($this->arguments)->symbol(')');
        }
    }
}
