<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Platform\MySql\Rules\Typing\Numbers;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of mathematical functions.
 *
 * ABS keeps the type of its argument; CEILING and FLOOR make a BIGINT, or a decimal of the integral
 * digits beyond 18 of them; ROUND and TRUNCATE keep the class of their argument with
 * the decimals a literal second argument asks for, a double keeping no fixed decimals; PI is a double of six decimals; the other
 * functions return doubles.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/mathematical-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class MathResults
{
    private const REAL = ['SQRT', 'EXP', 'LN', 'LOG2', 'LOG10', 'SIN', 'COS', 'TAN', 'ASIN', 'ACOS', 'ATAN', 'COT', 'DEGREES', 'RADIANS', 'POW', 'POWER'];

    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $rules = [
            'ABS' => fn (Invocation $call): Domain => $this->same($call->domain(0)),
            'CEILING' => fn (Invocation $call): Domain => $this->integral($call->domain(0)),
            'CEIL' => fn (Invocation $call): Domain => $this->integral($call->domain(0)),
            'FLOOR' => fn (Invocation $call): Domain => $this->integral($call->domain(0)),
            'ROUND' => $this->rounded(...),
            'TRUNCATE' => $this->rounded(...),
            'PI' => static fn (Invocation $call): Domain => Domain::double(8, 6),
        ];
        foreach (self::REAL as $name) {
            $rules[$name] = static fn (Invocation $call): Domain => Domain::double(23);
        }

        return $rules;
    }

    /**
     * Resolves a result of the class of its argument.
     */
    public function same(Domain $domain): Domain
    {
        $numbers = new Numbers();

        return match ($numbers->operand($domain)) {
            Kind::Integer => Domain::integer($domain->kind === Kind::Integer ? $domain->field : Field::LongLong, $domain->length, $domain->unsigned),
            Kind::Decimal => $domain->kind === Kind::Decimal ? $domain : Domain::decimal(...$numbers->digits($domain)),
            Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Domain::double(23),
        };
    }

    /**
     * Resolves CEILING and FLOOR.
     */
    public function integral(Domain $domain): Domain
    {
        $numbers = new Numbers();
        if ($numbers->operand($domain) === Kind::Double) {
            return Domain::double(23);
        }
        [$precision, $scale] = $numbers->digits($domain);
        $digits = $precision - $scale + ($scale > 0 ? 1 : 0);

        return $digits < 19 ? Domain::integer(Field::LongLong, 21, $domain->unsigned) : Domain::decimal($digits, 0);
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
}
