<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
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
        $grammar = $derivation->context->profile->grammar;
        if ($grammar !== GrammarRelease::MySql5651 && $grammar !== GrammarRelease::MySql5744) {
            $integral = static fn (Domain $domain): bool => $domain->kind === Kind::Integer || $domain->kind === Kind::Year || $domain->kind === Kind::Bit;

            return match (true) {
                $all(static fn (Domain $domain): bool => $domain->kind === Kind::Json) => $operation === 'UNION' ? $present[0] : new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'), [], $present[0]->coercibility),
                $all(static fn (Domain $domain): bool => $domain->kind === Kind::Year) => $present[0],
                $all(static fn (Domain $domain): bool => $domain->kind === Kind::Bit) => $this->strings($present, $operation, $derivation),
                $all($integral) => $this->integers($present),
                $all(static fn (Domain $domain): bool => $integral($domain) || $domain->kind === Kind::Decimal) => $this->decimals($present),
                $all(static fn (Domain $domain): bool => $domain->kind->numeric()) => $this->doubles($present, $operation, $grammar),
                $all(static fn (Domain $domain): bool => $domain->kind->temporal()) => $this->temporals($present, $operation, $derivation),
                default => $this->strings($present, $operation, $derivation),
            };
        }

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
     * FLOAT values with integers settle on a FLOAT, and with any other number on a DOUBLE; from
     * MySQL 8.0 only the integers up to a MEDIUMINT and a YEAR keep it a FLOAT, and a BIGINT that
     * follows a FLOAT, as the server settles the values from the first (verified on live 5.7, 8.0
     * and 8.4 servers). The double is 23 characters wide. Before MySQL 8.1, which simplified how the type of several
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
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $single = true;
        $floated = false;
        foreach ($domains as $domain) {
            $floated = $floated || $domain->field === Field::Float;
            $exact = $legacy ? $domain->kind === Kind::Integer : $domain->kind === Kind::Year || ($domain->kind === Kind::Integer && (in_array($domain->field, [Field::Tiny, Field::Short, Field::Int24], true) || ($floated && $domain->field === Field::LongLong)));
            $single = $single && ($domain->field === Field::Float || $exact);
        }

        return $single ? new Domain(Kind::Double, Field::Float, $length, Domain::NOT_FIXED, false, null, [], Coercibility::Numeric) : Domain::double($length);
    }

    /**
     * Settles integers on the widest of them, as long as the longest.
     *
     * Signed and unsigned integers settle on the next wider type than the widest unsigned one,
     * signed, unless a signed one is wider still, which they settle on; with an unsigned BIGINT
     * among them, on a DECIMAL of the digits of the longest. A BIT takes part as an unsigned
     * BIGINT as long as its bits, and a YEAR as an integer of four characters of either sign
     * (verified on live 8.0, 8.4 and 9.1 servers).
     *
     * @param non-empty-list<Domain> $domains
     */
    public function integers(array $domains): Domain
    {
        $signed = -1;
        $unsigned = -1;
        $length = 0;
        $digits = 0;
        foreach ($domains as $domain) {
            $length = max($length, $domain->length);
            if ($domain->kind === Kind::Year) {
                $digits = max($digits, $domain->length);
                continue;
            }
            $rank = $domain->kind === Kind::Bit ? 4 : (int) array_search($domain->field, self::INTEGERS, true);
            $unsigned = $domain->unsigned || $domain->kind === Kind::Bit ? max($unsigned, $rank) : $unsigned;
            $signed = $domain->unsigned || $domain->kind === Kind::Bit ? $signed : max($signed, $rank);
            $digits = max($digits, $domain->length - ($domain->unsigned || $domain->kind === Kind::Bit ? 0 : 1));
        }
        if ($signed >= 0 && $unsigned === 4) {
            return Domain::decimal($digits, 0);
        }
        if ($signed >= 0 && $unsigned >= $signed) {
            return Domain::integer(self::INTEGERS[$unsigned + 1], $length);
        }

        return Domain::integer(self::INTEGERS[max(0, $signed, $unsigned)] ?? Field::LongLong, $length, $signed < 0);
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
     * Settles temporal values: one kind keeps it, and different kinds make a datetime.
     *
     * From MySQL 8.0 the result is text in the connection collation, which counts the bytes of
     * its characters, and a time takes the current date; MySQL 5.6 and 5.7 keep it binary but
     * for a value already in a character set, as a column of a merged derived table holds it,
     * and settle a time and another kind on a string (verified on live 5.7, 8.0, 8.4 and 9.1
     * servers).
     *
     * @param non-empty-list<Domain> $domains
     */
    public function temporals(array $domains, string $operation, Derivation $derivation): ?Domain
    {
        $kinds = array_unique(array_map(static fn (Domain $domain): string => $domain->kind->name, $domains));
        $decimals = max(array_map(static fn (Domain $domain): int => $domain->decimals, $domains));
        $texts = array_values(array_filter($domains, static fn (Domain $domain): bool => !$domain->collation->bytes()));
        $grammar = $derivation->context->profile->grammar;
        $legacy = $grammar === GrammarRelease::MySql5651 || $grammar === GrammarRelease::MySql5744;
        $collation = $legacy ? ($texts === [] ? null : $texts[0]->collation) : $this->collations->connection;
        if (count($kinds) === 1) {
            $field = count(array_unique(array_map(static fn (Domain $domain): int => $domain->field->value, $domains))) === 1 ? $domains[0]->field : Field::DateTime;

            return new Domain($domains[0]->kind, $field, max(array_map(static fn (Domain $domain): int => $domain->length, $domains)), $decimals, false, $collation);
        }
        if (!$legacy || !in_array(Kind::Time->name, $kinds, true)) {
            return new Domain(Kind::DateTime, Field::DateTime, 19 + ($decimals > 0 ? $decimals + 1 : 0), $decimals, false, $collation);
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
        $grammar = $derivation->context->profile->grammar;
        if ($grammar !== GrammarRelease::MySql5651 && $grammar !== GrammarRelease::MySql5744) {
            return $this->texts($domains, $operation, $derivation);
        }
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

        return $blob || $operation === 'UNION' ? Domain::string($length, $collation, $blob ? Field::Blob : Field::VarString, $coercibility) : $this->sized($length, $collation, $coercibility);
    }

    /**
     * Settles values on a string from MySQL 8.0: as long as the longest of them written as text.
     *
     * A BIT takes part as a binary string as long as its bits, and a JSON value as a utf8mb4_bin
     * string. In a binary result a string counts
     * the bytes of its characters; in another a TEXT does, and any other string its characters. A
     * FLOAT column is written in its own length and any other double in 22 characters. With a
     * JSON value among values of other types, the result is a LONGTEXT, or a LONGBLOB when binary.
     * MySQL 8.0, before the aggregation of types was simplified in 8.1 (Bug #34847836), keeps the
     * most decimals of the values when none is a string, and makes a VARCHAR of JSON values among
     * others but a TEXT. The columns of
     * a set operation are settled so too, but that a TEXT counts its characters
     * until the temporary table holds it (Materialization), JSON values among others make a TEXT
     * and JSON values alone keep their type (verified on live 8.0, 8.4 and 9.1 servers).
     *
     * @param non-empty-list<Domain> $domains
     */
    public function texts(array $domains, string $operation, Derivation $derivation): ?Domain
    {
        $operands = array_map(static fn (Domain $domain): Domain => match ($domain->kind) {
            Kind::Bit => Domain::string($domain->length, Collation::binary()),
            Kind::Json => new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'), [], $domain->coercibility),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Null => $domain,
        }, $domains);
        $settled = $this->collations->aggregate($operands, $operation, $derivation);
        if ($settled === null) {
            return null;
        }
        [$collation, $coercibility] = $settled;
        if ($collation->bytes() && count(array_filter($operands, static fn (Domain $domain): bool => $domain->kind === Kind::String)) === 0) {
            $collation = $this->collations->connection;
        }
        $united = $operation === 'UNION';
        $early = $derivation->context->profile->grammar === GrammarRelease::MySql8044 && !$united;
        $blob = array_filter($operands, static fn (Domain $domain): bool => $domain->field === Field::Blob) !== [];
        if (array_filter($operands, static fn (Domain $domain): bool => $domain->kind === Kind::Json) !== []) {
            return Domain::string(4294967295, $collation, $united ? Field::Blob : ($early && !$blob ? Field::VarString : Field::LongBlob), $coercibility);
        }
        $length = $this->textLength($operands, $collation, $united);
        $written = array_filter($domains, static fn (Domain $domain): bool => $domain->kind === Kind::String) !== [];
        $decimals = $early && !$written ? min(Domain::NOT_FIXED, max(array_map(static fn (Domain $domain): int => $domain->decimals, $domains))) : Domain::NOT_FIXED;

        return !$blob && !$united && $written ? $this->sized($length, $collation, $coercibility, $early) : new Domain(Kind::String, $blob ? Field::Blob : Field::VarString, min(4294967295, $length), $decimals, false, $collation, [], $coercibility);
    }

    /**
     * Selects the field type for a long string result outside a set operation.
     *
     * MySQL 8.0 retains VARCHAR for long branches with its earlier width rule.
     * Other supported releases choose a medium or long BLOB.
     * Verified through COALESCE over user variables.
     */
    public function sized(int $length, Collation $collation, Coercibility $coercibility, bool $early = false): Domain
    {
        $bytes = $length * $collation->charset->maxLength;
        if ($early && $bytes > 65535) {
            return Domain::string(intdiv($length, $collation->charset->maxLength), $collation, Field::VarString, $coercibility);
        }

        $field = $bytes <= 65535 ? Field::VarString : ($bytes <= 16777215 ? Field::MediumBlob : Field::LongBlob);

        return Domain::string(min(4294967295, $length), $collation, $field, $coercibility);
    }

    /**
     * Answers the length of the longest value written as text, from MySQL 8.0.
     *
     * In a binary result a string counts the bytes of its characters; outside a set operation a
     * TEXT does too, and any other string counts its characters. A FLOAT is written in its own
     * length and any other double in 22 characters; any other value in its length.
     *
     * @param non-empty-list<Domain> $operands The values, a BIT already a binary string and a JSON value a utf8mb4_bin string
     * @param bool $united Whether the values are the columns of a set operation
     */
    public function textLength(array $operands, Collation $collation, bool $united): int
    {
        $length = 0;
        foreach ($operands as $domain) {
            $length = max($length, match (true) {
                $domain->kind === Kind::String => $collation->bytes() || ($domain->field === Field::Blob && !$united) ? $domain->length * $domain->collation->charset->maxLength : $domain->length,
                $domain->kind === Kind::Double => $domain->field === Field::Float ? $domain->length : 22,
                default => $domain->length,
            });
        }

        return $length;
    }
}
