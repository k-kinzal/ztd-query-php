<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use MySqlMemory\Registry\SpatialCatalog;
use MySqlMemory\System\Reading;
use MySqlMemory\System\SystemRows;
use Override;

/**
 * The rows of INFORMATION_SCHEMA.ST_SPATIAL_REFERENCE_SYSTEMS: the spatial reference systems of the server, by SRID.
 *
 * The systems a server is installed with are EPSG systems, numbered by EPSG as by MySQL, but
 * SRID 0. The emulator does not carry their definitions, which it reports empty.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-st-spatial-reference-systems-table.html.
 *
 * @visibility MySqlMemory
 */
final class SpatialReferences implements SystemRows
{
    /**
     * Answers a row for each spatial reference system.
     */
    #[Override]
    public function rows(Reading $reading): array
    {
        $catalog = $reading->instance->registry->spatialCatalog;
        $installed = SpatialCatalog::installed();
        $rows = [];
        foreach (array_unique([...array_keys($installed), ...array_keys($catalog->changed)]) as $srid) {
            $name = $catalog->name($srid);
            if ($name === null) {
                continue;
            }
            $rows[$srid] = ['SRS_NAME' => $name, 'SRS_ID' => $srid, 'ORGANIZATION' => $srid === 0 || isset($catalog->changed[$srid]) ? null : 'EPSG', 'ORGANIZATION_COORDSYS_ID' => $srid === 0 || isset($catalog->changed[$srid]) ? null : $srid, 'DEFINITION' => '', 'DESCRIPTION' => null];
        }
        ksort($rows);

        return array_values($rows);
    }
}
