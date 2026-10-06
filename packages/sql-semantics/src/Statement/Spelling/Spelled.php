<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Spelling;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * How one token of a spelled region was written: the trivia before it and its own spelling.
 *
 * The spelling is a choice among the equivalent spellings of the token the
 * rendering produces at that position, such as the letter case of a keyword
 * or the quoting of a name. It never adds or removes a token.
 *
 * @visibility public
 * @example Reading the spelling of a token
 *     $token = new \SqlSemantics\Statement\Spelling\Spelled(' ', 'and');
 *     [$token->gap, $token->text] // => [' ', 'and']
 */
final class Spelled
{
    use Snapshot;

    /**
     * @param string $gap The whitespace and comments written before the token; empty for the first token of a region
     * @param string $text The spelling of the token
     */
    public function __construct(public readonly string $gap, public readonly string $text)
    {
        Check::input($text !== '', 'A spelled token is not empty.');
    }
}
