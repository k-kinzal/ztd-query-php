<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\HashBound::class)]
#[Medium]
final class HashBoundTest extends TestCase
{
    public function testDeriveClauseReportsAnUnknownItem(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (modulus 4, rest 1)', []);
        self::assertSame([
          0 => 'Relation p does not exist.',
          1 => 'unrecognized hash partition bound specification "rest"',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (MODULUS 4, REMAINDER 1)', []);
        self::assertSame('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (modulus 4, remainder 1)', $statement->toString());
    }
}
