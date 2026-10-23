<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Partition\Reorganization;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reorganization::class)]
#[Small]
final class ReorganizationTest extends TestCase
{
    public function testRewritePreservesRowsWhileReplacingThePartitionNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT) PARTITION BY RANGE(a) (PARTITION old VALUES LESS THAN MAXVALUE); INSERT INTO t VALUES(1),(11)');
        $session->query('ALTER TABLE t REORGANIZE PARTITION old INTO (PARTITION p0 VALUES LESS THAN(10),PARTITION p1 VALUES LESS THAN MAXVALUE)');
        $result = $session->query('SELECT * FROM t PARTITION(p1)')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['11']], $result->rows);
    }

    public function testRolesRefusesAListBoundInAnExistingRangeTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT) PARTITION BY RANGE(a) (PARTITION old VALUES LESS THAN MAXVALUE)');
        $this->expectExceptionCode(1480);
        $session->query('ALTER TABLE t REORGANIZE PARTITION old INTO (PARTITION p VALUES IN(1))');
    }

    public function testValidateRefusesAChangedInteriorRangeBeforePublishingIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT) PARTITION BY RANGE(a) (PARTITION p0 VALUES LESS THAN(10),PARTITION p1 VALUES LESS THAN MAXVALUE)');
        $this->expectExceptionCode(1520);
        $session->query('ALTER TABLE t REORGANIZE PARTITION p0 INTO (PARTITION p VALUES LESS THAN(11))');
    }

    public function testAffectedCountsOnlySelectedRowsOnMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT) PARTITION BY RANGE(a) (PARTITION p0 VALUES LESS THAN(10),PARTITION p1 VALUES LESS THAN MAXVALUE); INSERT INTO t VALUES(1),(11)');
        $result = $session->query('ALTER TABLE t REORGANIZE PARTITION p0 INTO (PARTITION p VALUES LESS THAN(10))')[0];
        self::assertInstanceOf(Completion::class, $result);
        self::assertSame(1, $result->affectedRows);
    }
    public function testSubpartitionsExpandsOmittedDefinitionsAgainstTheStoredCount(): void
    {
        $definition = new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition(new \SqlSemantics\Statement\Identifier\Name('p0'), null, [], []);
        $resolved = (new Reorganization())->subpartitions([$definition], 2);
        self::assertSame(['p0sp0', 'p0sp1'], array_map(static fn ($sub): string => $sub->name->value, $resolved[0]->subpartitions));
    }

}
