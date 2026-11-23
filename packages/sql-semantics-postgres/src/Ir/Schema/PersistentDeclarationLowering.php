<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedViewLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateMaterializedView as ParsedCreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView as ParsedCreateView;

/**
 * Routes checked persistent relation declarations to their owning boundaries.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class PersistentDeclarationLowering
{
    /**
     * Publishes the corresponding checked table, view or materialized view.
     */
    public function lower(
        BoundStatement $operation,
        ParsedCreateTable|ParsedCreateView|ParsedCreateMaterializedView $statement,
    ): CreateTable|CreateView|CreateMaterializedView {
        return match (true) {
            $statement instanceof ParsedCreateTable => (new CreateTableLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateView => (new CreateViewLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateMaterializedView => (new CreateMaterializedViewLowering())->lower($operation, $statement),
        };
    }
}
