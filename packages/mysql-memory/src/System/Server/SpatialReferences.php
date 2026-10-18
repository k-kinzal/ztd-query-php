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
 * SRID 0. Installed definitions follow the selected release catalog. Created
 * or replaced systems retain every attribute supplied by their CREATE statement.
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
        $installed = SpatialCatalog::installed($catalog->version);
        $rows = [];
        foreach (array_unique([...array_keys($installed), ...array_keys($catalog->changed)]) as $srid) {
            $name = $catalog->name($srid);
            if ($name === null) {
                continue;
            }
            [$organization, $identifier, $definition, $description] = $catalog->attributes($srid);
            $rows[$srid] = ['SRS_NAME' => $name, 'SRS_ID' => $srid, 'ORGANIZATION' => $organization, 'ORGANIZATION_COORDSYS_ID' => $identifier, 'DEFINITION' => $definition, 'DESCRIPTION' => $description];
        }
        ksort($rows);

        return array_values($rows);
    }
}
