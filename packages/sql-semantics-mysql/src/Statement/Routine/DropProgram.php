<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * DROP PROCEDURE, DROP FUNCTION, DROP TRIGGER or DROP EVENT.
 *
 * Rule: MYSQL-DROP-PROGRAM-001. The statement is structured as a request
 * only: it changes no context and derives nothing. DROP FUNCTION with an
 * unqualified name drops a stored function of the current database or,
 * when there is none, a loadable function of that name; which one is not
 * decided here.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-function-loadable.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-trigger.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-event.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a DROP TRIGGER
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DROP TRIGGER IF EXISTS shop.tr');
 *     [$drop->statement->kind->value, $drop->statement->ifExists, $drop->statement->name->schema->value] // => ['TRIGGER', true, 'shop']
 */
final class DropProgram implements Statement
{
    use Snapshot;

    /**
     * @param ProgramKind $kind The kind of stored program dropped
     * @param QualifiedName $name The program name with its optional database
     * @param bool $ifExists Whether IF EXISTS is written
     */
    public function __construct(public readonly ProgramKind $kind, public readonly QualifiedName $name, public readonly bool $ifExists = false)
    {
        Check::input($name->catalog === null, 'A program name has at most a database qualifier.');
    }

    /**
     * Derives nothing: dropping a stored program is a request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', $this->kind->value);
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        (new ProgramNames())->qualified($out, $this->name);
    }
}
