<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDOStatement;
use ZtdQuery\Platform\SqlPlaceholderEscaper;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class PdoParameterBinder
{
    /**
     * @param array<int|string, mixed>|null $params
     * @return array{sql: string, params: array<int|string, mixed>|null}
     */
    public function compile(
        string $sql,
        string $driver,
        ?array $params,
        ?SqlPlaceholderEscaper $placeholderEscaper = null,
    ): array {
        if ($driver !== 'pgsql' && $driver !== 'sqlite') {
            return ['sql' => $sql, 'params' => $params];
        }
        if ($driver === 'sqlite' && $params === null) {
            return ['sql' => $sql, 'params' => null];
        }

        $tokens = SqlTokenStream::tokenize($sql)->tokens();
        $replacements = [];
        $positionalIndex = 0;
        $nativePositions = [];

        foreach ($tokens as $token) {
            if ($token->kind !== SqlTokenKind::Parameter) {
                continue;
            }

            if ($driver === 'pgsql') {
                if (!str_starts_with($token->text, '$')) {
                    continue;
                }
                $position = filter_var(
                    substr($token->text, 1),
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]],
                );
                if (!is_int($position)) {
                    continue;
                }
                $nativePositions[$position] = $position;
                $replacements[$token->offset] = [
                    'length' => strlen($token->text),
                    'sql' => ':__ztd_pdo_' . $position,
                ];
                continue;
            }

            if ($token->text === '?') {
                $parameterKey = $positionalIndex;
                $positionalIndex++;
            } else {
                $name = ltrim($token->text, ':');
                $parameterKey = array_key_exists($name, $params) ? $name : $token->text;
            }
            if (!array_key_exists($parameterKey, $params)) {
                continue;
            }
            $cast = $this->sqliteCast($params[$parameterKey]);
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

        if ($driver === 'pgsql' && $placeholderEscaper !== null) {
            $sql = $placeholderEscaper->escape($sql);
        }

        if ($nativePositions === [] || $params === null) {
            return ['sql' => $sql, 'params' => $params];
        }

        $mapped = [];
        foreach ($nativePositions as $position) {
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
            $parameter = is_int($key) ? $position++ : $this->parameterName($key);
            if (!$statement->bindValue($parameter, $value, PdoParameterType::fromValue($value))) {
                return false;
            }
        }

        return $statement->execute();
    }

    private function parameterName(string $parameter): string
    {
        if (str_starts_with($parameter, ':')) {
            return $parameter;
        }

        return sprintf(':%s', $parameter);
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
}
