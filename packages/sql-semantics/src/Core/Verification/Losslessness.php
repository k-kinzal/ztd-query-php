<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Verification;

use SqlSemantics\Core\Language;

/**
 * Finds what a statement lost: the difference between the syntax of SQL and the syntax of the SQL its statement writes.
 *
 * Two texts have the same syntax when the language parses them into the
 * same rules and alternatives, with the same tokens spelled the same way, and
 * the same comments before the same tokens. Only two things may differ,
 * because neither changes what the server reads: whitespace, and the letter
 * case of a fixed word of the grammar, such as a keyword, which the model
 * spells as the grammar does. A token a value keeps as a field, such as a
 * name, a literal, or a keyword written as a name, must come back byte for
 * byte, as must operators, parentheses, optional words, and comments.
 *
 * @visibility public
 * @example Finding what the statement of analyzed SQL lost, or null when it lost nothing
 *     $lost = static fn (\SqlSemantics\Facade\Semantics $semantics, string $sql): ?string => (new \SqlSemantics\Core\Verification\Losslessness($semantics->language()))->difference($sql, $semantics->analyze($sql)->toString());
 *     $lost instanceof \Closure // => true
 */
final class Losslessness
{
    /**
     * Compares texts of one language.
     */
    public function __construct(private readonly Language $language)
    {
    }

    /**
     * Describes the first difference between the syntax of the SQL and the syntax of what was written for it, or answers null.
     *
     * @throws \SqlParser\Lexer\SourceException When either text is not SQL of the language
     */
    public function difference(string $sql, string $written): ?string
    {
        $parser = $this->language->parser();
        $values = $this->language->values();
        $source = $parser->parse($sql);
        $copy = $parser->parse($written);
        $sourceComments = $values->comments($source);
        $copyComments = $values->comments($copy);
        if ($sourceComments->leading !== $copyComments->leading || $sourceComments->trailing !== $copyComments->trailing) {
            return 'The comments around the statement differ: ' . json_encode([$sourceComments->leading, $sourceComments->trailing]) . ' became ' . json_encode([$copyComments->leading, $copyComments->trailing]);
        }

        return (new TreeComparison($this->language->vocabulary(), $sourceComments, $copyComments))->nodes($source, $copy);
    }
}
