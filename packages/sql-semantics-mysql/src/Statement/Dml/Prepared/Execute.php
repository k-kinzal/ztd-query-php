<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * EXECUTE: runs a prepared statement with user variables for its parameter markers.
 *
 * Rule: MYSQL-EXECUTE-001. The prepared statement is a session object; what
 * it does and whether it returns rows depends on the text it was prepared
 * from, so the statement records no rows. The variables are derived as
 * session values. Terminates: one pass over the variables. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/execute.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading an EXECUTE
 *     $execute = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('EXECUTE stmt USING @a, @b');
 *     [count($execute->statement->variables), $execute->toString()] // => [2, 'EXECUTE stmt USING @a, @b']
 */
final class Execute implements Statement
{
    use Snapshot;

    /**
     * @var list<UserVariable> The variables in written order
     */
    public readonly array $variables;

    /**
     * @param Name $name The name of the prepared statement
     * @param list<UserVariable> $variables The variables that supply the parameter values
     */
    public function __construct(public readonly Name $name, array $variables = [])
    {
        $this->variables = Check::listOf($variables, UserVariable::class, 'EXECUTE ... USING takes user variables.');
    }

    /**
     * Derives the variables; the statement records no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->variables as $variable) {
            $derivation->scalar($variable, $derivation->environment());
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXECUTE')->name($this->name, NameUse::Label);
        if ($this->variables !== []) {
            $out->keyword('USING')->list($this->variables);
        }
    }
}
