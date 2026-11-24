<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLike;
use SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLikeLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAsLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike as ParsedCreateTableLike;
use SqlSemantics\Platform\MySql\Statement\View\CreateView as ParsedCreateView;

/**
 * Dispatches checked relation declarations.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateRelationLowering
{
    /**
     * Publishes the declaration of a table or a view with its own checks.
     */
    public function lower(
        BoundStatement $operation,
        ParsedCreateTable|ParsedCreateTableLike|ParsedCreateView $statement,
    ): CreateTable|CreateTableLike|CreateTableAs|CreateView {
        return match (true) {
            $statement instanceof ParsedCreateTable && $statement->query !== null => (new CreateTableAsLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateTable => (new CreateTableLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateTableLike => (new CreateTableLikeLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateView => (new CreateViewLowering())->lower($operation, $statement),
        };
    }
}
