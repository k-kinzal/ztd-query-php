<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class SelectListAliaser
{
    private const MODIFIERS = [
        'ALL', 'DISTINCT', 'DISTINCTROW', 'HIGH_PRIORITY', 'STRAIGHT_JOIN',
        'SQL_SMALL_RESULT', 'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT',
        'SQL_NO_CACHE', 'SQL_CALC_FOUND_ROWS',
    ];

    public function alias(string $sql, IdentifierQuoter $quoter): string
    {
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        $select = null;
        $end = null;
        foreach ($tokens as $token) {
            if (!$token->isTopLevel()) {
                continue;
            }
            if ($select === null && $token->isKeyword('SELECT')) {
                $select = $token;
                continue;
            }
            if ($select !== null && $this->endsSelectList($token)) {
                $end = $token->offset;
                break;
            }
        }
        if (!$select instanceof SqlToken) {
            return $sql;
        }

        $listEnd = $end ?? strlen($sql);
        $listSql = substr($sql, $select->endOffset(), $listEnd - $select->endOffset());
        $expressions = SqlTokenStream::tokenize($listSql)->splitTopLevel();
        if ($expressions === [] || $this->containsWildcard($expressions)) {
            return $sql;
        }

        $prefix = $this->removeModifiers($expressions[0]);
        $expressions[0] = $prefix['expression'];
        foreach ($expressions as $index => $expression) {
            $expressions[$index] = $this->withoutExplicitAlias($expression)
                . ' AS ' . $quoter->quote('__ztd_insert_' . $index);
        }

        $replacement = ' ' . $prefix['modifiers'] . implode(', ', $expressions) . ' ';

        return substr($sql, 0, $select->endOffset()) . $replacement . substr($sql, $listEnd);
    }

    private function endsSelectList(SqlToken $token): bool
    {
        return $token->isKeyword('FROM')
            || $token->isKeyword('WHERE')
            || $token->isKeyword('GROUP')
            || $token->isKeyword('HAVING')
            || $token->isKeyword('ORDER')
            || $token->isKeyword('LIMIT')
            || $token->isKeyword('UNION')
            || $token->isKeyword('INTERSECT')
            || $token->isKeyword('EXCEPT');
    }

    /** @param list<string> $expressions */
    private function containsWildcard(array $expressions): bool
    {
        foreach ($expressions as $expression) {
            $tokens = SqlTokenStream::tokenize($expression)->significantTokens();
            $last = $tokens[count($tokens) - 1] ?? null;
            if ($last?->kind === SqlTokenKind::Symbol && $last->text === '*') {
                return true;
            }
        }

        return false;
    }

    /** @return array{modifiers: string, expression: string} */
    private function removeModifiers(string $expression): array
    {
        $end = 0;
        foreach (SqlTokenStream::tokenize($expression)->significantTokens() as $token) {
            if ($token->kind !== SqlTokenKind::Word || !in_array(strtoupper($token->text), self::MODIFIERS, true)) {
                break;
            }
            $end = $token->endOffset();
        }

        if ($end === 0) {
            return ['modifiers' => '', 'expression' => trim($expression)];
        }

        return [
            'modifiers' => trim(substr($expression, 0, $end)) . ' ',
            'expression' => trim(substr($expression, $end)),
        ];
    }

    private function withoutExplicitAlias(string $expression): string
    {
        $tokens = SqlTokenStream::tokenize($expression)->significantTokens();
        for ($index = count($tokens) - 2; $index >= 0; $index--) {
            $token = $tokens[$index];
            if ($token->isTopLevel() && $token->isKeyword('AS')) {
                return rtrim(substr($expression, 0, $token->offset));
            }
        }

        return trim($expression);
    }
}
