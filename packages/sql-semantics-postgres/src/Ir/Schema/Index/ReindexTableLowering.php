<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;

/**
 * Turns the checked table use into an immutable local index owner.
 *
 * @visibility SqlSemantics
 */
final class ReindexTableLowering
{
    /**
     * Publishes a rebuild request for a checked standalone index owner.
     */
    public function lower(BoundStatement $operation, Reindex $statement): ReindexTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive, 'REINDEX TABLE requires the native PostgreSQL index profile.');
        Check::input($statement->target === ReindexTarget::Table && $statement->object instanceof QualifiedName
            && $statement->object->catalog === null, 'REINDEX TABLE needs a local schema and owner.');
        foreach ($statement->options as $option) {
            Check::input($option->option() === 'concurrently', 'A complete REINDEX TABLE accepts only concurrency options.');
        }
        $resolution = $operation->facts->relation($statement)->table;
        Check::input($resolution instanceof DeclaredTable, 'REINDEX TABLE requires a resolved index owner.');
        $name = $resolution->table->name;
        $kind = $resolution->table->kind;
        $schema = $name->schema ?? $context->declarationSchema;
        Check::input($name->catalog === null && ($kind === RelationKind::BaseTable || $kind === RelationKind::MaterializedView), 'REINDEX TABLE requires a local table or materialized view.');
        Check::input(!$context->indexNamespace->partitioned($schema, $name->name), 'REINDEX TABLE requires a nonpartitioned owner.');

        return new ReindexTable(
            new ReindexTableTarget(
                new Identifier($schema->value),
                new Identifier($name->name->value),
                $kind === RelationKind::BaseTable ? ReindexTableKind::BaseTable : ReindexTableKind::MaterializedView,
            ),
            $statement->requestsConcurrentBuild() ? IndexRebuildMode::Concurrent : IndexRebuildMode::Standard,
            $profile->grammar,
        );
    }
}
