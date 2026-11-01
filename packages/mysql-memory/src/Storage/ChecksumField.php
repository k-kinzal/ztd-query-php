<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Evaluation\ExactDecimal;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\TemporalWeight;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Json\Json;
use MySqlMemory\Value\Zone;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Encodes the field bytes consumed by CHECKSUM TABLE's row scan.
 *
 * Integers use little-endian storage, temporal fractions use sortable packed
 * fields, and exact decimals use signed groups of nine digits. Variable strings,
 * BIT, geometry and JSON contribute their value bytes without a length prefix.
 * Source: https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_table.cc,
 * https://dev.mysql.com/doc/refman/8.4/en/storage-requirements.html.
 *
 * @visibility MySqlMemory
 */
final class ChecksumField
{
    /**
     * Encodes an already stored field, including its reset value when SQL NULL.
     */
    public function value(Domain $domain, int|float|string|ExactDecimal|null $value): string
    {
        $raw = ExactDecimal::raw($value);
        return match ($domain->field) {
            Field::Tiny => pack('C', (int) $raw),
            Field::Short => pack('v', (int) $raw),
            Field::Int24 => substr(pack('V', (int) $raw), 0, 3),
            Field::Long => pack('V', (int) $raw),
            Field::LongLong => pack('P', (int) $raw),
            Field::Float => pack('g', (float) $raw),
            Field::Double => pack('e', (float) $raw),
            Field::Decimal, Field::NewDecimal => $this->decimal($domain, (string) ($raw ?? '0')),
            Field::Date, Field::NewDate, Field::Time, Field::DateTime, Field::Timestamp => $this->temporal($domain, $raw === null ? null : (string) $raw),
            Field::Year => chr($raw === null || (int) $raw === 0 ? 0 : (int) $raw - 1900),
            Field::Enum, Field::Set => $this->members($domain, (string) $raw),
            Field::String => $this->fixed($domain, (string) $raw),
            Field::Bit => str_pad((string) $raw, intdiv($domain->length + 7, 8), "\0", STR_PAD_LEFT),
            Field::Json => Json::visible((string) $raw),
            Field::Null, Field::VarChar, Field::VarString, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::Geometry, Field::Vector => (string) $raw,
        };
    }

    /**
     * Pads fixed-width strings with the column charset's space, or binary zero.
     */
    public function fixed(Domain $domain, string $value): string
    {
        $charset = $domain->collation->charset;
        $padding = $charset === Charset::binary() ? "\0" : Encoding::convert(' ', Charset::known('ascii'), $charset);
        return str_pad($value, $domain->length * $charset->maxLength, $padding);
    }

    /**
     * Encodes the stored ordinal or membership bitmap, independent of collation.
     */
    public function members(Domain $domain, string $value): string
    {
        if ($domain->field === Field::Enum) {
            $index = array_search($value, $domain->members, true);
            $number = $index === false ? 0 : $index + 1;
            return count($domain->members) < 256 ? pack('C', $number) : pack('v', $number);
        }
        $number = 0;
        foreach ($value === '' ? [] : explode(',', $value) as $member) {
            $index = array_search($member, $domain->members, true);
            if ($index !== false) {
                $number |= 1 << $index;
            }
        }
        $width = intdiv(count($domain->members) + 7, 8);
        return substr(pack('P', $number), 0, $width > 4 ? 8 : $width);
    }

    /**
     * Encodes decimal groups without converting through floating point.
     */
    public function decimal(Domain $domain, string $value): string
    {
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '+-'), 2), 2, '');
        $digits = $domain->precision() - $domain->decimals;
        $whole = str_pad(ltrim($whole, '0'), $digits, '0', STR_PAD_LEFT);
        $fraction = str_pad($fraction, $domain->decimals, '0');
        $bytes = '';
        $first = $digits % 9;
        if ($first > 0) {
            $bytes .= $this->group(substr($whole, 0, $first));
        }
        for ($at = $first; $at < $digits; $at += 9) {
            $bytes .= $this->group(substr($whole, $at, 9));
        }
        for ($at = 0; $at < $domain->decimals; $at += 9) {
            $bytes .= $this->group(substr($fraction, $at, 9));
        }
        $bytes = $negative ? ~$bytes : $bytes;
        $bytes[0] = chr(ord($bytes[0]) ^ 0x80);
        return $bytes;
    }

    /**
     * Packs one decimal group into its required whole number of bytes.
     */
    public function group(string $digits): string
    {
        $width = \MySqlMemory\Value\Json\JsonBinary::digits(strlen($digits));
        return substr(pack('N', (int) $digits), -$width);
    }

    /**
     * Encodes UTC timestamps and packed calendar fields; DATE uses little-endian order.
     */
    public function temporal(Domain $domain, ?string $value): string
    {
        $value ??= $domain->field === Field::Time ? '00:00:00' : '0000-00-00 00:00:00';
        $bytes = (new TemporalWeight())->stored($value, $domain, Zone::utc());
        return $domain->field === Field::Date || $domain->field === Field::NewDate ? strrev($bytes) : $bytes;
    }
}
