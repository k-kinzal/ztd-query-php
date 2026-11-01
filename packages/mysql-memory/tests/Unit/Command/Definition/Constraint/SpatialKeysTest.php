<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\SpatialKeys;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpatialKeys::class)]
#[Small]
final class SpatialKeysTest extends TestCase
{
    public function testEngineRefusesGeometricMemoryColumnsEvenWithoutIndexes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $this->expectExceptionMessage("The storage engine for the table doesn't support GEOMETRY");

        $session->query('CREATE TABLE t(a POINT) ENGINE=MEMORY');
    }

    public function testResolveBuildsASpatialKeyFromTheResolvedColumnType(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a POINT NOT NULL,KEY k(a))');
        $result = $session->query('SHOW CREATE TABLE t')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertStringContainsString('SPATIAL KEY `k` (`a`)', (string) $result->rows[0][1]);
    }

    public function testWarnDistinguishesRestrictedAndUnrestrictedSpatialColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a POINT NOT NULL,KEY k(a))');
        $warning = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warning);
        self::assertSame('3674', $warning->rows[0][1]);
        $session->query('CREATE TABLE restricted(a POINT NOT NULL SRID 0,KEY k(a))');
        $restricted = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $restricted);

        self::assertSame([], $restricted->rows);
    }
    public function testResolveReportsTheFiveSevenRtreeDiagnostic(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $this->expectExceptionCode(1070);
        $this->expectExceptionMessage('Too many key parts specified; max 1 parts allowed');

        $session->query('CREATE TABLE t(a INT,KEY k(a) USING RTREE)');
    }

}
