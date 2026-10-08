<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Comparison\Pattern;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Statement\Operation;

/**
 * The rows a SHOW statement answers, filtered by its LIKE or WHERE clause and sent as the server sends them.
 *
 * LIKE matches the pattern against the column that names each row, in the collation the server
 * compares that column in; a pattern with characters that collation lacks is an illegal mix of
 * collations. WHERE is a condition over the columns of the rows, by their names; a
 * condition fixed for the statement is evaluated once, before any row is read, as the server
 * evaluates a constant condition while it optimizes the statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/extended-show.html.
 *
 * @visibility MySqlMemory
 */
final class Listing
{
    /**
     * @param list<Heading> $headings The columns of the rows
     */
    public function __construct(public readonly array $headings)
    {
    }

    /**
     * Answers the result set of rows, filtered by the clause of the statement.
     *
     * @param list<list<int|float|string|null>> $rows The rows, each value in the domain of its column
     * @param int $named The position of the column LIKE matches
     * @param string $collation The collation LIKE compares that column in
     * @param string $derivation How strongly that collation holds, as ER_CANT_AGGREGATE_2COLLATIONS names it
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating the condition fails
     */
    public function result(array $rows, Operation $operation, Session $session, Context $context, Connection $connection, ShowLike|ShowWhere|null $filter = null, int $named = 0, string $collation = 'utf8mb3_general_ci', string $derivation = 'IMPLICIT'): ResultSet
    {
        if ($filter instanceof ShowLike) {
            $this->mix($filter->pattern->value, $collation, $derivation, $context);
            $rows = $this->like($rows, $filter->pattern->value, $named, $collation, $context);
        }
        if ($filter instanceof ShowWhere) {
            $rows = $this->where($rows, $filter, $operation, $session, $context, $connection);
        }

        return $this->sent($rows, $context);
    }

    /**
     * Refuses a pattern with characters the character set of the matched column lacks, as LIKE refuses to mix the collations.
     *
     * The pattern is a literal in the collation of the connection.
     *
     * @throws \MySqlMemory\Error\SqlError When the column cannot hold every character of the pattern
     */
    public function mix(string $pattern, string $collation, string $derivation, Context $context): void
    {
        $column = Collation::known($collation);
        $connection = Collation::named((string) $context->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci');
        if ($column->charset === $connection->charset || $column->bytes() || Encoding::convertible($pattern, $connection->charset, $column->charset) === strlen($pattern)) {
            return;
        }

        throw DataError::CantAggregateTwoCollations->error($collation, $derivation, $connection->name, 'COERCIBLE', 'like');
    }

    /**
     * Keeps the rows whose named column matches a LIKE pattern.
     *
     * @param list<list<int|float|string|null>> $rows
     * @return list<list<int|float|string|null>>
     *
     * @throws \MySqlMemory\Error\SqlError When matching fails
     */
    public function like(array $rows, string $pattern, int $named, string $collation, Context $context): array
    {
        $matched = Collation::known($collation);
        $domain = Domain::string(65535, $matched);
        $kept = [];
        foreach ($rows as $row) {
            $value = $row[$named] ?? null;
            $test = new Pattern(new Constant($domain, $value === null ? null : (string) $value), new Constant($domain, $pattern), null, $matched, false, Domain::integer());
            if ($test->evaluate(new Frame($context)) === 1) {
                $kept[] = $row;
            }
        }

        return $kept;
    }

    /**
     * Keeps the rows a WHERE condition is true for.
     *
     * @param list<list<int|float|string|null>> $rows
     * @return list<list<int|float|string|null>>
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating the condition fails
     */
    public function where(array $rows, ShowWhere $where, Operation $operation, Session $session, Context $context, Connection $connection): array
    {
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $scope = new Scope();
        $scope->place($operation->statement, array_map(static fn (Heading $heading): Domain => $heading->domain(), $this->headings));
        $condition = $planner->compiler->compile($where->condition, $scope);
        if ($planner->compiler->constancy($where->condition)->constant()) {
            $frame = new Frame($context);

            return Convert::toBool($condition->evaluate($frame), $condition->domain(), $context) === true ? $rows : [];
        }
        $kept = [];
        foreach ($rows as $row) {
            if (Convert::toBool($condition->evaluate(new Frame($context, $row)), $condition->domain(), $context) === true) {
                $kept[] = $row;
            }
        }

        return $kept;
    }

    /**
     * Answers the result set of rows: each value in its text, a text value in the character set of the results.
     *
     * @param list<list<int|float|string|null>> $rows
     */
    public function sent(array $rows, Context $context): ResultSet
    {
        $results = $context->variables->read('character_set_results');
        $charset = is_string($results) ? Charset::named($results) : null;
        $sent = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($this->headings as $position => $heading) {
                $value = $row[$position] ?? null;
                $text = $value === null ? null : (is_float($value) ? Convert::toText($value, $heading->domain()) : (string) $value);
                $values[] = $text !== null && $heading->text && $charset !== null ? Encoding::convert($text, Charset::known('utf8mb4'), $charset) : $text;
            }
            $sent[] = $values;
        }

        return new ResultSet(array_map(static fn (Heading $heading) => $heading->column($charset), $this->headings), $sent, $context->diagnostics->count());
    }
}
