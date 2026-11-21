<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Decimal parts of a SQLite REAL constant.
 *
 * @visibility public
 * @example Representing a negative zero with REAL storage
 *     (new \SqlSemantics\Platform\Sqlite\Ir\RealLiteral('0', '0', null, true))->negative // => true
 */
final class RealLiteral
{
    use Snapshot;

    /**
     * @param string $whole The digits before the point
     * @param string|null $fraction The digits after the point, or null without a point
     * @param string|null $exponent The optionally signed exponent digits
     * @param bool $negative Whether the literal has a minus sign
     */
    public function __construct(
        public readonly string $whole,
        public readonly ?string $fraction,
        public readonly ?string $exponent = null,
        public readonly bool $negative = false,
    ) {
        Check::input(preg_match('/\A[0-9]*\z/', $whole) === 1 && preg_match('/\A[0-9]*\z/', $fraction ?? '') === 1, 'REAL significand parts must be decimal digits.');
        Check::input($whole !== '' || ($fraction !== null && $fraction !== ''), 'REAL literal requires at least one significand digit.');
        Check::input($exponent === null || preg_match('/\A[+-]?[0-9]+\z/', $exponent) === 1, 'REAL exponent must contain optionally signed decimal digits.');
        if ($fraction === null && $exponent === null) {
            $digits = ltrim($whole, '0');
            $boundary = $negative ? '9223372036854775808' : '9223372036854775807';
            Check::input(strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, $boundary) > 0), 'A point-free, exponent-free REAL must exceed its signed INTEGER boundary.');
        }
    }
}
