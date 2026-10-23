<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Catalog;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The tables of the system databases of one release, read from the catalog generated from a server of the release.
 *
 * The information_schema, mysql and performance_schema databases hold them. A statement that
 * reads one is bound against its declaration, which this catalog supplies with the type each
 * column has when it is read. The name of information_schema and the names of its tables are
 * compared without regard to case, whatever lower_case_table_names says; the names of the
 * other system databases and of their tables follow the setting.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-introduction.html,
 * https://dev.mysql.com/doc/refman/8.4/en/identifier-case-sensitivity.html.
 *
 * @visibility public
 * @example Finding a table of INFORMATION_SCHEMA by any case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables::of(\SqlSemantics\Contract\GrammarRelease::MySql847)->find('INFORMATION_SCHEMA', 'schemata')?->name // => 'SCHEMATA'
 */
final class SystemTables
{
    use Snapshot;

    /**
     * The database whose name and table names are compared without regard to case.
     */
    public const INFORMATION_SCHEMA = 'information_schema';

    /**
     * @var array<string, self>
     */
    private static array $releases = [];

    /**
     * @var array<string, SystemTable> The tables by `database.name`, the name of an INFORMATION_SCHEMA table in lower case
     */
    private array $index = [];

    /**
     * @param list<SystemTable> $tables The tables, by database and name
     */
    public function __construct(public readonly array $tables)
    {
        foreach ($tables as $table) {
            $this->index[self::key($table->schema, $table->name)] = $table;
        }
    }

    /**
     * Answers the system tables of a release: those of the catalog of its series, else of the latest series of its major version.
     */
    public static function of(GrammarRelease $release): self
    {
        if (!isset(self::$releases[$release->value])) {
            $directory = dirname(__DIR__, 4) . '/resources/system-tables/';
            $files = glob($directory . substr($release->value, 0, 9) . '*.php');
            $major = glob($directory . substr($release->value, 0, 7) . '*.php');
            $file = is_array($files) && $files !== [] ? $files[0] : (is_array($major) && $major !== [] ? $major[count($major) - 1] : $directory . GrammarRelease::MySql847->value . '.php');
            /** @var list<array{string, string, string, string|null, int|null, string|null, string|null, string, string, list<array{string, string, string, string, int, int, int, int, string, int, string, string, string|null, string, string, string, string|null}>}> $entries */
            $entries = require $file;
            $profile = new LanguageProfile($release);
            $tables = [];
            foreach ($entries as [$schema, $name, $type, $engine, $version, $format, $collation, $options, $comment, $columns]) {
                $read = [];
                foreach ($columns as [$column, $originalName, $originalTable, $database, $code, $length, $decimals, $flags, $columnCollation, $coercibility, $columnType, $listedNullable, $default, $key, $extra, $columnComment, $listedCollation]) {
                    $read[] = new SystemColumn($column, self::domain($code, $length, $decimals, $flags, $columnCollation, $coercibility, $columnType), ($flags & 1) === 0, $flags, $originalName, $originalTable, $database, $columnType, $default, $key, $extra, $columnComment, $listedCollation, $listedNullable === 'YES');
                }
                $declaration = new Table(
                    new QualifiedName(new Name($name), new Name($schema)),
                    $profile,
                    array_map(static fn (SystemColumn $column): Column => new Column(new Name($column->name), $column->domain, $column->nullable ? Nullability::Nullable : Nullability::NotNull), $read),
                    [],
                    true,
                    $type === 'BASE TABLE' ? RelationKind::BaseTable : RelationKind::View,
                    [],
                    [],
                );
                $tables[] = new SystemTable($schema, $name, $type, $engine, $version, $format, $collation, $options, $comment, $read, $declaration);
            }
            self::$releases[$release->value] = new self($tables);
        }

        return self::$releases[$release->value];
    }

    /**
     * Answers the key a table is found under: the name of an INFORMATION_SCHEMA table in lower case.
     */
    public static function key(string $schema, string $name): string
    {
        return strcasecmp($schema, self::INFORMATION_SCHEMA) === 0 ? self::INFORMATION_SCHEMA . '.' . strtolower($name) : $schema . '.' . $name;
    }

    /**
     * Builds the type of a read of a column from the metadata a catalog entry records.
     *
     * @param int $code The field code a result reports
     * @param int $length The length in characters, or in bytes for a binary value
     * @param int $decimals The decimals a result reports
     * @param int $flags The column definition flags a result reports, which mark an ENUM or a SET
     * @param string $collation The collation of the value
     * @param int $coercibility The coercibility of the collation
     * @param string $columnType The column type, which lists the members of an ENUM or a SET
     */
    public static function domain(int $code, int $length, int $decimals, int $flags, string $collation, int $coercibility, string $columnType): Domain
    {
        $field = Field::tryFrom($code) ?? Field::VarString;
        $unsigned = ($flags & 32) !== 0;
        $text = Collation::named($collation) ?? Collation::binary();
        $holds = Coercibility::tryFrom($coercibility) ?? Coercibility::Implicit;
        if (($flags & 256) !== 0 || ($flags & 2048) !== 0) {
            return new Domain(Kind::String, ($flags & 256) !== 0 ? Field::Enum : Field::Set, $length, $decimals, false, $text, self::members($columnType), $holds);
        }

        return match ($field) {
            Field::Tiny, Field::Short, Field::Int24, Field::Long, Field::LongLong => new Domain(Kind::Integer, $field, $length, 0, $unsigned, null, [], Coercibility::Numeric),
            Field::Decimal, Field::NewDecimal => new Domain(Kind::Decimal, Field::NewDecimal, $length, $decimals, $unsigned, null, [], Coercibility::Numeric),
            Field::Float, Field::Double => new Domain(Kind::Double, $field, $length, $decimals, $unsigned, null, [], Coercibility::Numeric),
            Field::Null => Domain::null(),
            Field::Date, Field::NewDate => new Domain(Kind::Date, Field::Date, $length, $decimals, false, $text, [], $holds),
            Field::Time => new Domain(Kind::Time, $field, $length, $decimals, false, $text, [], $holds),
            Field::DateTime, Field::Timestamp => new Domain(Kind::DateTime, $field, $length, $decimals, false, $text, [], $holds),
            Field::Year => new Domain(Kind::Year, $field, $length, 0, true, null, [], Coercibility::Numeric),
            Field::Json => new Domain(Kind::Json, $field, $length, $decimals, false, $text->bytes() ? null : $text, [], $holds),
            Field::Bit => new Domain(Kind::Bit, $field, $length, 0, true, null, [], Coercibility::Numeric),
            Field::Enum, Field::Set, Field::VarChar, Field::Vector, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => new Domain(Kind::String, $field, $length, $decimals, false, $text, [], $holds),
        };
    }

    /**
     * Answers the members an ENUM or SET column type lists, in their order.
     *
     * @return list<string>
     */
    public static function members(string $columnType): array
    {
        preg_match_all("/'((?:[^']|'')*)'/", $columnType, $matches);

        return array_map(static fn (string $member): string => str_replace("''", "'", $member), $matches[1]);
    }

    /**
     * Finds a table by database and name, or answers null; INFORMATION_SCHEMA and its tables are named in any case.
     */
    public function find(string $schema, string $name): ?SystemTable
    {
        return $this->index[self::key($schema, $name)] ?? null;
    }

    /**
     * Answers the declarations of the tables, for binding statements that read them.
     *
     * @return list<Table>
     */
    public function declarations(): array
    {
        return array_map(static fn (SystemTable $table): Table => $table->declaration, $this->tables);
    }
}
