<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\SpatialReferences;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpatialReferences::class)]
#[Small]
final class SpatialReferencesTest extends TestCase
{
    public function testRowsListsTheSystemsBySrid(): void
    {
        $s = (new Instance())->connect();

        $result1 = $s->query('SELECT SRS_NAME, SRS_ID, ORGANIZATION, ORGANIZATION_COORDSYS_ID FROM information_schema.ST_SPATIAL_REFERENCE_SYSTEMS LIMIT 2')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['', '0', null, null], ['Anguilla 1957 / British West Indies Grid', '2000', 'EPSG', '2000']], $result1->rows);
    }
}
