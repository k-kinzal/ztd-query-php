<?php

declare(strict_types=1);

namespace Tests\Support;

use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownColumn;
use SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Tells whether the analysis of a generated statement accepts it.
 *
 * A statement is accepted when the server reports no problem while parsing it and every
 * retained problem reports only database state the statement depends on: a table, column,
 * variable, condition or cursor it names that does not exist yet, a table that exists
 * already, or a feature the build of the server leaves out.
 */
final class Analysis
{
    /**
     * The retained problems that report only database state, that a maintenance statement answers
     * in its result rows, or that planning resolves against the installed table.
     */
    private const STATE = [
        MissingTable::class,
        MissingColumn::class,
        MissingTableQualifier::class,
        UnknownColumn::class,
        UnknownKeyColumn::class,
        UnknownHistogramColumn::class,
        TableExists::class,
        UndeclaredVariable::class,
        HistogramTables::class,
        ConflictingTables::class,
    ];

    /**
     * Tells whether the analysis of the statement reports no parse failure and only database-state problems.
     */
    public static function accepted(Statement $statement): bool
    {
        $information = $statement->statementInformation();
        foreach ($information->warnings as $warning) {
            if ($warning instanceof ParseFailure) {
                return false;
            }
        }
        foreach ($information->diagnostics as $diagnostic) {
            if ($diagnostic instanceof ProgramProblem) {
                if (!in_array($diagnostic->rule, [ProgramRule::UndeclaredVariable, ProgramRule::UndefinedCondition, ProgramRule::UndefinedCursor], true)) {
                    return false;
                }
                continue;
            }
            if ($diagnostic instanceof UtilityMisuse) {
                if ($diagnostic->rule !== UtilityRule::DebugOnly) {
                    return false;
                }
                continue;
            }
            if (!self::state($diagnostic)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tells whether a retained problem reports only database state the statement depends on;
     * a refused CREATE TABLE tail is judged by the condition it retains.
     */
    public static function state(Diagnostic $diagnostic): bool
    {
        if ($diagnostic instanceof RefusedCreateTail) {
            return self::state($diagnostic->problem);
        }

        return in_array($diagnostic::class, self::STATE, true);
    }
}
