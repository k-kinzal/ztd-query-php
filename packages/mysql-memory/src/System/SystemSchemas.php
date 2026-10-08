<?php

declare(strict_types=1);

namespace MySqlMemory\System;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Instance;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Storage\Heap;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Declaration\Table;

/**
 * The tables of the system databases information_schema, mysql and performance_schema, as views over the state of the server.
 *
 * Each table has the columns of the emulated release, typed as a result of the release reports
 * them. Its rows are computed from the dictionary, the accounts, the variables and the sessions
 * of the server each time a statement reads it; a table without a source of rows here is read
 * empty. In MySQL 8.0 and later INFORMATION_SCHEMA is a set of views over the data dictionary,
 * so a column of one is named as the view names it, and one the view computes rather than reads
 * names no database in a result; in 5.6 and 5.7 its tables are temporary tables.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema.html,
 * https://dev.mysql.com/doc/refman/8.4/en/data-dictionary-information-schema.html.
 *
 * @visibility MySqlMemory
 */
final class SystemSchemas
{
    /**
     * The sources of the rows of the tables that have them, by `database.name`, the name of an INFORMATION_SCHEMA table in lower case.
     */
    public const SOURCES = [
        'information_schema.schemata' => Schema\Schemata::class,
        'information_schema.tables' => Schema\Tables::class,
        'information_schema.columns' => Schema\Columns::class,
        'information_schema.statistics' => Schema\Statistics::class,
        'information_schema.key_column_usage' => Schema\KeyColumnUsage::class,
        'information_schema.table_constraints' => Schema\TableConstraints::class,
        'information_schema.referential_constraints' => Schema\ReferentialConstraints::class,
        'information_schema.check_constraints' => Schema\CheckConstraints::class,
        'information_schema.views' => Schema\Views::class,
        'information_schema.processlist' => Server\Processlist::class,
        'performance_schema.processlist' => Server\Processlist::class,
        'information_schema.routines' => Program\Routines::class,
        'information_schema.parameters' => Program\Parameters::class,
        'information_schema.triggers' => Program\Triggers::class,
        'information_schema.events' => Program\Events::class,
        'information_schema.character_sets' => Server\CharacterSets::class,
        'information_schema.collations' => Server\Collations::class,
        'information_schema.collation_character_set_applicability' => Server\CollationApplicability::class,
        'information_schema.engines' => Server\EngineSupport::class,
        'information_schema.plugins' => Server\Plugins::class,
        'information_schema.keywords' => Server\Keywords::class,
        'information_schema.resource_groups' => Server\ResourceGroups::class,
        'information_schema.st_spatial_reference_systems' => Server\SpatialReferences::class,
        'information_schema.user_privileges' => Privilege\UserPrivileges::class,
        'information_schema.schema_privileges' => Privilege\SchemaPrivileges::class,
        'information_schema.table_privileges' => Privilege\TablePrivileges::class,
        'information_schema.column_privileges' => Privilege\ColumnPrivileges::class,
        'mysql.user' => Privilege\Users::class,
        'mysql.db' => Privilege\Databases::class,
        'mysql.tables_priv' => Privilege\TablesPriv::class,
        'mysql.columns_priv' => Privilege\ColumnsPriv::class,
        'mysql.procs_priv' => Privilege\ProcsPriv::class,
        'mysql.role_edges' => Privilege\RoleEdges::class,
        'mysql.default_roles' => Privilege\DefaultRoles::class,
        'mysql.servers' => Privilege\Servers::class,
        'mysql.global_grants' => Privilege\GlobalGrants::class,
        'mysql.proxies_priv' => Privilege\ProxiesPriv::class,
        'mysql.time_zone' => Server\TimeZones::class,
        'mysql.time_zone_name' => Server\TimeZones::class,
        'performance_schema.global_variables' => Performance\GlobalVariables::class,
        'performance_schema.session_variables' => Performance\SessionVariables::class,
        'performance_schema.variables_by_thread' => Performance\VariablesByThread::class,
        'performance_schema.global_status' => Performance\GlobalStatus::class,
        'performance_schema.session_status' => Performance\SessionStatus::class,
        'performance_schema.status_by_thread' => Performance\StatusByThread::class,
        'information_schema.global_variables' => Performance\GlobalVariables::class,
        'information_schema.session_variables' => Performance\SessionVariables::class,
        'information_schema.global_status' => Performance\GlobalStatus::class,
        'information_schema.session_status' => Performance\SessionStatus::class,
    ];

    /**
     * The system tables of the release.
     */
    public readonly SystemTables $catalog;

    /**
     * @var array<int, SystemTable> The tables, by the object id of their declaration
     */
    public array $declared = [];

    /**
     * @var array<int, TableDefinition> The definitions built so far, by the object id of the declaration of their table
     */
    public array $definitions = [];

