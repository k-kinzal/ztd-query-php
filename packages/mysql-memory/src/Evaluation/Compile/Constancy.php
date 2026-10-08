<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;

/**
 * How long the value of an expression stays the same: for the whole statement, known when it is resolved or only when it runs, or for one row.
 *
 * A literal, and a function of literals such as DATABASE(), ROW_COUNT() or CONCAT('a', 'b'), is
 * known when the statement is resolved. The account functions (USER() and its synonyms), the
 * clocks of the statement, CONNECTION_ID(), LAST_INSERT_ID(), FOUND_ROWS(), user and system
 * variables the statement does not assign, parameters and an uncorrelated subquery are fixed for the statement but known only
 * when it runs. A column, an aggregate, an assignment, a correlated subquery and a function such
 * as RAND() or SYSDATE() vary by row. A subquery of one select item without a table or any other
 * clause is the expression it selects, as the server merges it into the enclosing block.
 *
 * @visibility MySqlMemory
 */
enum Constancy: int
{
    case Resolved = 0;
    case Statement = 1;
    case Row = 2;

    /**
     * The functions fixed for a statement but known only when it runs.
     */
    public const STATEMENT_FUNCTIONS = ['USER', 'SESSION_USER', 'SYSTEM_USER', 'CURRENT_USER', 'CONNECTION_ID', 'FOUND_ROWS', 'LAST_INSERT_ID', 'UNIX_TIMESTAMP', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'NOW', 'CURDATE', 'CURTIME', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'LOCALTIME', 'LOCALTIMESTAMP'];

    /**
     * The functions whose value can differ for each call.
     */
    public const ROW_FUNCTIONS = ['RAND', 'UUID', 'UUID_SHORT', 'SYSDATE', 'SLEEP', 'BENCHMARK', 'GET_LOCK', 'RELEASE_LOCK', 'RELEASE_ALL_LOCKS', 'IS_FREE_LOCK', 'IS_USED_LOCK', 'RANDOM_BYTES', 'VALUES', 'NEXTVAL'];

    /**
     * Answers how long the value of an expression stays the same.
     *
     * @param bool $correlation Whether a subquery that reads a column of the enclosing block varies by row; when false, every subquery is fixed for the statement
     * @param list<string> $assigned The lower-case names of the user variables the statement assigns, which vary by row
     * @param bool $assignmentsVary Whether an assignment to a user variable varies by row; when false, it stays as long as the value it assigns
     */
    public static function of(Node $node, Facts $facts, bool $correlation = true, array $assigned = [], bool $assignmentsVary = true): self
    {
        return self::within($node, $facts, 0, $correlation, $assigned, $assignmentsVary);
    }

    /**
     * Answers how long the value of a node stays the same, the node lying a number of subqueries inside the expression.
     *
     * Inside a subquery only a column of a block outside the expression varies by row.
     *
     * @param list<string> $assigned The lower-case names of the user variables the statement assigns
     * @param bool $assignmentsVary Whether an assignment to a user variable varies by row
     */
    public static function within(Node $node, Facts $facts, int $level, bool $correlation, array $assigned, bool $assignmentsVary = true): self
    {
        if ($node instanceof ColumnUse) {
            return self::column($node, $facts, $level);
        }
        if ($level > 0) {
            return self::children($node, $facts, $node instanceof Query ? $level + 1 : $level, $correlation, $assigned, $assignmentsVary);
        }
        $merged = $node instanceof ScalarSubquery ? self::merged($node->query) : null;
        if ($merged !== null) {
            return self::within($merged, $facts, 0, $correlation, $assigned, $assignmentsVary);
        }
        if ($node instanceof Query) {
            $inner = self::children($node, $facts, 1, $correlation, $assigned, $assignmentsVary);

            return $inner === self::Row && $correlation ? self::Row : self::Statement;
        }

        return self::form($node, $facts, $correlation, $assigned, $assignmentsVary);
    }

