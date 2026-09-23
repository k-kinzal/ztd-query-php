<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Spatial\CreationPolicy;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes spatial definitions with metadata literals and mutually exclusive creation policies.
 * @visibility SqlSemantics
 */
final class SpatialDefinitions
{
    /**
     * Serializes each operation's required identifiers and metadata without executing it.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\DropSpatialReferenceSystemStatement) {
            return new Tree('drop-srs', [Build::keyword('DROP SPATIAL REFERENCE SYSTEM' . ($statement->ifExists ? ' IF EXISTS' : '') . ' ' . $statement->srid)]);
        }
        if (!$statement instanceof Statement\CreateSpatialReferenceSystemStatement) {
            return null;
        }
        $definition = $statement->definition;
        return new Tree('create-srs', [
            Build::keyword('CREATE' . ($statement->policy === CreationPolicy::Replace ? ' OR REPLACE' : '') . ' SPATIAL REFERENCE SYSTEM' . ($statement->policy === CreationPolicy::IfNotExists ? ' IF NOT EXISTS' : '') . ' ' . $statement->srid),
            Build::keyword('NAME'), Expressions::write($definition->name),
            Build::keyword('DEFINITION'), Expressions::write($definition->definition),
            ...($definition->organization === null ? [] : [Build::keyword('ORGANIZATION'), Expressions::write($definition->organization->name), Build::keyword('IDENTIFIED BY ' . $definition->organization->identifier)]),
            ...($definition->description === null ? [] : [Build::keyword('DESCRIPTION'), Expressions::write($definition->description)]),
        ]);
    }
}
