<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachPartition::class)]
#[Medium]
final class AttachPartitionTest extends TestCase
{
    public function testDeriveClauseResolvesThePartition(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('ALTER TABLE p ATTACH PARTITION t DEFAULT', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable::class, $n1);
        $n2 = $n1->commands[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachPartition::class, $n2);
        $n3 = $statement->facts->relation($n2->partition)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $n3);
        self::assertSame(true, $n3->table === $context[0]);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE p ATTACH PARTITION p1 FOR VALUES FROM (1) TO (10)', []);
        self::assertSame('ALTER TABLE p ATTACH PARTITION p1 FOR VALUES FROM (1) TO (10)', $statement->toString());
    }
}
