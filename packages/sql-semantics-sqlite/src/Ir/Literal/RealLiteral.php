<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Literal;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Decimal parts of a SQLite REAL constant.
 *
 * @visibility public
 * @example Representing a negative zero with REAL storage
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral('0', '0', null, true))->negative // => true
 */
final class RealLiteral
{
    use Snapshot;

    /**
     * @param string $whole The digits before the point
     * @param string|null $fraction The digits after the point, or null without a point
     * @param string|null $exponent The optionally signed exponent digits
     * @param bool $negative Whether the literal has a minus sign
     * @param bool $uppercaseExponent Whether a TEXT column's old-row fallback sees an uppercase exponent marker
     */
    public function __construct(
        public readonly string $whole,
        public readonly ?string $fraction,
        public readonly ?string $exponent = null,
        public readonly bool $negative = false,
        public readonly bool $uppercaseExponent = false,
    ) {
        Check::input(preg_match('/\A[0-9]*\z/', $whole) === 1 && preg_match('/\A[0-9]*\z/', $fraction ?? '') === 1, 'REAL significand parts must be decimal digits.');
        Check::input($whole !== '' || ($fraction !== null && $fraction !== ''), 'REAL literal requires at least one significand digit.');
        Check::input($exponent === null || preg_match('/\A[+-]?[0-9]+\z/', $exponent) === 1, 'REAL exponent must contain optionally signed decimal digits.');
        Check::input(!$uppercaseExponent || $exponent !== null, 'An uppercase REAL exponent marker needs exponent digits.');
        if ($fraction === null && $exponent === null) {
            $digits = ltrim($whole, '0');
            $boundary = $negative ? '9223372036854775808' : '9223372036854775807';
            Check::input(strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, $boundary) > 0), 'A point-free, exponent-free REAL must exceed its signed INTEGER boundary.');
        }
    }

    /**
     * Counts the variable number bytes used by the statement budget.
     */
    public function payloadBytes(): int
    {
        return strlen($this->whole) + strlen($this->fraction ?? '') + strlen($this->exponent ?? '');
    }

    /**
     * Emits the signed significand and optional exponent.
     */
    public function toString(): string
    {
        return ($this->negative ? '-' : '') . $this->whole
            . ($this->fraction === null ? '' : '.' . $this->fraction)
            . ($this->exponent === null ? '' : ($this->uppercaseExponent ? 'E' : 'e') . $this->exponent);
    }
}
