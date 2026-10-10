<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Extreme;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Rules\Typing\Numbers;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of GREATEST and LEAST.
 *
 * The result is the type that holds every argument that is not NULL: a JSON argument makes a
 * LONGTEXT, temporal values alone settle on their kind, numbers alone on a number, and anything
 * else on a string. MySQL 5.6 and 5.7 settle the arguments as the branches of COALESCE, and size
 * them as their comparison (see legacyExtreme).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#function_greatest.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class ExtremeResults
{
    private const INTEGERS = [Field::Tiny, Field::Short, Field::Int24, Field::Long, Field::LongLong];

    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        return [
            'GREATEST' => fn (Invocation $call): ?Domain => $this->extreme($call, 'greatest'),
            'LEAST' => fn (Invocation $call): ?Domain => $this->extreme($call, 'least'),
        ];
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
     * Resolves GREATEST and LEAST: the type that holds every argument that is not NULL.
     *
     * A JSON argument makes a LONGTEXT in utf8mb4_bin (binary with a binary string or BIT
     * value); temporal values alone settle on their kind, a datetime when kinds differ; numbers
     * alone settle on a number; anything else on a string as long as the longest argument written
     * as text. MySQL 5.6 and 5.7 settle the arguments as the branches of COALESCE.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#function_greatest;
     * the types are verified on live 8.0.44 and 8.4.7 servers.
     *
     * @param string $operation The function as the server names it in messages
     */
    public function extreme(Invocation $call, string $operation): ?Domain
    {
        if ($this->legacy($call)) {
            return $this->legacyExtreme($call, $operation);
        }
        $present = array_values(array_filter($call->domains, static fn (Domain $domain): bool => $domain->kind !== Kind::Null));
        if ($present === []) {
            return Domain::null();
        }
        $modern = $call->derivation->context->profile->grammar !== GrammarRelease::MySql8044;
        $kinds = array_map(static fn (Domain $domain): Kind => $domain->kind, $present);
        $temporal = count(array_filter($kinds, static fn (Kind $kind): bool => $kind->temporal()));

        return match (true) {
            in_array(Kind::Json, $kinds, true) => $this->jsonExtreme($present, $modern),
            $temporal === count($kinds) => $this->temporalExtreme($present, $call),
            $temporal === 0 && !in_array(Kind::String, $kinds, true) => $this->numericExtreme($present, $modern),
            default => $this->textExtreme($present, $operation, $modern, $call),
        };
    }

    /**
     * Resolves GREATEST and LEAST as MySQL 5.6 and 5.7 do: the type of the branches of COALESCE, sized as the comparison of the arguments.
     *
     * Without a number among the arguments the type is that of COALESCE. A number with a string,
     * or a floating-point number, makes a value 23 characters long of no fixed scale. Otherwise the
     * arguments compare as decimals: the result holds the most integer digits, those an integer
     * literal writes, and the largest scale, a sign unless every argument is unsigned; a NULL or
     * TIME argument counts 16 digits, 15 with a scale, a DATE 8 and a DATETIME 14. A temporal argument makes a string of that length, a
     * decimal one a DECIMAL, and integers an integer of the type of COALESCE, a BIGINT where that
     * is a DECIMAL (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param string $operation The function as the server names it in messages
     */
    public function legacyExtreme(Invocation $call, string $operation): ?Domain
    {
        $settled = $call->aggregation()->of($call->domains, $operation, $call->derivation);
        $present = array_values(array_filter($call->domains, static fn (Domain $domain): bool => $domain->kind !== Kind::Null));
        $numeric = array_filter($present, static fn (Domain $domain): bool => in_array($domain->kind, [Kind::Integer, Kind::Decimal, Kind::Double, Kind::Bit, Kind::Year], true));
        if ($settled === null || $numeric === []) {
            return $settled;
        }
        $kinds = array_map(static fn (Domain $domain): Kind => $domain->kind, $present);
        if (in_array(Kind::String, $kinds, true) || in_array(Kind::Json, $kinds, true) || in_array(Kind::Double, $kinds, true)) {
            return new Domain($settled->kind, $settled->field, 23, Domain::NOT_FIXED, $settled->unsigned, $settled->collation, [], $settled->coercibility);
        }
        [$length, $scale, $signed] = $this->compared($call);
        if (array_filter($kinds, static fn (Kind $kind): bool => $kind->temporal()) !== []) {
            return new Domain(Kind::String, Field::VarString, $length, $scale, false, $settled->collation, [], $settled->coercibility);
        }
        if ($scale > 0 || in_array(Kind::Decimal, $kinds, true)) {
            return new Domain(Kind::Decimal, Field::NewDecimal, $length, $scale, !$signed, null, [], Coercibility::Numeric);
        }
        $field = $settled->kind === Kind::Integer ? $settled->field : Field::LongLong;

        return Domain::integer($field, $length, !$signed);
    }

    /**
     * Sizes the arguments of GREATEST and LEAST as MySQL 5.6 and 5.7 compare them: as decimals.
     *
     * The result holds the most integer digits, those an integer literal writes, and the largest
     * scale, with a point when there is a scale and a sign unless every argument is unsigned. A
     * NULL or TIME argument counts 16 integer digits, 15 with a scale, and is signed; a TIME also
     * brings its fractional digits (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @return array{int, int, bool} The length, the scale, and whether the result is signed
     */
    public function compared(Invocation $call): array
    {
        $numbers = new Numbers();
        $scale = 0;
        $integral = 0;
        $signed = false;
        $wide = false;
        foreach ($call->domains as $index => $domain) {
            if ($domain->kind === Kind::Null || $domain->kind === Kind::Time) {
                $wide = true;
                $scale = max($scale, $domain->kind === Kind::Time ? $domain->decimals : 0);
                $signed = true;
                continue;
            }
            [$precision, $digits] = $numbers->digits($domain);
            $written = $this->written($call->nodes[$index] ?? null);
            $precision = $written !== null && ($domain->kind === Kind::Integer || $domain->kind === Kind::Decimal) ? $written + $digits : $precision;
            $scale = max($scale, $digits);
            $integral = max($integral, $precision - $digits);
            $signed = $signed || !$domain->unsigned || $domain->kind->temporal();
        }
        if ($wide) {
            $integral = max($integral, $scale > 0 ? 15 : 16);
        }

        return [$integral + $scale + ($scale > 0 ? 1 : 0) + ($signed ? 1 : 0), $scale, $signed];
    }

    /**
     * Answers the integer digits a number literal writes, under parentheses and a sign, or null for another expression.
     */
    public function written(?\SqlSemantics\Statement\Scalar $node): ?int
    {
        while ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary && in_array($node->operator, [\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Plus], true))) {
            $node = $node->operand;
        }
        if (!$node instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral) {
            return null;
        }
        $integral = ltrim((string) preg_replace('/[.eE].*\z/', '', $node->text), '0');

        return max(1, strlen($integral));
    }

    /**
     * Resolves GREATEST and LEAST of a JSON value: a LONGTEXT in utf8mb4_bin, or a LONGBLOB with a binary string or BIT value.
     *
     * Only JSON values make a VARCHAR as long as a LONGTEXT holds; MySQL 8.0 makes every one a VARCHAR.
     *
     * @param non-empty-list<Domain> $present The arguments that are not NULL
     * @param bool $modern Whether MySQL 8.1 or later resolves the call
     */
    public function jsonExtreme(array $present, bool $modern): Domain
    {
        $binary = array_filter($present, static fn (Domain $domain): bool => $domain->kind === Kind::Bit || ($domain->kind === Kind::String && $domain->collation->bytes())) !== [];
        $json = array_filter($present, static fn (Domain $domain): bool => $domain->kind !== Kind::Json) === [];
        $collation = $binary ? Collation::binary() : Collation::known('utf8mb4_bin');

        return new Domain(Kind::String, $json || !$modern ? Field::VarString : Field::LongBlob, 4294967295, $modern ? Domain::NOT_FIXED : $this->textDecimals($present), false, $collation, [], Coercibility::Implicit);
    }

    /**
     * Resolves GREATEST and LEAST of temporal values: one kind keeps it, a TIMESTAMP only with TIMESTAMP values, and different kinds make a DATETIME.
     *
     * The value is written in the character set of the connection, so its length counts there.
     *
     * @param non-empty-list<Domain> $present The arguments that are not NULL
     */
    public function temporalExtreme(array $present, Invocation $call): Domain
    {
        $decimals = max(array_map(static fn (Domain $domain): int => $domain->decimals, $present));
        $fraction = $decimals > 0 ? $decimals + 1 : 0;
        $kinds = array_values(array_unique(array_map(static fn (Domain $domain): string => $domain->kind->name, $present)));
        $fields = array_values(array_unique(array_map(static fn (Domain $domain): int => $domain->field->value, $present)));
        $kind = count($kinds) === 1 ? $present[0]->kind : Kind::DateTime;
        $field = match ($kind) {
            Kind::Date => Field::Date,
            Kind::Time => Field::Time,
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => count($kinds) === 1 && count($fields) === 1 ? $present[0]->field : Field::DateTime,
        };
        $length = match ($kind) {
            Kind::Date => 10,
            Kind::Time => 10 + $fraction,
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => 19 + $fraction,
        };

        return new Domain($kind, $field, $length, $decimals, false, $call->settings->connection, [], Coercibility::Numeric);
    }

    /**
     * Resolves GREATEST and LEAST of numbers: integers on an integer, with decimals on a decimal, with a floating-point number on a double.
     *
     * @param non-empty-list<Domain> $present The arguments that are not NULL
     * @param bool $modern Whether MySQL 8.1 or later resolves the call
     */
    public function numericExtreme(array $present, bool $modern): Domain
    {
        $kinds = array_values(array_unique(array_map(static fn (Domain $domain): string => $domain->kind->name, $present)));
        if (count($kinds) === 1 && ($present[0]->kind === Kind::Year || $present[0]->kind === Kind::Bit)) {
            usort($present, static fn (Domain $left, Domain $right): int => $right->length <=> $left->length);

            return $present[0];
        }
        if (array_diff($kinds, [Kind::Integer->name, Kind::Year->name, Kind::Bit->name]) === []) {
            return $this->integers($present);
        }
        if (!in_array(Kind::Double->name, $kinds, true)) {
            $integral = 0;
            $scale = 0;
            foreach ($present as $domain) {
                [$precision, $digits] = (new Numbers())->digits($domain);
                $integral = max($integral, $precision - $digits);
                $scale = max($scale, $digits);
            }

            return Domain::decimal(min(65, $integral + $scale), min(30, $scale));
        }

        return $this->reals($present, $modern);
    }

    /**
     * Settles integers on the widest of them: a YEAR value takes no part in the type or its sign, and a BIT value is an unsigned BIGINT.
     *
     * Signed and unsigned integers settle on the next wider type, signed, as long as the most
     * digits; with an unsigned BIGINT among them, on a DECIMAL of those digits. Otherwise the
     * length holds the most digits and a sign when signed.
     *
     * @param non-empty-list<Domain> $present
     */
    public function integers(array $present): Domain
    {
        $widest = 0;
        $digits = 1;
        $signed = false;
        $unsigned = false;
        $big = false;
        foreach ($present as $domain) {
            if ($domain->kind === Kind::Year) {
                $digits = max($digits, $domain->length - 1);
                continue;
            }
            $field = $domain->kind === Kind::Bit ? Field::LongLong : $domain->field;
            $positive = $domain->unsigned || $domain->kind === Kind::Bit;
            $widest = max($widest, (int) array_search($field, self::INTEGERS, true));
            $digits = max($digits, $positive ? $domain->length : $domain->length - 1);
            $signed = $signed || !$positive;
            $unsigned = $unsigned || $positive;
            $big = $big || ($positive && $field === Field::LongLong);
        }
        if ($signed && $big) {
            return Domain::decimal($digits, 0);
        }
        if ($signed && $unsigned) {
            return Domain::integer(self::INTEGERS[min(4, $widest + 1)], $digits);
        }

        return Domain::integer(self::INTEGERS[$widest], $digits + ($signed ? 1 : 0), !$signed);
    }

    /**
     * Settles numbers of which one is a floating-point number on a FLOAT or a DOUBLE.
     *
     * FLOAT values settle on a FLOAT with any integer but an INT or an unsigned BIGINT, and with
     * YEAR values; any other number makes a DOUBLE. With fixed decimals only, the double keeps
     * the most of them after the longest integral part; otherwise it is 23 characters wide, and
     * as wide as the widest argument in MySQL 8.0.
     *
     * @param non-empty-list<Domain> $present
     * @param bool $modern Whether MySQL 8.1 or later resolves the call
     */
    public function reals(array $present, bool $modern): Domain
    {
        $single = true;
        $fixed = true;
        $decimals = 0;
        $integral = 0;
        $widest = 0;
        foreach ($present as $domain) {
            $single = $single && match ($domain->kind) {
                Kind::Double => $domain->field === Field::Float,
                Kind::Integer => $domain->field !== Field::Long && !($domain->field === Field::LongLong && $domain->unsigned),
                Kind::Year => true,
                Kind::Decimal, Kind::Bit, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Json, Kind::Null => false,
            };
            $fixed = $fixed && $domain->decimals < Domain::NOT_FIXED;
            $decimals = max($decimals, $domain->decimals);
            $integral = max($integral, $domain->length - $domain->decimals);
            $widest = max($widest, $domain->length);
        }
        $length = match (true) {
            $fixed => $integral + $decimals,
            $modern => 23,
            default => $widest,
        };

        return new Domain(Kind::Double, $single ? Field::Float : Field::Double, $length, $fixed ? $decimals : Domain::NOT_FIXED, false, null, [], Coercibility::Numeric);
    }

    /**
     * Resolves GREATEST and LEAST compared or written as strings: a string in the collation the arguments settle on, as long as the longest of them written as text.
     *
     * A BIT value takes part as a binary string. In a binary result a string counts its bytes. A
     * DOUBLE is 22 characters long and a FLOAT as long as its type; a time or datetime has the
     * fractional digits of the argument with the most, at most six, a string or floating-point
     * number counting six. MySQL 8.0 gives the string those decimals when a temporal value is
     * among the arguments, and counts a YEAR five characters long.
     *
     * @param non-empty-list<Domain> $present The arguments that are not NULL
     * @param string $operation The function as the server names it in messages
     * @param bool $modern Whether MySQL 8.1 or later resolves the call
     */
    public function textExtreme(array $present, string $operation, bool $modern, Invocation $call): ?Domain
    {
        $settled = $call->collations()->aggregate(array_map(static fn (Domain $domain): Domain => $domain->kind === Kind::Bit ? Domain::string($domain->length, Collation::binary()) : $domain, $present), $operation, $call->derivation);
        if ($settled === null) {
            return null;
        }
        [$collation, $coercibility] = $settled;
        $fraction = $this->fraction($present);
        $fraction = $fraction > 0 ? $fraction + 1 : 0;
        $length = 0;
        $blob = false;
        foreach ($present as $domain) {
            $length = max($length, match ($domain->kind) {
                Kind::String => $collation->bytes() ? $domain->length * $domain->collation->charset->maxLength : $domain->length,
                Kind::Double => $domain->field === Field::Float ? $domain->length : 22,
                Kind::Date => 10,
                Kind::Time => 10 + $fraction,
                Kind::DateTime => 19 + $fraction,
                Kind::Year => $domain->length + ($modern ? 0 : 1),
                Kind::Integer, Kind::Decimal, Kind::Bit, Kind::Json, Kind::Null => $domain->length,
            });
            $blob = $blob || in_array($domain->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true);
        }

        return new Domain(Kind::String, $blob ? Field::Blob : Field::VarString, $length, $modern ? Domain::NOT_FIXED : $this->textDecimals($present), false, $collation, [], $coercibility);
    }

    /**
     * Answers the fractional digits a time or datetime argument of GREATEST or LEAST is written with: those of the argument with the most, at most six.
     *
     * A string, or an argument without fixed decimals, counts six.
     *
     * @param list<Domain> $present
     */
    public function fraction(array $present): int
    {
        $digits = 0;
        foreach ($present as $domain) {
            $digits = max($digits, $domain->kind === Kind::String || $domain->kind === Kind::Json || $domain->decimals >= Domain::NOT_FIXED ? 6 : $domain->decimals);
        }

        return min(6, $digits);
    }

    /**
     * Answers the decimals MySQL 8.0 gives a string GREATEST or LEAST: none without a temporal value, the fractional digits with a time or datetime, and zero with dates alone.
     *
     * @param list<Domain> $present
     */
    public function textDecimals(array $present): int
    {
        $kinds = array_map(static fn (Domain $domain): Kind => $domain->kind, $present);
        if (array_filter($kinds, static fn (Kind $kind): bool => $kind->temporal()) === []) {
            return Domain::NOT_FIXED;
        }

        return in_array(Kind::Time, $kinds, true) || in_array(Kind::DateTime, $kinds, true) ? $this->fraction($present) : 0;
    }
}
