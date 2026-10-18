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
 * @example Refusing a modern stored-call interpretation inside a legacy query
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('X'));
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($call)]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing two calls whose stored-function interpretations cannot coexist
 *     $x = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('X')));
 *     $regexp = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('REGEXP_LIKE')));
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [$x, $regexp]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a legacy parameter name combined with a vector parameter
 *     $parameter = new \SqlSemantics\Platform\MySql\Statement\Routine\Parameter(new \SqlSemantics\Statement\Identifier\Name(''), new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Vector));
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([$parameter]), new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers(false)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a legacy default collation in a transactional table definition
 *     new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), options: [new \SqlSemantics\Platform\MySql\Statement\Table\Option\StartTransaction(), new \SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption(new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing engine attributes combined with ALTER IGNORE
 *     new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions([new \SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind::EngineAttribute, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('{}'))])], true) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
        $releases = ReleaseDomain::ALL;
        $languages = new ReleaseDomain();
        $calls = [];
        $transition = 0;
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
                if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall || $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall) {
                    $calls[] = $value;
                }
                $requirements |= $this->requirement($value);
                $transition |= $this->transition($value);
                Check::input($transition !== 3, 'A structure cannot combine MySQL 5.6-only forms with forms introduced in MySQL 5.7 or later.');
                $modes |= $lexical->requirement($value);
                $lexical->check($modes);
                Check::input($requirements !== 3, 'A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');
                $releases &= $languages->of($value) & ($requirements === 1 ? 3 : ($requirements === 2 ? 508 : ReleaseDomain::ALL)) & ($transition === 1 ? 1 : ($transition === 2 ? 510 : ReleaseDomain::ALL));
                Check::input($releases !== 0, 'The retained operands must share a supported MySQL release.');
                if ($value instanceof Node) {
                    array_push($pending, ...array_values(get_object_vars($value)));
                }
            }
        }
        Check::input((new Signatures())->compatible($calls, $requirements, $transition, $spelling, $releases), 'Native signatures and grammar operands must share a supported language interpretation.');
        Check::input(!$namedParameters || $spelling === null || $spelling->parameters === ParameterStyle::Named, 'A named parameter cannot accompany a spelling that requires native parameters.');
    }

    /**
     * Answers legacy (1) and modern (2) requirement bits, including conflicts within one node.
     */
    public function requirement(object $value): int
    {
        if ($value instanceof LanguageProfile) {
            return in_array($value->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? 1 : 2;
        }
        return ((new LegacyForms())->requires($value) ? 1 : 0) | ((new ModernForms())->requires($value) ? 2 : 0);
    }

    /**
     * Identifies the incompatible sides of the 5.6-to-5.7 grammar transition.
     */
    public function transition(object $value): int
    {
        return match (true) {
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram && $value->legacyName() => 1,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable && $value->ignore => 1,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn,
            $value === \SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Json => 2,
            $value instanceof LanguageProfile => $value->grammar === GrammarRelease::MySql5651 ? 1 : 2,
            $value instanceof \SqlSemantics\Platform\MySql\Statement\Name\AccountName => (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->problem($value->user, $value->host, GrammarRelease::MySql5651) === null ? 0 : 2,
            default => 0,
        };
    }

}
