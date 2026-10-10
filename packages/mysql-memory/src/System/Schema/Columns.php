<?php

declare(strict_types=1);

namespace MySqlMemory\System\Schema;

use MySqlMemory\Command\Show\ColumnText;
use MySqlMemory\Command\Show\Keys;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Dictionary\View;
use MySqlMemory\System\Listed;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\SridAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;

/**
 * The rows of INFORMATION_SCHEMA.COLUMNS: one for each column of each table and view, in the order of the tables and of their columns.
 *
 * The type is written as SHOW COLUMNS writes it, and DATA_TYPE is its name. A string reports
 * its length in characters and in bytes, a TEXT or BLOB the bytes of its type for both; an
 * ENUM the length of its longest member and a SET of all its members. A number reports its
 * precision, the digits of the widest value of an integer type, and its scale: none for a
 * floating-point type declared without one or a BIT. A time, DATETIME or TIMESTAMP reports
 * its fractional digits (verified on a live 8.4.7 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-columns-table.html.
 *
 * @visibility MySqlMemory
 */
final class Columns implements SystemRows
{
    /**
     * The privileges a column lists for an account that holds all of them.
     */
    public const PRIVILEGES = 'select,insert,update,references';

    /**
     * Answers a row for each column of each table and view.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $rows = [];
        foreach (Listed::schemas($reading) as $schema) {
            foreach (Listed::of($schema, $reading) as $name => $object) {
                $table = ['TABLE_CATALOG' => 'def', 'TABLE_SCHEMA' => $schema->name, 'TABLE_NAME' => $name];
                if ($object instanceof SystemTable) {
                    foreach ($object->columns as $position => $column) {
                        $rows[] = $table + $this->system($column, $position, $schema->name, $reading);
                    }
                    continue;
                }
                $definition = $object instanceof View ? Listed::stored($object, $reading)->definition : $object->definition;
                foreach ($definition->columns as $position => $column) {
                    $rows[] = $table + $this->column($definition, $column, $position, $object instanceof StoredTable, $reading);
                }
            }
        }

        return $rows;
    }

    /**
     * Answers the columns of the row of a column of a table or a view.
     *
     * @param bool $keyed Whether the column belongs to a base table, whose keys it reports
     *
     * @return array<string, int|string|null>
     */
    public function column(TableDefinition $table, ColumnDefinition $column, int $position, bool $keyed, Reading $reading): array
    {
        $text = new ColumnText($reading->release);
        $domain = $column->domain;
        $written = $text->written($table, $column);
        $type = $text->type($domain, $written);
        $textual = $text->textual($domain);
        [$characters, $octets] = $this->lengths($domain->field, $domain->length, $domain->members, $textual ? $domain->collation->charset->maxLength : 1, $domain->kind === Kind::String && !$written instanceof Spatial);
        [$precision, $scale] = $this->precision($domain->field, $domain->length, $domain->decimals, $domain->unsigned, $domain->precision(), $type);

        return [
            'COLUMN_NAME' => $column->name,
            'ORDINAL_POSITION' => $position + 1,
            'COLUMN_DEFAULT' => $text->shownDefault($column),
            'IS_NULLABLE' => $column->nullable() ? 'YES' : 'NO',
            'DATA_TYPE' => $this->name($type),
            'CHARACTER_MAXIMUM_LENGTH' => $characters,
            'CHARACTER_OCTET_LENGTH' => $octets,
            'NUMERIC_PRECISION' => $precision,
            'NUMERIC_SCALE' => $scale,
            'DATETIME_PRECISION' => in_array($domain->field, [Field::Time, Field::DateTime, Field::Timestamp], true) ? $domain->decimals : null,
            'CHARACTER_SET_NAME' => $textual ? $domain->collation->charset->nameIn($reading->release) : null,
            'COLLATION_NAME' => $textual ? $domain->collation->nameIn($reading->release) : null,
            'COLUMN_TYPE' => $type,
            'COLUMN_KEY' => $keyed ? (new Keys())->role($table, $position) : '',
            'EXTRA' => $text->extra($column),
            'PRIVILEGES' => self::PRIVILEGES,
            'COLUMN_COMMENT' => $column->comment,
            'GENERATION_EXPRESSION' => $column->generated === null ? '' : $column->expression,
            'SRS_ID' => $written instanceof Spatial ? $this->srid($table, $column) : null,
        ];
    }

