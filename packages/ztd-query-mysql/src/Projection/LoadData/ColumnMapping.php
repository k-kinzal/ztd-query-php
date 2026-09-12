<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\LoadData;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Column Mapping.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ColumnMapping
{
    /**
     * @return list<string>
     * @throws UnsupportedSqlException
     */
    public function inputTargets(LoadStatement $statement, TableDefinition $definition, string $sql): array
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
        $closing = $tokens[count($tokens) - 1];
        if ($opening->text !== '(' || $closing->text !== ')') {
            throw new UnsupportedSqlException($sql, 'Invalid LOAD DATA column list');
        }
        $contents = substr($expression, $opening->endOffset(), $closing->offset - $opening->endOffset());
        $parts = SqlTokenStream::tokenize($contents, MySqlLexerProfile::create())->splitTopLevel();
        $targets = [];
        foreach ($parts as $part) {
            $target = $this->inputTarget($part, $sql);
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
     * Input Target for the supplied MySQL input.
     * @throws UnsupportedSqlException
     */
    public function inputTarget(string $sqlPart, string $sql): string
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
     * @return array<string, string>
     * @throws UnsupportedSqlException
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
     * Ignore Rows for the supplied MySQL input.
     * @throws UnsupportedSqlException
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
     * Option Value for the supplied MySQL input.
     */
    public function optionValue(?OptionsArray $options, string $name, string $default): string
    {
        if ($options === null) {
            return $default;
        }
        foreach ($options->options as $option) {
            if (!is_array($option)) {
                continue;
            }
            if (($option['name'] ?? null) !== $name) {
                continue;
            }
            $expression = $option['expr'] ?? null;
            if (!$expression instanceof Expression) {
                continue;
            }
            if (!is_string($expression->column)) {
                continue;
            }

            return $expression->column;
        }

        return $default;
    }
}
