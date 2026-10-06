<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

/**
 * Reads the written spelling of a region of a parse tree.
 *
 * @visibility SqlSemantics
 */
final class Layouts
{
    /**
     * Answers the layout of the tokens a node covers: their spellings and the trivia between them.
     *
     * @param Node $node The region
     * @param string $trail The trivia written after the region: the leading trivia of the next token, or the trailing trivia at the end of the input
     *
     * @throws \SqlSemantics\Diagnostic\InvariantViolation When the node covers no token
     */
    public function of(Node $node, string $trail = ''): Layout
    {
        $spelled = [];
        foreach ($node->tokens() as $token) {
            if ($token->text !== '') {
                $spelled[] = new Spelled($spelled === [] ? '' : $token->leading, $token->text);
            }
        }
        Check::invariant($spelled !== [], 'A spelled region covers at least one token.');

        return new Layout($spelled, $trail);
    }
}
