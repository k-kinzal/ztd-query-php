<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Value\Json\JsonBinary;

/**
 * Reads exact packed decimal groups without a floating-point conversion.
 * Group widths follow the public decimal storage specification; sign and byte
 * order are checked against positive, negative and zero native row images.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/precision-math-decimal-characteristics.html.
 *
 * @visibility MySqlMemory
 */
final class DecimalStorage
{
    /**
     * Reads precision and scale from the table map, then the packed value.
     */
    public function read(Reader $reader, string $metadata): string
    {
        $meta = new Reader($metadata);
        $precision = $meta->integer(1);
        $scale = $meta->integer(1);
        if ($precision < 1 || $precision > 65 || $scale > $precision || $scale > 30) {
            throw StatementError::SyntaxError->error();
        }
        $integer = $precision - $scale;
        $bytes = $reader->bytes(JsonBinary::digits($integer) + JsonBinary::digits($scale));
        $negative = (ord($bytes[0]) & 0x80) === 0;
        $bytes[0] = chr(ord($bytes[0]) ^ 0x80);
        $digits = new Reader($negative ? ~$bytes : $bytes);
        $whole = $this->groups($digits, $integer % 9) . $this->groups($digits, $integer - $integer % 9);
        $fraction = $this->groups($digits, $scale - $scale % 9) . $this->groups($digits, $scale % 9);
        $whole = ltrim($whole, '0');
        $zero = $whole === '' && trim($fraction, '0') === '';

        return ($negative && !$zero ? '-' : '') . ($whole === '' ? '0' : $whole) . ($scale === 0 ? '' : '.' . $fraction);
    }

    /**
     * Reads whole groups of nine digits or a final shorter group.
     */
    public function groups(Reader $reader, int $digits): string
    {
        $text = '';
        while ($digits > 0) {
            $width = min(9, $digits);
            $number = $reader->integer(JsonBinary::digits($width), true);
            if ($number >= 10 ** $width) {
                throw StatementError::SyntaxError->error();
            }
            $text .= str_pad((string) $number, $width, '0', STR_PAD_LEFT);
            $digits -= $width;
        }

        return $text;
    }
}
