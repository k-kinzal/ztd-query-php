<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionSpec::class)]
#[Medium]
final class PartitionSpecTest extends TestCase
{
    public function testDeriveClauseReportsAnUnknownStrategy(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int) PARTITION BY tree (a)', []);
        self::assertSame([
          0 => 'unrecognized partitioning strategy "tree"',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int) PARTITION BY "RANGE" (a)', []);
        self::assertSame('CREATE TABLE n (a INT) PARTITION BY "RANGE" (a)', $statement->toString());
    }
}
