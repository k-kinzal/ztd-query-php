<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Transform\Materialize;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Plans a reference to a view: its query, computed in a frame of its own, read like a derived table.
 *
 * The query is planned as it was resolved; when a table it reads has been dropped or replaced
 * since, it is resolved again first, and a query that no longer resolves, or no longer returns
 * the columns of the view, makes the view invalid (ER_VIEW_INVALID). A view the server merges
 * into the query that reads it reports the view as the table of its columns, and the database
 * of the view for the columns it reads from tables; a materialized one reports the tables it
 * read (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/view-algorithms.html.
 *
 * @visibility MySqlMemory
 */
final class Views
{
    /**
     * @param Planner $planner The planner of the statement that reads the view
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans a view reference and places it in the scope of the block.
     *
     * @throws SqlError When the view is invalid
     */
    public function plan(TableReference $reference, View $view, Scope $scope, Relations $relations): AccessPath
    {
        $plan = $this->query($view);
        $names = array_map(static fn ($column): string => $column->name->value, $view->declaration->columns);
        $merged = $view->algorithm !== 'TEMPTABLE' && $relations->mergeable($view->query);
        $placed = array_map(static fn (Domain $domain): Domain => !$merged && $domain->kind === Kind::Null ? new Domain(Kind::String, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, 0, 0, false, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary(), true) : $domain, $relations->shaped($reference, $plan->domains));
        $scope->place($reference, $placed, $names);
        $scope->merged[spl_object_id($reference)] = array_map(static fn (?ColumnOrigin $origin): ?ColumnOrigin => $merged
            ? new ColumnOrigin($origin !== null && $origin->originalTable !== '' ? $view->schema : '', '', $view->name, $origin->column ?? '', $origin->flags ?? 0)
            : $origin?->unkeyed(), array_pad($plan->origins, count($plan->domains), null));
        $scope->derived[spl_object_id($reference)] = $reference->alias->value ?? $view->name;

        return new Materialize($plan);
    }

    /**
     * Plans the query of a view in a planner of its own, its columns in the order of the view.
     *
     * @throws SqlError When the view is invalid
     */
    public function query(View $view): QueryPlan
    {
        if (!$view->valid) {
            foreach ($this->planner->settings->legacy() ? $view->operation->facts->diagnostics : [] as $diagnostic) {
                if ($diagnostic instanceof \SqlSemantics\Statement\Reference\Table\MissingTable) {
                    $this->planner->compiler->connection->context->diagnostics->error(1146, QueryError::NoSuchTable->message($diagnostic->name->schema->value ?? $view->database, $diagnostic->name->name->value));
                    break;
                }
            }
            throw SchemaError::ViewInvalid->error($view->schema, $view->name);
        }
        $positions = $view->positions === [] ? array_keys($view->declaration->columns) : $view->positions;
        $settings = new Settings($this->planner->settings->connectionCollation, $this->planner->settings->modes, $this->planner->settings->divPrecisionIncrement, $view->database, $this->planner->settings->version, $this->planner->settings->resolution);
        $planner = new Planner($view->operation->statement, $view->operation->facts, $settings, $this->planner->compiler->connection, $this->planner->dictionary);
        try {
            $plan = $planner->query($view->query, null);
        } catch (SqlError $error) {
            if ($error->error === QueryError::NoSuchTable || $error->error === QueryError::BadField || $error->error === SchemaError::ViewInvalid) {
                throw new SqlError(SchemaError::ViewInvalid, SchemaError::ViewInvalid->message($view->schema, $view->name), $error);
            }
            throw $error;
        }
        if ($positions === array_keys($positions) && count($positions) === count($plan->domains)) {
            return $plan;
        }

        return new QueryPlan(
            new Project($plan->root, array_map(static fn (int $position): ColumnRead => new ColumnRead($plan->domains[$position], $position), $positions)),
            array_map(static fn (int $position) => $plan->domains[$position], $positions),
            array_map(static fn (int $position) => $plan->names[$position] ?? '', $positions),
            array_map(static fn (int $position) => $plan->origins[$position] ?? null, $positions),
        );
    }

    /**
     * Plans TABLE of a view: the rows of its query, under the names of its columns, read through the view.
     *
     * @throws SqlError When the view is invalid
     */
    public function table(View $view): QueryPlan
    {
        $plan = $this->query($view);
        $names = array_map(static fn ($column): string => $column->name->value, $view->declaration->columns);
        $origins = array_map(static fn (?ColumnOrigin $origin, string $name): ColumnOrigin => new ColumnOrigin($origin !== null && $origin->originalTable !== '' ? $view->schema : '', $view->name, $view->name, $name, $origin->flags ?? 0), array_pad($plan->origins, count($plan->domains), null), $names);

        return new QueryPlan(new Materialize($plan), $plan->domains, $names, $origins);
    }

    /**
     * Answers a table of no rows with the columns of a view, as SHOW COLUMNS and DESCRIBE describe a view.
     *
     * A column read from a table has the default and the comment of that column, the zero value
     * of its type for an AUTO_INCREMENT column; a computed one that is never NULL has the zero
     * value of its type, and another none; a column of NULL alone is a VARBINARY(0) (verified on
     * a live 8.4 server).
     */
    public static function stored(View $view, Dictionary $dictionary): \MySqlMemory\Dictionary\StoredTable
    {
        $columns = [];
        foreach ($view->declaration->columns as $index => $column) {
            $type = $column->type;
            $nullable = $column->nullability !== \SqlSemantics\Statement\Type\Nullability::NotNull;
            $domain = $type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain && $type->kind !== Kind::Null ? Domain::of($type, $nullable) : new Domain(Kind::String, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, 0, 0, false, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary(), true);
            $read = self::base($view, $dictionary, $view->positions[$index] ?? $index);
            $base = $read === null || $read->autoIncrement ? null : $read->default;
            $default = $base ?? ($nullable ? \MySqlMemory\Dictionary\Fill::none() : \MySqlMemory\Dictionary\Fill::constant(self::zero($domain), null));
            $columns[] = new \MySqlMemory\Dictionary\ColumnDefinition($column->name->value, $domain, $default, false, false, null, false, $column, $read->comment ?? '');
        }

        return new \MySqlMemory\Dictionary\StoredTable(new \MySqlMemory\Dictionary\TableDefinition($view->schema, $view->name, $columns, [], $view->declaration, ''), new \MySqlMemory\Storage\Heap());
    }

    /**
     * Answers the default of the table column an output column of the query of a view reads
     * directly, or null for a computed column.
     *
     * @param int $position The position of the column in the output of the query
     */
    public static function baseDefault(View $view, Dictionary $dictionary, int $position): ?\MySqlMemory\Dictionary\Fill
    {
        return self::base($view, $dictionary, $position)?->default;
    }

    /**
     * Answers the table column an output column of the query of a view reads directly, or null for a computed column.
     *
     * @param int $position The position of the column in the output of the query
     */
    public static function base(View $view, Dictionary $dictionary, int $position): ?\MySqlMemory\Dictionary\ColumnDefinition
    {
        $field = $view->operation->facts->query($view->query)->projection[$position] ?? null;
        $resolution = $field instanceof \SqlSemantics\Statement\Shape\Field && ($field->expression === null || $field->expression instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) ? ($field->expression === null ? $field->resolution : $view->operation->facts->scalar($field->expression)->resolution) : null;
        if (!$resolution instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn || !$resolution->relation instanceof TableReference || $resolution->slot->name === null) {
            return null;
        }
        $read = $resolution->relation->name;
        $table = $dictionary->table($read->schema->value ?? $view->database, $read->name->value);
        $column = $table?->definition->position($resolution->slot->name->value);

        return $table !== null && $column !== null ? $table->definition->columns[$column] : null;
    }

    /**
     * Answers the zero value of a type: what a computed column that is never NULL defaults to.
     */
    public static function zero(Domain $domain): int|float|string|null
    {
        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => 0,
            Kind::Decimal => $domain->decimals > 0 ? '0.' . str_repeat('0', $domain->decimals) : '0',
            Kind::Double => 0.0,
            Kind::Date => '0000-00-00',
            Kind::DateTime => '0000-00-00 00:00:00',
            Kind::Time => '00:00:00',
            Kind::String, Kind::Json => '',
            Kind::Null => null,
        };
    }

    /**
     * Answers the lower-case names of the columns a resolved query returns.
     *
     * @return list<string>
     */
    public static function outputs(Operation $operation, Query $query): array
    {
        return array_map(static fn ($slot): string => strtolower($slot->name->value ?? ''), $operation->facts->query($query)->shape->slots);
    }

    /**
     * Answers the declarations of the tables and views a resolved query reads, by `database.name`.
     *
     * @return array<string, Table>
     */
    public static function tables(Operation $operation, Query $query, string $database): array
    {
        $tables = [];
        foreach ((new Walker())->find($query, TableReference::class) as $reference) {
            $resolution = $operation->facts->covers($reference) ? $operation->facts->relation($reference)->table : null;
            if ($resolution instanceof DeclaredTable) {
                $name = $resolution->table->name;
                $tables[($name->schema->value ?? $database) . '.' . $name->name->value] = $resolution->table;
            }
        }

        return $tables;
    }

    /**
     * Answers the declaration a database holds under a name: of a table, or of a view.
     */
    public static function declaration(Dictionary $dictionary, string $schema, string $name): ?Table
    {
        $found = $dictionary->schema($schema);

        return $found?->table($name)?->definition->declaration ?? ($found->views[$name] ?? null)?->declaration;
    }
}
