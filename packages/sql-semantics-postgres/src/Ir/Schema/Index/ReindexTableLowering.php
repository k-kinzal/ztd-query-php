<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\ReindexPartitionTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespaceLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartitionRole;
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
    public function lower(BoundStatement $operation, Reindex $statement): ReindexTable|ReindexPartitionTable
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive, 'REINDEX TABLE requires the native PostgreSQL index profile.');
        Check::input($statement->target === ReindexTarget::Table && $statement->object instanceof QualifiedName, 'REINDEX TABLE needs a qualified-name owner.');
        $tablespace = (new IndexTablespaceLowering())->resolve($statement, $context);
        $resolution = $operation->facts->relation($statement)->table;
        Check::input($resolution instanceof DeclaredTable, 'REINDEX TABLE requires a resolved index owner.');
        $name = $resolution->table->name;
        $kind = $resolution->table->kind;
        $schema = $name->schema ?? $context->declarationSchema;
        Check::input($name->catalog === null && ($kind === RelationKind::BaseTable || $kind === RelationKind::MaterializedView), 'REINDEX TABLE requires a local table or materialized view.');
        $partition = $context->indexNamespace->partitionRole($schema, $name->name);
        $namespace = new Identifier($schema->value);
        $relation = new Identifier($name->name->value);
        $mode = $statement->requestsConcurrentBuild() ? IndexRebuildMode::Concurrent : IndexRebuildMode::Standard;
        if ($partition !== null) {
            Check::input($kind === RelationKind::BaseTable, 'A partition index owner requires a declared base table.');
            $scope = match ($partition) {
                TablePartitionRole::Branch => PartitionRebuildKind::Branch,
                TablePartitionRole::Leaf => PartitionRebuildKind::Leaf,
            };
            return new ReindexPartitionTable(new TableName($namespace, $relation), $scope, $mode, $profile->grammar, $statement->reportsProgress(), $tablespace);
        }

        return new ReindexTable(
            new ReindexTableTarget(
                $namespace,
                $relation,
                $kind === RelationKind::BaseTable ? ReindexTableKind::BaseTable : ReindexTableKind::MaterializedView,
            ),
            $mode,
            $profile->grammar,
            $statement->reportsProgress(),
            $tablespace,
        );
    }
}
