<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * ALTER PROCEDURE or ALTER FUNCTION: changes the characteristics of a stored routine.
 *
 * Rule: MYSQL-ALTER-ROUTINE-001. The statement is structured as a request
 * only: it changes no context and derives nothing. The parameters and the
 * body of a routine cannot be altered, and DETERMINISTIC is no
 * characteristic of ALTER.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-function.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading an ALTER FUNCTION
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER FUNCTION shop.f NO SQL');
 *     [$alter->statement->kind, count($alter->statement->characteristics)] // => [\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function, 1]
 */
final class AlterRoutine implements Statement
{
    use Snapshot;

    /**
     * @var list<Characteristic> The characteristics in written order
     */
    public readonly array $characteristics;

    /**
     * @param ProgramKind $kind Whether a procedure or a function is altered
     * @param QualifiedName $name The routine name with its optional database
     * @param list<Characteristic> $characteristics The new characteristics; may be empty
     */
    public function __construct(public readonly ProgramKind $kind, public readonly QualifiedName $name, array $characteristics = [])
    {
        Check::input($kind === ProgramKind::Procedure || $kind === ProgramKind::Function, 'ALTER of a routine names a procedure or a function.');
        Check::input($name->catalog === null, 'A routine name has at most a database qualifier.');
        $this->characteristics = Check::listOf($characteristics, Characteristic::class, 'A routine holds characteristics.');
        foreach ($this->characteristics as $characteristic) {
            Check::input(!$characteristic instanceof Determinism, 'ALTER of a routine has no DETERMINISTIC characteristic.');
        }
    }

    /**
     * Derives nothing: altering a routine is a request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', $this->kind->value);
        (new ProgramNames())->qualified($out, $this->name);
        foreach ($this->characteristics as $characteristic) {
            $out->node($characteristic);
        }
    }
}
