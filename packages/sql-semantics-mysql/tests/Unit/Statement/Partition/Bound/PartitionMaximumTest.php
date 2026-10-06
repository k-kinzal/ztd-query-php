<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Bound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum;

#[CoversClass(PartitionMaximum::class)]
#[Medium]
final class PartitionMaximumTest extends TestCase
{
    public function testRenderWritesTheKeyword(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE COLUMNS (a) (PARTITION p VALUES LESS THAN (MAXVALUE))', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY RANGE COLUMNS (a) (PARTITION p VALUES LESS THAN (maxvalue))')->toString());
    }
}
