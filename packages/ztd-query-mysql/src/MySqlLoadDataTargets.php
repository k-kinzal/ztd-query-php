<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Decides where the values a LOAD DATA reads are going.
 *
 * A field goes into a column, or into a user variable that a SET clause later
 * writes an expression with. A column the table generates is written by the
 * table, not by the statement, so nothing may be loaded into one.
 */
final class MySqlLoadDataTargets
{
    /**
     * @param MySqlValueRenderer $valueRenderer Writes a value as MySQL would write it
     */
    public function __construct(private readonly MySqlValueRenderer $valueRenderer = new MySqlValueRenderer())
    {
    }

    /**
     * Answers where each field of a record goes, in order.
     *
     * A statement that names nothing loads into every column the table does not
     * generate itself, in the order the table declares them.
     *
     * @param LoadStatement $statement Statement being simulated
     * @param TableDefinition $definition What the table holds
     * @param string $sql Statement text, for anything it refuses
     *
     * @return list<string> Column names, and user variables written with a leading at sign
     *
     * @throws UnsupportedSqlException When the statement names a target the table cannot be loaded into
     */
    public function of(LoadStatement $statement, TableDefinition $definition, string $sql): array
    {
        $expressions = $statement->col_name_or_user_var;
        if ($expressions === null || $expressions === []) {
            return array_values(array_filter(
                $definition->columns,
                static fn (string $column): bool => !isset($definition->generatedExpressions[$column]),
            ));
        }
        if (count($expressions) !== 1) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve LOAD DATA column list');
        }

        $expression = $expressions[0]->expr;
        if ($expression === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve LOAD DATA column list');
        }
        $stream = SqlTokenStream::tokenize($expression, MySqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        if (count($tokens) < 2) {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA column list');
        }
        $opening = $tokens[0];
        if ($opening->text !== '(') {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA column list');
        }
        $closing = $tokens[count($tokens) - 1];
        if ($closing->text !== ')') {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA column list');
        }
        $contents = substr($expression, $opening->endOffset(), $closing->offset - $opening->endOffset());
        $parts = SqlTokenStream::tokenize($contents, MySqlLexerProfile::create())->splitTopLevel();
        $targets = [];
        foreach ($parts as $part) {
            $target = $this->targetIn($part, $sql);
            $normalized = strtolower($target);
            if (isset($targets[$normalized])) {
                throw new UnsupportedSqlException($sql, 'Duplicate LOAD DATA input target');
            }
            if ($target[0] !== '@') {
                if (!in_array($target, $definition->columns, true)) {
                    throw new UnsupportedSqlException($sql, 'Unknown LOAD DATA target column');
                }
                if (isset($definition->generatedExpressions[$target])) {
                    throw new UnsupportedSqlException($sql, 'LOAD DATA cannot assign a generated column');
                }
            }
            $targets[$normalized] = $target;
        }

        return array_values($targets);
    }

