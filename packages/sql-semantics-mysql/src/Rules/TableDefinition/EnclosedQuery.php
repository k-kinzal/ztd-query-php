<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\QueryTails;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;

/**
 * Writes the 5.x form of CREATE TABLE ... SELECT whose PARTITION BY clause stands inside the parenthesis that opens the query.
 *
 * Rule: MYSQL-ENCLOSED-PARTITIONING-001. MySQL 5.6 and 5.7 accept
 * `CREATE TABLE t (PARTITION BY … SELECT …) UNION …` (`create2a:
 * opt_create_partitioning create_select ')' union_opt`): the partitioning
 * belongs to the table and the parenthesized SELECT is the first operand of
 * the query, as in `CREATE TABLE t PARTITION BY … (SELECT …) UNION …`. The
 * query's first operand is reached through the left operands of set
 * operations and leading unions and the body of a query expression without a WITH clause; it
 * must be a parenthesized query. The partitioning is written after its
 * opening parenthesis, everything else as the query writes it
 * (MYSQL-QUERY-TAIL-001). Terminates:
 * the walk follows strict subtrees.
 * Source: sql/sql_yacc.yy of MySQL 5.7 (`create2a`),
 * https://dev.mysql.com/doc/refman/5.7/en/create-table-select.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class EnclosedQuery
{
    /**
     * Tells whether the first operand of a query is a parenthesized query that the partitioning can be written into.
     */
    public function accepts(Query $query): bool
    {
        while (!$query instanceof ParenthesizedQuery) {
            if ($query instanceof SetOperation || $query instanceof LeadingUnion) {
                $query = $query->left;
            } elseif ($query instanceof QueryExpression && $query->with === null) {
                $query = $query->body;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes a query with the partitioning inside the parenthesis of its first operand.
     */
    public function write(Output $out, Query|LeadingUnion $query, Partitioning $partitioning): void
    {
        if ($query instanceof ParenthesizedQuery) {
            $out->symbol('(')->node($partitioning)->node($query->query)->symbol(')');
        } elseif ($query instanceof SetOperation) {
            $this->write($out, $query->left, $partitioning);
            (new QueryTails())->operation($query, $out);
        } elseif ($query instanceof LeadingUnion) {
            $this->write($out, $query->left, $partitioning);
            (new QueryTails())->leading($query, $out);
        } else {
            Check::invariant($query instanceof QueryExpression && $query->with === null, 'An enclosed query starts with a parenthesized query.');
            $this->write($out, $query->body, $partitioning);
            (new QueryTails())->expression($query, $out);
        }
    }
}
