<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Access\SchemaGrantLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Index\SchemaIndexLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View\SchemaViewLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View\SchemaViewOrder;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema as ParsedCreateSchema;

/**
 * Resolves the ownership expression before publishing a schema declaration.
 *
 * @visibility SqlSemantics
 */
final class CreateSchemaLowering
{
    /**
     * Publishes the standalone schema declaration.
     * @throws InvalidConstruction When the authorization does not select a single owner
     */
    public function lower(BoundStatement $operation, ParsedCreateSchema $statement): CreateSchema
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'CREATE SCHEMA requires the native PostgreSQL namespace profile.'
        );
        $owner = (new SchemaOwnerLowering())->lower($context, $statement->authorization);
        $name = $statement->name === null ? null : new Identifier($statement->name->value);
        $resolved = $name ?? ($owner instanceof Identifier ? $owner : null);
        $sequences = (new SchemaSequenceLowering())->lower($context, $statement, $resolved);
        $tables = (new SchemaTableLowering())->lower($context, $statement, $resolved);
        $views = (new SchemaViewLowering())->lower($operation, $statement, $resolved);
        $release = $context->profile->grammar;
        $occupied = SchemaRelationOrder::check($resolved, $statement->ifNotExists, $release, $sequences, $tables);
        $occupied = SchemaViewOrder::check($resolved, $statement->ifNotExists, $release, $occupied, $views);
        $indexes = (new SchemaIndexLowering())->lower($operation, $statement, $resolved, $occupied);
        $grants = (new SchemaGrantLowering())->lower($operation, $statement, $resolved, $indexes);

        return new CreateSchema($name, $owner, $statement->ifNotExists, $release, $tables, $sequences, $views, $indexes, $grants);
    }
}
