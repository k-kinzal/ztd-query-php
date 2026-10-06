<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;

#[CoversClass(PartitionDefinition::class)]
#[Medium]
final class PartitionDefinitionTest extends TestCase
{
    public function testDerivePartitionDerivesTheValues(): void
    {
        self::assertCount(1, (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES LESS THAN (x))')->facts->diagnostics);
    }

    public function testRenderWritesEveryPart(): void
    {
        self::assertSame("ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1) COMMENT = 'c' (SUBPARTITION s))", (new Semantics(Dialect::MySql))->analyze("ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1) COMMENT 'c' (SUBPARTITION s))")->toString());
    }
}
