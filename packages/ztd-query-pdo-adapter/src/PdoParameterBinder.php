<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use PDOStatement;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class PdoParameterBinder
{
    /**
     * @param array<int|string, mixed>|null $params
     * @return array{sql: string, params: array<int|string, mixed>|null}
     */
    public function compile(string $sql, string $driver, ?array $params): array
    {
        $tokens = SqlTokenStream::tokenize($sql)->tokens();
        $replacements = [];
        $positionalIndex = 0;
        $nativePositions = [];

        foreach ($tokens as $token) {
            if ($token->kind !== SqlTokenKind::Parameter) {
                continue;
            }

            if ($driver === 'pgsql' && preg_match('/^\$(\d+)$/', $token->text, $matches) === 1) {
                $position = (int) $matches[1];
                $nativePositions[$position] = true;
                $replacements[$token->offset] = [
                    'length' => strlen($token->text),
                    'sql' => ':__ztd_pdo_' . $position,
                ];
                continue;
            }

            if ($driver !== 'sqlite') {
                continue;
            }

            $value = null;
            $hasValue = false;
            if ($token->text === '?') {
                if ($params !== null && array_key_exists($positionalIndex, $params)) {
                    $value = $params[$positionalIndex];
                    $hasValue = true;
                }
                $positionalIndex++;
            } elseif ($params !== null) {
                $name = ltrim($token->text, ':');
                if (array_key_exists($name, $params)) {
                    $value = $params[$name];
                    $hasValue = true;
                } elseif (array_key_exists($token->text, $params)) {
                    $value = $params[$token->text];
                    $hasValue = true;
                }
            }

            $cast = $hasValue ? $this->sqliteCast($value) : null;
            if ($cast !== null) {
                $replacements[$token->offset] = [
                    'length' => strlen($token->text),
                    'sql' => 'CAST(' . $token->text . ' AS ' . $cast . ')',
                ];
            }
        }

        if ($replacements !== []) {
            krsort($replacements);
            foreach ($replacements as $offset => $replacement) {
                $sql = substr_replace($sql, $replacement['sql'], $offset, $replacement['length']);
            }
        }

        if ($driver === 'pgsql') {
            $sql = PostgreSqlPlaceholderEscaper::escape($sql);
        }

        if ($nativePositions === [] || $params === null) {
            return ['sql' => $sql, 'params' => $params];
        }

        $mapped = [];
        foreach (array_keys($nativePositions) as $position) {
            if (array_key_exists($position - 1, $params)) {
                $mapped['__ztd_pdo_' . $position] = $params[$position - 1];
            }
        }

        return ['sql' => $sql, 'params' => $mapped];
    }

    /** @param array<int|string, mixed>|null $params */
    public function execute(PDOStatement $statement, ?array $params): bool
    {
        if ($params === null) {
            return $statement->execute();
        }

        $position = 1;
        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $position++ : ':' . ltrim($key, ':');
            if (!$statement->bindValue($parameter, $value, $this->pdoType($value))) {
                return false;
            }
        }

        return $statement->execute();
    }

    private function sqliteCast(mixed $value): ?string
    {
        if (is_int($value) || is_bool($value)) {
            return 'INTEGER';
        }
        if (is_float($value)) {
            return 'REAL';
        }

        return null;
    }

    private function pdoType(mixed $value): int
    {
        return match (true) {
            $value === null => PDO::PARAM_NULL,
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            is_resource($value) => PDO::PARAM_LOB,
            default => PDO::PARAM_STR,
        };
    }
}
