<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable as ParsedTruncateTable;

/**
 * Resolves each target of a PostgreSQL truncation.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TruncateLowering
{
    /**
     * Publishes a checked target list with the effective native policies.
     */
    public function lower(BoundStatement $operation, ParsedTruncateTable $statement): TruncateTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input(
            $profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'TRUNCATE requires the native naming profile.'
        );
        $targets = [];
        foreach ($statement->tables as $reference) {
            $resolution = $operation->facts->relation($reference)->table;
            Check::input(
                $resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable
                && $reference->name->catalog === null && $resolution->table->name->catalog === null,
                'TRUNCATE requires a resolved persistent base table.'
            );
            $name = $resolution->table->name;
            $schema = ($name->schema ?? $context->declarationSchema)->value;
            $targets[] = new TruncateTarget(new TableName(new Identifier($schema), new Identifier($name->name->value)), $reference->only);
        }

        return new TruncateTable($targets, $statement->restartIdentity ?? false, $statement->behavior === DropBehavior::Cascade, $profile->grammar);
    }
}
