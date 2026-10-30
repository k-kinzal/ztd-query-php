<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\CoalescePartition;

#[CoversClass(CoalescePartition::class)]
#[Medium]
final class CoalescePartitionTest extends TestCase
{
    #[DataProvider('providerRefusedCounts')]
    public function testDeriveCommandRetainsIntegerSyntaxFailures(string $version, string $count): void
    {
        $sql = 'ALTER TABLE t COALESCE PARTITION ' . $count;
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

    public function testDeriveCommandAcceptsAnIntegerCount(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t COALESCE PARTITION 1')->diagnostics());
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t COALESCE PARTITION LOCAL 3', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t COALESCE PARTITION LOCAL 3')->toString());
    }
}