    /**
     * Answers the columns of the row of a column of a system table, as the catalog of the release lists it.
     *
     * @return array<string, int|string|null>
     */
    public function system(SystemColumn $column, int $position, string $schema, Reading $reading): array
    {
        $type = $column->columnType;
        $collation = $column->collation === null ? null : Collation::named($column->collation);
        $name = $this->name($type);
        $textual = $collation !== null;
        $width = preg_match('/\((\d+)/', $type, $match) === 1 ? (int) $match[1] : 0;
        $blobs = ['tinytext' => 255, 'text' => 65535, 'mediumtext' => 16777215, 'longtext' => 4294967295, 'tinyblob' => 255, 'blob' => 65535, 'mediumblob' => 16777215, 'longblob' => 4294967295];
        $members = in_array($name, ['enum', 'set'], true) ? \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables::members($type) : [];
        $characters = match (true) {
            isset($blobs[$name]) => $blobs[$name],
            $name === 'enum' => max(array_map('mb_strlen', $members === [] ? [''] : $members)),
            $name === 'set' => max(0, array_sum(array_map('mb_strlen', $members)) + count($members) - 1),
            in_array($name, ['char', 'varchar', 'binary', 'varbinary'], true) => $width,
            default => null,
        };
        $octets = $characters === null ? null : (isset($blobs[$name]) ? $characters : $characters * ($collation->charset->maxLength ?? 1));
        [$precision, $scale] = $this->numerics($type, $name, $width);

        return [
            'COLUMN_NAME' => $column->name,
            'ORDINAL_POSITION' => $position + 1,
            'COLUMN_DEFAULT' => $column->default,
            'IS_NULLABLE' => $column->listedNullable ? 'YES' : 'NO',
            'DATA_TYPE' => $name,
            'CHARACTER_MAXIMUM_LENGTH' => $characters,
            'CHARACTER_OCTET_LENGTH' => $octets,
            'NUMERIC_PRECISION' => $precision,
            'NUMERIC_SCALE' => $scale,
            'DATETIME_PRECISION' => in_array($name, ['time', 'datetime', 'timestamp'], true) ? $width : null,
            'CHARACTER_SET_NAME' => $collation?->charset->nameIn($reading->release),
            'COLLATION_NAME' => $textual ? $collation->nameIn($reading->release) : null,
            'COLUMN_TYPE' => $type,
            'COLUMN_KEY' => $column->key,
            'EXTRA' => $column->extra,
            'PRIVILEGES' => $schema === 'information_schema' ? 'select' : self::PRIVILEGES,
            'COLUMN_COMMENT' => $column->comment,
            'GENERATION_EXPRESSION' => '',
        ];
    }

    /**
     * Answers the precision and scale of a numeric column of a system table from its written type, or nulls for another type.
     *
     * @param string $name The name of the type
     * @param int $width The first number in the parentheses of the type, or 0
     *
     * @return array{int|null, int|null}
     */
    public function numerics(string $type, string $name, int $width): array
    {
        $integers = ['tinyint' => [3, 3], 'smallint' => [5, 5], 'mediumint' => [7, 8], 'int' => [10, 10], 'bigint' => [19, 20]];
        if (isset($integers[$name])) {
            return [$integers[$name][str_contains($type, 'unsigned') && $name === 'bigint' ? 1 : 0], 0];
        }

        return [
            in_array($name, ['decimal', 'float', 'double', 'bit'], true) ? ($width > 0 ? $width : ($name === 'double' ? 22 : 12)) : null,
            $name === 'decimal' ? (preg_match('/,(\d+)\)/', $type, $scale) === 1 ? (int) $scale[1] : 0) : null,
        ];
    }

    /**
     * Answers the name of a column type: the type written without its length, attributes and members.
     */
    public function name(string $type): string
    {
        return strtolower((string) preg_replace('/[ (].*\z/s', '', $type));
    }

    /**
     * Answers the length of a string column in characters and in bytes, or nulls for another type.
     *
     * @param list<string> $members The members of an ENUM or a SET
     * @param int $width The bytes of a character of its character set
     * @param bool $string Whether the column holds strings
     *
     * @return array{int|null, int|null}
     */
    public function lengths(Field $field, int $length, array $members, int $width, bool $string): array
    {
        if (!$string || $field === Field::Geometry) {
            return [null, null];
        }
        $characters = $field === Field::Enum ? max(array_map('mb_strlen', $members === [] ? [''] : $members)) : ($field === Field::Set ? max(0, array_sum(array_map('mb_strlen', $members)) + count($members) - 1) : $length);
        $blob = in_array($field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true);

        return [$characters, $blob ? $characters : $characters * $width];
    }

    /**
     * Answers the precision and scale of a numeric column, or nulls for another type.
     *
     * @param int $digits The precision of a decimal
     * @param string $type The type written, which tells whether a floating-point type was declared with a scale
     *
     * @return array{int|null, int|null}
     */
    public function precision(Field $field, int $length, int $decimals, bool $unsigned, int $digits, string $type): array
    {
        return match ($field) {
            Field::Tiny => [3, 0],
            Field::Short => [5, 0],
            Field::Int24 => [$unsigned ? 8 : 7, 0],
            Field::Long => [10, 0],
            Field::LongLong => [$unsigned ? 20 : 19, 0],
            Field::Decimal, Field::NewDecimal => [$digits, $decimals],
            Field::Float, Field::Double => [$length, str_contains($type, ',') ? $decimals : null],
            Field::Bit => [$length, null],
            Field::Null, Field::Timestamp, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate, Field::VarChar, Field::Vector, Field::Json, Field::Enum, Field::Set,
            Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => [null, null],
        };
    }

    /**
     * Answers the spatial reference system a spatial column is restricted to, or null for none.
     */
    public function srid(TableDefinition $table, ColumnDefinition $column): ?int
    {
        $specification = (new ColumnText())->element($table, $column)?->specification;
        foreach ($specification instanceof OrdinaryColumn ? $specification->attributes : [] as $attribute) {
            if ($attribute instanceof SridAttribute) {
                return (int) $attribute->srid->text;
            }
        }

        return null;
    }
}
