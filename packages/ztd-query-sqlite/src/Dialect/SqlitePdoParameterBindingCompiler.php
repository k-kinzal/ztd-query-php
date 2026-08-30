<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Dialect;

use ZtdQuery\Platform\ParameterBindingCompiler;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The sqlite pdo parameter binding compiler, as parameter binding compiler.
 */
final class SqlitePdoParameterBindingCompiler implements ParameterBindingCompiler
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param SqliteCastRenderer $castRenderer
     */
    public function __construct(
        private readonly SqliteCastRenderer $castRenderer = new SqliteCastRenderer(),
    ) {
    }

    /**
     * Compile.
     *
     * @param string $sql
     * @param array<array-key, mixed>|null $params Parameters the caller bound, or null where it bound none
     */
    public function compile(string $sql, ?array $params): array
    {
        if ($params === null) {
            return ['sql' => $sql, 'params' => null];
        }

        $replacements = [];
        $positionalIndex = 0;
        foreach (SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->tokens() as $token) {
            if ($token->kind !== SqlTokenKind::Parameter) {
                continue;
            }
            if ($token->text === '?') {
                $parameterKey = $positionalIndex++;
            } else {
                $name = ltrim($token->text, ':');
                $parameterKey = array_key_exists($name, $params) ? $name : $token->text;
            }
            if (!array_key_exists($parameterKey, $params)) {
                continue;
            }
            $type = $this->parameterType($params[$parameterKey]);
            if ($type !== null) {
                $replacements[$token->offset] = [
                    'length' => strlen($token->text),
                    'sql' => $this->castRenderer->renderCast($token->text, $type),
                ];
            }
        }

        if ($replacements !== []) {
            krsort($replacements);
            foreach ($replacements as $offset => $replacement) {
                $sql = substr_replace($sql, $replacement['sql'], $offset, $replacement['length']);
            }
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Answers which of PDO's types a value is bound as.
     *
     * @param mixed $value Value to read
     *
     * @return ColumnDeclaration|null What it answers
     */
    public function parameterType(mixed $value): ?ColumnDeclaration
    {
        return match (true) {
            is_bool($value) => new ColumnDeclaration(ColumnTypeFamily::BOOLEAN, 'INTEGER'),
            is_int($value) => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
            is_float($value) => new ColumnDeclaration(ColumnTypeFamily::DOUBLE, 'REAL'),
            default => null,
        };
    }
}
