<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Catalog;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog as Address;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationMemberKind;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Routines;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Writes an object class keyword and the identity operands of one catalog object.
 * @visibility SqlSemantics
 */
final class ObjectAddresses
{
    /**
     * Every identity form owns its own spelling; names are identifiers and types are declarations.
     * @throws InvalidStructure
     */
    public static function write(ObjectAddress $object): Tree
    {
        $dialect = Dialect::PostgreSql;
        return new Tree('object-address', match (true) {
            $object instanceof Address\NamedIdentity => [Build::keyword($object->kind->value), Build::identifier([$object->name], $dialect)],
            $object instanceof Address\RelationIdentity => [Build::keyword($object->kind->value), Build::identifier($object->name->parts, $dialect)],
            $object instanceof Address\SchemaObjectIdentity => [Build::keyword($object->kind->value), Build::identifier($object->name->parts, $dialect)],
            $object instanceof Address\RelationMemberIdentity => self::member($object),
            $object instanceof Address\DomainConstraintIdentity => [Build::keyword('CONSTRAINT'), Build::identifier([$object->name], $dialect), Build::keyword('ON DOMAIN'), Build::identifier($object->domain->parts, $dialect)],
            $object instanceof Address\DeclaredTypeIdentity => [Build::keyword($object->kind->value), TypeDeclaration::write($object->type)],
            $object instanceof Address\TypeNameIdentity => [Build::keyword($object->kind->value), Build::identifier($object->name->parts, $dialect)],
            $object instanceof Address\AggregateIdentity => [Build::keyword('AGGREGATE'), Routines::aggregate($object->target)],
            $object instanceof Address\RoutineIdentity => [Build::keyword($object->kind->value), Routines::routine($object->target)],
            $object instanceof Address\OperatorIdentity => [Build::keyword('OPERATOR'), self::operator($object->name), Build::parentheses(Build::separated([self::operand($object->left), self::operand($object->right)]))],
            $object instanceof Address\OperatorSetIdentity => [Build::keyword($object->kind->value), Build::identifier($object->name->parts, $dialect), Build::keyword('USING'), Build::identifier([$object->method], $dialect)],
            $object instanceof Address\LargeObjectIdentity => [Build::keyword('LARGE OBJECT'), Build::keyword((string) $object->id)],
            $object instanceof Address\CastIdentity => [Build::keyword('CAST'), Build::parentheses(new Tree('cast-signature', [TypeDeclaration::write($object->source), Build::keyword('AS'), TypeDeclaration::write($object->target)]))],
            $object instanceof Address\TransformIdentity => [Build::keyword('TRANSFORM FOR'), TypeDeclaration::write($object->type), Build::keyword('LANGUAGE'), Build::identifier([$object->language], $dialect)],
            default => throw new InvalidStructure('Unclassified catalog object address: ' . $object::class),
        });
    }

    /**
     * A column is one qualified path; other members name themselves and their relation.
     * @return list<Tree>
     */
    public static function member(Address\RelationMemberIdentity $object): array
    {
        if ($object->kind === RelationMemberKind::Column) {
            return [Build::keyword('COLUMN'), Build::identifier([...$object->relation->parts, $object->name], Dialect::PostgreSql)];
        }
        return [Build::keyword($object->kind->value), Build::identifier([$object->name], Dialect::PostgreSql), Build::keyword('ON'), Build::identifier($object->relation->parts, Dialect::PostgreSql)];
    }

    /**
     * Schema components are identifiers; the operator symbol itself is never quoted.
     */
    public static function operator(QualifiedName $name): Tree
    {
        $parts = array_map(static fn (string $part): Tree => Build::identifier([$part], Dialect::PostgreSql), array_slice($name->parts, 0, -1));
        $parts[] = Build::keyword($name->parts[count($name->parts) - 1]);
        return Build::separated($parts, '.');
    }

    /**
     * A missing operand of a unary operator is spelled NONE.
     */
    public static function operand(?TypeDescriptor $type): Tree
    {
        return $type === null ? Build::keyword('NONE') : TypeDeclaration::write($type);
    }
}
