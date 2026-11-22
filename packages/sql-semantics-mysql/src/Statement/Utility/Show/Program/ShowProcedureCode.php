<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Program;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule;

/**
 * SHOW PROCEDURE CODE: the internal instructions of a stored procedure, in debug builds of the server.
 *
 * Rule: MYSQL-SHOW-PROCEDURECODE-001. The statement is available in debug builds of the server only; a
 * release build refuses it (ER_FEATURE_DISABLED), which is reported as
 * UtilityRule::DebugOnly. The rows are those of a debug build: `Pos` and
 * `Instruction`, the columns the manual names, typed as the server source
 * declares them. Stored programs are not part of a context, so the name is
 * not resolved. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-procedure-code.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowProcedureCode implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $name The name with its optional database
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::RoutineCode);
        $derivation->report(new UtilityMisuse(UtilityRule::DebugOnly));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'PROCEDURE', 'CODE');
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Routine);
    }
}
