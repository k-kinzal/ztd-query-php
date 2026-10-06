<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeBound::class)]
#[Medium]
final class RangeBoundTest extends TestCase
{
    public function testDeriveClauseSeesNoColumnButTheInfiniteBounds(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (MINVALUE) TO (x)', []);
        self::assertSame([
          0 => 'Relation p does not exist.',
          1 => 'Column x does not exist.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (1, MINVALUE) TO (10, MAXVALUE)', []);
        self::assertSame('CREATE TABLE p1 PARTITION OF p FOR VALUES FROM (1, minvalue) TO (10, maxvalue)', $statement->toString());
    }
}
