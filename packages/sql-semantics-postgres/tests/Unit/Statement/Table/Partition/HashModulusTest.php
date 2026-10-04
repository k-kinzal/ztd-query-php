<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\HashModulus::class)]
#[Medium]
final class HashModulusTest extends TestCase
{
    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH ("modulus" 2, remainder 0)', []);
        self::assertSame('CREATE TABLE p1 PARTITION OF p FOR VALUES WITH (modulus 2, remainder 0)', $statement->toString());
    }
}
