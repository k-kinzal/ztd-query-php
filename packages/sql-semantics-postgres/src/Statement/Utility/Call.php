<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OpenStar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;

/**
 * `CALL name(arguments)`: a request to run a procedure.
 *
 * Rule: PG-CALL-001. Mirrors PostgreSQL's `CallStmt`, which holds the call
 * as a `FuncCall`. Only a name and plain or named arguments are a procedure
 * call. Facts: the arguments are derived where no relation is visible; the
 * call itself depends on the declaration of the procedure. A procedure with
 * output parameters returns one row of them, so the returned row is open
 * and names the procedure as the missing declaration. Termination: the
 * arguments are derived once.
 * Source: https://www.postgresql.org/docs/17/sql-call.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a window call
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Call(new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('p')]), [], false, false, false, [], false, null, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('w'))) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class Call implements Statement
{
    use Snapshot;

    /**
     * @param FunctionCall $call The procedure name and its arguments
     */
    public function __construct(public readonly FunctionCall $call)
    {
        Check::input($call->filter === null && $call->over === null && !$call->withinGroup, 'A procedure call takes no FILTER, OVER or WITHIN GROUP.');
    }

    /**
     * Derives the call and records the open row of the output parameters.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->scalar($this->call, $derivation->environment());
        $name = $this->call->name->qualified();
        if ($name !== null) {
            $derivation->output(new QueryFact([new OpenStar([new UndeclaredRoutine($name)])], $derivation->context->columnNames));
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CALL')->node($this->call);
    }
}
