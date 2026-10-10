<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Constants;
use SqlSemantics\Platform\MySql\Rules\Typing\Numbers;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of mathematical functions.
 *
 * ABS makes an integer a BIGINT and keeps a decimal's precision and scale; CEILING and FLOOR make a BIGINT, or a decimal of the integral
 * digits beyond 18 of them (in MySQL 5.6 and 5.7 the BIGINT of
 * CEILING and FLOOR is as long as the argument, verified on live 5.6.51 and 5.7.44 servers); ROUND and TRUNCATE make an integer a
 * BIGINT and keep a decimal with at most the decimals a constant second argument asks for, and MySQL 5.6 and 5.7 keep the class of
 * their argument with the decimals a literal second argument asks for, a double keeping no fixed decimals; PI is a double of six decimals; the other
 * functions return doubles.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/mathematical-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class MathResults
{
    private const REAL = ['SQRT', 'EXP', 'LN', 'LOG', 'LOG2', 'LOG10', 'SIN', 'COS', 'TAN', 'ASIN', 'ACOS', 'ATAN', 'ATAN2', 'COT', 'DEGREES', 'RADIANS', 'POW', 'POWER', 'RAND'];

    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $rules = [
            'ABS' => fn (Invocation $call): Domain => $this->same($call->domain(0)),
            'CEILING' => fn (Invocation $call): Domain => $this->integral($call->domain(0), $this->legacy($call)),
            'CEIL' => fn (Invocation $call): Domain => $this->integral($call->domain(0), $this->legacy($call)),
            'FLOOR' => fn (Invocation $call): Domain => $this->integral($call->domain(0), $this->legacy($call)),
            'ROUND' => fn (Invocation $call): Domain => $this->legacy($call) ? $this->rounded($call) : $this->nearest($call),
            'TRUNCATE' => fn (Invocation $call): Domain => $this->legacy($call) ? $this->legacyTruncated($call) : $this->truncated($call),
            'PI' => static fn (Invocation $call): Domain => Domain::double(8, 6),
            'MOD' => $this->modulo(...),
        ];
        foreach (self::REAL as $name) {
            $rules[$name] = static fn (Invocation $call): Domain => Domain::double(23);
        }

        return $rules;
    }

    /**
     * Tells whether a call is read by MySQL 5.6 or 5.7.
     */
    public function legacy(Invocation $call): bool
    {
        $grammar = $call->derivation->context->profile->grammar;

        return $grammar === GrammarRelease::MySql5651 || $grammar === GrammarRelease::MySql5744;
    }

    /**
     * Resolves ABS: integer arguments yield a BIGINT of the same length and signedness.
     */
    public function same(Domain $domain): Domain
    {
        $numbers = new Numbers();

        return match ($numbers->operand($domain)) {
            Kind::Integer => Domain::integer(Field::LongLong, $domain->length, $domain->unsigned),
            Kind::Decimal => $domain->kind === Kind::Decimal ? $domain : Domain::decimal(...$numbers->digits($domain)),
            Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Domain::double(23),
        };
    }

    /**
     * Resolves CEILING and FLOOR: an integer makes a BIGINT, a decimal a BIGINT up to 18 integral digits and a DECIMAL beyond, anything else a double.
     *
     * In MySQL 5.6 and 5.7 an integer or a decimal makes a BIGINT as long as the argument without
     * its fraction, and two more, any result at most 17 long, and the double is 17 long with no
     * decimals (verified on a live 5.7.44 server).
     */
    public function integral(Domain $domain, bool $legacy = false): Domain
    {
        $numbers = new Numbers();
        if ($numbers->operand($domain) === Kind::Double || $domain->kind->temporal()) {
            return $legacy ? new Domain(Kind::Double, Field::Double, 17, 0, false, null, [], Coercibility::Numeric) : Domain::double(23);
        }
        if (!$legacy && $numbers->operand($domain) === Kind::Integer) {
            return Domain::integer(Field::LongLong, 21, $domain->unsigned || $domain->kind === Kind::Bit);
        }
        [$precision, $scale] = $numbers->digits($domain);
        $digits = $precision - $scale + ($scale > 0 ? 1 : 0);
        if (!$legacy) {
            return $digits < 19 ? Domain::integer(Field::LongLong, 21, $domain->unsigned) : Domain::decimal($digits, 0);
        }
        $length = min(17, $domain->length - ($domain->decimals > 0 ? $domain->decimals + 1 : 0) + 2);
        $decimal = Domain::decimal($digits, 0);

        return $digits < 19 ? Domain::integer(Field::LongLong, $length, $domain->unsigned) : new Domain(Kind::Decimal, Field::NewDecimal, min(17, $decimal->length), 0, false, null, [], Coercibility::Numeric);
    }

    /**
     * Resolves ROUND and TRUNCATE.
     */
    public function rounded(Invocation $call): Domain
    {
        $numbers = new Numbers();
        $domain = $call->domain(0);
        $places = $call->constant(1);
        $decimals = $places ?? (count($call->domains) === 1 ? 0 : null);
        if ($numbers->operand($domain) === Kind::Double) {
            return Domain::double(23);
        }
        [$precision, $scale] = $numbers->digits($domain);
        if ($decimals === null) {
            return Domain::decimal($precision, $scale);
        }
        $newScale = max(0, min(30, $decimals));
        if ($numbers->operand($domain) === Kind::Integer && $newScale === 0) {
            return Domain::integer(Field::LongLong, $domain->length, $domain->unsigned);
        }

        return Domain::decimal(min(65, $precision - $scale + $newScale + 1), $newScale);
    }

    /**
     * Resolves ROUND from MySQL 8.0 on: an integer makes a BIGINT, a double, a string or a temporal value a double, and a decimal keeps its type unless a constant second argument asks for fewer decimals than it has, which round into one more integral digit.
     *
     * A second argument that is not constant leaves a decimal its type, and one of no more
     * decimals than its own too (verified on live 8.0.44, 8.4.7 and 9.1.0 servers).
     */
    public function nearest(Invocation $call): Domain
    {
        $numbers = new Numbers();
        $domain = $call->domain(0);
        $kind = $numbers->operand($domain);
        if ($kind === Kind::Double || $domain->kind->temporal()) {
            return Domain::double(23);
        }
        if ($kind === Kind::Integer) {
            return Domain::integer(Field::LongLong, 21, $domain->unsigned || $domain->kind === Kind::Bit);
        }
        [$precision, $scale] = $numbers->digits($domain);
        $places = count($call->domains) === 1 ? 0 : $this->places($call);
        if ($places === null || $places >= $scale) {
            return Domain::decimal($precision, $scale);
        }
        $kept = max(0, $places);

        return Domain::decimal(min(65, $precision - $scale + $kept + 1), $kept);
    }

    /**
     * Resolves TRUNCATE from MySQL 8.0 on: an integer makes a BIGINT, a double, a string or a temporal value a double, and a decimal keeps its integral digits with the decimals a constant second argument asks for, at most its own.
     *
     * A second argument that is not constant leaves a decimal its type (verified on live 8.0 and 8.4 servers).
     */
    public function truncated(Invocation $call): Domain
    {
        $numbers = new Numbers();
        $domain = $call->domain(0);
        $kind = $numbers->operand($domain);
        if ($kind === Kind::Double || $domain->kind->temporal()) {
            return Domain::double(23);
        }
        if ($kind === Kind::Integer) {
            return Domain::integer(Field::LongLong, 21, $domain->unsigned || $domain->kind === Kind::Bit);
        }
        [$precision, $scale] = $numbers->digits($domain);
        $places = $this->places($call);
        if ($places === null) {
            return Domain::decimal($precision, $scale);
        }
        $kept = max(0, min($scale, $places));

        return Domain::decimal(max(1, $precision - $scale + $kept), $kept);
    }

    /**
     * Resolves TRUNCATE in MySQL 5.6 and 5.7: an integer keeps its length, a decimal keeps its integral digits and its sign with the decimals a constant second argument asks for, and anything else makes a double of those decimals, 17 characters and the decimals long (verified on a live 5.7.44 server).
     */
    public function legacyTruncated(Invocation $call): Domain
    {
        $numbers = new Numbers();
        $domain = $call->domain(0);
        $kind = $numbers->operand($domain);
        if ($kind === Kind::Integer && !$domain->kind->temporal()) {
            return Domain::integer(Field::LongLong, $domain->length, $domain->unsigned);
        }
        $places = $this->places($call);
        if ($places === null) {
            return $this->rounded($call);
        }
        $kept = max(0, min(30, $places));
        if ($kind !== Kind::Decimal || $domain->kind->temporal()) {
            return new Domain(Kind::Double, Field::Double, 17 + $kept, $kept, false, null, [], Coercibility::Numeric);
        }
        [$precision, $scale] = $numbers->digits($domain);

        return Domain::decimal(max(1, min(65, $precision - $scale + $kept)), $kept, $domain->unsigned);
    }

    /**
     * Answers the decimals the constant second argument of ROUND or TRUNCATE asks for, or null when it is not constant.
     *
     * An integer constant asks for its value, NULL for none, and a decimal or floating-point
     * literal for its value rounded to an integer.
     */
    public function places(Invocation $call): ?int
    {
        $node = $call->nodes[1] ?? null;
        $value = $node === null ? null : (new Constants())->value($node);
        $constant = $call->constant(1) ?? ($value === null ? null : ($value[1] && $value[0] < 0 ? PHP_INT_MAX : $value[0]));
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }

        return match (true) {
            $constant !== null => $constant,
            $node instanceof NullLiteral => 0,
            $node instanceof NumberLiteral && $node->form === NumberForm::Decimal => (int) round((float) $node->text),
            $node instanceof NumberLiteral && $node->form === NumberForm::Float => (int) round((float) $node->text, 0, PHP_ROUND_HALF_EVEN),
            default => null,
        };
    }

    /**
     * Resolves MOD(N, M) as the operator N % M.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/mathematical-functions.html#function_mod.
     */
    public function modulo(Invocation $call): Domain
    {
        $numbers = new Numbers($call->settings->divPrecisionIncrement, $call->settings->unsignedSubtraction);
        $left = isset($call->nodes[0]) ? $numbers->numeric($call->nodes[0], $call->domain(0)) : $call->domain(0);
        $right = isset($call->nodes[1]) ? $numbers->numeric($call->nodes[1], $call->domain(1)) : $call->domain(1);

        return $numbers->binary(ArithmeticOperator::Modulo, $left, $right);
    }
}
