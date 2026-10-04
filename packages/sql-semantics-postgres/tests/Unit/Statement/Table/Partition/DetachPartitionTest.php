<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachPartition::class)]
#[Medium]
final class DetachPartitionTest extends TestCase
{
    public function testDeriveClauseResolvesThePartition(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE p DETACH PARTITION p1', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable::class, $n1);
        $n2 = $n1->commands[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachPartition::class, $n2);
        $n3 = $statement->facts->relation($n2->partition)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $n3);
        self::assertSame('SqlSemantics\\Statement\\Reference\\Table\\MissingTable', $n3::class);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE p DETACH PARTITION p1 CONCURRENTLY', []);
        self::assertSame('ALTER TABLE p DETACH PARTITION p1 CONCURRENTLY', $statement->toString());
    }
}
