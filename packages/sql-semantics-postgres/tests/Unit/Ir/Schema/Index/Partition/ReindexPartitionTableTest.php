<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\ReindexPartitionTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(ReindexPartitionTable::class)]
#[Small]
final class ReindexPartitionTableTest extends TestCase
{
    public function testToStringSelectsTheExactPartitionAndRequestedMode(): void
    {
        $target = new TableName(new Identifier('reporting.data'), new Identifier('nested"data'));
        $root = new ReindexPartitionTable($target, PartitionRebuildKind::Branch, IndexRebuildMode::Concurrent);

        self::assertSame('REINDEX TABLE CONCURRENTLY "reporting.data"."nested""data"', $root->toString());
    }

    public function testProfileIdRetainsTheDialectRelease(): void
    {
        $target = new TableName(new Identifier('public'), new Identifier('partition'));
        $leaf = new ReindexPartitionTable($target, PartitionRebuildKind::Leaf, release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $leaf->profileId());
    }
}
