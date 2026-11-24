<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Analyze as ParsedAnalyze;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex as ParsedReindex;

/**
 * Dispatches the SQLite maintenance commands that have complete representations.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class MaintenanceLowering
{
    /**
     * Binds the command using its native maintenance rules.
     */
    public function lower(BoundStatement $operation, ParsedAnalyze|ParsedReindex|Pragma $statement): Analyze|Reindex|FlagPragma|InventoryPragma|IndexInspection|TableInspection|TableListing
    {
        return match (true) {
            $statement instanceof ParsedAnalyze => (new AnalyzeLowering())->lower($operation, $statement),
            $statement instanceof ParsedReindex => (new ReindexLowering())->lower($operation, $statement),
            $statement instanceof Pragma => (new PragmaLowering())->lower($operation, $statement),
        };
    }
}
