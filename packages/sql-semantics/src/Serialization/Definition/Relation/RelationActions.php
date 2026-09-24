<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Relation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Definition\Relation\Storage;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Foreign\WrapperOptions;
use SqlSemantics\Serialization\Definition\Ownership\OwnershipCommands;
use SqlSemantics\Serialization\Definition\Storage as StorageWriter;

/**
 * Writes each relation-level ALTER TABLE action from its typed operands.
 * @visibility SqlSemantics
 */
final class RelationActions
{
    /**
     * Column, constraint, identity, and partition actions delegate to their own writers.
     * @throws InvalidStructure
     */
    public static function write(RelationAction $action): Tree
    {
        $dialect = Dialect::PostgreSql;
        return ColumnActions::write($action) ?? ConstraintActions::write($action) ?? PartitionActions::write($action) ?? new Tree('relation-action', match (true) {
            $action instanceof Relation\ChangeOwner => [Build::keyword('OWNER TO'), OwnershipCommands::role($action->newOwner)],
            $action instanceof Relation\ClusterOn => [Build::keyword('CLUSTER ON'), Build::identifier([$action->index], $dialect)],
            $action instanceof Relation\RemoveRelationProperty => [Build::keyword($action->property->value)],
            $action instanceof Relation\SetFiring => self::firing($action),
            $action instanceof Relation\SetInheritance => [Build::keyword($action->inherit ? 'INHERIT' : 'NO INHERIT'), Build::identifier($action->parent->parts, $dialect)],
            $action instanceof Relation\TypedTableBinding => [Build::keyword('OF'), Build::identifier($action->type->parts, $dialect)],
            $action instanceof Relation\SetReplicaIdentity => [Build::keyword('REPLICA IDENTITY'), ...(is_string($action->identity) ? [Build::keyword('USING INDEX'), Build::identifier([$action->identity], $dialect)] : [Build::keyword($action->identity->value)])],
            $action instanceof Relation\SetRowSecurity => [Build::keyword($action->change->value)],
            default => self::storage($action) ?? throw new InvalidStructure('Unclassified relation action: ' . $action::class),
        });
    }

    /**
     * Writes persistence, placement, access method, storage parameter, and foreign option changes.
     * @return list<Tree>|null
     */
    public static function storage(RelationAction $action): ?array
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $action instanceof Storage\SetLogging => [Build::keyword('SET ' . $action->logging->value)],
            $action instanceof Storage\SetTablespace => [Build::keyword('SET TABLESPACE'), Build::identifier([$action->tablespace], $dialect)],
            $action instanceof Storage\SetAccessMethod => [Build::keyword('SET ACCESS METHOD'), $action->method === null ? Build::keyword('DEFAULT') : Build::identifier([$action->method], $dialect)],
            $action instanceof Storage\SetStorageParameters => [Build::keyword('SET'), Build::parentheses(StorageWriter::parameters($action->parameters, $dialect))],
            $action instanceof Storage\ResetStorageParameters => [Build::keyword('RESET'), Build::parentheses(Build::separated(array_map(static fn ($name): Tree => Build::identifier($name->parts, $dialect), $action->names)))],
            $action instanceof Storage\SetForeignOptions => [Build::keyword('OPTIONS'), Build::parentheses(Build::separated(array_map(WrapperOptions::change(...), $action->changes)))],
            default => null,
        };
    }

    /**
     * Trigger groups are keywords; named triggers and rules are identifiers.
     * @return list<Tree>
     */
    public static function firing(Relation\SetFiring $action): array
    {
        $subject = is_string($action->name) ? Build::identifier([$action->name], Dialect::PostgreSql) : Build::keyword($action->name->value);
        $words = explode(' ', $action->firing->value);
        return [Build::keyword($words[0] . (isset($words[1]) ? ' ' . $words[1] : '') . ' ' . $action->target->value), $subject];
    }
}
