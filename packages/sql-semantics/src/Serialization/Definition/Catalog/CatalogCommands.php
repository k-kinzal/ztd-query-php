<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Catalog;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Serialization\Definition\Ownership\OwnershipCommands;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes catalog object commands from their object address and their single operand.
 * @visibility SqlSemantics
 */
final class CatalogCommands
{
    /**
     * Returns null for statements outside the catalog object family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $statement instanceof Statement\RenameObjectStatement => new Tree('rename-object', [Build::keyword('ALTER'), ObjectAddresses::write($statement->object), Build::keyword('RENAME TO'), Build::identifier([$statement->newName], $dialect)]),
            $statement instanceof Statement\SetObjectSchemaStatement => new Tree('set-object-schema', [Build::keyword('ALTER'), ObjectAddresses::write($statement->object), Build::keyword('SET SCHEMA'), Build::identifier([$statement->schema], $dialect)]),
            $statement instanceof Statement\AddExtensionDependencyStatement => new Tree('add-dependency', [Build::keyword('ALTER'), ObjectAddresses::write($statement->object), Build::keyword('DEPENDS ON EXTENSION'), Build::identifier([$statement->extension], $dialect)]),
            $statement instanceof Statement\RemoveExtensionDependencyStatement => new Tree('remove-dependency', [Build::keyword('ALTER'), ObjectAddresses::write($statement->object), Build::keyword('NO DEPENDS ON EXTENSION'), Build::identifier([$statement->extension], $dialect)]),
            $statement instanceof Statement\ChangeObjectOwnerStatement => new Tree('change-owner', [Build::keyword('ALTER'), ObjectAddresses::write($statement->object), Build::keyword('OWNER TO'), OwnershipCommands::role($statement->newOwner)]),
            $statement instanceof Statement\CommentOnStatement => new Tree('comment', [Build::keyword('COMMENT ON'), ObjectAddresses::write($statement->object), Build::keyword('IS'), self::text($statement->comment)]),
            $statement instanceof Statement\SecurityLabelStatement => new Tree('security-label', [Build::keyword('SECURITY LABEL'), ...($statement->provider === null ? [] : [Build::keyword('FOR'), Build::identifier([$statement->provider], $dialect)]), Build::keyword('ON'), ObjectAddresses::write($statement->object), Build::keyword('IS'), self::text($statement->label)]),
            $statement instanceof Statement\RenameDomainConstraintStatement => new Tree('rename-domain-constraint', [Build::keyword('ALTER DOMAIN'), Build::identifier($statement->domain->parts, $dialect), Build::keyword('RENAME CONSTRAINT'), Build::identifier([$statement->constraint], $dialect), Build::keyword('TO'), Build::identifier([$statement->newName], $dialect)]),
            $statement instanceof Statement\RenameTypeAttributeStatement => new Tree('rename-attribute', [Build::keyword('ALTER TYPE'), Build::identifier($statement->type->parts, $dialect), Build::keyword('RENAME ATTRIBUTE'), Build::identifier([$statement->attribute], $dialect), Build::keyword('TO'), Build::identifier([$statement->newName], $dialect), Build::keyword($statement->behavior->value)]),
            $statement instanceof Statement\RenamePolicyStatement => new Tree('rename-policy', [Build::keyword('ALTER POLICY' . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier([$statement->name], $dialect), Build::keyword('ON'), Build::identifier($statement->table->parts, $dialect), Build::keyword('RENAME TO'), Build::identifier([$statement->newName], $dialect)]),
            default => null,
        };
    }

    /**
     * A removed comment or label is spelled NULL.
     */
    public static function text(?Literal $text): Tree
    {
        return $text === null ? Build::keyword('NULL') : Expressions::write($text);
    }
}