    /**
     * Answers the one target a part of the list names.
     *
     * @param string $sqlPart One entry of the target list, as written
     * @param string $sql Statement text, for anything it refuses
     *
     * @return string The column name, or the user variable with its leading at sign
     *
     * @throws UnsupportedSqlException When the entry names no single target
     */
    public function targetIn(string $sqlPart, string $sql): string
    {
        $stream = SqlTokenStream::tokenize($sqlPart, MySqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $first = $tokens[0] ?? null;
        if ($first === null) {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA target column');
        }
        if ($first->text === '@') {
            $identifier = $stream->identifierAt(1);
            if ($identifier === null) {
                throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA user variable');
            }
            if ($identifier['next'] !== count($tokens)) {
                throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA user variable');
            }

            return '@' . $identifier['name'];
        }

        $identifier = $stream->identifierAt();
        if ($identifier === null) {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA target column');
        }
        if ($identifier['next'] !== count($tokens)) {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA target column');
        }

        return $identifier['name'];
    }

    /**
     * Answers what each SET clause assigns, under the column it assigns to.
     *
     * @param LoadStatement $statement Statement being simulated
     * @param TableDefinition $definition What the table holds
     * @param string $sql Statement text, for anything it refuses
     *
     * @return array<string, string> Column => the expression assigned to it, as written
     *
     * @throws UnsupportedSqlException When a clause assigns to a column the table will not take
     */
    public function setOperations(LoadStatement $statement, TableDefinition $definition, string $sql): array
    {
        $operations = [];
        foreach ($statement->set ?? [] as $operation) {
            $column = $operation->column;
            $value = $operation->value;
            if (!in_array($column, $definition->columns, true)) {
                throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA SET operation');
            }
            if (isset($definition->generatedExpressions[$column])) {
                throw new UnsupportedSqlException($sql, 'LOAD DATA cannot assign a generated column');
            }
            if (isset($operations[$column])) {
                throw new UnsupportedSqlException($sql, 'Duplicate LOAD DATA SET target');
            }
            $operations[$column] = $value;
        }

        return $operations;
    }

    /**
     * Answers how many records at the start of the file are not data.
     *
     * @param LoadStatement $statement Statement being simulated
     * @param string $sql Statement text, for anything it refuses
     *
     * @return int How many records to skip, and none where the statement said nothing
     *
     * @throws UnsupportedSqlException When the count is not a number of records
     */
    public function ignoreRows(LoadStatement $statement, string $sql): int
    {
        $value = $statement->ignore_number?->expr;
        if ($value === null) {
            return 0;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if (!is_int($parsed)) {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA IGNORE row count');
        }
        if ($parsed < 0) {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA IGNORE row count');
        }

        return $parsed;
    }

    /**
     * Answers the row one record would write, as SQL each column would take.
     *
     * A field the record simply does not have is left to the table's own default
     * rather than written as null: a short record is not a record of nulls.
     *
     * @param list<string> $targets Where each field goes, in order
     * @param array<string, string> $setOperations Column => the expression assigned to it
     * @param list<string|null> $values The values the record held
     *
     * @return array<string, string> Column => the SQL that writes its value
     */
    public function rowOf(array $targets, array $setOperations, array $values): array
    {
        $row = [];
        $variables = [];
        foreach ($targets as $index => $target) {
            $hasValue = array_key_exists($index, $values);
            $value = $values[$index] ?? null;
            if ($target[0] === '@') {
                $variables[strtolower(substr($target, 1))] = $hasValue ? $value : null;
                continue;
            }
            $row[$target] = $hasValue ? $this->renderField($value) : 'DEFAULT';
        }
        foreach ($setOperations as $column => $expression) {
            $row[$column] = $this->withVariables($expression, $variables);
        }

        return $row;
    }

    /**
     * Answers a loaded value as the SQL that writes it.
     *
     * @param string|null $value Value the record held, or null where it held none
     *
     * @return string SQL writing that value
     */
    public function renderField(?string $value): string
    {
        return $value === null ? 'NULL' : $this->valueRenderer->renderValue($value);
    }

    /**
     * Writes an expression with each user variable replaced by what was loaded into it.
     *
     * Only a variable the statement actually loaded into is replaced; anything
     * else is left alone, because the database has its own value for it.
     *
     * @param string $expression Expression a SET clause assigns, as written
     * @param array<string, string|null> $variables Variable name in lower case => what was loaded into it
     *
     * @return string The expression, with the loaded values written into it
     */
    public function withVariables(string $expression, array $variables): string
    {
        $stream = SqlTokenStream::tokenize($expression, MySqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $result = '';
        $cursor = 0;
        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; $index++) {
            $token = $tokens[$index];
            if ($token->text !== '@') {
                continue;
            }
            $previous = $tokens[$index - 1] ?? null;
            if ($previous !== null && $previous->text === '@') {
                continue;
            }
            $identifier = $stream->identifierAt($index + 1);
            if ($identifier === null) {
                continue;
            }
            $name = strtolower($identifier['name']);
            if (!array_key_exists($name, $variables)) {
                continue;
            }
            $last = $tokens[$identifier['next'] - 1];
            $result .= substr($expression, $cursor, $token->offset - $cursor);
            $result .= $this->renderField($variables[$name]);
            $cursor = $last->endOffset();
        }

        return $result . substr($expression, $cursor);
    }
}
