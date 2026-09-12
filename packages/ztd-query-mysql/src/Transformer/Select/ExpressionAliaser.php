<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Select;

use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Expression Aliaser.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ExpressionAliaser
{
    private const MODIFIERS = [
        'ALL', 'DISTINCT', 'DISTINCTROW', 'HIGH_PRIORITY', 'STRAIGHT_JOIN',
        'SQL_SMALL_RESULT', 'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT',
        'SQL_NO_CACHE', 'SQL_CALC_FOUND_ROWS',
    ];
    /**
     * Select list terminators.
     */
    public const SELECT_LIST_TERMINATORS = [
        'FROM', 'WHERE', 'GROUP', 'HAVING', 'ORDER',
        'LIMIT', 'UNION', 'INTERSECT', 'EXCEPT',
    ];

    /**
     * Ends Select List for the supplied MySQL input.
     */
    public function endsSelectList(SqlToken $token): bool
    {
        foreach (self::SELECT_LIST_TERMINATORS as $terminator) {
            if ($token->isKeyword($terminator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $expressions
     */
    public function containsWildcard(array $expressions): bool
    {
        foreach ($expressions as $expression) {
            $tokens = SqlTokenStream::tokenize($expression, MySqlLexerProfile::create())->significantTokens();
            if ($tokens === []) {
                return true;
            }
            $last = $tokens[count($tokens) - 1];
            if ($last->kind !== SqlTokenKind::Symbol) {
                continue;
            }
            if ($last->text === '*') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{modifiers: string, expression: string}
     */
    public function removeModifiers(string $expression): array
    {
        $end = null;
        foreach (SqlTokenStream::tokenize($expression, MySqlLexerProfile::create())->significantTokens() as $token) {
            if (!$this->isModifier($token)) {
                break;
            }
            $end = $token->endOffset();
        }

        if ($end === null) {
            return ['modifiers' => '', 'expression' => $expression];
        }

        return [
            'modifiers' => substr($expression, 0, $end) . ' ',
            'expression' => trim(substr($expression, $end)),
        ];
    }

    /**
     * Is Modifier for the supplied MySQL input.
     */
    public function isModifier(SqlToken $token): bool
    {
        foreach (self::MODIFIERS as $modifier) {
            if ($token->isKeyword($modifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Without Explicit Alias for the supplied MySQL input.
     */
    public function withoutExplicitAlias(string $expression): string
    {
        $tokens = SqlTokenStream::tokenize($expression, MySqlLexerProfile::create())->significantTokens();
        array_pop($tokens);
        foreach (array_reverse($tokens) as $token) {
            if ($token->isTopLevel() && $token->isKeyword('AS')) {
                return rtrim(substr($expression, 0, $token->offset));
            }
        }

        return $expression;
    }
}