    /**
     * @param Instance $instance The server
     * @param GrammarRelease $release The release emulated
     */
    public function __construct(public readonly Instance $instance, public readonly GrammarRelease $release)
    {
        $this->catalog = SystemTables::of($release);
        foreach ($this->catalog->tables as $table) {
            $this->declared[spl_object_id($table->declaration)] = $table;
        }
    }

    /**
     * Answers the declarations of the system tables, for binding statements that read them.
     *
     * @return list<Table>
     */
    public function declarations(): array
    {
        return $this->catalog->declarations();
    }

    /**
     * Finds the system table a declaration declares, or answers null for another table.
     */
    public function table(Table $declaration): ?SystemTable
    {
        return $this->declared[spl_object_id($declaration)] ?? null;
    }

    /**
     * Finds a system table by database and name, or answers null; INFORMATION_SCHEMA and its tables are named in any case.
     */
    public function find(string $schema, string $name): ?SystemTable
    {
        return $this->catalog->find($schema, $name);
    }

    /**
     * Answers the definition of a system table: its columns, without keys.
     */
    public function definition(SystemTable $table): TableDefinition
    {
        return $this->definitions[spl_object_id($table->declaration)] ??= new TableDefinition(
            $table->schema,
            $table->name,
            array_map(static fn ($column, $declared): ColumnDefinition => new ColumnDefinition($column->name, Domain::of($column->domain, $column->nullable), Fill::none(), declaration: $declared), $table->columns, $table->declaration->columns),
            [],
            $table->declaration,
            $table->engine ?? '',
            $table->collation ?? 'utf8mb3_general_ci',
            false,
            $table->comment,
        );
    }

    /**
     * Answers a system table with the rows it holds now, as the connection reads them.
     *
     * Reading INFORMATION_SCHEMA.PROCESSLIST in MySQL 8.0 and later warns that it is deprecated
     * (verified on live 8.0.44 and 8.4.7 servers).
     *
     * @throws \MySqlMemory\Error\SqlError When the release refuses to read the table
     */
    public function read(SystemTable $table, Connection $connection): StoredTable
    {
        if ($this->release !== GrammarRelease::MySql5651 && $this->release !== GrammarRelease::MySql5744 && SystemTables::key($table->schema, $table->name) === 'information_schema.processlist') {
            $connection->context->warning(StatementError::DeprecatedSyntax, 'INFORMATION_SCHEMA.PROCESSLIST', 'performance_schema.processlist');
        }
        $definition = $this->definition($table);
        $heap = new Heap();
        foreach ($this->rows($table, $connection) as $row) {
            $values = [];
            foreach ($definition->columns as $column) {
                $values[] = self::held($row[$column->name] ?? null, $column->domain);
            }
            $heap->insert($values);
        }

        return new StoredTable($definition, $heap);
    }

    /**
     * Answers the rows of a system table, each a value by column name.
     *
     * @return list<array<string, int|float|string|null>>
     *
     * @throws \MySqlMemory\Error\SqlError When the release refuses to read the table
     */
    public function rows(SystemTable $table, Connection $connection): array
    {
        $source = self::SOURCES[SystemTables::key($table->schema, $table->name)] ?? null;

        return $source === null ? [] : (new $source())->rows(new Reading($this->instance, $connection, $table, $this->release));
    }

    /**
     * Answers a value as a column of a domain holds it: an integer as an int, a string as a string.
     */
    public static function held(int|float|string|null $value, Domain $domain): int|float|string|null
    {
        return match (true) {
            $value === null => null,
            $domain->kind === Kind::Integer => is_string($value) && !is_numeric($value) ? $value : (int) $value,
            $domain->kind === Kind::Double => (float) $value,
            default => is_string($value) ? $value : (string) $value,
        };
    }

    /**
     * Answers the base column a result reports for a column of a system table, read through an alias; null for another table.
     *
     * A column a view of INFORMATION_SCHEMA computes names no database, and one it reads from a
     * table of the data dictionary names that table.
     */
    public function origin(TableDefinition $definition, int $position, string $alias): ?ColumnOrigin
    {
        $table = $this->table($definition->declaration);
        $column = $table?->columns[$position] ?? null;
        if ($table === null || $column === null) {
            return null;
        }

        return new ColumnOrigin($column->database, $alias, $column->originalTable, $column->originalName, $column->flags & ~ColumnFlag::NotNull->value, true);
    }

    /**
     * Tells whether a result names a column of a table as the table names it, rather than as the statement writes it: a column of a view of INFORMATION_SCHEMA in 8.0 and later.
     */
    public function named(Table $declaration): bool
    {
        $table = $this->table($declaration);

        return $table !== null && $table->engine === null && $table->type === 'SYSTEM VIEW';
    }
}
