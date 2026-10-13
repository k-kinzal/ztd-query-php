<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SplObjectStorage;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Node;

/**
 * Refuses repeated node occurrences and contradictory language requirements.
 *
 * Rule: MYSQL-QUERY-GENERATIONS-001. PROCEDURE ANALYSE, SQL_CACHE and the
 * legacy trailing-clause forms end before 8.0. WITH, VALUES, TABLE, window
 * specifications, JSON_TABLE, QUALIFY, INTERSECT and EXCEPT start in 8.x or later. A
 * graph containing both sets cannot belong to any supported release. This
 * includes legacy password calls, WEIGHT_STRING levels, table escapes and
 * generated-column entry points nested inside modern statements. Every MySQL
 * statement constructor and composite expression checks its entire graph.
 * This is a constructor invariant; it does not decide whether a statement
 * executes or whether a particular release accepts every feature.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/mysql-nutshell.html,
 * https://dev.mysql.com/doc/refman/8.0/en/with.html,
 * https://dev.mysql.com/doc/refman/8.4/en/set-operations.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Generations
{
    /**
     * Checks all nested operands, including subqueries, assignments and routine bodies.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When a node occurs twice or no language profile can contain the combination
     */
    public function check(Node $root): void
    {
        $pending = [$root];
        $seen = new SplObjectStorage();
        $requirements = 0;
        $modes = 0;
        $spelling = null;
        $namedParameters = false;
        $lexical = new LexicalModes();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (is_object($value)) {
                if (isset($seen[$value])) {
                    Check::input(!$value instanceof Node, 'A statement node occurs at one position only.');
                    continue;
                }
                $seen[$value] = true;
                if ($value instanceof LanguageProfile) {
                    Check::input($spelling === null || $spelling->compatibleWith($value), 'Observable spellings in one structure require one compatible language profile.');
                    $spelling = $value;
                }
                $namedParameters = $namedParameters || ($value instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Parameter && $value->marker !== '?');
                $requirements |= $this->requirement($value);
                $modes |= $lexical->requirement($value);
                $lexical->check($modes);
                Check::input($requirements !== 3, 'A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');
                if ($value instanceof Node) {
                    array_push($pending, ...array_values(get_object_vars($value)));
                }
            }
        }
        Check::input(!$namedParameters || $spelling === null || $spelling->parameters === ParameterStyle::Named, 'A named parameter cannot accompany a spelling that requires native parameters.');
    }

    /**
     * Answers a legacy requirement (1), a modern requirement (2), or neither (0).
     */
    public function requirement(object $value): int
    {
        if ($value instanceof LanguageProfile) {
            return in_array($value->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? 1 : 2;
        }
        if ((new LegacyForms())->requires($value)) {
            return 1;
        }
        return (new ModernForms())->requires($value) ? 2 : 0;
    }

}
