<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Role;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Definition\Routines;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes privilege targets with their explicit object class keyword.
 * @visibility SqlSemantics
 */
final class PrivilegeTargets
{
    /**
     * Tables always carry the TABLE keyword so the class is explicit.
     */
    public static function target(TableTargets|SchemaObjectTargets|ServerObjectTargets|RoutineTargets|LargeObjectTargets|ParameterTargets|SchemaScopedTargets $target): Tree
    {
        return match (true) {
            $target instanceof TableTargets => new Tree('table-targets', [Build::keyword('TABLE'), Build::separated(array_map(static fn (TableReference $table): Tree => Relations::target($table, Dialect::PostgreSql), $target->tables))]),
            $target instanceof SchemaObjectTargets => new Tree('object-targets', [Build::keyword($target->class->value), Build::separated(array_map(self::qualified(...), $target->names))]),
            $target instanceof ServerObjectTargets => new Tree('object-targets', [Build::keyword($target->class->value), self::names($target->names)]),
            $target instanceof RoutineTargets => new Tree('routine-targets', [Build::keyword($target->class->value), Build::separated(array_map(Routines::routine(...), $target->routines))]),
            $target instanceof LargeObjectTargets => new Tree('large-object-targets', [Build::keyword('LARGE OBJECT'), Build::separated(array_map(static fn (int $id): Tree => new Tree('object-id', [new Atom('literal', (string) $id)]), $target->ids))]),
            $target instanceof ParameterTargets => new Tree('parameter-targets', [Build::keyword('PARAMETER'), Build::separated(array_map(self::qualified(...), $target->parameters))]),
            $target instanceof SchemaScopedTargets => new Tree('schema-scoped-targets', [Build::keyword('ALL ' . $target->class->value . ' IN SCHEMA'), self::names($target->schemas)]),
        };
    }

    /**
     * Writes a qualified object or parameter name.
     */
    public static function qualified(QualifiedName $name): Tree
    {
        return Build::identifier($name->parts, Dialect::PostgreSql);
    }

    /**
     * @param non-empty-list<string> $names
     */
    public static function names(array $names): Tree
    {
        return Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], Dialect::PostgreSql), $names));
    }
}
