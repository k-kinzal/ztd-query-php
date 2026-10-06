<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionBound::class)]
#[Medium]
final class PartitionBoundTest extends TestCase
{
    public function testBoundsArePartitionBounds(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p DEFAULT', []);
        self::assertSame('CREATE TABLE p1 PARTITION OF p DEFAULT', $statement->toString());
    }
}
