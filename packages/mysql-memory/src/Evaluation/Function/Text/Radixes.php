<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Text;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The functions that write a number in another base: CONV(N, from, to), BIN(N) as CONV(N, 10, 2) and OCT(N) as CONV(N, 10, 8).
 *
 * The bases are 2 to 36 either way, else the result is NULL. A BIT value or a hexadecimal or bit
 * literal is read as its integer; anything else is read from its text, as strtoull reads it in
 * the base: spaces skipped, a sign, then the digits up to the first that is none, so that 1.9
 * reads as 1 and 1e20 as 1. A negative from-base reads a signed number. A text without digits
 * reads as 0 and an empty one is NULL. A number beyond 64 bits warns that the DECIMAL value is
 * truncated: a signed one becomes the largest or smallest BIGINT, an unsigned one 2^64-1, or 0
 * when it is negative and starts with a decimal digit. A negative to-base writes the 64 bits as
 * a signed number (verified on a live 8.4 server). MySQL 5.6 and 5.7 warn of neither, and make
 * every unsigned number beyond 64 bits 2^64-1 (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/mathematical-functions.html#function_conv,
 * https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_bin.
 *
 * @visibility MySqlMemory
 */
final class Radixes
{
    /**
     * The digits of base 36, in order.
     */
    public const DIGITS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * The number of values of 64 bits.
     */
    public const SPAN = '18446744073709551616';

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('CONV', 3, 3, fn (Frame $f, array $a, Domain $r): ?string => $this->convert($f, $a[0], Convert::toInteger($a[1]->evaluate($f), $a[1]->domain(), $f->context), Convert::toInteger($a[2]->evaluate($f), $a[2]->domain(), $f->context), $r)),
            new Routine('BIN', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->convert($f, $a[0], 10, 2, $r)),
            new Routine('OCT', 1, 1, fn (Frame $f, array $a, Domain $r): ?string => $this->convert($f, $a[0], 10, 8, $r)),
        ];
    }

    /**
     * Writes the number of an argument, read in a base, in another base; NULL for a NULL argument or a base out of range.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function convert(Frame $frame, Evaluable $argument, ?int $from, ?int $to, Domain $result): ?string
    {
        $value = $argument->evaluate($frame);
        if ($value === null || $from === null || $to === null || abs($from) < 2 || abs($from) > 36 || abs($to) < 2 || abs($to) > 36) {
            return null;
        }
        $domain = $argument->domain();
        if ($domain->kind === Kind::Bit || $domain->numericBytes) {
            $number = $this->unsigned((int) Convert::toInteger($value, $domain, $frame->context, true));
        } else {
            $text = (string) Convert::toText($value, $domain);
            if ($text === '') {
                return null;
            }
            $number = $this->read($frame, $text, abs($from), $from < 0, $this->charsetOf($domain));
        }
        $negative = $to < 0 && bccomp($number, '9223372036854775808') >= 0;
        $digits = $this->write($negative ? bcsub(self::SPAN, $number) : $number, abs($to));

        return Encoding::convert(($negative ? '-' : '') . $digits, Charset::known('ascii'), $result->collation->charset);
    }

    /**
     * Answers the character set of the text of a value.
     */
    public function charsetOf(Domain $domain): Charset
    {
        return $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4');
    }

    /**
     * Answers a 64-bit integer as the unsigned decimal of its bits.
     *
     * @return numeric-string
     */
    public function unsigned(int $value): string
    {
        return $value < 0 ? bcadd((string) $value, self::SPAN) : (string) $value;
    }

    /**
     * Reads a text as a number in a base, signed or not, and answers its 64 bits as an unsigned decimal.
     *
     * @return numeric-string
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function read(Frame $frame, string $text, int $base, bool $signed, Charset $charset): string
    {
        preg_match('/\A[ \t\n\r\v\f]*([+-]?)([0-9A-Za-z]*)/', $text, $match);
        $negative = ($match[1] ?? '') === '-';
        [$magnitude, $count] = $this->magnitude($match[2] ?? '', $base);
        $legacy = in_array($frame->context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        if ($count === 0) {
            if (!$legacy) {
                $frame->context->warning(DataError::TruncatedWrongValue, 'DECIMAL', Convert::shown($text, $charset));
            }

            return '0';
        }
        $limit = $signed ? ($negative ? '9223372036854775808' : '9223372036854775807') : '18446744073709551615';
        if (bccomp($magnitude, $limit) > 0) {
            if (!$legacy) {
                $frame->context->warning(DataError::TruncatedWrongValue, 'DECIMAL', Convert::shown($text, $charset));
            }

            return match (true) {
                $signed => $negative ? '9223372036854775808' : '9223372036854775807',
                $negative && !$legacy && ctype_digit(($match[2] ?? '')[0] ?? '') => '0',
                default => '18446744073709551615',
            };
        }

        return $negative && $magnitude !== '0' ? bcsub(self::SPAN, $magnitude) : $magnitude;
    }

    /**
     * Reads the leading digits of a text in a base, up to the first that is none, and answers their number and how many were read.
     *
     * @return array{numeric-string, int}
     */
    public function magnitude(string $digits, int $base): array
    {
        $magnitude = '0';
        $count = 0;
        foreach ($digits === '' ? [] : str_split(strtoupper($digits)) as $character) {
            $digit = strpos(self::DIGITS, $character);
            if ($digit === false || $digit >= $base) {
                break;
            }
            $magnitude = bcadd(bcmul($magnitude, (string) $base), (string) $digit);
            $count++;
        }

        return [$magnitude, $count];
    }

    /**
     * Writes an unsigned decimal in a base, in upper-case digits.
     *
     * @param numeric-string $number
     */
    public function write(string $number, int $base): string
    {
        if ($number === '0') {
            return '0';
        }
        $digits = '';
        while (bccomp($number, '0') > 0) {
            $digits = self::DIGITS[(int) bcmod($number, (string) $base)] . $digits;
            $number = bcdiv($number, (string) $base, 0);
        }

        return $digits;
    }
}
