<?php

declare(strict_types=1);

namespace Requirements\Source;

use Requirements\Input\InvalidInputException;

/** Exact-text directives identifying complete units within a declared CSS scope. */
final class TextFragment
{
    public static function isFragment(string $selector): bool
    {
        return str_starts_with($selector, '#') && str_contains($selector, ':~:');
    }

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

    public static function create(string $text): string
    {
        return '#:~:text=' . str_replace('-', '%2D', rawurlencode(Unit::normalize($text)));
    }
}
