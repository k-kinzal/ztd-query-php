<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the types of literals.
 *
 * An integer literal is a BIGINT whose display length counts its digits and a sign, a BIGINT
 * UNSIGNED above the signed range, and a DECIMAL above the unsigned range; a decimal literal is a
 * DECIMAL of its digits, where leading zeros count as one; a literal with an exponent is a DOUBLE
 * as long as its text. A string is
 * a VARCHAR of its characters in the connection collation or the character set of its
 * introducer; a hexadecimal or bit literal is a binary string of its bytes. A temporal literal
 * keeps the fractional digits it writes, up to six.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/literals.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Literals
{
    private const SIGNED_MAX = '9223372036854775807';

    private const UNSIGNED_MAX = '18446744073709551615';

    /**
     * @param Settings $settings The session the literals are read in
     * @param GrammarRelease $release The release whose character sets introducers name
     */
    public function __construct(public readonly Settings $settings, public readonly GrammarRelease $release)
    {
    }

    /**
     * Answers the rules of a context: its session and its release.
     */
    public static function of(AnalysisContext $context): self
    {
        return new self(Settings::of($context), $context->profile->grammar);
    }

    /**
     * Resolves a number literal, negated when a sign is written before it.
     */
    public function number(NumberLiteral $literal, bool $negative = false): Domain
    {
        if ($literal->form === NumberForm::Float) {
            return Domain::double(strlen($literal->text));
        }
        $digits = ltrim($literal->text, '0');
        if ($literal->form === NumberForm::Integer) {
            if ($this->within($digits, $negative ? '9223372036854775808' : self::SIGNED_MAX)) {
                return Domain::integer(Field::LongLong, strlen($literal->text) + 1);
            }
            if (!$negative && $this->within($digits, self::UNSIGNED_MAX)) {
                return Domain::integer(Field::LongLong, strlen($literal->text), true);
            }
        }
        $point = strpos($literal->text, '.');
        $whole = $point === false ? $literal->text : substr($literal->text, 0, $point);
        $scale = $point === false ? 0 : strlen($literal->text) - $point - 1;
        $significant = ltrim($whole, '0');
        $integral = $whole === '' ? 0 : strlen($significant) + ($significant === $whole ? 0 : 1);

        return Domain::decimal(max(1, $integral + $scale), $scale);
    }

    /**
     * Answers whether unsigned digits without leading zeros are at most a bound.
     */
    public function within(string $digits, string $bound): bool
    {
        return strlen($digits) < strlen($bound) || (strlen($digits) === strlen($bound) && strcmp($digits, $bound) <= 0);
    }

    /**
     * Resolves a string literal.
     */
    public function string(StringLiteral $literal): Domain
    {
        $collation = match (true) {
            $literal->national => Collation::known('utf8mb3_general_ci'),
            $literal->introducer !== null => $this->introduced($literal->introducer->value),
            default => $this->settings->connection,
        };

        return Domain::string($collation->charset->length($literal->value()), $collation, Field::VarString, Coercibility::Coercible);
    }

    /**
     * Answers the collation an introducer gives a literal: the default of its character set.
     */
    public function introduced(string $charset): Collation
    {
        return Charset::named($charset)?->defaultCollation($this->release) ?? Collation::binary();
    }

    /**
     * Resolves a hexadecimal or bit literal.
     */
    public function radix(RadixLiteral $literal): Domain
    {
        $bits = $literal->radix === Radix::Bit ? strlen($literal->digits) : strlen($literal->digits) * 4;
        $collation = $literal->introducer === null ? Collation::binary() : $this->introduced($literal->introducer->value);

        return Domain::string(intdiv($bits + 7, 8), $collation, Field::VarString, Coercibility::Coercible);
    }

    /**
     * Resolves a DATE, TIME or TIMESTAMP literal.
     */
    public function temporal(TemporalLiteral $literal): Domain
    {
        $point = strrpos($literal->text, '.');
        $decimals = $point === false ? 0 : min(6, strlen(rtrim(substr($literal->text, $point + 1), " \t")));
        $fraction = $decimals > 0 ? $decimals + 1 : 0;

        return match ($literal->form) {
            TemporalForm::Date => new Domain(Kind::Date, Field::Date, 10, 0, false, null, [], Coercibility::Numeric),
            TemporalForm::Time => new Domain(Kind::Time, Field::Time, 8 + $fraction, $decimals, false, null, [], Coercibility::Numeric),
            TemporalForm::Timestamp => new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $decimals, false, null, [], Coercibility::Numeric),
        };
    }

    /**
     * Resolves TRUE or FALSE, the integers 1 and 0.
     */
    public function boolean(): Domain
    {
        return Domain::integer(Field::LongLong, 1);
    }
}
