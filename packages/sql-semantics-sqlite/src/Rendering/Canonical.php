<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rendering;

use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

/**
 * Reads the spelling a node has when it is rendered without a layout of its own.
 *
 * Rule: CORE-SPELLING-001 (SQLite part). A layout is kept only where it
 * differs from this spelling, so that a node rendered without a layout reads
 * back without one, and a layout always records a spelling the rendering
 * would not produce by itself. Layouts nested in the node keep their effect,
 * as they do when the node is rendered. The trivia after a region belongs to
 * its spelling only when it holds a comment: SQLite drops the whitespace at
 * the end of a span (`sqlite3DbSpanDup()` in `malloc.c`), so whitespace
 * alone spells nothing. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Canonical
{
    /**
     * The characters SQLite treats as whitespace (`sqlite3Isspace()`).
     */
    public const SPACE = " \t\n\v\f\r";

    /**
     * Answers the layout of the text a node renders to: its pieces with the gaps the lexical writer puts between them.
     */
    public function layout(Node $node): Layout
    {
        $out = new Output(new Codec());
        $node->render($out);
        $spelled = [];
        $previous = null;
        foreach ($out->pieces() as $piece) {
            $spelled[] = new Spelled($previous === null ? '' : ($piece->gap ?? ((new Lexical())->tight($previous, $piece) ? '' : ' ')), $piece->text);
            $previous = $piece;
        }

        return new Layout($spelled);
    }

    /**
     * Answers the part of the trivia after a region that its spelling keeps: all of it when it holds a comment, nothing when it is whitespace alone.
     */
    public function trail(string $trivia): string
    {
        return rtrim($trivia, self::SPACE) === '' ? '' : $trivia;
    }

    /**
     * Answers the text a region of a layout names: its tokens and the trivia after them, without the whitespace at the end.
     */
    public function span(Layout $layout): string
    {
        return rtrim($layout->text() . $layout->trail, self::SPACE);
    }

    /**
     * Tells whether two layouts spell the same tokens with the same gaps and the same trivia after them.
     */
    public function same(Layout $left, Layout $right): bool
    {
        if (count($left->tokens) !== count($right->tokens) || $left->trail !== $right->trail) {
            return false;
        }
        foreach ($left->tokens as $position => $token) {
            if ($token->text !== $right->tokens[$position]->text || ($position > 0 && $token->gap !== $right->tokens[$position]->gap)) {
                return false;
            }
        }

        return true;
    }
}
