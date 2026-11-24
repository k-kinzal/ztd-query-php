<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSize;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSpill;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCacheSize;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Inventory\InventoryPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\ForeignKeyAudit;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\AutoVacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reclaim\IncrementalVacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\Vacuum;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Vacuum\VacuumLowering;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Analyze as ParsedAnalyze;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Reindex as ParsedReindex;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Vacuum as ParsedVacuum;

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
    public function lower(BoundStatement $operation, ParsedAnalyze|ParsedReindex|Pragma|ParsedVacuum $statement): Analyze|Reindex|FlagPragma|InventoryPragma|HeaderPragma|IntegrityAudit|ForeignKeyAudit|IndexInspection|TableInspection|TableListing|PagePragma|AutoVacuum|IncrementalVacuum|CacheSize|DefaultCacheSize|CacheSpill|Vacuum
    {
        return match (true) {
            $statement instanceof ParsedAnalyze => (new AnalyzeLowering())->lower($operation, $statement),
            $statement instanceof ParsedReindex => (new ReindexLowering())->lower($operation, $statement),
            $statement instanceof Pragma => (new PragmaLowering())->lower($operation, $statement),
            $statement instanceof ParsedVacuum => (new VacuumLowering())->lower($operation, $statement),
        };
    }
}
