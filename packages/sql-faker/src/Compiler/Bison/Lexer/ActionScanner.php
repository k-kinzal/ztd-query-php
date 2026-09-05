<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Lexer;

use Override;
use SqlFaker\Grammar\Lexical\SourceCursor;

/**
 * Consumes a brace-delimited semantic action.
 *
 * The body is host-language code, so the closing brace cannot be found by
 * counting braces alone: a brace inside a comment or a string literal does not
 * nest. This scanner therefore walks the body with the same comment and quote
 * rules the lexer uses elsewhere, and only braces it meets outside those change
 * the depth. An action left open by a truncated grammar ends at the end of the
 * file rather than swallowing the parse.
 *
 * @visibility root
 */
final class ActionScanner implements BisonScanner
{
    /** @readonly */
    private BisonTrivia $trivia;

    /**
     * @param BisonTrivia|null $trivia Recognises comments met inside the action body
     */
    public function __construct(?BisonTrivia $trivia = null)
    {
        $this->trivia = $trivia ?? new BisonTrivia();
    }

    /**
     * Reports whether an action can start here.
     *
     * @param string $character A single character; never the empty string
     *
     * @return bool True for an opening brace
     */
    #[Override]
    public function handles(string $character): bool
    {
        return $character === '{';
    }

    /**
     * Consumes the action and everything nested inside it.
     *
     * @param SourceCursor $cursor Cursor positioned on the opening brace
     *
     * @return BisonToken The action and its body, without the outer braces
     */
    #[Override]
    public function scan(SourceCursor $cursor): BisonToken
    {
        $offset = $cursor->offset();
        $bodyStart = $offset + 1;
        $depth = 0;
        $closed = false;

        while (!$cursor->atEnd()) {
            $character = $cursor->current();

            if ($this->trivia->skipCommentAt($cursor)) {
                continue;
            }

            if ($character === '"' || $character === '\'') {
                $cursor->takeQuoted($character);
                continue;
            }

            if ($character === '{') {
                ++$depth;
                $cursor->advance();
                continue;
            }

            $cursor->advance();

            if ($character !== '}') {
                continue;
            }

            --$depth;
            if ($depth <= 0) {
                $closed = true;
                break;
            }
        }

        $bodyEnd = $closed ? $cursor->offset() - 1 : $cursor->offset();

        return new BisonToken(BisonLexeme::Action, $cursor->textBetween($bodyStart, $bodyEnd), $offset);
    }
}
