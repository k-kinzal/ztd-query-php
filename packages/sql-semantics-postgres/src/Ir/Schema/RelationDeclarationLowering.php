<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndexLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchemaLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Query\CreateTableAsLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedViewLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema as ParsedCreateSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAs as ParsedCreateTableAs;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex as ParsedCreateIndex;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateMaterializedView as ParsedCreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView as ParsedCreateView;

/**
 * Routes relation and index declarations to their owning boundaries.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RelationDeclarationLowering
{
    /**
     * Publishes a checked table, view, materialized view or index declaration.
     */
    public function lower(
        BoundStatement $operation,
        ParsedCreateIndex|ParsedCreateTable|ParsedCreateTableAs|ParsedCreateView|ParsedCreateMaterializedView|ParsedCreateSchema $statement,
    ): CreateIndex|CreateSchema|CreateTable|CreateTableAs|CreateView|CreateMaterializedView {
        return match (true) {
            $statement instanceof ParsedCreateIndex => (new CreateIndexLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateSchema => (new CreateSchemaLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateTable => (new CreateTableLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateTableAs => (new CreateTableAsLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateView => (new CreateViewLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateMaterializedView => (new CreateMaterializedViewLowering())->lower($operation, $statement),
        };
    }
}