    /**
     * Answers how long the value of a node of the expression itself, outside any subquery, stays the same by its form.
     *
     * @param list<string> $assigned The lower-case names of the user variables the statement assigns
     * @param bool $assignmentsVary Whether an assignment to a user variable varies by row
     */
    public static function form(Node $node, Facts $facts, bool $correlation, array $assigned, bool $assignmentsVary): self
    {
        return match (true) {
            $node instanceof VariableAssignment && !$assignmentsVary => self::Statement->join(self::within($node->value, $facts, 0, $correlation, $assigned, $assignmentsVary)),
            $node instanceof OutputOrdinal, $node instanceof Aggregate, $node instanceof GroupConcat, $node instanceof VariableAssignment, $node instanceof DefaultOfColumn, $node instanceof InsertedColumn => self::Row,
            $node instanceof UserVariable => in_array(strtolower($node->name->value), $assigned, true) ? self::Row : self::Statement,
            $node instanceof SystemVariable, $node instanceof Parameter => self::Statement,
            $node instanceof ClockCall => $node->clock === Clock::SystemDate ? self::Row : self::Statement,
            $node instanceof FunctionCall => self::function(strtoupper($node->name->value), count($node->arguments))->join(self::children($node, $facts, 0, $correlation, $assigned, $assignmentsVary)),
            $node instanceof KeywordCall => ($node->function === KeywordFunction::User || $node->function === KeywordFunction::CurrentUser ? self::Statement : self::Resolved)->join(self::children($node, $facts, 0, $correlation, $assigned, $assignmentsVary)),
            default => self::children($node, $facts, 0, $correlation, $assigned, $assignmentsVary),
        };
    }

    /**
     * Answers how long a column read stays the same: a column of the block, or of a block outside the subquery it is read in, varies by row.
     */
    public static function column(ColumnUse $use, Facts $facts, int $level): self
    {
        if ($level === 0) {
            return self::Row;
        }
        $resolution = $facts->covers($use) ? $facts->scalar($use)->resolution : null;

        return $resolution instanceof ResolvedColumn && $resolution->depth >= $level ? self::Row : self::Resolved;
    }

    /**
     * Answers how long a call of a function of a name stays the same, before its arguments are considered.
     */
    public static function function(string $name, int $arguments): self
    {
        if (in_array($name, self::ROW_FUNCTIONS, true) || ($name === 'LAST_INSERT_ID' && $arguments > 0)) {
            return self::Row;
        }

        return in_array($name, self::STATEMENT_FUNCTIONS, true) ? self::Statement : self::Resolved;
    }

    /**
     * Joins how long the children of a node stay the same.
     *
     * @param list<string> $assigned The lower-case names of the user variables the statement assigns
     * @param bool $assignmentsVary Whether an assignment to a user variable varies by row
     */
    public static function children(Node $node, Facts $facts, int $level, bool $correlation, array $assigned, bool $assignmentsVary = true): self
    {
        $children = [];
        $properties = get_object_vars($node);
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        $constancy = self::Resolved;
        foreach ($children as $child) {
            $constancy = $constancy->join(self::within($child, $facts, $level, $correlation, $assigned, $assignmentsVary));
            if ($constancy === self::Row) {
                break;
            }
        }

        return $constancy;
    }

    /**
     * Answers the expression a subquery selects when the server merges it into the enclosing block: one select item and no table or other clause.
     */
    public static function merged(Query $query): ?Scalar
    {
        while ($query instanceof ParenthesizedQuery || ($query instanceof QueryExpression && $query->with === null && $query->orderBy === [] && $query->limit === null)) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof Select || ($query->from !== null && !$query->from instanceof Dual) || $query->where !== null || $query->groupBy !== null || $query->having !== null || $query->windows !== [] || $query->qualify !== null || $query->orderBy !== [] || $query->into !== null || count($query->items) !== 1) {
            return null;
        }
        $item = $query->items[0];

        return $item instanceof SelectExpression ? $item->expression : null;
    }

    /**
     * Answers the longer lived of two constancies being the shorter: a row-varying part makes the whole vary by row.
     */
    public function join(self $other): self
    {
        return $this->value >= $other->value ? $this : $other;
    }

    /**
     * Tells whether the value stays the same for the whole statement.
     */
    public function constant(): bool
    {
        return $this !== self::Row;
    }
}
