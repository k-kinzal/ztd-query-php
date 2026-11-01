<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;

#[CoversClass(UnpartitionedTable::class)]
#[Small]
final class UnpartitionedTableTest extends TestCase
{
    public function testMessageIsTheServerMessage(): void
    {
        self::assertSame('PARTITION () clause on non partitioned table', (new UnpartitionedTable())->message());
    }
}
