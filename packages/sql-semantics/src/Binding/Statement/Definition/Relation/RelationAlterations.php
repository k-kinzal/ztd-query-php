<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\PostgreSqlRoles;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\Definition\Catalog\RelationTargets;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\MoveTablespaceRelationsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds the ALTER TABLE family with one typed action per command and a tablespace move as its own form.
 * @visibility SqlSemantics
 */
final class RelationAlterations
{
    /**
     * Returns null when the head does not name a relation class.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $kind = RelationKind::tryFrom(ObjectAddresses::objectClass($source));
        if ($kind === null) {
            return null;
        }
        $words = ObjectAddresses::words($source);
        if (($words[substr_count($kind->value, ' ') + 2] ?? '') === 'ALL') {
            return self::move($origin, $source, $context, $kind);
        }
        if ($kind === RelationKind::ForeignTable) {
            ForeignConstraints::check($source);
        }
        [$name, $ifExists, $only] = RelationTargets::read($source, $context);
        $scope = self::scope($origin, $source, $context, $kind, $name);
        $actions = [];
        foreach (Tree::outer($source, ['alter_table_cmd', 'partition_cmd', 'index_partition_cmd']) as $command) {
            $actions[] = $command->name === 'alter_table_cmd' ? RelationActions::read($command, $scope, $context) : PartitionActions::read($command, $scope, $context);
        }
        return new AlterRelationStatement($origin, $kind, $name, Collections::nonEmpty($actions), $ifExists, $only);
    }

    /**
     * Tables and foreign tables resolve so that column expressions bind against their declaration.
     */
    public static function scope(Origin $origin, Node $source, QueryContext $context, RelationKind $kind, QualifiedName $name): Scope
    {
        if (!in_array($kind, [RelationKind::Table, RelationKind::ForeignTable], true)) {
            return new Scope($context->tables->identifiers, queries: $context);
        }
        $target = $context->tables->resolve($name->parts, $source);
        $reference = TableOccurrence::bind($context->ids->relation(), $origin->scopeId, $target, $context->tables->name($name->parts, $target), null, $source);
        return new Scope($context->tables->identifiers, [$reference], queries: $context);
    }

    /**
     * Moves every relation of a tablespace, optionally restricted to the listed owners.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function move(Origin $origin, Node $source, QueryContext $context, RelationKind $kind): MoveTablespaceRelationsStatement
    {
        $names = array_map(static fn (Node $node): string => $context->tables->identifiers->name($node->tokens()[0]), Tree::outer($source, ['name']));
        if (count($names) !== 2) {
            throw new UnclassifiedSql('A tablespace move requires its source and destination.');
        }
        $roles = Tree::child($source, ['role_list']);
        $owners = $roles === null ? [] : array_map(PostgreSqlRoles::read(...), Tree::outer($roles, ['RoleSpec']));
        $nowait = Tree::child($source, ['opt_nowait']);
        return new MoveTablespaceRelationsStatement($origin, $kind, $names[0], $names[1], $owners, $nowait !== null && Tree::hasTokens($nowait));
    }

    /**
     * Reads a relation action list of storage parameter names for RESET.
     * @return non-empty-list<QualifiedName>
     * @throws InvalidSql
     */
    public static function parameterNames(Node $command, QueryContext $context): array
    {
        $names = [];
        foreach (Tree::outer($command, ['reloption_elem']) as $option) {
            if (in_array('=', array_map(static fn ($token): string => $token->text, $option->tokens()), true)) {
                throw new InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ResetParameterValue, $option);
            }
            $names[] = ObjectAddresses::name($option, $context, 2);
        }
        return Collections::nonEmpty($names);
    }
}
