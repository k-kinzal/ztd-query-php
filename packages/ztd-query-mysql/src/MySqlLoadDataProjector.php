<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Projects LOAD DATA input into the regular simulated INSERT/REPLACE pipeline.
 */
final class MySqlLoadDataProjector
{
    private MySqlIdentifierQuoter $quoter;
    private MySqlValueRenderer $valueRenderer;

    public function __construct(private readonly TableDefinitionRegistry $registry)
    {
        $this->quoter = new MySqlIdentifierQuoter();
        $this->valueRenderer = new MySqlValueRenderer();
    }

    public function project(string $sql, LoadStatement $statement): string
    {
        $tableName = $statement->table?->table;
        if (!is_string($tableName)) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve LOAD DATA target');
        }
        if ($tableName === '') {
            throw new UnsupportedSqlException($sql, 'Cannot resolve LOAD DATA target');
        }
        $definition = $this->registry->get($tableName);
        if ($definition === null) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }
        if ($statement->partition !== null) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA PARTITION cannot be simulated safely');
        }
        if ($statement->charset_name !== null) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA CHARACTER SET conversion is not supported');
        }

        if ($statement->file_name === null) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        $fileProperties = get_object_vars($statement->file_name);
        $path = $fileProperties['file'] ?? null;
        if (!is_string($path)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        if ($path === '') {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        if (!is_file($path)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        if (!is_readable($path)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file could not be read');
        }

        $fieldTerminator = $this->optionValue($statement->fields_options, 'TERMINATED BY', "\t");
        $enclosure = $this->optionValue($statement->fields_options, 'ENCLOSED BY', '');
        $escape = $this->optionValue($statement->fields_options, 'ESCAPED BY', '\\');
        $linePrefix = $this->optionValue($statement->lines_options, 'STARTING BY', '');
        $lineTerminator = $this->optionValue($statement->lines_options, 'TERMINATED BY', "\n");
        if ($fieldTerminator === '' || $lineTerminator === '') {
            throw new UnsupportedSqlException($sql, 'LOAD DATA fixed-row input is not supported');
        }
        if (strlen($enclosure) > 1 || strlen($escape) > 1) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA enclosure and escape must be single-byte values');
        }

        $targets = $this->inputTargets($statement, $definition, $sql);
        $setOperations = $this->setOperations($statement, $definition, $sql);
        $ignoreRows = $this->ignoreRows($statement, $sql);
        $records = $this->splitRecords(
            $contents,
            $fieldTerminator,
            $lineTerminator,
            $enclosure,
            $escape,
        );
        $records = array_slice($records, $ignoreRows);

        $rows = [];
        foreach ($records as $record) {
            if ($linePrefix !== '' && !str_starts_with($record, $linePrefix)) {
                continue;
            }
            if ($linePrefix !== '') {
                $record = substr($record, strlen($linePrefix));
            }
            $values = $this->parseFields($record, $fieldTerminator, $enclosure, $escape);
            $rows[] = $this->projectRow($targets, $setOperations, $values);
        }

        return $this->buildInsertSql($statement, $tableName, $definition, $targets, $setOperations, $rows);
    }

    private function optionValue(?OptionsArray $options, string $name, string $default): string
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

    private function decodeEscapeByte(string $byte): string
    {
        return match ($byte) {
            '0' => "\0",
            'b' => "\x08",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'Z' => "\x1a",
            default => $byte,
        };
    }

    /**
     * @return list<string>
     */
    private function inputTargets(LoadStatement $statement, TableDefinition $definition, string $sql): array
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

    private function inputTarget(string $sqlPart, string $sql): string
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
     */
    private function setOperations(LoadStatement $statement, TableDefinition $definition, string $sql): array
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

    private function ignoreRows(LoadStatement $statement, string $sql): int
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
     * @return list<string>
     */
    private function splitRecords(
        string $contents,
        string $fieldTerminator,
        string $lineTerminator,
        string $enclosure,
        string $escape,
    ): array {
        $records = [];
        $record = '';
        $enclosed = false;
        $atFieldStart = true;

        for ($index = 0; isset($contents[$index]);) {
            $byte = $contents[$index];
            $followingByte = $this->followingByte($contents, $index);
            if ($escape !== '' && $byte === $escape) {
                if ($followingByte !== null) {
                    $record .= $byte . $followingByte;
                    $index += 2;
                    $atFieldStart = false;
                    continue;
                }
            }
            if ($enclosure !== '' && $byte === $enclosure) {
                if ($enclosed && $followingByte === $enclosure) {
                    $record .= $enclosure . $enclosure;
                    $index += 2;
                    continue;
                }
                if ($enclosed) {
                    $enclosed = false;
                } elseif ($atFieldStart) {
                    $enclosed = true;
                }
                $record .= $enclosure;
                $index++;
                continue;
            }
            if (!$enclosed && $this->startsWithAt($contents, $lineTerminator, $index)) {
                $records[] = $record;
                $record = '';
                $index += strlen($lineTerminator);
                $atFieldStart = true;
                continue;
            }
            if (!$enclosed && $this->startsWithAt($contents, $fieldTerminator, $index)) {
                $record .= $fieldTerminator;
                $index += strlen($fieldTerminator);
                $atFieldStart = true;
                continue;
            }
            $record .= $byte;
            $index++;
            $atFieldStart = false;
        }
        if ($record !== '') {
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @return list<string|null>
     */
    private function parseFields(string $record, string $terminator, string $enclosure, string $escape): array
    {
        $fields = [];
        $raw = '';
        $decoded = '';
        $quoted = false;
        $enclosed = false;

        for ($index = 0; isset($record[$index]);) {
            $byte = $record[$index];
            $followingByte = $this->followingByte($record, $index);
            if ($escape !== '' && $byte === $escape) {
                if ($followingByte !== null) {
                    $raw .= $byte . $followingByte;
                    $decoded .= $this->decodeEscapeByte($followingByte);
                    $index += 2;
                    continue;
                }
            }
            if ($enclosure !== '' && $byte === $enclosure) {
                if ($enclosed && $followingByte === $enclosure) {
                    $decoded .= $enclosure;
                    $index += 2;
                    continue;
                }
                if ($enclosed) {
                    $enclosed = false;
                    $index++;
                    continue;
                }
                if (!$quoted) {
                    if ($raw === '') {
                        if ($decoded === '') {
                            $quoted = true;
                            $enclosed = true;
                            $index++;
                            continue;
                        }
                    }
                }
            }
            if (!$enclosed && $this->startsWithAt($record, $terminator, $index)) {
                $fields[] = $this->fieldValue($raw, $decoded, $quoted, $enclosure, $escape);
                $raw = '';
                $decoded = '';
                $quoted = false;
                $index += strlen($terminator);
                continue;
            }
            $raw .= $byte;
            $decoded .= $byte;
            $index++;
        }
        $fields[] = $this->fieldValue($raw, $decoded, $quoted, $enclosure, $escape);

        return $fields;
    }

    private function fieldValue(
        string $raw,
        string $decoded,
        bool $quoted,
        string $enclosure,
        string $escape,
    ): ?string {
        if ($quoted) {
            return $decoded;
        }
        if ($escape !== '' && $raw === $escape . 'N') {
            return null;
        }
        if ($raw !== 'NULL') {
            return $decoded;
        }
        if ($enclosure !== '') {
            return null;
        }
        if ($escape === '') {
            return null;
        }

        return $decoded;
    }

    /**
     * @param list<string> $targets
     * @param array<string, string> $setOperations
     * @param list<string|null> $values
     * @return array<string, string>
     */
    private function projectRow(array $targets, array $setOperations, array $values): array
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
            $row[$column] = $this->substituteVariables($expression, $variables);
        }

        return $row;
    }

    private function renderField(?string $value): string
    {
        return $value === null ? 'NULL' : $this->valueRenderer->renderValue($value);
    }

    /**
     * @param array<string, string|null> $variables
     */
    private function substituteVariables(string $expression, array $variables): string
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

    /**
     * @param list<string> $targets
     * @param array<string, string> $setOperations
     * @param list<array<string, string>> $rows
     */
    private function buildInsertSql(
        LoadStatement $statement,
        string $tableName,
        TableDefinition $definition,
        array $targets,
        array $setOperations,
        array $rows,
    ): string {
        /** @var array<string, null> $columns */
        $columns = [];
        foreach ($targets as $target) {
            if ($target[0] !== '@') {
                $columns[$target] = null;
            }
        }
        foreach (array_keys($setOperations) as $column) {
            $columns[$column] = null;
        }
        $orderedColumns = [];
        foreach ($definition->columns as $column) {
            if (array_key_exists($column, $columns)) {
                $orderedColumns[] = $column;
            }
        }
        if ($orderedColumns === []) {
            throw new UnsupportedSqlException($statement->build(), 'LOAD DATA has no target columns');
        }

        $mode = $statement->replace_ignore;
        if ($mode === 'REPLACE') {
            $prefix = 'REPLACE INTO ';
        } else {
            $local = $statement->options !== null && $statement->options->has('LOCAL') !== false;
            $prefix = ($mode === 'IGNORE' || $local) ? 'INSERT IGNORE INTO ' : 'INSERT INTO ';
        }
        $columnSql = implode(', ', array_map($this->quoter->quote(...), $orderedColumns));
        $targetSql = $this->quoter->quote($tableName) . ' (' . $columnSql . ')';
        if ($rows === []) {
            $selects = [];
            foreach ($orderedColumns as $column) {
                $selects[] = 'NULL AS ' . $this->quoter->quote($column);
            }

            return $prefix . $targetSql . ' SELECT ' . implode(', ', $selects) . ' WHERE FALSE';
        }

        $valueRows = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($orderedColumns as $column) {
                $values[] = $row[$column] ?? 'DEFAULT';
            }
            $valueRows[] = '(' . implode(', ', $values) . ')';
        }

        return $prefix . $targetSql . ' VALUES ' . implode(', ', $valueRows);
    }

    private function startsWithAt(string $value, string $needle, int $offset): bool
    {
        return substr_compare($value, $needle, $offset, strlen($needle)) === 0;
    }

    private function followingByte(string $value, int $offset): ?string
    {
        return $value[$offset + 1] ?? null;
    }

}
