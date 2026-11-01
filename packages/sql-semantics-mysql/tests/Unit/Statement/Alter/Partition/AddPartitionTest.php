<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition;

#[CoversClass(AddPartition::class)]
#[Medium]
final class AddPartitionTest extends TestCase
{
    #[DataProvider('providerRefusedCounts')]
    public function testDeriveCommandRetainsIntegerSyntaxFailures(string $version, string $count): void
    {
        $sql = 'ALTER TABLE t ADD PARTITION PARTITIONS ' . $count;
        $statement = (new Semantics(Dialect::MySql, $version))->analyze($sql);
        self::assertSame($sql, $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber::class, $statement->diagnostics()[0]);
        $notice = $statement->warnings()[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $notice);
        self::assertTrue($notice->aborts);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax, $notice->phase);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRefusedCounts(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['1.0', '1e0', '18446744073709551616'] as $count) {
                yield $release->value . ':' . $count => [$release->value, $count];
            }
        }
    }

    public function testDeriveCommandDerivesTheValues(): void
    {
        self::assertCount(1, (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (x))')->diagnostics());
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION LOCAL PARTITIONS 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t ADD PARTITION LOCAL PARTITIONS 2')->toString());
    }
    public function testDefinitionsRequireOneSubpartitionCount(): void
    {
        $definitions = [new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition(new \SqlSemantics\Statement\Identifier\Name('p0'), null, [], []), new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition(new \SqlSemantics\Statement\Identifier\Name('p1'), null, [], [new \SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition(new \SqlSemantics\Statement\Identifier\Name('s'), [])])];
        $this->expectExceptionMessage('Wrong number of subpartitions defined, mismatch with previous setting');
        new AddPartition(null, $definitions);
    }

}
