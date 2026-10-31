<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as ColumnElement;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Resolves spatial index structures from the columns SQL Semantics has typed.
 *
 * Modern geometric indexes use RTREE and require non-null geometric columns.
 * A geometric index cannot be unique or request BTREE or HASH. MEMORY cannot
 * store geometric columns. Verified through SQL on MySQL 5.6 and 8.4.
 *
 * @visibility MySqlMemory
 */
final class SpatialKeys
{
    /**
     * @param Planner $planner The statement, release and diagnostics context
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Refuses geometric columns on engines that cannot store them.
     *
     * @param list<ColumnDefinition> $columns The resolved table columns
     */
    public function engine(array $columns, string $engine, ?Key $key = null): void
    {
        if (in_array(strtoupper($engine), ['MEMORY', 'HEAP'], true) && array_filter($columns, static fn (ColumnDefinition $column): bool => $column->domain->field === Field::Geometry) !== []) {
            throw SchemaError::EngineUnsupportedOperation->error('GEOMETRY');
        }
        if ($key?->kind === KeyKind::Spatial && (in_array(strtoupper($engine), ['MEMORY', 'HEAP'], true) || ($this->planner->settings->release() === GrammarRelease::MySql5651 && strcasecmp($engine, 'InnoDB') === 0))) {
            throw SchemaError::SpatialIndexUnsupported->error();
        }
    }

    /**
     * Resolves RTREE or implicit geometric indexes and checks their columns.
     *
     * @param list<ColumnDefinition> $columns The resolved table columns
     */
    public function resolve(IndexDefinition $index, Key $key, array $columns, string $engine = 'InnoDB'): Key
    {
        $legacy = $this->planner->settings->legacy();
        $algorithm = (new IndexOptions())->algorithm($index, 'MEMORY');
        if ($legacy && $algorithm === IndexAlgorithm::Rtree) {
            throw $this->planner->settings->release() === GrammarRelease::MySql5651 ? StatementError::WrongArguments->error('RTREE INDEX') : SchemaError::TooManyKeyParts->error(1);
        }
        $this->engine($columns, $engine, $key);
        $geometric = array_filter($key->columns, static fn (int $position): bool => $columns[$position]->domain->field === Field::Geometry);
        if ($key->kind === KeyKind::FullText && $geometric !== []) {
            throw SchemaError::FullTextColumn->error($columns[array_values($geometric)[0]]->name);
        }
        if ($key->kind !== KeyKind::Spatial && $algorithm !== IndexAlgorithm::Rtree && ($legacy || $geometric === [])) {
            return $key;
        }
        if (count($key->columns) > 1) {
            throw SchemaError::TooManyKeyParts->error(1);
        }
        if (count($geometric) !== count($key->columns)) {
            throw SchemaError::SpatialColumnType->error();
        }
        if ($algorithm !== null && $algorithm !== IndexAlgorithm::Rtree) {
            throw SchemaError::SpatialIndexAlgorithm->error($algorithm->value);
        }
        if ($key->unique()) {
            throw SchemaError::SpatialUnique->error();
        }
        if (array_filter($key->prefixes, static fn (?int $prefix): bool => $prefix !== null) !== []) {
            throw SchemaError::IncorrectPrefixKey->error();
        }
        $this->warn($key, $columns);
        if (array_filter($key->columns, static fn (int $position): bool => $columns[$position]->nullable()) !== []) {
            throw SchemaError::SpatialColumnNullable->error();
        }

        return new Key($key->name, KeyKind::Spatial, $key->columns, $key->prefixes, $key->descending, $key->generated, $key->visible);
    }

    /**
     * Warns when modern spatial columns have no SRID restriction.
     *
     * @param list<ColumnDefinition> $columns The resolved table columns
     */
    public function warn(Key $key, array $columns): void
    {
        if ($this->planner->settings->legacy()) {
            return;
        }
        $statement = $this->planner->statement;
        $restricted = [];
        foreach ($statement instanceof CreateTable ? $statement->elements : [] as $element) {
            if ($element instanceof ColumnElement && $element->srid() !== null) {
                $restricted[] = strtolower($element->name->column->value);
            }
        }
        foreach ($key->columns as $position) {
            if (!in_array(strtolower($columns[$position]->name), $restricted, true)) {
                $this->planner->compiler->connection->context->warning(SchemaError::SpatialIndexWithoutSrid, $columns[$position]->name);
            }
        }
    }
}
