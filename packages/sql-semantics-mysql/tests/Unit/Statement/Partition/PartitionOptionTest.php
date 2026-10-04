<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption;

#[CoversClass(PartitionOption::class)]
#[Medium]
final class PartitionOptionTest extends TestCase
{
    public function testRenderWritesTheKeywordsAndTheValue(): void
    {
        self::assertSame("ALTER TABLE t ADD PARTITION (PARTITION p ENGINE = InnoDB DATA DIRECTORY = '/d' MAX_ROWS = 10)", (new Semantics(Dialect::MySql))->analyze("ALTER TABLE t ADD PARTITION (PARTITION p STORAGE ENGINE InnoDB DATA DIRECTORY '/d' MAX_ROWS 10)")->toString());
    }
}
