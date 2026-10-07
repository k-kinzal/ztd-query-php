<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Result\FieldType;

/**
 * Aggregates the domains of values that one result takes: the results of CASE, IF, COALESCE, and the columns of a UNION.
 *
 * NULL operands take no part. Numbers aggregate to the widest kind (integer, decimal, double);
 * strings to a string as long as the longest in the aggregated collation; dates and times of one
 * kind stay that kind, and a date with a datetime is a datetime; any other mix is a string as
 * long as the longest text of a value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Aggregation
{
    /**
     * @param Collation $connection The connection collation, for numbers turned into strings
     */
    public function __construct(public readonly Collation $connection)
    {
    }

    /**
     * Answers the domain of a result that takes values of the domains.
     *
     * @param list<Domain> $domains
     *
     * @throws \MySqlMemory\Error\SqlError When strings of collations that do not mix meet
     */
    public function of(array $domains, string $operation): Domain
    {
        $nullable = false;
        $present = [];
        foreach ($domains as $domain) {
            $nullable = $nullable || $domain->nullable;
            if ($domain->kind !== Kind::Null) {
                $present[] = $domain;
            }
        }
        if ($present === []) {
            return Domain::null();
        }
        $kinds = array_values(array_unique(array_map(static fn (Domain $domain): string => Numeric::operand($domain)->name . ':' . $domain->kind->name, $present)));
        $all = static fn (callable $test): bool => count(array_filter($present, $test)) === count($present);
        $domain = match (true) {
            count($kinds) === 1 && $present[0]->kind === Kind::Integer => $this->integers($present),
            $all(static fn (Domain $domain): bool => $domain->kind === Kind::Integer || $domain->kind === Kind::Decimal) => $this->decimals($present),
            $all(static fn (Domain $domain): bool => $domain->kind->numeric() && $domain->kind !== Kind::Year && $domain->kind !== Kind::Bit) => Domain::double(23),
            $all(static fn (Domain $domain): bool => $domain->kind->temporal()) => $this->temporals($present),
            $all(static fn (Domain $domain): bool => $domain->kind === Kind::Year) => $present[0],
            default => $this->strings($present, $operation),
        };

        return $domain->withNullable($nullable || $domain->nullable);
    }

    /**
     * Aggregates integer domains.
     *
     * @param list<Domain> $domains
     */
    public function integers(array $domains): Domain
    {
        $order = [FieldType::Tiny, FieldType::Short, FieldType::Int24, FieldType::Long, FieldType::LongLong];
        $widest = 0;
        $length = 0;
        $unsigned = true;
        foreach ($domains as $domain) {
            $widest = max($widest, (int) array_search($domain->field, $order, true));
            $length = max($length, $domain->length);
            $unsigned = $unsigned && $domain->unsigned;
        }
        if (!$unsigned && count(array_filter($domains, static fn (Domain $domain): bool => $domain->unsigned)) > 0) {
            $widest = min(4, $widest + 1);
            $length++;
        }

        return Domain::integer($order[$widest], $length, $unsigned);
    }

    /**
     * Aggregates integer and decimal domains into a decimal.
     *
     * @param list<Domain> $domains
     */
    public function decimals(array $domains): Domain
    {
        $integer = 0;
        $scale = 0;
        foreach ($domains as $domain) {
            [$precision, $digits] = Numeric::digits($domain);
            $integer = max($integer, $precision - $digits);
            $scale = max($scale, $digits);
        }

        return Domain::decimal(min(65, $integer + $scale), $scale);
    }

    /**
     * Aggregates temporal domains.
     *
     * @param list<Domain> $domains
     */
    public function temporals(array $domains): Domain
    {
        $kinds = array_unique(array_map(static fn (Domain $domain): string => $domain->kind->name, $domains));
        $decimals = max(array_map(static fn (Domain $domain): int => $domain->decimals, $domains));
        if (count($kinds) === 1) {
            $field = count(array_unique(array_map(static fn (Domain $domain): int => $domain->field->value, $domains))) === 1 ? $domains[0]->field : FieldType::DateTime;

            return new Domain($domains[0]->kind, $field, max(array_map(static fn (Domain $domain): int => $domain->length, $domains)), $decimals);
        }
        if (!in_array(Kind::Time->name, $kinds, true)) {
            return new Domain(Kind::DateTime, FieldType::DateTime, 19 + ($decimals > 0 ? $decimals + 1 : 0), $decimals);
        }

        return $this->strings($domains, 'case');
    }

    /**
     * Aggregates domains into a string domain as long as the longest text of a value.
     *
     * @param list<Domain> $domains
     */
    public function strings(array $domains, string $operation): Domain
    {
        [$collation, $coercibility] = Collations::aggregate($domains, $operation, $this->connection);
        $length = 0;
        $blob = false;
        foreach ($domains as $domain) {
            $length = max($length, $domain->kind === Kind::String ? $domain->length : $this->textLength($domain));
            $blob = $blob || $domain->field === FieldType::Blob;
        }
        if ($collation === Collation::Binary && count(array_filter($domains, static fn (Domain $domain): bool => $domain->kind === Kind::String)) === 0) {
            $collation = $this->connection;
        }

        return Domain::string($length, $collation, $blob ? FieldType::Blob : FieldType::VarString)->withCollation($collation, $coercibility);
    }

    /**
     * Answers the length of the text of a value of a domain that is not a string.
     */
    public function textLength(Domain $domain): int
    {
        return match ($domain->kind) {
            Kind::Double => 23,
            Kind::Decimal => $domain->length,
            default => $domain->length,
        };
    }
}
