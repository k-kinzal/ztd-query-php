<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
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
            $all(static fn (Domain $domain): bool => $domain->kind->numeric() && $domain->kind !== Kind::Year && $domain->kind !== Kind::Bit) => Domain::double(23),
            $all(static fn (Domain $domain): bool => $domain->kind->temporal()) => $this->temporals($present, $operation, $derivation),
            $all(static fn (Domain $domain): bool => $domain->kind === Kind::Year) => $present[0],
            default => $this->strings($present, $operation, $derivation),
        };
    }

    /**
     * Settles integers on the widest of them.
     *
     * @param non-empty-list<Domain> $domains
     */
    public function integers(array $domains): Domain
    {
        $widest = 0;
        $length = 0;
        $unsigned = true;
        $mixed = false;
        foreach ($domains as $domain) {
            $widest = max($widest, (int) array_search($domain->field, self::INTEGERS, true));
            $length = max($length, $domain->length);
            $unsigned = $unsigned && $domain->unsigned;
            $mixed = $mixed || $domain->unsigned;
        }
        if (!$unsigned && $mixed) {
            $widest = min(4, $widest + 1);
            $length++;
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
     * Settles values on a string as long as the longest of them written as text.
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
            $length = max($length, $domain->kind === Kind::String ? $domain->length : ($domain->kind === Kind::Double ? 23 : $domain->length));
            $blob = $blob || $domain->field === Field::Blob;
        }
        if ($collation->bytes() && count(array_filter($domains, static fn (Domain $domain): bool => $domain->kind === Kind::String)) === 0) {
            $collation = $this->collations->connection;
        }

        return Domain::string($length, $collation, $blob ? Field::Blob : Field::VarString, $coercibility);
    }
}
