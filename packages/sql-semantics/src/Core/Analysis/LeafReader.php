<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Element;

/**
 * Reads one lexical spelling into the leaf value a role admits for it.
 *
 * The spelling is tokenized by the language's own lexer, under its mode, so
 * the terminal it becomes is the one the server would read: a bare word is a
 * keyword or an identifier as the release's keyword table says, a number is
 * the width the lexer gives it, and a quoted string is one whatever it holds.
 *
 * @visibility SqlSemantics
 */
final class LeafReader
{
    /**
     * Reads leaves of the language.
     */
    public function __construct(private readonly Language $language)
    {
    }

    /**
     * Answers the terminal name of a spelling that is exactly one token after a prefix, or null.
     *
     * The prefix is the text the lexer has just read, such as the `.` after
     * which some lexers read any word as a name; its own tokens are not counted.
     */
    public function terminal(string $text, string $prefix = ''): ?string
    {
        try {
            $before = count($this->tokens($prefix));
            $names = $this->tokens($prefix . $text);
        } catch (SourceException) {
            return null;
        }
        if (count($names) !== $before + 1 || trim($text) !== $text) {
            return null;
        }

        return $names[$before];
    }

    /**
     * Answers the leaf value the role admits for a spelling, or null when it admits none.
     *
     * @param string $role The grammar rule of the position the value is for
     * @param string $text The complete spelling of one token
     * @param string $prefix The text the lexer has just read before the spelling
     */
    public function read(string $role, string $text, string $prefix = ''): ?Element
    {
        $terminal = $this->terminal($text, $prefix);
        if ($terminal === null) {
            return null;
        }
        $recipe = $this->language->vocabulary()->leaf($role, $terminal);
        if ($recipe === null) {
            return null;
        }

        return $this->language->vocabulary()->build($recipe, isset($recipe['fields']) && $recipe['fields'] !== [] ? [$text] : []);
    }

    /**
     * Answers the terminal names of the tokens of a text, the end marker left out.
     *
     * @return list<string>
     * @throws SourceException When the text cannot be tokenized
     */
    public function tokens(string $text): array
    {
        return array_values(array_map(static fn (Token $token): string => $token->name, array_filter($this->language->parser()->tokenize($text), static fn (Token $token): bool => $token->text !== '')));
    }
}
