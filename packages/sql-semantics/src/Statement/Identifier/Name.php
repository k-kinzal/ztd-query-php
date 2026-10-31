<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Identifier;

use SqlSemantics\Statement\Snapshot;

/**
 * A decoded identifier and its optional delimiter choice.
 *
 * Quotes and escapes are removed from the value and language-specific case
 * folding is already applied. The delimiter choice retains syntax without
 * retaining an SQL token. A null choice asks the profile codec to select a valid
 * spelling for a directly constructed value.
 *
 * @visibility public
 * @example Holding a decoded name
 *     (new \SqlSemantics\Statement\Identifier\Name('order items'))->value // => 'order items'
 */
final class Name
{
    use Snapshot;

    /**
     * @param string $value The decoded identifier
     * @param NameQuote|null $quote The written delimiter choice, or null for codec selection
     */
    public function __construct(public readonly string $value, public readonly ?NameQuote $quote = null)
    {
        \SqlSemantics\Diagnostic\Check::input(!in_array($quote, [NameQuote::Bare, NameQuote::Keyword], true) || preg_match('/\\A[A-Za-z0-9_$\\x80-\\xff]+\\z/', $value) === 1, 'A bare identifier contains only identifier characters.');
    }
}
