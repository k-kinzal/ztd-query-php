<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Leaf\Constant;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;

/**
 * Compiles literals into constants of the domain the server gives them.
 *
 * An integer literal is a BIGINT whose display length counts its digits and a sign, a BIGINT
 * UNSIGNED above the signed range, and a DECIMAL above the unsigned range; a decimal literal is a
 * DECIMAL of its digits; a literal with an exponent is a DOUBLE as long as its text. A string is
 * a VARCHAR of its characters in the connection collation or the character set of its
 * introducer; a hexadecimal or bit literal is a binary string.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/literals.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Literals
{
    /**
     * @param Settings $settings The session settings literals are read under
     */
    public function __construct(public readonly Settings $settings)
    {
    }

    /**
     * Compiles a number literal.
     */
    public function number(NumberLiteral $literal, bool $negative = false): Constant
    {
        $text = ($negative ? '-' : '') . $literal->text;
        if ($literal->form === NumberForm::Float) {
            return new Constant(Domain::double(strlen($literal->text))->withNullable(false), (float) $text);
        }
        if ($literal->form === NumberForm::Integer) {
            $digits = strlen(ltrim($literal->text, '0')) ?: 1;
            $number = Decimal::canonical($text);
            if (Integer::signedRange($number)) {
                return new Constant(new Domain(Kind::Integer, Field::LongLong, $digits + 1, 0, false, Collation::binary(), false), (int) $number);
            }
            if (!$negative && Integer::unsignedRange($number)) {
                return new Constant(new Domain(Kind::Integer, Field::LongLong, $digits, 0, true, Collation::binary(), false), Integer::fromUnsignedText($number));
            }
        }
        $number = Decimal::canonical($text);
        $scale = Decimal::scale($literal->text);

        return new Constant(Domain::decimal(max(1, strlen(str_replace('.', '', ltrim($literal->text, '0'))) ?: 1), $scale)->withNullable(false), $number);
    }

    /**
     * Compiles a signed number, which only a DEFAULT clause writes.
     */
    public function signed(SignedLiteral $literal): Constant
    {
        return $this->number($literal->number, $literal->negative);
    }

    /**
     * Compiles a string literal.
     */
    public function string(StringLiteral $literal): Constant
    {
        $collation = $literal->introducer === null ? $this->settings->connectionCollation : (Charset::named($literal->introducer->value)?->defaultCollation($this->settings->release()) ?? Collation::binary());
        if ($literal->national) {
            $collation = Collation::known('utf8mb3_general_ci');
        }
        $value = $literal->value();

        return new Constant(Domain::string($collation->charset->length($value), $collation)->withNullable(false), $value);
    }

    /**
     * Compiles a hexadecimal or bit literal into the bytes it writes.
     */
    public function radix(RadixLiteral $literal): Constant
    {
        $digits = $literal->digits;
        if ($literal->radix === Radix::Bit) {
            $bytes = '';
            $digits = str_pad($digits, (int) ceil(strlen($digits) / 8) * 8, '0', STR_PAD_LEFT);
            foreach (str_split($digits, 8) as $octet) {
                $bytes .= $digits === '' ? '' : chr((int) bindec($octet));
            }
        } else {
            $bytes = (string) hex2bin(strlen($digits) % 2 === 1 ? '0' . $digits : $digits);
        }
        $collation = $literal->introducer === null ? Collation::binary() : (Charset::named($literal->introducer->value)?->defaultCollation($this->settings->release()) ?? Collation::binary());

        return new Constant(new Domain(Kind::String, Field::VarString, strlen($bytes), Domain::NOT_FIXED, false, $collation, false), $bytes);
    }

    /**
     * Compiles a DATE, TIME or TIMESTAMP literal.
     *
     * @throws \MySqlMemory\Error\SqlError When the text is not a valid value of the form
     */
    public function temporal(TemporalLiteral $literal): Constant
    {
        if ($literal->form === TemporalForm::Time) {
            $parts = Temporal::parseTime($literal->text);
            if ($parts === null) {
                throw ErrorCode::WrongValue->error('TIME', $literal->text);
            }
            $decimals = $this->decimals($literal->text);

            return new Constant(new Domain(Kind::Time, Field::Time, 8 + ($decimals > 0 ? $decimals + 1 : 0), $decimals, false, Collation::binary(), false), Temporal::time($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $decimals));
        }
        $parts = Temporal::parseDateTime($literal->text);
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2])) {
            throw ErrorCode::WrongValue->error($literal->form === TemporalForm::Date ? 'DATE' : 'DATETIME', $literal->text);
        }
        if ($literal->form === TemporalForm::Date) {
            return new Constant(new Domain(Kind::Date, Field::Date, 10, 0, false, Collation::binary(), false), Temporal::date($parts[0], $parts[1], $parts[2]));
        }
        $decimals = $this->decimals($literal->text);

        return new Constant(new Domain(Kind::DateTime, Field::DateTime, 19 + ($decimals > 0 ? $decimals + 1 : 0), $decimals, false, Collation::binary(), false), Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], $decimals));
    }

    /**
     * Answers the number of fractional digits written in a temporal text, at most six.
     */
    public function decimals(string $text): int
    {
        $point = strrpos($text, '.');

        return $point === false ? 0 : min(6, strlen(rtrim(substr($text, $point + 1), " \t")));
    }

    /**
     * Compiles TRUE or FALSE, the integers 1 and 0.
     */
    public function boolean(BooleanLiteral $literal): Constant
    {
        return new Constant(new Domain(Kind::Integer, Field::LongLong, 1, 0, false, Collation::binary(), false), $literal->value ? 1 : 0);
    }

    /**
     * Compiles NULL.
     */
    public function null(NullLiteral $literal): Constant
    {
        return new Constant(Domain::null(), null);
    }
}
