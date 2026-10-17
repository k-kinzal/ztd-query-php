<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Decoded bytes that do not form a character in their declared character set.
 *
 * @visibility public
 * @example Retaining the invalid bytes independently of literal spelling
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidCharacterString('utf32', "\0ab"))->charset // => 'utf32'
 */
final class InvalidCharacterString implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $charset The declared character set
     * @param string $bytes The diagnostic prefix beginning at the invalid character
     */
    public function __construct(public readonly string $charset, public readonly string $bytes)
    {
    }

    /**
     * Describes the invalid character bytes.
     */
    public function message(): string
    {
        return 'Invalid ' . $this->charset . ' character string.';
    }
}
