<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry;

/**
 * Resolves the type a user variable is read with, from the type of the value it holds.
 *
 * An integer reads as a BIGINT (logical width 20 in MySQL 5.6 and 5.7, and 21 from 8.0).
 * Legacy integer result metadata expands that width by the assignment character set;
 * arithmetic keeps the logical width. A decimal reads as DECIMAL(65,30)
 * (reported width 83 in MySQL 5.6 and 5.7, and 67 from 8.0), a double as a double, and any
 * other value as a blob in its collation, a temporal value in latin1: a medium blob of 16777215 bytes
 * in MySQL 5.6 and 5.7, and from MySQL 8.0 with a byte bound of 16777215 times
 * the character set's maximum width, a long blob when that passes 16777215 bytes. A
 * newly assigned untyped NULL is a binary medium blob. Later untyped NULL assignments
 * preserve an entry's previous type; typed NULL assignments change the type but retain
 * its assignment collation. An allocated, unexecuted entry is a NULL string in the
 * operand's allocation collation. One never allocated is a binary long
 * blob of 16777216 bytes in MySQL 5.6 and 5.7, and a binary string of 65532 bytes from 8.0
 * (verified through SQL on live servers and Testcontainers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Variables
{
    /**
     * @param Settings $settings The session the variables belong to
     * @param GrammarRelease $release The release the session runs
     */
    public function __construct(public readonly Settings $settings, public readonly GrammarRelease $release = GrammarRelease::MySql847)
    {
    }

    /**
     * Resolves the read of a user variable, or answers null when the session does not say what the variables hold.
     */
    public function read(string $name): ?Domain
    {
        $variables = $this->settings->userVariables;
        if ($variables === null) {
            return null;
        }
        $held = $variables[strtolower($name)] ?? null;
        if ($held instanceof UserVariableEntry) {
            return $this->held($held->domain, $held->collation);
        }

        return $held === null ? (in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? Domain::string(16777216, Collation::binary(), Field::LongBlob) : Domain::string(65532, Collation::binary())) : $this->held($held);
    }

    /**
     * Records the empty entry an expression assignment introduces after its value is resolved.
     *
     * The assigned value has not executed yet. Later occurrences therefore see an
     * allocated string entry, while the session snapshot remains unchanged.
     */
    public function introduce(string $name, \SqlSemantics\Construction\Derivation $derivation, ?Domain $operand = null): void
    {
        $name = strtolower($name);
        if ($this->settings->userVariables !== null && !isset($this->settings->userVariables[$name])) {
            $derivation->introducedVariables[$name] ??= new \SqlSemantics\Statement\Type\Known($this->text($operand === null ? $this->settings->connection : $this->allocation($operand)));
        }
    }

    /**
     * Resolves the collation of an empty entry allocated while preparing an assignment.
     * Numeric and temporal operands allocate in the connection collation; strings keep
     * their own, and an untyped NULL allocates binary. Verified through zero-row SELECT
     * assignments on MySQL 8.0.44, 8.4.7 and 9.1.0.
     */
    public function allocation(Domain $operand): Collation
    {
        return match ($operand->kind) {
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::Year, Kind::Bit, Kind::Date, Kind::Time, Kind::DateTime => $this->settings->connection,
            Kind::String => $operand->collation,
            Kind::Json => Collation::known('utf8mb4_bin'),
            Kind::Null => Collation::binary(),
        };
    }

    /**
     * Resolves the result of an assignment independently from a later read of the stored variable.
     */
    public function assigned(\SqlSemantics\Statement\Type\TypeFact $type): \SqlSemantics\Statement\Type\TypeFact
    {
        $domain = (new Precision())->domain($type);
        if ($domain === null && $type instanceof \SqlSemantics\Statement\Type\Known) {
            $domain = (new Declared($this->settings->connection, $this->release))->domain($type->descriptor);
        }

        return $domain === null ? $type : new \SqlSemantics\Statement\Type\Known($this->assignment($domain->value()));
    }

    /**
     * Gives an assignment its expression result type: integer, decimal, double or string.
     * Integer widths follow the operand; legacy metadata expands them by the connection
     * character set. MySQL 5.6 and 5.7 retain operand decimals,
     * floating widths and unsigned decimal widths, and give every result implicit
     * coercibility. From 8.0 floating results use DOUBLE's expression width.
     * Verified through PDO metadata on live servers and Testcontainers.
     */
    public function assignment(Domain $domain): Domain
    {
        $legacy = in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $result = match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => Domain::integer(Field::LongLong, $domain->length, $domain->unsigned),
            Kind::Decimal => Domain::decimal($domain->precision(), $domain->decimals, $legacy && $domain->unsigned),
            Kind::Double => Domain::double($legacy ? $domain->length : 23),
            Kind::Null => Domain::string(0, Collation::binary()),
            Kind::Date, Kind::Time, Kind::DateTime => Domain::string($domain->length, $this->settings->connection),
            Kind::Json => $this->assignmentText(Domain::string($domain->length, Collation::known('utf8mb4_bin'))),
            Kind::String => $this->assignmentText($domain),
        };

        $decimals = match ($domain->kind) {
            Kind::Json => Domain::NOT_FIXED,
            Kind::String => $domain->displayDecimals ?? ($domain->coercibility === Coercibility::Implicit ? Domain::NOT_FIXED : $domain->decimals),
            Kind::Date, Kind::Time, Kind::DateTime, Kind::Null, Kind::Double, Kind::Decimal, Kind::Integer, Kind::Year, Kind::Bit => $domain->decimals,
        };

        return $legacy ? new Domain($result->kind, $result->field, $result->length, $decimals, $result->unsigned, $result->collation, coercibility: Coercibility::Implicit, display: $this->assignmentDisplay($domain), displayDecimals: $result->kind === Kind::String && $decimals < Domain::NOT_FIXED ? $decimals : null) : $result;
    }

    /**
     * Separates a legacy assignment's reported numeric width from its logical precision.
     */
    public function assignmentDisplay(Domain $domain): ?int
    {
        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => $this->settings->connection->charset->maxLength > 1 ? $domain->length * $this->settings->connection->charset->maxLength : null,
            Kind::Decimal => $domain->display,
            Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Json, Kind::Null => null,
        };
    }

    /**
     * Resolves an assignment's text width and field. A result beyond VARCHAR's byte bound
     * uses a BLOB field whose bound counts bytes, including the character-set expansion.
     */
    public function assignmentText(Domain $domain): Domain
    {
        $width = $domain->collation->charset->maxLength;
        $bound = in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? intdiv(4294967295, $width) * $width : 4294967295;
        $bytes = min($bound, $domain->length * $width);
        if ($bytes <= 65535) {
            return Domain::string($domain->length, $domain->collation);
        }

        return Domain::string($bytes, $domain->collation, $bytes <= 16777215 ? Field::MediumBlob : Field::LongBlob);
    }

    /**
     * Infers an absent variable's operand type from another result branch.
     *
     * Strings use the branch collation and the VARCHAR byte limit. Temporal
     * operands become latin1 strings: DATE has ten characters, TIME at least
     * fifteen, and DATETIME twenty-six. Verified through SQL on MySQL 8.0 and 8.4.
     */
    public function inferred(Domain $domain): Domain
    {
        if ($domain->kind->numeric()) {
            return $this->held($domain);
        }

        return match ($domain->kind) {
            Kind::String, Kind::Json => Domain::string(intdiv(65535, $domain->collation->charset->maxLength), $domain->collation),
            Kind::Null => Domain::string(65535, Collation::binary()),
            Kind::Date => Domain::string(10, Collation::known('latin1_swedish_ci')),
            Kind::Time => Domain::string(15, Collation::known('latin1_swedish_ci')),
            Kind::DateTime => Domain::string(26, Collation::known('latin1_swedish_ci')),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::Year, Kind::Bit => $domain,
        };
    }

    /**
     * Resolves the read of a variable that holds a value of a type.
     */
    public function held(Domain $domain, ?Collation $assignment = null): Domain
    {
        $legacy = in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $integerWidth = $legacy ? 20 : 21;
        $charset = ($assignment ?? $this->settings->connection)->charset;
        $integerDisplay = $legacy && $charset->maxLength > 1 ? 20 * $charset->maxLength : null;

        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => new Domain(Kind::Integer, Field::LongLong, $integerWidth, unsigned: $domain->unsigned, coercibility: Coercibility::Numeric, display: $integerDisplay),
            Kind::Decimal => new Domain(Kind::Decimal, Field::NewDecimal, 67, 30, coercibility: Coercibility::Numeric, display: $legacy ? 83 : null),
            Kind::Double => Domain::double(23),
            Kind::Null => Domain::string(16777215, Collation::binary(), Field::MediumBlob),
            Kind::String, Kind::Json, Kind::Date, Kind::Time, Kind::DateTime => $this->text($domain->kind === Kind::String || $domain->kind === Kind::Json ? $domain->collation : Collation::known('latin1_swedish_ci')),
        };
    }

    /**
     * Answers the blob a string variable reads as: 16777215 bytes in legacy releases,
     * and the maximum bytes of 16777215 characters from MySQL 8.0.
     */
    public function text(Collation $collation): Domain
    {
        $legacy = in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $field = !$legacy && $collation->charset->maxLength > 1 ? Field::LongBlob : Field::MediumBlob;

        return Domain::string($legacy ? 16777215 : 16777215 * $collation->charset->maxLength, $collation, $field);
    }
}
