<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\PostgreSqlRoles;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Schema\StorageParameters;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\Definition\WrapperOptions;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Definition\Relation\Storage;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Definition\Trigger\TriggerFiring;
use SqlSemantics\Model\Validation\Collections;

/**
 * Classifies one ALTER TABLE command by its leading keywords into a typed relation action.
 * @visibility SqlSemantics
 */
final class RelationActions
{
    /**
     * Column and constraint commands delegate; every other command is a relation-level action.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $command, Scope $scope, QueryContext $context): RelationAction
    {
        $words = ObjectAddresses::words($command);
        $identifiers = $context->tables->identifiers;
        return match ($words[0]) {
            'ADD', 'ALTER', 'DROP', 'VALIDATE' => self::member($command, $words, $scope, $context),
            'INHERIT', 'NO', 'FORCE', 'OF', 'NOT' => self::hierarchy($command, $words, $context),
            'SET' => self::set($command, $words, $scope, $context),
            'RESET' => new Storage\ResetStorageParameters(RelationAlterations::parameterNames($command, $context)),
            'ENABLE', 'DISABLE' => self::firing($command, $words, $context),
            'CLUSTER' => new Relation\ClusterOn($identifiers->name((Tree::child($command, ['name']) ?? throw new UnclassifiedSql('CLUSTER ON requires an index.'))->tokens()[0])),
            'OWNER' => new Relation\ChangeOwner(PostgreSqlRoles::read(Tree::child($command, ['RoleSpec']) ?? throw new UnclassifiedSql('OWNER TO requires a role.'))),
            'REPLICA' => self::replicaIdentity($command, $context),
            'OPTIONS' => new Storage\SetForeignOptions(Collections::nonEmpty(array_map(static fn (Node $change) => WrapperOptions::change($change, $identifiers), Tree::outer($command, ['alter_generic_option_elem'])))),
            default => throw new UnclassifiedSql('Unclassified relation action: ' . Tree::text($command)),
        };
    }

    /**
     * Column and constraint commands delegate to their own readers.
     * @param list<string> $words
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function member(Node $command, array $words, Scope $scope, QueryContext $context): RelationAction
    {
        return match ($words[0]) {
            'ADD' => (Tree::child($command, ['TableConstraint']) === null ? ColumnActions::add($command, $scope, $context) : ConstraintActions::add($command, $scope, $context)),
            'ALTER' => (($words[1] ?? '') === 'CONSTRAINT' ? ConstraintActions::alter($command, $context) : ColumnActions::read($command, $scope, $context)),
            'DROP' => (($words[1] ?? '') === 'CONSTRAINT' ? ConstraintActions::drop($command, $context) : ColumnActions::drop($command, $context)),
            default => new Relation\Constraint\ValidateConstraint($context->tables->identifiers->name((Tree::child($command, ['name']) ?? throw new UnclassifiedSql('VALIDATE CONSTRAINT requires a name.'))->tokens()[0])),
        };
    }

    /**
     * Inheritance, typed-table binding, and forced row-level security change how the relation relates to others.
     * @param list<string> $words
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function hierarchy(Node $command, array $words, QueryContext $context): RelationAction
    {
        $parent = static fn (): \SqlSemantics\Model\Relation\QualifiedName => ObjectAddresses::name(Tree::child($command, ['qualified_name']) ?? throw new UnclassifiedSql('An inheritance change requires a parent.'), $context, 3);
        return match ($words[0]) {
            'INHERIT' => new Relation\SetInheritance($parent(), true),
            'NO' => ($words[1] ?? '') === 'INHERIT' ? new Relation\SetInheritance($parent(), false) : new Relation\SetRowSecurity(Relation\RowSecurityChange::NoForce),
            'FORCE' => new Relation\SetRowSecurity(Relation\RowSecurityChange::Force),
            'OF' => new Relation\TypedTableBinding(ObjectAddresses::name(Tree::child($command, ['any_name']) ?? throw new UnclassifiedSql('OF requires a type.'), $context, 2)),
            default => new Relation\RemoveRelationProperty(Relation\RelationProperty::Type),
        };
    }

    /**
     * SET selects persistence, tablespace, access method, storage parameters, or a property removal.
     * @param list<string> $words
     * @throws UnclassifiedSql
     */
    public static function set(Node $command, array $words, Scope $scope, QueryContext $context): RelationAction
    {
        $identifiers = $context->tables->identifiers;
        return match ($words[1] ?? '') {
            'WITHOUT' => new Relation\RemoveRelationProperty(($words[2] ?? '') === 'OIDS' ? Relation\RelationProperty::Oids : Relation\RelationProperty::Cluster),
            'LOGGED' => new Storage\SetLogging(Storage\RelationLogging::Logged),
            'UNLOGGED' => new Storage\SetLogging(Storage\RelationLogging::Unlogged),
            'TABLESPACE' => new Storage\SetTablespace($identifiers->name((Tree::child($command, ['name']) ?? throw new UnclassifiedSql('SET TABLESPACE requires a name.'))->tokens()[0])),
            'ACCESS' => new Storage\SetAccessMethod(self::accessMethod(Tree::child($command, ['set_access_method_name']) ?? throw new UnclassifiedSql('SET ACCESS METHOD requires a method.'), $context)),
            '(' => new Storage\SetStorageParameters(Collections::nonEmpty(StorageParameters::read($command, $scope))),
            default => throw new UnclassifiedSql('Unclassified SET action: ' . Tree::text($command)),
        };
    }

    /**
     * DEFAULT selects the server's default access method.
     */
    public static function accessMethod(Node $source, QueryContext $context): ?string
    {
        $token = $source->tokens()[0];
        return $token->name === 'DEFAULT' ? null : $context->tables->identifiers->name($token);
    }

    /**
     * ENABLE and DISABLE address triggers, rules, or row-level security.
     * @param list<string> $words
     * @throws UnclassifiedSql
     */
    public static function firing(Node $command, array $words, QueryContext $context): RelationAction
    {
        if (($words[1] ?? '') === 'ROW') {
            return new Relation\SetRowSecurity($words[0] === 'ENABLE' ? Relation\RowSecurityChange::Enable : Relation\RowSecurityChange::Disable);
        }
        $firing = match ($words[1] ?? '') {
            'ALWAYS' => TriggerFiring::Always,
            'REPLICA' => TriggerFiring::Replica,
            default => $words[0] === 'ENABLE' ? TriggerFiring::Origin : TriggerFiring::Disabled,
        };
        $target = in_array('RULE', $words, true) ? Relation\FiringTarget::Rule : Relation\FiringTarget::Trigger;
        $name = Tree::child($command, ['name']);
        $last = $words[count($words) - 1];
        $subject = $name !== null ? $context->tables->identifiers->name($name->tokens()[0]) : (Relation\TriggerGroup::tryFrom($last) ?? throw new UnclassifiedSql('A firing change requires a trigger, rule, or group.'));
        return new Relation\SetFiring($target, $subject, $firing);
    }

    /**
     * REPLICA IDENTITY selects a policy keyword or a unique index.
     * @throws UnclassifiedSql
     */
    public static function replicaIdentity(Node $command, QueryContext $context): Relation\SetReplicaIdentity
    {
        $identity = Tree::child($command, ['replica_identity']) ?? throw new UnclassifiedSql('REPLICA IDENTITY requires its policy.');
        $name = Tree::child($identity, ['name']);
        return new Relation\SetReplicaIdentity($name !== null ? $context->tables->identifiers->name($name->tokens()[0]) : Relation\ReplicaIdentity::from(strtoupper(Tree::text($identity))));
    }
}
