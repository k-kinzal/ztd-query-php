<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

final class SqlStatementTemplateDetector
{
    public static function contains(string $value, bool $completeStatementContext = false): bool
    {
        $tokens = self::tokenize($value);
        foreach ($tokens as $index => $token) {
            if (!self::isBoundary($tokens, $index)) {
                continue;
            }
            if ($token === 'WITH' && self::isCommonTableExpression($tokens, $index)) {
                return true;
            }
            if ($token === 'SELECT' && self::isSelect($tokens, $index, $completeStatementContext)) {
                return true;
            }
            if (in_array($token, ['INSERT', 'REPLACE', 'MERGE'], true)
                && self::containsAfter($tokens, $index, 'INTO')
            ) {
                return true;
            }
            if ($token === 'UPDATE' && self::containsAfter($tokens, $index, 'SET')) {
                return true;
            }
            if ($token === 'DELETE' && self::containsAfter($tokens, $index, 'FROM')) {
                return true;
            }
            if ($token === 'CREATE' && self::containsObjectType($tokens, $index)) {
                return true;
            }
            if (in_array($token, ['ALTER', 'DROP'], true)
                && self::containsObjectType($tokens, $index)
            ) {
                return true;
            }
            if ($token === 'TRUNCATE' && self::hasValueAfter($tokens, $index)) {
                return true;
            }
            if ($token === 'LOAD' && ($tokens[$index + 1] ?? null) === 'DATA') {
                return true;
            }
            if ($token === 'COPY'
                && (self::containsAfter($tokens, $index, 'FROM') || self::containsAfter($tokens, $index, 'TO'))
            ) {
                return true;
            }
            if (in_array($token, ['CALL', 'EXPLAIN', 'VACUUM', 'PRAGMA', 'ATTACH', 'DETACH', 'GRANT', 'REVOKE'], true)
                && self::hasValueAfter($tokens, $index)
            ) {
                return true;
            }
            if ($token === 'START' && ($tokens[$index + 1] ?? null) === 'TRANSACTION') {
                return true;
            }
            if ($token === 'SAVEPOINT' && self::hasValueAfter($tokens, $index)) {
                return true;
            }
            if ($token === 'RELEASE' && ($tokens[$index + 1] ?? null) === 'SAVEPOINT') {
                return true;
            }
            if ($token === 'SET'
                && (self::containsAfter($tokens, $index, '=')
                    || in_array($tokens[$index + 1] ?? null, ['TRANSACTION', 'SESSION', 'LOCAL', 'NAMES'], true))
            ) {
                return true;
            }
            if (in_array($token, ['BEGIN', 'COMMIT', 'ROLLBACK'], true)
                && self::isTransactionControl($tokens, $index, $completeStatementContext)
            ) {
                return true;
            }
            if ($token === 'FROM'
                && self::hasValueAfter($tokens, $index)
                && self::containsAfter($tokens, $index, 'WHERE')
            ) {
                return true;
            }
            if ($token === 'JOIN'
                && self::hasValueAfter($tokens, $index)
                && self::containsAfter($tokens, $index, 'ON')
            ) {
                return true;
            }
            if ($token === 'WHERE' && self::containsComparisonAfter($tokens, $index)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function tokenize(string $value): array
    {
        $tokens = [];
        $length = strlen($value);
        for ($index = 0; $index < $length;) {
            $character = $value[$index];
            $next = $value[$index + 1] ?? '';
            if (ctype_space($character)) {
                $index++;
                continue;
            }
            if ($character === '/' && $next === '*') {
                $index += 2;
                while ($index < $length && !($value[$index] === '*' && ($value[$index + 1] ?? '') === '/')) {
                    $index++;
                }
                $index = min($length, $index + 2);
                continue;
            }
            if (($character === '-' && $next === '-') || $character === '#') {
                while ($index < $length && !in_array($value[$index], ["\r", "\n"], true)) {
                    $index++;
                }
                continue;
            }
            if (in_array($character, ["'", '"', '`'], true)) {
                $quote = $character;
                $index++;
                while ($index < $length) {
                    if ($value[$index] === '\\') {
                        $index += 2;
                        continue;
                    }
                    if ($value[$index] !== $quote) {
                        $index++;
                        continue;
                    }
                    if (($value[$index + 1] ?? '') === $quote) {
                        $index += 2;
                        continue;
                    }
                    $index++;
                    break;
                }
                $tokens[] = '__ZTD_VALUE__';
                continue;
            }
            if ($character === '[') {
                $index++;
                while ($index < $length && $value[$index] !== ']') {
                    $index++;
                }
                $index = min($length, $index + 1);
                $tokens[] = '__ZTD_VALUE__';
                continue;
            }
            if (ctype_alnum($character) || in_array($character, ['_', '$', ':', '%', '{'], true)) {
                $start = $index;
                do {
                    $index++;
                    $current = $value[$index] ?? '';
                } while ($index < $length
                    && (ctype_alnum($current) || in_array($current, ['_', '$', ':', '%', '.', '}'], true))
                );
                $tokens[] = strtoupper(substr($value, $start, $index - $start));
                continue;
            }
            if (in_array($character . $next, ['<>', '!=', '<=', '>='], true)) {
                $tokens[] = $character . $next;
                $index += 2;
                continue;
            }
            $tokens[] = $character;
            $index++;
        }

        return $tokens;
    }

    /**
     * @param list<string> $tokens
     */
    private static function isBoundary(array $tokens, int $index): bool
    {
        if ($index === 0) {
            return true;
        }

        return isset($tokens[$index - 1]) && in_array($tokens[$index - 1], ['(', '=', ';'], true);
    }

    /**
     * @param list<string> $tokens
     */
    private static function isCommonTableExpression(array $tokens, int $index): bool
    {
        $nameIndex = $index + (($tokens[$index + 1] ?? null) === 'RECURSIVE' ? 2 : 1);
        if (!self::isValue($tokens[$nameIndex] ?? null)) {
            return false;
        }

        return self::containsAfter($tokens, $nameIndex, 'AS')
            && self::containsAfter($tokens, $nameIndex, '(');
    }

    /**
     * @param list<string> $tokens
     */
    private static function isSelect(array $tokens, int $index, bool $completeStatementContext): bool
    {
        $projectionIndex = $index + 1;
        if (in_array($tokens[$projectionIndex] ?? null, ['DISTINCT', 'ALL'], true)) {
            $projectionIndex++;
        }
        $projection = $tokens[$projectionIndex] ?? null;
        if (!self::isValue($projection) && $projection !== '*' && $projection !== '(') {
            return false;
        }

        return $completeStatementContext || self::containsAfter($tokens, $projectionIndex, 'FROM');
    }

    /**
     * @param list<string> $tokens
     */
    private static function containsObjectType(array $tokens, int $index): bool
    {
        foreach (array_slice($tokens, $index + 1, 8) as $token) {
            if (in_array($token, ['TABLE', 'VIEW', 'INDEX', 'DATABASE', 'SCHEMA', 'DOMAIN', 'TYPE', 'SEQUENCE'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     */
    private static function isTransactionControl(array $tokens, int $index, bool $completeStatementContext): bool
    {
        $next = $tokens[$index + 1] ?? null;
        if ($next === null || $next === ';') {
            return $completeStatementContext;
        }

        return in_array($next, ['WORK', 'TRANSACTION', 'TO', 'PREPARED', 'AND'], true);
    }

    /**
     * @param list<string> $tokens
     */
    private static function hasValueAfter(array $tokens, int $index): bool
    {
        return self::isValue($tokens[$index + 1] ?? null);
    }

    private static function isValue(?string $token): bool
    {
        return $token !== null && !in_array($token, [')', '(', ',', ';', '='], true);
    }

    /**
     * @param list<string> $tokens
     */
    private static function containsAfter(array $tokens, int $index, string $expected): bool
    {
        return in_array($expected, array_slice($tokens, $index + 1), true);
    }

    /**
     * @param list<string> $tokens
     */
    private static function containsComparisonAfter(array $tokens, int $index): bool
    {
        foreach (array_slice($tokens, $index + 1) as $token) {
            if (in_array($token, ['=', '<>', '!=', '<', '>', '<=', '>='], true)) {
                return true;
            }
        }

        return false;
    }
}
