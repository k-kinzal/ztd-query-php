<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog as Address;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The object classes each PostgreSQL catalog command accepts, as the grammar defines them.
 * @visibility SqlSemantics
 */
final class CatalogInvariant
{
    /**
     * Every catalog command is a PostgreSQL operation.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Catalog object commands require PostgreSQL.');
        }
    }

    /**
     * Comment and label text is a PostgreSQL text literal.
     * @throws InvalidStructure
     */
    public static function text(?Literal $text): void
    {
        if ($text !== null && ($text->literalKind !== LiteralKind::Text || $text->type->dialect !== Dialect::PostgreSql)) {
            throw new InvalidStructure('A catalog comment or label requires a PostgreSQL text literal.');
        }
    }

    /**
     * COMMENT ON addresses types by declaration, never by catalog name.
     * @throws InvalidStructure
     */
    public static function comment(ObjectAddress $object): void
    {
        if ($object instanceof Address\TypeNameIdentity) {
            throw new InvalidStructure('A comment addresses a type by its declaration.');
        }
    }

    /**
     * SECURITY LABEL supports the object classes the server documents: relations other than indexes, columns,
     * databases, roles, schemas, tablespaces, languages, publications, subscriptions, event triggers, types, aggregates, routines, and large objects.
     * @throws InvalidStructure
     */
    public static function label(ObjectAddress $object): void
    {
        $allowed = ($object instanceof Address\RelationIdentity && $object->kind !== Kind\RelationKind::Index)
            || ($object instanceof Address\NamedIdentity && !in_array($object->kind, [Kind\NamedObjectKind::AccessMethod, Kind\NamedObjectKind::Extension, Kind\NamedObjectKind::ForeignDataWrapper, Kind\NamedObjectKind::Server], true))
            || $object instanceof Address\DeclaredTypeIdentity || $object instanceof Address\AggregateIdentity || $object instanceof Address\RoutineIdentity || $object instanceof Address\LargeObjectIdentity
            || ($object instanceof Address\RelationMemberIdentity && $object->kind === Kind\RelationMemberKind::Column);
        if (!$allowed) {
            throw new InvalidStructure('The object class does not accept security labels.');
        }
    }

    /**
     * RENAME TO applies to objects whose name is not a type declaration, a signature pair, or a relation with IF EXISTS.
     * @throws InvalidStructure
     */
    public static function rename(ObjectAddress $object): void
    {
        $allowed = $object instanceof Address\AggregateIdentity || $object instanceof Address\SchemaObjectIdentity || $object instanceof Address\TypeNameIdentity
            || $object instanceof Address\RoutineIdentity || $object instanceof Address\OperatorSetIdentity
            || ($object instanceof Address\NamedIdentity && !in_array($object->kind, [Kind\NamedObjectKind::AccessMethod, Kind\NamedObjectKind::Extension, Kind\NamedObjectKind::EventTrigger], true))
            || ($object instanceof Address\RelationMemberIdentity && in_array($object->kind, [Kind\RelationMemberKind::Rule, Kind\RelationMemberKind::Trigger], true));
        if (!$allowed) {
            throw new InvalidStructure('The object class has its own rename form or cannot be renamed.');
        }
    }

    /**
     * SET SCHEMA applies to schema-scoped objects; relations use their own form with IF EXISTS.
     * @throws InvalidStructure
     */
    public static function schema(ObjectAddress $object): void
    {
        $allowed = $object instanceof Address\AggregateIdentity || $object instanceof Address\SchemaObjectIdentity || $object instanceof Address\TypeNameIdentity
            || $object instanceof Address\RoutineIdentity || $object instanceof Address\OperatorIdentity || $object instanceof Address\OperatorSetIdentity
            || ($object instanceof Address\NamedIdentity && $object->kind === Kind\NamedObjectKind::Extension);
        if (!$allowed) {
            throw new InvalidStructure('The object class cannot move between schemas with this form.');
        }
    }

    /**
     * OWNER TO applies to owned objects other than relations, whose ownership changes inside ALTER TABLE.
     * @throws InvalidStructure
     */
    public static function owner(ObjectAddress $object): void
    {
        $allowed = $object instanceof Address\AggregateIdentity || $object instanceof Address\TypeNameIdentity || $object instanceof Address\RoutineIdentity
            || $object instanceof Address\LargeObjectIdentity || $object instanceof Address\OperatorIdentity || $object instanceof Address\OperatorSetIdentity
            || ($object instanceof Address\SchemaObjectIdentity && !in_array($object->kind, [Kind\SchemaObjectKind::TextSearchParser, Kind\SchemaObjectKind::TextSearchTemplate], true))
            || ($object instanceof Address\NamedIdentity && !in_array($object->kind, [Kind\NamedObjectKind::AccessMethod, Kind\NamedObjectKind::Extension, Kind\NamedObjectKind::Role, Kind\NamedObjectKind::EventTrigger], true));
        if (!$allowed) {
            throw new InvalidStructure('The object class has no owner or changes its owner with another form.');
        }
    }

    /**
     * Extension dependencies are declared for routines, triggers, materialized views, and indexes.
     * @throws InvalidStructure
     */
    public static function dependency(Address\RoutineIdentity|Address\RelationMemberIdentity|Address\RelationIdentity $object): void
    {
        $allowed = $object instanceof Address\RoutineIdentity
            || ($object instanceof Address\RelationMemberIdentity && $object->kind === Kind\RelationMemberKind::Trigger)
            || ($object instanceof Address\RelationIdentity && in_array($object->kind, [Kind\RelationKind::MaterializedView, Kind\RelationKind::Index], true));
        if (!$allowed) {
            throw new InvalidStructure('Only routines, triggers, materialized views, and indexes can depend on an extension.');
        }
    }
}
