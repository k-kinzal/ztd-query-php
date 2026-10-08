<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\DataError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Rules\Typing\Literals as Rules;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape;
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
            Kind::Decimal, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Decimal::canonical($text),
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
        $form = match ($literal->form) {
            TemporalForm::Date => 'DATE',
            TemporalForm::Time => 'TIME',
            TemporalForm::Timestamp => 'DATETIME',
        };
        $modes = $this->compiler->settings->modes;
        $value = Temporal::literal($form, $literal->text, $domain->decimals, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'));
        if ($value === null) {
            throw DataError::WrongValue->error($form, $literal->text);
        }

        return new Constant($domain, $value);
    }

    /**
     * Compiles an ODBC escape `{ kind expr }`.
     *
     * With the kind `d`, `t` or `ts`, written in lower case, over a string that is a DATE, TIME or
     * TIMESTAMP literal of that kind, the escape is that literal; otherwise it is its operand.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html.
     */
    public function odbc(OdbcEscape $escape, Scope $scope): Evaluable
    {
        $form = match ($escape->kind->value) {
            'd' => 'DATE',
            't' => 'TIME',
            'ts' => 'DATETIME',
            default => null,
        };
        if ($form !== null && $escape->operand instanceof StringLiteral) {
            $domain = $this->compiler->domain($escape);
            $modes = $this->compiler->settings->modes;
            $value = Temporal::literal($form, $escape->operand->value(), $domain->kind->temporal() ? $domain->decimals : 0, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'));
            if ($value !== null) {
                return new Constant($domain, $value);
            }
        }

        return $this->compiler->compile($escape->operand, $scope);
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
