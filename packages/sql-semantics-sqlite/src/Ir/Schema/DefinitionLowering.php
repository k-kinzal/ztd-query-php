<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\CreateIndexLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Query\CreateTableAsLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\TriggerLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex as ParsedCreateIndex;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTableAs as ParsedCreateTableAs;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView as ParsedCreateView;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger as ParsedTrigger;

/**
 * Selects the construction boundary for a native schema declaration.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DefinitionLowering
{
    /**
     * Compiles the definition using the contract of its exact role.
     * @throws InvalidConstruction When the selected declaration cannot be completed
     */
    public function lower(
        BoundStatement $operation,
        ParsedCreateTable|ParsedCreateTableAs|ParsedCreateIndex|ParsedCreateView|ParsedTrigger $statement,
    ): CreateTable|CreateTableAs|CreateIndex|CreateView|CreateTrigger {
        return match (true) {
            $statement instanceof ParsedCreateTable => (new CreateTableLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateTableAs => (new CreateTableAsLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateIndex => (new CreateIndexLowering())->lower($operation, $statement),
            $statement instanceof ParsedCreateView => (new CreateViewLowering())->lower($operation, $statement),
            $statement instanceof ParsedTrigger => (new TriggerLowering())->lower($operation, $statement),
        };
    }
}
