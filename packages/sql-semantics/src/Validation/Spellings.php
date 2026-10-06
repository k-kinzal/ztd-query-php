<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Contract\LeafKeys;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvariantViolation;
use SqlSemantics\Lowering\Productions;

/**
 * Checks that the layouts of a rendering only re-spelled the rendered tokens.
 *
 * Rule: CORE-SPELLING-001 (validation part). The SQL as the structure renders
 * it and the SQL as the layouts spell it are read by the parser of the
 * profile. Both must have the same number of tokens, and the comparison keys
 * of the token correspondence check, which depend on the production and
 * position of each token, must be equal position by position. A layout
 * therefore cannot add, drop or change a significant token, its gaps can only
 * hold whitespace and comments, and a spelling counts as the same token only
 * where the database reads it as the same request. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Spellings
{
    /**
     * @param SqlParser $parser The parser of the profile
     * @param Productions $productions The productions of the grammar release
     * @param LeafKeys $keys The token rules of the profile
     */
    public function __construct(private readonly SqlParser $parser, private readonly Productions $productions, private readonly LeafKeys $keys)
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
        $correspondence = new TokenCorrespondence();
        $difference = $correspondence->difference($correspondence->keys($this->tree($rendered), $this->productions, $this->keys), $correspondence->keys($this->tree($spelled), $this->productions, $this->keys));
        Check::invariant($difference === null, 'The spelled SQL is not the rendered SQL at ' . $difference . ': ' . $spelled);
    }

    /**
     * Parses a rendered or spelled text.
     *
     * @throws InvariantViolation When the text is outside the grammar
     */
    public function tree(string $text): Node
    {
        try {
            return $this->parser->parse($text);
        } catch (SourceException $error) {
            throw new InvariantViolation('A rendered or spelled text is outside the grammar: ' . $text, 0, $error);
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
