<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ChangeOwner;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename;

/**
 * Publishes a checked PostgreSQL namespace change.
 *
 * @visibility SqlSemantics
 */
final class SchemaAlterLowering
{
    /**
     * Resolves the namespace and the destination or owner.
     */
    public function lower(BoundStatement $operation, Rename|ChangeOwner $statement): RenameSchema|ChangeSchemaOwner
    {
        Check::input($statement->kind === ObjectKind::Schema, 'A namespace change requires ALTER SCHEMA.');
        $context = $operation->context;
        $target = (new SchemaTargetLowering())->lower($context, $statement->object);
        $release = $context->profile->grammar;

        if ($statement instanceof Rename) {
            return new RenameSchema($target, new Identifier($statement->newName->value), $release);
        }

        return new ChangeSchemaOwner($target, (new SchemaOwnerLowering())->lower($context, $statement->owner), $release);
    }
}
