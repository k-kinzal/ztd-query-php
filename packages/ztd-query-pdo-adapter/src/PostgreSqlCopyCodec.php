<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class PostgreSqlCopyCodec
{
    /** @return array{name: string, sql: string} */
    public function relation(string $tableName): array
    {
        $tokens = SqlTokenStream::tokenize($tableName)->significantTokens();
        if ($tokens === []) {
            throw new \ValueError('PostgreSQL COPY table name must not be empty.');
        }

        $components = [];
        for ($index = 0; isset($tokens[$index]); $index++) {
            $components[] = $this->identifier($tokens[$index], 'table name');
            $index++;
            if (!isset($tokens[$index])) {
                continue;
            }

            $separator = $tokens[$index];
            if ($separator->kind !== SqlTokenKind::Symbol || $separator->text !== '.') {
                throw new \ValueError('PostgreSQL COPY table name must be an identifier or schema-qualified identifier.');
            }
            if (!isset($tokens[$index + 1])) {
                throw new \ValueError('PostgreSQL COPY table name must not end with a qualifier separator.');
            }
        }

        if (count($components) > 2) {
            throw new \ValueError('PostgreSQL COPY table name may contain at most a schema and table component.');
        }

        return [
            'name' => $components[count($components) - 1],
            'sql' => implode('.', array_map($this->quoteIdentifier(...), $components)),
        ];
    }

    /** @return list<string> */
    public function columns(?string $fields, TableDefinition $definition): array
    {
        if ($fields === null) {
            $columns = [];
            foreach ($definition->columns as $column) {
                if (!isset($definition->generatedExpressions[$column])) {
                    $columns[] = $column;
                }
            }

            return $columns;
        }

        $parts = SqlTokenStream::tokenize($fields)->splitTopLevel();
        if ($parts === [] || in_array('', $parts, true)) {
            throw new \ValueError('PostgreSQL COPY fields must contain at least one column identifier.');
        }

        $columns = [];
        foreach ($parts as $part) {
            $tokens = SqlTokenStream::tokenize($part)->significantTokens();
            if (count($tokens) !== 1) {
                throw new \ValueError('Each PostgreSQL COPY field must be a single column identifier.');
            }
            $column = $this->identifier($tokens[0], 'field');
            if (in_array($column, $columns, true)) {
                throw new \ValueError(sprintf('PostgreSQL COPY field "%s" is specified more than once.', $column));
            }
            $columns[] = $column;
        }

        return $columns;
    }

    /** @param list<string> $columns */
    public function columnListSql(array $columns): string
    {
        if ($columns === []) {
            throw new \ValueError('PostgreSQL COPY requires at least one non-generated column.');
        }

        return implode(', ', array_map($this->quoteIdentifier(...), $columns));
    }

    /** @param list<mixed> $values */
    public function encodeRow(array $values, string $separator, string $nullAs): string
    {
        $this->validateSeparator($separator);
        $encoded = [];
        foreach ($values as $value) {
            if ($value === null) {
                $encoded[] = $nullAs;
                continue;
            }

            $encoded[] = $this->escape($this->copyOutput($value), $separator);
        }

        return implode($separator, $encoded) . "\n";
    }

    /** @return list<string|null> */
    public function decodeRow(string $row, string $separator, string $nullAs): array
    {
        $this->validateSeparator($separator);
        if (str_ends_with($row, "\r\n")) {
            $row = substr($row, 0, -2);
        } elseif (str_ends_with($row, "\n") || str_ends_with($row, "\r")) {
            $row = substr($row, 0, -1);
        }
        if (str_contains($row, "\n") || str_contains($row, "\r")) {
            throw new \ValueError('PostgreSQL COPY rows must escape embedded newlines and carriage returns.');
        }
        if ($row === '\\.') {
            throw new \ValueError('PostgreSQL COPY end-of-data markers are not row values.');
        }

        return $this->decodeFields($row, $separator, $nullAs);
    }

    private function identifier(SqlToken $token, string $subject): string
    {
        if (!in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true)) {
            throw new \ValueError(sprintf('PostgreSQL COPY %s must be a valid identifier.', $subject));
        }

        $parsed = SqlTokenStream::tokenize($token->text)->identifierAt();
        if ($parsed === null) {
            throw new \ValueError(sprintf('PostgreSQL COPY %s must be a valid identifier.', $subject));
        }

        return $token->kind === SqlTokenKind::Word ? strtolower($parsed['name']) : $parsed['name'];
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function validateSeparator(string $separator): void
    {
        if (strlen($separator) !== 1) {
            throw new \ValueError('PostgreSQL COPY separator must be exactly one byte.');
        }
    }

    private function copyOutput(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? 't' : 'f';
        }
        if (is_resource($value)) {
            $bytes = stream_get_contents($value);
            if ($bytes === false) {
                throw new \ValueError('PostgreSQL COPY could not read a binary value.');
            }

            return '\\x' . bin2hex($bytes);
        }

        throw new \ValueError(sprintf('PostgreSQL COPY cannot encode a value of type %s.', get_debug_type($value)));
    }

    private function escape(string $value, string $separator): string
    {
        $result = '';
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];
            $escaped = match ($character) {
                "\x08" => '\\b',
                "\x0C" => '\\f',
                "\n" => '\\n',
                "\r" => '\\r',
                "\t" => '\\t',
                "\x0B" => '\\v',
                '\\' => '\\\\',
                default => null,
            };
            $result .= $escaped ?? ($character === $separator ? '\\' . $character : $character);
        }

        return $result;
    }

    /** @return list<string|null> */
    private function decodeFields(string $row, string $separator, string $nullAs): array
    {
        $values = [];
        $raw = '';
        $decoded = '';
        $length = strlen($row);
        for ($index = 0; $index <= $length; $index++) {
            if ($index === $length || $row[$index] === $separator) {
                $values[] = $raw === $nullAs ? null : $decoded;
                $raw = '';
                $decoded = '';
                continue;
            }

            $character = $row[$index];
            if ($character !== '\\') {
                $raw .= $character;
                $decoded .= $character;
                continue;
            }

            $raw .= $character;
            $next = $row[$index + 1] ?? null;
            if ($next === null) {
                throw new \ValueError('PostgreSQL COPY field ends with an incomplete backslash escape.');
            }
            $raw .= $next;
            $index++;

            if ($next >= '0' && $next <= '7') {
                $digits = $next;
                while (strlen($digits) < 3 && isset($row[$index + 1]) && $row[$index + 1] >= '0' && $row[$index + 1] <= '7') {
                    $digit = $row[++$index];
                    $raw .= $digit;
                    $digits .= $digit;
                }
                $byte = intval($digits, 8);
                if ($byte < 0 || $byte > 255) {
                    throw new \ValueError('PostgreSQL COPY octal escape must fit in one byte.');
                }
                $decoded .= chr($byte);
                continue;
            }
            if ($next === 'x' && isset($row[$index + 1]) && ctype_xdigit($row[$index + 1])) {
                $digit = $row[++$index];
                $raw .= $digit;
                $digits = $digit;
                if (isset($row[$index + 1]) && ctype_xdigit($row[$index + 1])) {
                    $digit = $row[++$index];
                    $raw .= $digit;
                    $digits .= $digit;
                }
                $byte = intval($digits, 16);
                if ($byte < 0 || $byte > 255) {
                    throw new \ValueError('PostgreSQL COPY hexadecimal escape must fit in one byte.');
                }
                $decoded .= chr($byte);
                continue;
            }

            $decoded .= match ($next) {
                'b' => "\x08",
                'f' => "\x0C",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'v' => "\x0B",
                default => $next,
            };
        }

        return $values;
    }
}
