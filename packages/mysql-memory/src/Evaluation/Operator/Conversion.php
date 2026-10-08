<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\DataError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * CAST(value AS type): the value converted into the domain of the type.
 *
 * A string that is not a date or time converts to NULL with a warning. A string converts into
 * the character set of the target. A string longer than a CHAR(N) or BINARY(N) target is cut with a warning that names the bytes it keeps; a shorter one
 * is padded with 0x00 bytes to the length of a BINARY(N) target. A negative number cast to UNSIGNED takes
 * its two's complement with a warning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Conversion implements Evaluable
{
    /**
     * @param Evaluable $operand The value converted
     * @param Domain $domain The domain of the target
     * @param int|null $limit The length of a CHAR(N) or BINARY(N) target
     * @param string $target The keyword of the target, for warnings
     */
    public function __construct(public readonly Evaluable $operand, public readonly Domain $domain, public readonly ?int $limit, public readonly string $target)
    {
    }

    /**
     * Answers the domain of the target.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Converts the operand for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $from = $this->operand->domain();
        $context = $frame->context;

        return match ($this->domain->kind) {
            Kind::Integer => $this->integer($value, $from, $context),
            Kind::Decimal => $this->decimal((string) Convert::operandDecimal($value, $this->operand, $context), $context),
            Kind::Double => Convert::toDouble($value, $from, $context),
            Kind::Date, Kind::DateTime, Kind::Time => (new Moments())->convert($value, $from, $this->domain, $context),
            Kind::Year => (new Moments())->year($value, $from, $context),
            Kind::String => ($text = self::transcode((string) Convert::toText($value, $from), $from, $this->domain->collation->charset, $context)) === null ? null : $this->text($text, $context),
            Kind::Json, Kind::Bit, Kind::Null => $this->text((string) Convert::toText($value, $from), $context),
        };
    }

    /**
     * Converts the text of a value into a character set.
     *
     * The bytes of a binary string are taken as characters of the set, padded with leading zero
     * bytes to the width of a UCS-2, UTF-16 or UTF-32 unit. Bytes that are no character of the set
     * warn (ER_INVALID_CHARACTER_STRING), quoting up to three of them from the first, and make the
     * result NULL in a multibyte set; a single-byte set keeps them.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-convert.html.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public static function transcode(string $text, Domain $from, Charset $to, Context $context): ?string
    {
        $source = $from->kind === Kind::String ? $from->collation->charset : Charset::known('utf8mb4');
        if ($source !== Charset::binary() || $to === Charset::binary()) {
            return Encoding::convert($text, $source, $to);
        }
        $unit = match ($to->name) {
            'ucs2', 'utf16', 'utf16le' => 2,
            'utf32' => 4,
            default => 1,
        };
        $text = strlen($text) % $unit === 0 ? $text : str_repeat("\0", $unit - strlen($text) % $unit) . $text;
        if (Encoding::valid($text, $to)) {
            return $text;
        }
        $context->warning(DataError::InvalidCharacterString, $to->name, strtoupper(bin2hex(substr($text, Encoding::prefix($text, $to), 3))));

        return $to->maxLength === 1 ? $text : null;
    }

    /**
     * Converts to SIGNED or UNSIGNED; a decimal converts as Convert::decimalInteger() reads it.
     *
     * The cast warns of a string that is more than a number wherever the string comes from, and of
     * a negative integer made unsigned except in MySQL 5.6 and 5.7 (verified on a live 5.7.44 server).
     */
    public function integer(int|float|string $value, Domain $from, Context $context): int
    {
        if ($from->kind === Kind::Decimal) {
            return Convert::decimalInteger((string) $value, $context, $this->domain->unsigned);
        }
        $result = (int) Convert::toInteger($value, $from->withQuiet(false), $context, $this->domain->unsigned);
        if ($this->domain->unsigned && $from->kind === Kind::Integer && !$from->unsigned && $result < 0 && !in_array($context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            $context->warning(StatementError::UnknownError, 'Cast to unsigned converted negative integer to its positive complement');
        }

        return $result;
    }

    /**
     * Converts to DECIMAL(M,D), clamping to the largest value of the precision with a warning.
     */
    public function decimal(string $value, Context $context): string
    {
        $rounded = Decimal::round($value, $this->domain->decimals);
        $digits = $this->domain->precision() - $this->domain->decimals;
        if (Decimal::integerDigits($rounded) > $digits && trim(explode('.', ltrim($rounded, '-'))[0], '0') !== '') {
            $context->warning(DataError::DataOutOfRange, 'DECIMAL', '');
            $largest = str_repeat('9', max(1, $digits)) . ($this->domain->decimals > 0 ? '.' . str_repeat('9', $this->domain->decimals) : '');

            return str_starts_with($rounded, '-') ? '-' . $largest : $largest;
        }

        return $rounded;
    }

    /**
     * Converts to CHAR or BINARY, cutting to the length of the target; a binary target pads a shorter value with 0x00 bytes to its length.
     *
     * The warning names the target by the bytes the kept characters take, as CHAR for CHAR and
     * NCHAR, so `CAST('é日本語' AS CHAR(2))` is truncated as CHAR(5).
     */
    public function text(string $value, Context $context): string
    {
        if ($this->limit === null) {
            return $value;
        }
        $characters = $this->domain->collation->charset;
        $binary = $this->domain->collation->bytes();
        if ($characters->length($value) <= $this->limit) {
            return $binary ? str_pad($value, $this->limit, "\0") : $value;
        }
        $kept = $characters->maxLength === 1 || !mb_check_encoding($value, 'UTF-8') ? substr($value, 0, $this->limit) : mb_substr($value, 0, $this->limit, 'UTF-8');
        $context->warning(DataError::TruncatedWrongValue, ($this->target === 'BINARY' ? 'BINARY' : 'CHAR') . '(' . strlen($kept) . ')', $this->quoted($value, $binary));

        return $kept;
    }

    /**
     * Answers a value as the truncation warning quotes it: its first 128 characters, a binary value with each byte that is not printable ASCII written \xHH, a text with each character beyond the Basic Multilingual Plane written `?`.
     */
    public function quoted(string $value, bool $binary): string
    {
        if ($binary || !mb_check_encoding($value, 'UTF-8')) {
            $quoted = '';
            foreach (str_split(substr($value, 0, 128)) as $byte) {
                $code = ord($byte);
                $quoted .= $code < 0x20 || $code >= 0x7F ? sprintf('\\x%02X', $code) : $byte;
            }

            return $quoted;
        }

        return (string) preg_replace('/[\x{10000}-\x{10FFFF}]/u', '?', mb_substr($value, 0, 128, 'UTF-8'));
    }
}
