<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the My Sql Select List Aliaser contract for MySQL.
 */
final class MySqlSelectListAliaser
{
    private MySqlIdentifierQuoter $quoter;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct()
    {
        $this->quoter = new MySqlIdentifierQuoter();
    }

    /**
     * Projection Count for the supplied MySQL input.
     */
    public function projectionCount(string $sql): ?int
    {
        $endKeywords = [];
        foreach (Select\ExpressionAliaser::SELECT_LIST_TERMINATORS as $terminator) {
            $endKeywords[] = [$terminator];
        }
        $selectList = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->topLevelClause(['SELECT'], $endKeywords);
        if ($selectList === null) {
            return null;
        }

        $expressions = SqlTokenStream::tokenize($selectList, MySqlLexerProfile::create())->splitTopLevel();
        if ($expressions === [] || (new Select\ExpressionAliaser())->containsWildcard($expressions)) {
            return null;
        }

        return count($expressions);
    }

    /**
     * Alias for the supplied MySQL input.
     */
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
            if ((new Select\ExpressionAliaser())->endsSelectList($token)) {
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
        if ($expressions === [] || (new Select\ExpressionAliaser())->containsWildcard($expressions)) {
            return $sql;
        }

        $prefix = (new Select\ExpressionAliaser())->removeModifiers($expressions[0]);
        $expressions[0] = $prefix['expression'];
        foreach ($expressions as $index => $expression) {
            $expressions[$index] = (new Select\ExpressionAliaser())->withoutExplicitAlias($expression)
                . ' AS ' . $this->quoter->quote('__ztd_insert_' . $index);
        }

        $replacement = ' ' . $prefix['modifiers'] . implode(', ', $expressions) . ' ';

        return substr($sql, 0, $select->endOffset()) . $replacement . substr($sql, $listEnd);
    }

}
