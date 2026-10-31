<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\Table;

/**
 * Checks the declarations visible while deriving a nested statement.
 *
 * Search settings may change, but the language, completeness and enclosing
 * declarations remain fixed. Only declarations of the current statement may be added.
 *
 * @visibility SqlSemantics
 */
final class DeclarationScope
{
    /**
     * Validates a nested context against the enclosing and newly declared tables.
     *
     * @param list<Table> $declarations The declarations already contributed by this statement
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the nested context changes its enclosing declarations or language
     */
    public function check(AnalysisContext $outer, AnalysisContext $nested, array $declarations): void
    {
        Check::input($nested->profile->compatibleWith($outer->profile) && $nested->complete === $outer->complete, 'A nested statement is derived under the same profile and completeness.');
        foreach ($outer->tables as $table) {
            Check::input(in_array($table, $nested->tables, true), 'A nested statement sees every declaration of the enclosing context.');
        }
        foreach ($nested->tables as $table) {
            Check::input(in_array($table, $outer->tables, true) || in_array($table, $declarations, true), 'A nested statement sees only the declarations of the enclosing context and of the statement itself.');
        }
    }
}
