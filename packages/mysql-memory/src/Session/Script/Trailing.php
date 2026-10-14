<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Script;

use SqlParser\Lexer\Token;
use SqlParser\Parser\SqlParser;

/**
 * Separates a trailing comment-only query from the preceding terminated statement.
 *
 * SQL Semantics' lossless splitter attaches trailing trivia to the last statement. The
 * MySQL client protocol instead answers a comment after a terminator as another empty
 * query, while trailing whitespace alone adds no reply. Verified on 5.6.51 and 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class Trailing
{
    /**
     * Preserves the parser's boundaries and separates only non-whitespace trailing trivia.
     *
     * @param list<string> $statements The texts returned by the semantic splitter
     * @return list<string> The client-visible statement texts
     */
    public function statements(array $statements, SqlParser $parser): array
    {
        if ($statements === []) {
            return [];
        }
        $last = count($statements) - 1;
        $text = $statements[$last];
        $tokens = array_values(array_filter($parser->tokenize($text), static fn (Token $token): bool => $token->text !== ''));
        $end = $tokens === [] ? null : $tokens[count($tokens) - 1];
        if ($end !== null && $end->text === ';' && trim(substr($text, $end->end())) !== '') {
            $statements[$last] = substr($text, 0, $end->end());
            $statements[] = substr($text, $end->end());
        }

        return $statements;
    }
}
