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
     * @throws \SqlSemantics\Diagnostic\InvariantViolation When the node covers no token
     */
    public function of(Node $node): Layout
    {
        $spelled = [];
        foreach ($node->tokens() as $token) {
            if ($token->text !== '') {
                $spelled[] = new Spelled($spelled === [] ? '' : $token->leading, $token->text);
            }
        }
        Check::invariant($spelled !== [], 'A spelled region covers at least one token.');

        return new Layout($spelled);
    }
}
