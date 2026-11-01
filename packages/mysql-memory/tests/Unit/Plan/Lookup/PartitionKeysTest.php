<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Lookup;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Lookup\PartitionKeys;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionKeys::class)]
#[Small]
final class PartitionKeysTest extends TestCase
{
    public function testPlanSkipsAFileExcludedByThePartitionKey(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; INSERT INTO p VALUES(0),(1); ALTER TABLE p DISCARD PARTITION p0 TABLESPACE');
        $result = $session->query('SELECT * FROM p WHERE id=1')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testPlanIntersectsExplicitPartitionSelection(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; ALTER TABLE p DISCARD PARTITION p0 TABLESPACE');
        $result = $session->query('SELECT * FROM p PARTITION(p0) WHERE id=1')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testBindingsExcludeContradictoryIntegerEqualities(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; ALTER TABLE p DISCARD TABLESPACE');
        $result = $session->query('SELECT * FROM p WHERE id=0 AND id=1')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }

    public function testSelectedPrunesTheFilesReadBySingleTableWrites(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE p(id INT) PARTITION BY HASH(id) PARTITIONS 2; INSERT INTO p VALUES(0),(1); ALTER TABLE p DISCARD PARTITION p0 TABLESPACE');
        $session->query('UPDATE p SET id=3 WHERE id=1; DELETE FROM p WHERE id=3');
        $result = $session->query('SELECT * FROM p PARTITION(p1)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
    }
}
