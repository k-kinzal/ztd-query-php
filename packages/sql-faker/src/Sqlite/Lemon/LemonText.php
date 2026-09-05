<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lemon;

/**
 * Removes from a Lemon file everything that is not grammar.
 *
 * A Lemon file carries the C the generated parser is built from as well as the
 * rules it recognises: comments, include blocks, destructors, and declarations
 * about types and names. None of that says what the parser accepts, and all of
 * it can contain text that reads like a rule, so it is taken out before the
 * rules are looked for.
 *
 * @visibility root
 */
final class LemonText
{
    /**
     * Removes C comments.
     *
     * @param string $input Contents of the grammar file
     *
     * @return string The same text with its comments gone
     */
    public function withoutComments(string $input): string
    {
        return preg_replace(['/\/\*.*?\*\//s', '/\/\/.*$/m'], '', $input) ?? $input;
    }

    /**
     * Removes the directives that carry C or configure the generated parser.
     *
     * @param string $input Contents of the grammar file
     *
     * @return string The same text with those directives gone
     */
    public function withoutDirectiveBlocks(string $input): string
    {
        return preg_replace([
            '/%(?:include|destructor|syntax_error|parse_accept|parse_failure|stack_overflow|code)\s*\{[^}]*\}/s',
            '/^%(?:token_type|default_type|extra_context|name|token_prefix|stack_size|ifdef|ifndef|endif)\b.*$/m',
        ], '', $input) ?? $input;
    }
}
