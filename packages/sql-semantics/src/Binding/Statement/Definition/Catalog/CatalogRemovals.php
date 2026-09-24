<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Catalog;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Removal as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;

/**
 * Binds the DROP forms whose object classes have no dedicated removal statement elsewhere.
 * @visibility SqlSemantics
 */
final class CatalogRemovals
{
    /**
     * Returns null for tables, views, indexes, triggers, servers, wrappers, and event triggers.
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $class = ObjectAddresses::objectClass($source);
        $identifiers = $context->tables->identifiers;
        $ifExists = array_filter($source->children, static fn ($child): bool => $child instanceof Token && $child->name === 'IF_P') !== [];
        $behaviorNode = Tree::child($source, ['opt_drop_behavior']);
        $behavior = $behaviorNode === null ? DropBehavior::Default : DropBehavior::from(strtoupper(Tree::text($behaviorNode)));
        $qualified = static fn (int $depth): array => Collections::nonEmpty(array_map(static fn (Node $name): QualifiedName => ObjectAddresses::name($name, $context, $depth), Tree::outer($source, ['any_name'])));
        $relation = Kind\RelationKind::tryFrom($class);
        if (in_array($relation, [Kind\RelationKind::Sequence, Kind\RelationKind::ForeignTable], true)) {
            return new Statement\DropRelationsStatement($origin, $relation, $qualified(3), $ifExists, $behavior);
        }
        $schemaObject = Kind\SchemaObjectKind::tryFrom($class);
        if ($schemaObject !== null) {
            return new Statement\DropSchemaObjectsStatement($origin, $schemaObject, $qualified(2), $ifExists, $behavior);
        }
        $named = Kind\NamedObjectKind::tryFrom($class);
        if (in_array($named, [Kind\NamedObjectKind::AccessMethod, Kind\NamedObjectKind::Extension, Kind\NamedObjectKind::Language, Kind\NamedObjectKind::Publication, Kind\NamedObjectKind::Schema], true)) {
            $names = Collections::nonEmpty(array_map(static fn (Node $name): string => $identifiers->name($name->tokens()[0]), Tree::outer($source, ['name'])));
            return new Statement\DropNamedObjectsStatement($origin, $named, $names, $ifExists, $behavior);
        }
        $member = Kind\RelationMemberKind::tryFrom($class);
        if (in_array($member, [Kind\RelationMemberKind::Policy, Kind\RelationMemberKind::Rule], true)) {
            $name = Tree::child($source, ['name']) ?? throw new UnclassifiedSql('A member removal requires its name.');
            $table = Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('A member removal requires its relation.');
            return new Statement\DropRelationMemberStatement($origin, $member, $identifiers->name($name->tokens()[0]), ObjectAddresses::name($table, $context, 3), $ifExists, $behavior);
        }
        $typeKind = Kind\TypeKind::tryFrom($class);
        if ($typeKind !== null) {
            $types = Collections::nonEmpty(array_map(static fn (Node $type) => (new TypeReader(Dialect::PostgreSql))->read($type), Tree::outer($source, ['Typename'])));
            return new Statement\DropTypesStatement($origin, $typeKind, $types, $ifExists, $behavior);
        }
        return null;
    }
}
