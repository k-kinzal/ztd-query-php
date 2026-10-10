<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the type a user variable is read with, from the type of the value it holds.
 *
 * An integer reads as a BIGINT (width 20 in MySQL 5.6 and 5.7, and 21 from 8.0), a decimal as DECIMAL(65,30), a double as a double, and any
 * other value as a blob in its collation, a temporal value in latin1: of 16777215 characters
 * in MySQL 5.6 and 5.7, and from MySQL 8.0 as long as 16777215 characters of the most bytes
 * of the character set count in its fewest, a long blob when that passes 16777215 bytes. A
 * variable that holds NULL is a binary medium blob. One never assigned is a binary long
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

        return $held === null ? (in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? Domain::string(16777216, Collation::binary(), Field::LongBlob) : Domain::string(65532, Collation::binary())) : $this->held($held);
    }

    /**
     * Records the empty entry an expression assignment introduces after its value is resolved.
     *
     * The assigned value has not executed yet. Later occurrences therefore see an
     * allocated string entry, while the session snapshot remains unchanged.
     */
    public function introduce(string $name, \SqlSemantics\Construction\Derivation $derivation): void
    {
        $name = strtolower($name);
        if ($this->settings->userVariables !== null && !isset($this->settings->userVariables[$name])) {
            $derivation->introducedVariables[$name] ??= new \SqlSemantics\Statement\Type\Known($this->text($this->settings->connection));
        }
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
            Kind::String => Domain::string(intdiv(65535, $domain->collation->charset->maxLength), $domain->collation),
            Kind::Date => Domain::string(10, Collation::known('latin1_swedish_ci')),
            Kind::Time => Domain::string(15, Collation::known('latin1_swedish_ci')),
            Kind::DateTime => Domain::string(26, Collation::known('latin1_swedish_ci')),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => $domain,
        };
    }

    /**
     * Resolves the read of a variable that holds a value of a type.
     */
    public function held(Domain $domain): Domain
    {
        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => Domain::integer(Field::LongLong, in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? 20 : 21, $domain->unsigned),
            Kind::Decimal => Domain::decimal(65, 30),
            Kind::Double => Domain::double(23),
            Kind::Null => Domain::string(16777215, Collation::binary(), Field::MediumBlob),
            Kind::String, Kind::Json, Kind::Date, Kind::Time, Kind::DateTime => $this->text($domain->kind === Kind::String || $domain->kind === Kind::Json ? $domain->collation : Collation::known('latin1_swedish_ci')),
        };
    }

    /**
     * Answers the long blob of 16777215 characters a string variable reads as.
     */
    public function text(Collation $collation): Domain
    {
        $field = 16777215 * $collation->charset->maxLength > 16777215 ? Field::LongBlob : Field::MediumBlob;
        $legacy = in_array($this->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);

        return Domain::string($legacy ? 16777215 : intdiv(16777215 * $collation->charset->maxLength, $collation->charset->minLength()), $collation, $field);
    }
}
