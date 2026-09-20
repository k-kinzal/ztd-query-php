<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\Token;
use SqlParser\Lexer\Tokenizer;

/**
 * Writes a syntax tree back out as SQL text.
 *
 * A tree still standing in the text it was parsed from writes itself back
 * with `Node::toString()`, which is what that is for. A tree a rewrite has
 * changed stands nowhere: tokens it built carry no trivia, so writing them
 * out as they stand would run them together, and what two tokens run
 * together read back as is not what they are.
 *
 * So the text is written and then read back rather than trusted. Every token
 * is first written after the trivia it was read with, which settles a tree
 * whose tokens were all read, and the whole of that costs one reading. Where
 * it does not settle, and it does not wherever a token was built or what
 * stands between two of them decides what they read back as, the text is
 * written again a token at a time, each held where it reads back as itself.
 *
 * @visibility root
 */
final class Printer
{
    /**
     * @param Separator $separator Puts between two tokens what makes them read back
     * @param Spacing $spacing Writes the text the way the tokens were read
     */
    public function __construct(
        private readonly Separator $separator,
        private readonly Spacing $spacing = new Spacing(),
    ) {
    }

    /**
     * Answers a printer that writes for one dialect.
     *
     * @param Tokenizer $tokenizer Reads the dialect's text back
     *
     * @return self The printer
     */
    public static function of(Tokenizer $tokenizer): self
    {
        $spacing = new Spacing();

        return new self(new Separator($tokenizer, $spacing), $spacing);
    }

    /**
     * Writes the SQL text of a tree.
     *
     * Tokens a lexer synthesises carry no text and take up no room in the
     * output, so the end marker of a tree is written as nothing at all.
     *
     * @param Node|Token $tree The tree, or one token of it
     *
     * @return string The SQL text
     *
     * @throws RenderException When two tokens of the tree cannot be written next to each other
     */
    public function render(Node|Token $tree): string
    {
        $tokens = [];
        foreach ($tree instanceof Token ? [$tree] : $tree->tokens() as $token) {
            if ($token->text !== '') {
                $tokens[] = $token;
            }
        }
        if ($tokens === []) {
            return '';
        }
        $sql = '';
        $previous = null;
        foreach ($tokens as $token) {
            if ($previous !== null) {
                $sql .= $token->isDetached()
                    ? ($this->spacing->separates($previous, $token) ? ' ' : '')
                    : $token->leading;
            }
            $sql .= $token->text;
            $previous = $token;
        }

        return $this->separator->reads($sql, $tokens) ? $sql : $this->separator->rebuild($tokens);
    }
}
