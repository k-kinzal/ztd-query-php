<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\Token;
use SqlParser\Lexer\Tokenizer;

/**
 * Writes a syntax tree back out as SQL text.
 *
 * A tree that has been rewritten no longer stands for a span of the text it
 * was parsed from, so its SQL has to be written from the tokens themselves.
 * Comments and whitespace are dropped before the parser sees them, so they
 * are not the tree's to keep; what is the tree's to keep is that the text it
 * is written to reads back as the tokens it holds, and that is what is
 * checked rather than assumed.
 *
 * The text is first written the way the tokens were read, tokens that
 * touched held together and tokens that stood apart separated, and then read
 * back. That is right nearly always and the whole of it costs one reading.
 * Where it is wrong, and it is wrong wherever what stands between two tokens
 * decides what they read back as, the text is written again a token at a
 * time, each held where it reads back as itself.
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
            if ($previous !== null && $this->spacing->separates($previous, $token)) {
                $sql .= ' ';
            }
            $sql .= $token->text;
            $previous = $token;
        }

        return $this->separator->reads($sql, $tokens) ? $sql : $this->separator->rebuild($tokens);
    }
}
