<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transaction\Xa;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An XA format operand, retaining the numeric spelling accepted by MySQL.
 * @visibility public
 * @example Preserving a format identifier
 *     (new \SqlSemantics\Model\Transaction\Xa\FormatIdentifier('42'))->spelling // => '42'
 */
final class FormatIdentifier
{
    /**
     * Validates the decimal domain accepted by MySQL's XA identifier grammar.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $spelling)
    {
        if (preg_match('/^(?:0[xX]([0-9a-fA-F]+)|[xX]\'([0-9a-fA-F]*)\')$/D', $spelling, $hex) === 1) {
            $digits = ltrim(strtolower($hex[2] ?? $hex[1]), '0');
            $valid = strlen($digits) < 16 || strlen($digits) === 16 && strcmp($digits, '7fffffffffffffff') <= 0;
        } else {
            $numeric = preg_match('/^(?:([0-9]+)(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/D', $spelling, $decimal) === 1;
            $digits = ltrim($decimal[1] ?? '', '0');
            $valid = $numeric && (strlen($digits) < 19 || strlen($digits) === 19 && strcmp($digits, '9223372036854775807') <= 0);
        }
        if (!$valid) {
            throw new InvalidStructure('An XA format operand requires a nonnegative numeric literal within the signed 64-bit format identifier domain.');
        }
    }
}
