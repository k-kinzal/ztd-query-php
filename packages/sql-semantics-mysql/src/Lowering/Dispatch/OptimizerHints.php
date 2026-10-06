<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dispatch;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Finds optimizer hint comments, which the parser delivers as trivia and no rule can read yet.
 *
 * Rule: MYSQL-OPTIMIZER-HINTS-001. From MySQL 5.7.7 on, the server reads a
 * comment that starts with `/*+` and follows, after whitespace only, one of
 * the keywords SELECT, INSERT, REPLACE, UPDATE or DELETE as a list of
 * optimizer hints of the statement; a hint changes the request, for example
 * `SET_VAR` or `MAX_EXECUTION_TIME`. The parser of this library has no hint
 * grammar: the comment is part of the whitespace before the next token. A
 * statement with such a comment therefore has a part no rule structures,
 * and the lowering reports it as a missing rule instead of dropping it. In
 * MySQL 5.6 the comment is an ordinary comment. Terminates: one iterative
 * pass over the tokens.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OptimizerHints
{
    /**
     * The terminals after which the server reads a hint comment, in every release.
     */
    private const KEYWORDS = ['SELECT_SYM' => true, 'INSERT' => true, 'INSERT_SYM' => true, 'REPLACE' => true, 'REPLACE_SYM' => true, 'UPDATE_SYM' => true, 'DELETE_SYM' => true];

    /**
     * Answers the first hint comment of a parse tree, or null when it has none.
     */
    public function first(Node $tree): ?string
    {
        $pending = [$tree];
        $previous = null;
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current instanceof Node) {
                for ($index = count($current->children) - 1; $index >= 0; $index--) {
                    $pending[] = $current->children[$index];
                }
                continue;
            }
            if ($previous instanceof Token && isset(self::KEYWORDS[$previous->name]) && str_starts_with(ltrim($current->leading), '/*+')) {
                return ltrim($current->leading);
            }
            $previous = $current;
        }

        return null;
    }
}
