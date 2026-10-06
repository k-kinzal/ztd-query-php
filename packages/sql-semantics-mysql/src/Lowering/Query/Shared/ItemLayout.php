<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\Layouts;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

/**
 * Reads the layout of the expression of an unaliased select item as the server reads its text.
 *
 * Rule: MYSQL-ITEM-LAYOUT-001. The server names such an item after the text
 * of its expression in the preprocessed query buffer (8.0 and later:
 * `PTI_expr_with_alias::itemize` copies `@1.cpp`; 5.6 and 5.7: `select_item`
 * copies the text between `remember_name` and `remember_end`, both in the
 * preprocessed buffer). The preprocessed buffer is the statement with its
 * whitespace and ordinary comments, where the marker that opens an
 * executed version comment (`/*!`, optionally with its version digits) is
 * left out, the `*` `/` that closes it becomes one space when neither the
 * character before nor the one after is a space, and a version comment for
 * a later release is left out entirely. The layout keeps the tokens as
 * written and the trivia between them as the preprocessed buffer holds it,
 * so the rendered text holds no version comment marker. The lexer reads
 * `WITH ROLLUP` and `WITH CUBE` as one lexeme, which the rendering writes as
 * two keywords; the layout keeps the two words with the trivia between them. Version digits are
 * five digits; from 8.1 on also six digits followed by a space. Source:
 * sql/sql_lex.cc (`MY_LEX_LONG_COMMENT`, `MY_LEX_END_LONG_COMMENT`) and
 * sql/parse_tree_items.cc of each release,
 * https://dev.mysql.com/doc/refman/8.4/en/comments.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class ItemLayout
{
    /**
     * @param GrammarRelease $release The release whose version decides which version comments are executed
     */
    public function __construct(private readonly GrammarRelease $release)
    {
    }

    /**
     * Answers the layout of an expression node with its trivia as the preprocessed buffer holds it.
     *
     * @throws \SqlSemantics\Diagnostic\InvariantViolation When the node covers no token
     */
    public function of(Node $expression): Layout
    {
        $layout = (new Layouts())->of($expression);
        $tokens = [];
        $before = '';
        foreach ($layout->tokens as $position => $token) {
            foreach ($this->words($position === 0 ? '' : $token->gap, $token->text) as [$gap, $text]) {
                $tokens[] = $tokens === [] ? new Spelled('', $text) : new Spelled($this->gap($gap, $before, $text), $text);
                $before = $text;
            }
        }

        return new Layout($tokens);
    }

    /**
     * Splits the one lexeme the lexer makes of `WITH ROLLUP` and `WITH CUBE` into its two words, which the rendering writes as two keywords.
     *
     * @return non-empty-list<array{string, string}> Each word with the trivia before it
     */
    public function words(string $gap, string $text): array
    {
        if (preg_match('/\A(WITH)(.+?)(ROLLUP|CUBE)\z/is', $text, $match) === 1) {
            return [[$gap, $match[1]], [$match[2], $match[3]]];
        }

        return [[$gap, $text]];
    }

    /**
     * Answers the trivia between two tokens as the preprocessed buffer holds it.
     */
    public function gap(string $gap, string $before, string $after): string
    {
        $result = '';
        $offset = 0;
        $length = strlen($gap);
        while ($offset < $length) {
            if (substr_compare($gap, '/*!', $offset, 3) === 0) {
                $offset = $this->opening($gap, $offset);
            } elseif (substr_compare($gap, '*/', $offset, 2) === 0) {
                $offset += 2;
                $next = $offset < $length ? $gap[$offset] : $after[0];
                $previous = $result !== '' ? $result[-1] : $before[-1];
                $result .= ctype_space($next) || ctype_space($previous) ? '' : ' ';
            } else {
                $end = $this->trivia($gap, $offset);
                $result .= substr($gap, $offset, $end - $offset);
                $offset = $end;
            }
        }

        return $result;
    }

    /**
     * Answers where the trivia after a version comment marker continues: after the marker when the comment is executed, after the whole comment otherwise.
     */
    public function opening(string $gap, int $offset): int
    {
        $digits = $this->digits($gap, $offset + 3);
        if ($digits === '' || (int) $digits <= $this->version()) {
            return $offset + 3 + strlen($digits);
        }
        $depth = 1;
        $position = $offset + 3;
        while ($depth > 0 && $position < strlen($gap)) {
            if (substr_compare($gap, '*/', $position, 2) === 0) {
                $depth--;
                $position += 2;
            } elseif ($depth === 1 && substr_compare($gap, '/*', $position, 2) === 0) {
                $depth++;
                $position += 2;
            } else {
                $position++;
            }
        }

        return $position;
    }

    /**
     * Answers the version digits written after a version comment marker, or an empty string when there are none.
     */
    public function digits(string $gap, int $offset): string
    {
        $six = $this->release !== GrammarRelease::MySql5651 && $this->release !== GrammarRelease::MySql5744 && $this->release !== GrammarRelease::MySql8044;
        if ($six && preg_match('/\G[0-9]{6}\s/', $gap, $match, 0, $offset) === 1) {
            return substr($match[0], 0, 6);
        }

        return preg_match('/\G[0-9]{5}/', $gap, $match, 0, $offset) === 1 ? $match[0] : '';
    }

    /**
     * Answers where the trivia element at an offset ends: one whitespace character, an ordinary comment, or a line comment without its line end.
     */
    public function trivia(string $gap, int $offset): int
    {
        if (substr_compare($gap, '/*', $offset, 2) === 0) {
            $end = strpos($gap, '*/', $offset + 2);

            return $end === false ? strlen($gap) : $end + 2;
        }
        if ($gap[$offset] === '#' || substr_compare($gap, '--', $offset, 2) === 0) {
            $end = strpos($gap, "\n", $offset);

            return $end === false ? strlen($gap) : $end;
        }

        return $offset + 1;
    }

    /**
     * Answers the server version of the release as `MYSQL_VERSION_ID` writes it.
     */
    public function version(): int
    {
        [$major, $minor, $patch] = array_map('intval', explode('.', substr($this->release->value, strlen('mysql-'))));

        return $major * 10000 + $minor * 100 + $patch;
    }
}
