<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SplObjectStorage;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevelRange;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionEntry;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\LateOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn;
use SqlSemantics\Statement\Node;

/**
 * Refuses statement and expression graphs with contradictory language requirements.
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
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When no grammar generation or lexical mode can contain the combination
     */
    public function check(Node $root): void
    {
        $pending = [$root];
        $seen = new SplObjectStorage();
        $requirements = 0;
        $modes = 0;
        $lexical = new LexicalModes();
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (is_object($value)) {
                if (isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;
                $requirements |= $this->requirement($value);
                $modes |= $lexical->requirement($value);
                $lexical->check($modes);
                Check::input($requirements !== 3, 'A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');
                if ($value instanceof Node) {
                    array_push($pending, ...array_values(get_object_vars($value)));
                }
            }
        }
    }

    /**
     * Answers a legacy requirement (1), a modern requirement (2), or neither (0).
     */
    public function requirement(object $value): int
    {
        if (in_array($value::class, [ProcedureAnalyse::class, LateOrdering::class, LeadingUnion::class, OrderedSetOperation::class, WeightLevel::class, WeightLevelRange::class, EscapedRelation::class, ParseGeneratedColumn::class, PartitionEntry::class, AlterTablespaceAccess::class], true)
            || in_array($value, [SelectOption::Cache, KeywordFunction::Password, KeywordFunction::OldPassword, PasswordFunction::Password, PasswordFunction::OldPassword, AliasMark::Equals], true)
            || ($value instanceof NestedRelation && $value->relation instanceof DerivedTable)) {
            return 1;
        }
        if ($value instanceof WithClause || $value instanceof WindowSpecification
            || in_array($value::class, [CommonTableExpression::class, ValuesQuery::class, ExplicitTable::class, JsonTable::class, JsonValueCall::class, AtTimeZone::class, OdbcJoin::class], true)
            || ($value instanceof Select && $value->qualify !== null)
            || ($value instanceof DerivedTable && ($value->lateral || $value->columns !== []))
            || $value === SetOperator::Intersect || $value === SetOperator::Except) {
            return 2;
        }

        return 0;
    }
}
