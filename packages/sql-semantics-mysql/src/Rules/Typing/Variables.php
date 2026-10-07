<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the type a user variable is read with, from the type of the value it holds.
 *
 * An integer reads as a BIGINT, a decimal as DECIMAL(65,30), a double as a double, and any
 * other value as a long blob of 16777215 characters in its collation; a variable that holds
 * NULL is a binary medium blob, and one never assigned a binary string of 65532 bytes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Variables
{
    /**
     * @param Settings $settings The session the variables belong to
     */
    public function __construct(public readonly Settings $settings)
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

        return $held === null ? Domain::string(65532, Collation::binary()) : $this->held($held);
    }

    /**
     * Resolves the read of a variable that holds a value of a type.
     */
    public function held(Domain $domain): Domain
    {
        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => Domain::integer(Field::LongLong, 21, $domain->unsigned),
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

        return Domain::string(16777215, $collation, $field);
    }
}
