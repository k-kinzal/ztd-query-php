<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\MySql\Statement\View\CreateView as ParsedCreateView;

/**
 * Dispatches checked persistent relation declarations.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateRelationLowering
{
    /**
     * Publishes the declaration of a table or a view with its own checks.
     */
    public function lower(BoundStatement $operation, ParsedCreateTable|ParsedCreateView $statement): CreateTable|CreateView
    {
        return $statement instanceof ParsedCreateTable
            ? (new CreateTableLowering())->lower($operation, $statement)
            : (new CreateViewLowering())->lower($operation, $statement);
    }
}
