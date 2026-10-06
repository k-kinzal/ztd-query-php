<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Bound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows;

#[CoversClass(ValuesInRows::class)]
#[Medium]
final class ValuesInRowsTest extends TestCase
{
    public function testDeriveBoundDerivesEveryTuple(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN ((1, b), (c, 2)))');

        self::assertCount(2, $alter->facts->diagnostics);
    }

    public function testRenderWritesTheTuples(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN ((1, 2), (3, 4)))', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN ((1,2),(3,4)))')->toString());
    }
}
