<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Rules\Typing\Literals as Rules;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles literals into constants: the value the text writes, in the type SQL Semantics resolved for it.
 *
 * A literal outside the statement SQL Semantics analyzed, such as a column default, is typed by
 * the literal rules of SQL Semantics directly. A hexadecimal or bit literal without an introducer
 * is a binary string whose bytes read as an unsigned integer in a numeric context.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Literals
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Answers the literal rules of the session.
     */
    public function rules(): Rules
    {
        return new Rules($this->compiler->settings->resolution(), $this->compiler->settings->release());
    }

    /**
     * Answers the type of a literal: the one SQL Semantics resolved, else the one its rules give.
     */
    public function typed(Scalar $node, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain $rule): Domain
    {
        return $this->compiler->resolved($node) ?? Domain::of($rule, false);
    }

    /**
     * Compiles a number literal, negated when a sign is written before it.
     */
    public function number(NumberLiteral $literal, bool $negative = false, ?Scalar $node = null): Constant
    {
        $domain = $this->typed($node ?? $literal, $this->rules()->number($literal, $negative));
        $text = ($negative ? '-' : '') . $literal->text;

        return new Constant($domain, match ($domain->kind) {
            Kind::Double => (float) $text,
            Kind::Integer => $domain->unsigned ? Integer::fromUnsignedText(Decimal::canonical($text)) : (int) Decimal::canonical($text),
            default => Decimal::canonical($text),
        });
    }

    /**
     * Compiles a signed number, which only a DEFAULT clause writes.
     */
    public function signed(SignedLiteral $literal): Constant
    {
        return $this->number($literal->number, $literal->negative, $literal);
    }

    /**
     * Compiles a string literal.
     */
    public function string(StringLiteral $literal): Constant
    {
        return new Constant($this->typed($literal, $this->rules()->string($literal)), $literal->value());
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

        $domain = $this->typed($literal, $this->rules()->radix($literal));

        return new Constant($literal->introducer === null ? $domain->withNumericBytes() : $domain, $bytes);
    }

    /**
     * Compiles a DATE, TIME or TIMESTAMP literal.
     *
     * @throws \MySqlMemory\Error\SqlError When the text is not a valid value of the form
     */
    public function temporal(TemporalLiteral $literal): Constant
    {
        $domain = $this->typed($literal, $this->rules()->temporal($literal));
        if ($literal->form === TemporalForm::Time) {
            $parts = Temporal::parseTime($literal->text);
            if ($parts === null) {
                throw ErrorCode::WrongValue->error('TIME', $literal->text);
            }

            return new Constant($domain, Temporal::time($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $domain->decimals));
        }
        $parts = Temporal::parseDateTime($literal->text);
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2])) {
            throw ErrorCode::WrongValue->error($literal->form === TemporalForm::Date ? 'DATE' : 'DATETIME', $literal->text);
        }
        if ($literal->form === TemporalForm::Date) {
            return new Constant($domain, Temporal::date($parts[0], $parts[1], $parts[2]));
        }

        return new Constant($domain, Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], $domain->decimals));
    }

    /**
     * Compiles TRUE or FALSE, the integers 1 and 0.
     */
    public function boolean(BooleanLiteral $literal): Constant
    {
        return new Constant($this->typed($literal, $this->rules()->boolean()), $literal->value ? 1 : 0);
    }

    /**
     * Compiles NULL.
     */
    public function null(NullLiteral $literal): Constant
    {
        return new Constant(Domain::null(), null);
    }
}
