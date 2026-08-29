<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite;

use Exception;
use PhpMyAdmin\SqlParser\Components\AlterOperation;
use PhpMyAdmin\SqlParser\Components\Condition;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\JoinKeyword;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Asks the parser to write a piece of a statement back out, and refuses when it will not.
 *
 * The parser answers a component's text by raising on anything it cannot
 * write, and it raises the language's own general failure -- which says
 * nothing about SQL and which no caller of ZTD could sensibly catch. This is
 * the one place that happens, so it is the one place that turns it into a
 * refusal a caller can catch: a statement ZTD could not write back out is a
 * statement ZTD cannot simulate.
 */
final class MySqlComponentSql
{
    /**
     * Writes one expression back out.
     *
     * @param Expression $expression Expression the parser read
     * @param string $sql Statement it came from, for the refusal
     *
     * @return string The expression, as SQL
     *
     * @throws UnsupportedSqlException When the parser will not write it back out
     */
    public function expression(Expression $expression, string $sql): string
    {
        try {
            return Expression::build($expression);
        } catch (Exception $exception) {
            throw new UnsupportedSqlException($sql, 'Unwritable expression', $exception);
        }
    }

    /**
     * Writes a run of joins back out.
     *
     * @param array<array-key, JoinKeyword> $joins Joins the parser read
     * @param string $sql Statement they came from, for the refusal
     *
     * @return string The joins, as SQL
     *
     * @throws UnsupportedSqlException When the parser will not write them back out
     */
    public function joins(array $joins, string $sql): string
    {
        try {
            return JoinKeyword::build($joins);
        } catch (Exception $exception) {
            throw new UnsupportedSqlException($sql, 'Unwritable join', $exception);
        }
    }

    /**
     * Writes one ordering term back out.
     *
     * @param OrderKeyword $order Term the parser read
     * @param string $sql Statement it came from, for the refusal
     *
     * @return string The term, as SQL
     *
     * @throws UnsupportedSqlException When the parser will not write it back out
     */
    public function order(OrderKeyword $order, string $sql): string
    {
        try {
            return OrderKeyword::build($order);
        } catch (Exception $exception) {
            throw new UnsupportedSqlException($sql, 'Unwritable ORDER BY', $exception);
        }
    }

    /**
     * Writes a LIMIT back out.
     *
     * @param Limit $limit Limit the parser read
     * @param string $sql Statement it came from, for the refusal
     *
     * @return string The limit, as SQL
     *
     * @throws UnsupportedSqlException When the parser will not write it back out
     */
    public function limit(Limit $limit, string $sql): string
    {
        try {
            return Limit::build($limit);
        } catch (Exception $exception) {
            throw new UnsupportedSqlException($sql, 'Unwritable LIMIT', $exception);
        }
    }

    /**
     * Writes a run of conditions back out.
     *
     * @param array<array-key, Condition> $conditions Conditions the parser read
     * @param string $sql Statement they came from, for the refusal
     *
     * @return string The conditions, as SQL
     *
     * @throws UnsupportedSqlException When the parser will not write them back out
     */
    public function condition(array $conditions, string $sql): string
    {
        try {
            return Condition::build($conditions);
        } catch (Exception $exception) {
            throw new UnsupportedSqlException($sql, 'Unwritable condition', $exception);
        }
    }

    /**
     * Writes one ALTER operation back out.
     *
     * @param AlterOperation $operation Operation the parser read
     * @param string $sql Statement it came from, for the refusal
     *
     * @return string The operation, as SQL
     *
     * @throws UnsupportedSqlException When the parser will not write it back out
     */
    public function alterOperation(AlterOperation $operation, string $sql): string
    {
        try {
            return AlterOperation::build($operation);
        } catch (Exception $exception) {
            throw new UnsupportedSqlException($sql, 'Unwritable ALTER TABLE operation', $exception);
        }
    }
}
