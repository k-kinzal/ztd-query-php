<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the types of string conversions: COLLATE, BINARY, CONVERT … USING and CHAR.
 *
 * A value that is not a string is first written as text in the connection character set; its
 * length is its display length, and 22 for a double without fixed decimals. COLLATE keeps the
 * character set and gives the collation explicit coercibility; BINARY makes the bytes a binary
 * string; CONVERT gives the default collation of the target character set.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collate.html,
 * https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Texts
{
    /**
     * @param Settings $settings The session the conversions are resolved in
     */
    public function __construct(public readonly Settings $settings)
    {
    }

    /**
     * Answers the length in characters of a value written as text.
     */
    public function length(Domain $domain): int
    {
        return match ($domain->kind) {
            Kind::Double => $domain->decimals < Domain::NOT_FIXED ? $domain->length : 22,
            Kind::Null => 0,
            Kind::Integer, Kind::Decimal, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit => $domain->length,
        };
    }

    /**
     * Resolves `expr COLLATE name`, reporting a collation that is unknown or of another character set.
     */
    public function collated(Domain $operand, string $name, Derivation $derivation): ?Domain
    {
        $collation = Collation::named($name);
        if ($collation === null) {
            $derivation->report(new UnknownCollation($name));

            return null;
        }
        $charset = $operand->kind === Kind::String ? $operand->collation->charset : $this->settings->connection->charset;
        if ($collation->charset !== $charset) {
            $derivation->report(new CollationMismatch($collation->name, $charset->name));

            return null;
        }
        $field = $operand->field === Field::Blob ? Field::Blob : Field::VarString;

        return Domain::string($operand->kind === Kind::String ? $operand->length : $this->length($operand), $collation, $field, Coercibility::Explicit);
    }

    /**
     * Resolves `BINARY expr`.
     */
    public function binary(Domain $operand): Domain
    {
        $length = $operand->kind === Kind::String ? $operand->length * $operand->collation->charset->maxLength : $this->length($operand);

        return Domain::string($length, Collation::binary(), $operand->field === Field::Blob ? Field::Blob : Field::VarString);
    }

    /**
     * Resolves `CONVERT(expr USING charset)`, reporting a character set that is unknown; a string longer than 65535 bytes is a MEDIUMBLOB or LONGBLOB (verified on a live 8.4 server).
     */
    public function converted(Domain $operand, string $name, Derivation $derivation): ?Domain
    {
        $charset = Charset::named($name);
        if ($charset === null) {
            $derivation->report(new UnknownCharset($name));

            return null;
        }
        $length = $operand->kind === Kind::String ? $operand->length : $this->length($operand);

        return (new Builtin\FormatResults())->sized($length, $charset->defaultCollation($derivation->context->profile->grammar), Coercibility::Implicit);
    }

    /**
     * Resolves CHAR(n, … [USING charset]): four characters for each code, binary without USING, reporting an unknown character set.
     */
    public function character(int $codes, ?string $charset, Derivation $derivation): ?Domain
    {
        if ($charset === null) {
            return Domain::string($codes * 4, Collation::binary(), Field::VarString, Coercibility::Coercible);
        }
        $found = Charset::named($charset);
        if ($found === null) {
            $derivation->report(new UnknownCharset($charset));

            return null;
        }

        return Domain::string($codes * 4, $found->defaultCollation($derivation->context->profile->grammar), Field::VarString, Coercibility::Coercible);
    }

}
