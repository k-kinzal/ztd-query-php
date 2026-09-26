<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Input\InvalidInputException;

/**
 * Exact-text directives identifying complete units within a declared CSS scope.
 *
 * Only the single exact form "#:~:text=percent-encoded-text" is supported.
 */
final class TextFragment
{
    /**
     * Tells whether a selector is a Text Fragment.
     *
     * @param string $selector The selector
     *
     * @return bool True for a fragment containing the ":~:" directive delimiter
     */
    public static function isFragment(string $selector): bool
    {
        return str_starts_with($selector, '#') && str_contains($selector, ':~:');
    }

    /**
     * Returns the normalized text a fragment selects.
     *
     * @param string $fragment The fragment
     *
     * @return string The decoded, normalized text
     *
     * @throws InvalidInputException When the fragment is a range, has context terms or several directives, or decodes to no text
     */
    public static function text(string $fragment): string
    {
        if (preg_match('/\A#[^#]*?:~:text=([^,&\-]+)\z/u', $fragment, $match) !== 1 || preg_match('/%(?![0-9a-f]{2})/i', $fragment) === 1) {
            throw new InvalidInputException('Use one exact Text Fragment (#:~:text=percent-encoded-text); ranges, context terms and multiple directives are unsupported.');
        }
        $text = rawurldecode($match[1]);
        if (!mb_check_encoding($text, 'UTF-8') || Unit::normalize($text) === '') {
            throw new InvalidInputException('Text Fragment text must be nonempty UTF-8.');
        }
        return Unit::normalize($text);
    }

    /**
     * Creates the fragment selecting a text.
     *
     * @param string $text The text
     *
     * @return string The fragment
     */
    public static function create(string $text): string
    {
        return '#:~:text=' . str_replace('-', '%2D', rawurlencode(Unit::normalize($text)));
    }
}
