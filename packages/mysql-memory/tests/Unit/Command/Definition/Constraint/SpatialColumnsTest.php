<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\SpatialColumns;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpatialColumns::class)]
#[Small]
final class SpatialColumnsTest extends TestCase
{
    public function testPrepareChecksSpatialReferencesBeforeIndexColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $this->expectExceptionCode(3548);
        $this->expectExceptionMessage("There's no spatial reference system with SRID 99999999.");

        $session->query('CREATE TABLE t(a POINT SRID 99999999,KEY k(missing))');
    }

    public function testCheckUsesTheLiveCatalogAndTheLastRestriction(): void
    {
        $instance = new Instance();
        $instance->registry->spatialCatalog->define(99999999, 'Custom reference');
        $session = $instance->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a POINT SRID 1 SRID 99999999)');
        $result = $session->query('SHOW CREATE TABLE t')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringContainsString('/*!80003 SRID 99999999 */', (string) $result->rows[0][1]);
        $instance->registry->spatialCatalog->drop(99999999);
        $this->expectExceptionCode(3548);

        $session->query('ALTER TABLE t ADD b POINT SRID 99999999');
    }
}
