<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page;

/**
 * Resolves the stock SQLite signed 64-bit decode and unsigned pager clamp.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PageLimitArgument
{
    /**
     * Resolves the decoded text to a positive capped limit or a native read.
     */
    public function read(string $text): ?PageLimit
    {
        if (strncasecmp($text, '0x', 2) === 0) {
            if (preg_match('/\A0[xX]([0-9a-fA-F]*)\z/', $text, $parts) !== 1) {
                return null;
            }
            $digits = strtolower(ltrim($parts[1], '0'));
            $width = strlen($digits);
            if ($width === 0 || $width > 16 || ($width === 16 && strcmp($digits[0], '8') >= 0)) {
                return null;
            }
            $decimal = $width > 8 ? PageLimit::MAXIMUM : base_convert($digits, 16, 10);
        } else {
            $nativeSpan = substr($text, 0, strspn($text, "+- \n\t0123456789") + 1);
            if (preg_match('/\A[\x09-\x0d ]*\+?([0-9]+)[\x09-\x0d ]*\z/', $nativeSpan, $parts) !== 1) {
                return null;
            }
            $decimal = ltrim($parts[1], '0');
            if ($decimal === '' || strlen($decimal) > 19 || (strlen($decimal) === 19 && strcmp($decimal, '9223372036854775807') > 0)) {
                return null;
            }
        }

        $limit = strlen($decimal) > 10 || (strlen($decimal) === 10 && strcmp($decimal, PageLimit::MAXIMUM) > 0)
            ? PageLimit::MAXIMUM : $decimal;

        return new PageLimit($limit);
    }
}
