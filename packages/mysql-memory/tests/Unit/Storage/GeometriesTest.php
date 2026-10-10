<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Instance;
use MySqlMemory\Storage\Geometries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Geometries::class)]
#[Small]
final class GeometriesTest extends TestCase
{
    public function testDefaultRefusesAnOmittedRequiredGeometryWithoutStrictMode(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("SET sql_mode=''");
        $session->query('CREATE TABLE t(a POINT NOT NULL)');
        $this->expectExceptionCode(1364);

        $session->query('INSERT IGNORE INTO t VALUES()');
    }

    public function testValueRefusesNullInARequiredGeometryEvenWithIgnore(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a POINT NOT NULL)');
        $this->expectExceptionCode(3673);

        $session->query('INSERT IGNORE INTO t VALUES(NULL)');
    }

    public function testValueRefusesTheWrongSridThroughTheCommonStore(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a POINT SRID 4326)');
        $this->expectExceptionCode(3643);
        $this->expectExceptionMessage("The SRID of the geometry does not match the SRID of the column 'a'.");

        $session->query('INSERT INTO t VALUES(Point(1,2))');
    }

    public function testValueRefusesAnIncompatibleGeometrySubtypeEvenWithIgnore(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a POINT)');
        $this->expectExceptionCode(1416);

        $session->query('INSERT IGNORE INTO t VALUES(LineString(Point(0,0),Point(1,1)))');
    }
}
