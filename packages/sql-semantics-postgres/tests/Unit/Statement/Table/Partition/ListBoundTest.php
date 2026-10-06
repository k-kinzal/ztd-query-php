<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\ListBound::class)]
#[Medium]
final class ListBoundTest extends TestCase
{
    public function testDeriveClauseSeesNoColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES IN (a, 1)', []);
        self::assertSame([
          0 => 'Relation p does not exist.',
          1 => 'Column a does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES IN (\'x\', NULL)', []);
        self::assertSame('CREATE TABLE p1 PARTITION OF p FOR VALUES IN (\'x\', NULL)', $statement->toString());
    }
}
