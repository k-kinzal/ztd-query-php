<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Contract\LeafKeys;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvariantViolation;

/**
 * Checks that the layouts of a rendering only re-spelled the rendered tokens.
 *
 * Rule: CORE-SPELLING-001 (validation part). The SQL as the structure renders
 * it and the SQL as the layouts spell it are read by the lexer of the
 * profile. Both must have the same number of tokens, and each written token
 * must be an equivalent spelling of the rendered token at its position. A
 * layout therefore cannot add, drop or change a token, and its gaps can only
 * hold whitespace and comments. Tokens are read in their full context, so a
 * word the lexer reads differently before a parenthesis is compared as it
 * is read. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Spellings
{
    /**
     * @param SqlParser $parser The parser of the profile
     * @param LeafKeys $keys The token rules of the profile
     */
    public function __construct(private readonly SqlParser $parser, private readonly LeafKeys $keys)
    {
    }

    /**
     * Refuses a spelled SQL text whose tokens are not those of the rendered SQL text.
     *
     * @throws InvariantViolation When a layout changed a token or wrote a token into a gap
     */
    public function check(string $rendered, string $spelled): void
    {
        if ($rendered === $spelled) {
            return;
        }
        $canonical = $this->tokens($rendered);
        $written = $this->tokens($spelled);
        Check::invariant(count($canonical) === count($written), 'The spelled SQL has ' . count($written) . ' tokens where the rendered SQL has ' . count($canonical) . ': ' . $spelled);
        foreach ($canonical as $position => $token) {
            Check::invariant($this->keys->synonymous($token, $written[$position]), 'The spelling ' . $written[$position]->text . ' is not the token ' . $token->text . ' in: ' . $spelled);
        }
    }

    /**
     * Reads the tokens of a text, without the end marker.
     *
     * @return list<Token>
     * @throws InvariantViolation When the text is not lexically valid
     */
    public function tokens(string $text): array
    {
        try {
            $tokens = $this->parser->tokenize($text);
        } catch (SourceException $error) {
            throw new InvariantViolation('A spelled text is not lexically valid: ' . $text, 0, $error);
        }

        return array_values(array_filter($tokens, static fn (Token $token): bool => $token->text !== ''));
    }
}
