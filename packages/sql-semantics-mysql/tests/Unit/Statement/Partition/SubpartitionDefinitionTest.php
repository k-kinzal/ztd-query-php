<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SubpartitionDefinition::class)]
#[Medium]
final class SubpartitionDefinitionTest extends TestCase
{
    public function testRenderWritesTheNameAndTheOptions(): void
    {
        self::assertSame("ALTER TABLE t ADD PARTITION (PARTITION p (SUBPARTITION s0 COMMENT = 'c', SUBPARTITION s1))", (new Inspection())->inspect("ALTER TABLE t ADD PARTITION (PARTITION p (SUBPARTITION s0 COMMENT 'c', SUBPARTITION s1))")->toString());
    }
}
