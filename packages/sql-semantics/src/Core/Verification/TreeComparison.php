<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Verification;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\SourceComments;
use SqlSemantics\Core\Analysis\Vocabulary;

/**
 * Compares two syntax trees of one release rule by rule and token by token, with the comments before each token.
 *
 * A token the model keeps as a field, such as a name or a literal, must be
 * spelled the same byte for byte. A fixed word of the grammar, which the
 * model spells as the grammar does, may differ in letter case only.
 *
 * @visibility SqlSemantics
 */
final class TreeComparison
{
    /**
     * @param Vocabulary $vocabulary Says which tokens of a rule the model keeps as fields
     * @param SourceComments $sourceComments The comments of the first tree
     * @param SourceComments $copyComments The comments of the second tree
     */
    public function __construct(
        private readonly Vocabulary $vocabulary,
        private readonly SourceComments $sourceComments,
        private readonly SourceComments $copyComments,
    ) {
    }

    /**
     * Describes the first difference between two subtrees, or answers null.
     */
    public function nodes(Node $source, Node $copy): ?string
    {
        if ($source->name !== $copy->name || $source->ordinal !== $copy->ordinal || count($source->children) !== count($copy->children)) {
            $token = $this->sourceComments->first($source);

            return 'The rule ' . $source->name . ':' . $source->ordinal . ' became ' . $copy->name . ':' . $copy->ordinal . ($token === null ? ' where it matched nothing' : ' at offset ' . $token->offset);
        }
        foreach ($source->children as $index => $child) {
            $other = $copy->children[$index];
            if ($child instanceof Node && $other instanceof Node) {
                $difference = $this->nodes($child, $other);
            } elseif ($child instanceof Token && $other instanceof Token) {
                $difference = $this->tokens($child, $other, $this->fixed($source, $index));
            } else {
                $difference = 'A token and a rule trade places in ' . $source->name;
            }
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }

    /**
     * Describes the difference between two tokens, their spelling, or the comments before them, or answers null.
     *
     * @param bool $fixed Whether the token is a fixed word, whose letter case may differ
     */
    public function tokens(Token $source, Token $copy, bool $fixed): ?string
    {
        if ($source->name !== $copy->name) {
            return 'The token ' . $source->name . ' became ' . $copy->name . ' at offset ' . $source->offset;
        }
        if ($source->text !== $copy->text && !($fixed && strcasecmp($source->text, $copy->text) === 0)) {
            return 'The spelling ' . var_export($source->text, true) . ' became ' . var_export($copy->text, true) . ' at offset ' . $source->offset;
        }
        if ($this->sourceComments->before($source) !== $this->copyComments->before($copy)) {
            return 'The comments before ' . var_export($source->text, true) . ' at offset ' . $source->offset . ' differ: ' . json_encode($this->sourceComments->before($source)) . ' became ' . json_encode($this->copyComments->before($copy));
        }

        return null;
    }

    /**
     * Answers whether the token at a position of a rule is a fixed word the model spells as the grammar does, rather than a field.
     */
    public function fixed(Node $node, int $index): bool
    {
        $recipe = $this->vocabulary->recipe($node->name, $node->ordinal);

        return $recipe !== null && (isset($recipe['constant']) || (isset($recipe['fields']) && !in_array($index, $recipe['fields'], true)));
    }
}
