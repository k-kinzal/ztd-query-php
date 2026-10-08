<?php

declare(strict_types=1);

namespace MySqlMemory\System\Program;

use MySqlMemory\Command\Show\ColumnText;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\System\Reading;
use MySqlMemory\System\Schema\Columns;
use MySqlMemory\Typing\Declared;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * The type of a parameter or of the value a stored function returns, as ROUTINES and PARAMETERS list it.
 *
 * The type is described as INFORMATION_SCHEMA.COLUMNS describes the type of a column: its name,
 * lengths, precision and scale, fractional digits, character set and collation, and the type
 * written in full as DTD_IDENTIFIER. A string declared without a character set has that of the
 * database of the routine.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-parameters-table.html.
 *
 * @visibility MySqlMemory
 */
final class Typed
{
    /**
     * Answers the columns that describe a type.
     *
     * @param TypeName $type The type as it is written
     * @param string|null $collation The collation written after it, or null
     *
     * @return array<string, int|string|null>
     */
    public static function columns(Routine $routine, TypeName $type, ?string $collation, Reading $reading): array
    {
        $written = $collation === null ? null : Collation::named($collation);
        $domain = (new Declared(Collation::named($routine->charsets[2]) ?? Collation::known('utf8mb4_0900_ai_ci'), $reading->release))->domain($type, $written);
        if ($written !== null && $domain->kind === Kind::String && !$domain->collation->bytes()) {
            $domain = $domain->withCollation($written, $domain->coercibility);
        }

        return self::described($domain, $type, $reading);
    }

    /**
     * Answers the columns that describe a type, from its domain.
     *
     * @return array<string, int|string|null>
     */
    public static function described(Domain $domain, ?TypeName $type, Reading $reading): array
    {
        $text = new ColumnText($reading->release);
        $columns = new Columns();
        $identifier = $text->type($domain, $type);
        $textual = $text->textual($domain);
        [$characters, $octets] = $columns->lengths($domain->field, $domain->length, $domain->members, $textual ? $domain->collation->charset->maxLength : 1, $domain->kind === Kind::String && $domain->field !== Field::Geometry);
        [$precision, $scale] = $columns->precision($domain->field, $domain->length, $domain->decimals, $domain->unsigned, $domain->precision(), $identifier);

        return [
            'DATA_TYPE' => $columns->name($identifier),
            'CHARACTER_MAXIMUM_LENGTH' => $characters,
            'CHARACTER_OCTET_LENGTH' => $octets,
            'NUMERIC_PRECISION' => $precision,
            'NUMERIC_SCALE' => $scale,
            'DATETIME_PRECISION' => in_array($domain->field, [Field::Time, Field::DateTime, Field::Timestamp], true) ? $domain->decimals : null,
            'CHARACTER_SET_NAME' => $textual ? $domain->collation->charset->nameIn($reading->release) : null,
            'COLLATION_NAME' => $textual ? $domain->collation->nameIn($reading->release) : null,
            'DTD_IDENTIFIER' => $identifier,
        ];
    }

    /**
     * Answers the routines of every database, by database and by name without regard to case.
     *
     * @return list<Routine>
     */
    public static function routines(Reading $reading): array
    {
        $routines = [];
        foreach ($reading->instance->dictionary->schemas as $schema) {
            array_push($routines, ...array_values($schema->procedures), ...array_values($schema->functions));
        }
        usort($routines, static fn (Routine $left, Routine $right): int => [$left->schema, strtolower($left->name), $left->statement instanceof CreateFunction ? 0 : 1] <=> [$right->schema, strtolower($right->name), $right->statement instanceof CreateFunction ? 0 : 1]);

        return $routines;
    }
}
