<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the type that holds every value of several types: the branches of CASE, IF, IFNULL and COALESCE.
 *
 * NULL branches take no part. Integers settle on the widest integer, signed when signed and
 * unsigned branches meet; integers and decimals on a decimal wide enough for both; other numbers
 * on a double; temporal values of one kind on that kind, a date and a datetime on a datetime;
 * everything else on a string in the collation the branches aggregate to.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Aggregation
{
    private const INTEGERS = [Field::Tiny, Field::Short, Field::Int24, Field::Long, Field::LongLong];

    /**
     * @param Collations $collations How string branches settle their collation
     */
    public function __construct(public readonly Collations $collations)
    {
    }

    /**
     * Resolves the type of branches, or answers null after reporting collations that conflict.
     *
     * @param list<Domain> $domains The branches
     * @param string $operation The operation as the server names it
     */
    public function of(array $domains, string $operation, Derivation $derivation): ?Domain
    {
        $present = array_values(array_filter($domains, static fn (Domain $domain): bool => $domain->kind !== Kind::Null));
        if ($present === []) {
            return Domain::null();
        }
        $numbers = new Numbers();
        $classes = array_unique(array_map(static fn (Domain $domain): string => $numbers->operand($domain)->name . ':' . $domain->kind->name, $present));
        $all = static fn (callable $test): bool => count(array_filter($present, $test)) === count($present);

        return match (true) {
            count($classes) === 1 && $present[0]->kind === Kind::Integer => $this->integers($present),
            $all(static fn (Domain $domain): bool => $domain->kind === Kind::Integer || $domain->kind === Kind::Decimal) => $this->decimals($present),
            $all(static fn (Domain $domain): bool => $domain->kind->numeric() && $domain->kind !== Kind::Year && $domain->kind !== Kind::Bit) => $this->doubles($present, $operation, $derivation->context->profile->grammar),
            $all(static fn (Domain $domain): bool => $domain->kind->temporal()) => $this->temporals($present, $operation, $derivation),
            $all(static fn (Domain $domain): bool => $domain->kind === Kind::Year) => $present[0],
            default => $this->strings($present, $operation, $derivation),
        };
    }

    /**
     * Settles numbers of which one is a floating-point number on a double.
     *
     * FLOAT values with integers settle on a FLOAT, and with any other number on a DOUBLE (verified
     * on live 8.0 and 8.4 servers). The double is 23 characters wide. Before MySQL 8.1, which simplified how the type of several
     * values is aggregated (Bug #34847836), it was as wide as the widest value: so in MySQL 8.0,
     * and in the branches of CASE, IF, IFNULL and COALESCE of MySQL 5.7 (verified on live 5.7,
     * 8.0 and 8.4 servers).
     * Source: https://dev.mysql.com/doc/relnotes/mysql/8.1/en/news-8-1-0.html.
     *
     * @param non-empty-list<Domain> $domains
     * @param string $operation The operation as the server names it
     */
    public function doubles(array $domains, string $operation, GrammarRelease $release): Domain
    {
        $widest = $release === GrammarRelease::MySql8044 || (($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) && $operation !== 'UNION');
        $length = $widest ? max(array_map(static fn (Domain $domain): int => $domain->length, $domains)) : 23;
        $single = array_filter($domains, static fn (Domain $domain): bool => $domain->kind !== Kind::Integer && $domain->field !== Field::Float) === [];

        return $single ? new Domain(Kind::Double, Field::Float, $length, Domain::NOT_FIXED, false, null, [], Coercibility::Numeric) : Domain::double($length);
    }

    /**
     * Settles integers on the widest of them, as long as the longest.
     *
     * Signed and unsigned integers settle on the next wider type, signed; with an unsigned
     * BIGINT among them, on a DECIMAL one digit longer than the longest (verified on live 8.0,
     * 8.4 and 9.1 servers).
     *
     * @param non-empty-list<Domain> $domains
     */
    public function integers(array $domains): Domain
    {
        $widest = 0;
        $length = 0;
        $unsigned = true;
        $mixed = false;
        $big = false;
        foreach ($domains as $domain) {
            $widest = max($widest, (int) array_search($domain->field, self::INTEGERS, true));
            $length = max($length, $domain->length);
            $unsigned = $unsigned && $domain->unsigned;
            $mixed = $mixed || $domain->unsigned;
            $big = $big || ($domain->unsigned && $domain->field === Field::LongLong);
        }
        if (!$unsigned && $big) {
            return Domain::decimal($length, 0);
        }
        if (!$unsigned && $mixed) {
            $widest = min(4, $widest + 1);
        }

        return Domain::integer(self::INTEGERS[$widest], $length, $unsigned);
    }

    /**
     * Settles integers and decimals on a decimal that holds the integral and fractional digits of each.
     *
     * @param non-empty-list<Domain> $domains
     */
    public function decimals(array $domains): Domain
    {
        $integral = 0;
        $scale = 0;
        foreach ($domains as $domain) {
            [$precision, $digits] = (new Numbers())->digits($domain);
            $integral = max($integral, $precision - $digits);
            $scale = max($scale, $digits);
        }

        return Domain::decimal(min(65, $integral + $scale), $scale);
    }

    /**
     * Settles temporal values: one kind keeps it, a date and a datetime make a datetime, a time and another kind a string.
     *
     * @param non-empty-list<Domain> $domains
     */
    public function temporals(array $domains, string $operation, Derivation $derivation): ?Domain
    {
        $kinds = array_unique(array_map(static fn (Domain $domain): string => $domain->kind->name, $domains));
        $decimals = max(array_map(static fn (Domain $domain): int => $domain->decimals, $domains));
        if (count($kinds) === 1) {
            $field = count(array_unique(array_map(static fn (Domain $domain): int => $domain->field->value, $domains))) === 1 ? $domains[0]->field : Field::DateTime;

            return new Domain($domains[0]->kind, $field, max(array_map(static fn (Domain $domain): int => $domain->length, $domains)), $decimals);
        }
        if (!in_array(Kind::Time->name, $kinds, true)) {
            return new Domain(Kind::DateTime, Field::DateTime, 19 + ($decimals > 0 ? $decimals + 1 : 0), $decimals);
        }

        return $this->strings($domains, $operation, $derivation);
    }

    /**
     * Settles values on a string as long as the longest of them written as text: 22 characters for a DOUBLE, 23 for a FLOAT (verified on live 8.0 and 8.4 servers).
     *
     * @param non-empty-list<Domain> $domains
     */
    public function strings(array $domains, string $operation, Derivation $derivation): ?Domain
    {
        $settled = $this->collations->aggregate($domains, $operation, $derivation);
        if ($settled === null) {
            return null;
        }
        [$collation, $coercibility] = $settled;
        $length = 0;
        $blob = false;
        foreach ($domains as $domain) {
            $length = max($length, $domain->kind === Kind::String ? $domain->length : ($domain->kind === Kind::Double ? ($domain->field === Field::Float ? 23 : 22) : $domain->length));
            $blob = $blob || $domain->field === Field::Blob;
        }
        if ($collation->bytes() && count(array_filter($domains, static fn (Domain $domain): bool => $domain->kind === Kind::String)) === 0) {
            $collation = $this->collations->connection;
        }

        return Domain::string($length, $collation, $blob ? Field::Blob : Field::VarString, $coercibility);
    }
}
