<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class MySqlSelectListAliaser
{
    private const MODIFIERS = [
        'ALL', 'DISTINCT', 'DISTINCTROW', 'HIGH_PRIORITY', 'STRAIGHT_JOIN',
        'SQL_SMALL_RESULT', 'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT',
        'SQL_NO_CACHE', 'SQL_CALC_FOUND_ROWS',
    ];

    private const SELECT_LIST_TERMINATORS = [
        'FROM', 'WHERE', 'GROUP', 'HAVING', 'ORDER',
        'LIMIT', 'UNION', 'INTERSECT', 'EXCEPT',
    ];

    private MySqlIdentifierQuoter $quoter;

    public function __construct()
    {
        $this->quoter = new MySqlIdentifierQuoter();
    }

    public function projectionCount(string $sql): ?int
    {
        $endKeywords = [];
        foreach (self::SELECT_LIST_TERMINATORS as $terminator) {
            $endKeywords[] = [$terminator];
        }
        $selectList = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->topLevelClause(['SELECT'], $endKeywords);
        if ($selectList === null) {
            return null;
        }

        $expressions = SqlTokenStream::tokenize($selectList, MySqlLexerProfile::create())->splitTopLevel();
        if ($expressions === [] || $this->containsWildcard($expressions)) {
            return null;
        }

        return count($expressions);
    }

    public function alias(string $sql): string
    {
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
        $select = null;
        $end = null;
        foreach ($tokens as $token) {
            if (!$token->isTopLevel()) {
                continue;
            }
            if ($select === null) {
                if (!$token->isKeyword('SELECT')) {
                    continue;
                }
                $select = $token;
                continue;
            }
            if ($this->endsSelectList($token)) {
                $end = $token->offset;
                break;
            }
        }
        if (!$select instanceof SqlToken) {
            return $sql;
        }

        $listEnd = $end ?? strlen($sql);
        $listSql = substr($sql, $select->endOffset(), $listEnd - $select->endOffset());
        $expressions = SqlTokenStream::tokenize($listSql, MySqlLexerProfile::create())->splitTopLevel();
        if ($expressions === [] || $this->containsWildcard($expressions)) {
            return $sql;
        }

        $prefix = $this->removeModifiers($expressions[0]);
        $expressions[0] = $prefix['expression'];
        foreach ($expressions as $index => $expression) {
            $expressions[$index] = $this->withoutExplicitAlias($expression)
                . ' AS ' . $this->quoter->quote('__ztd_insert_' . $index);
        }

        $replacement = ' ' . $prefix['modifiers'] . implode(', ', $expressions) . ' ';

        return substr($sql, 0, $select->endOffset()) . $replacement . substr($sql, $listEnd);
    }

    private function endsSelectList(SqlToken $token): bool
    {
        foreach (self::SELECT_LIST_TERMINATORS as $terminator) {
            if ($token->isKeyword($terminator)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $expressions */
    private function containsWildcard(array $expressions): bool
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

    /** @return array{modifiers: string, expression: string} */
    private function removeModifiers(string $expression): array
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

    private function isModifier(SqlToken $token): bool
    {
        foreach (self::MODIFIERS as $modifier) {
            if ($token->isKeyword($modifier)) {
                return true;
            }
        }

        return false;
    }

    private function withoutExplicitAlias(string $expression): string
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
