<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Bound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ValuesInRows::class)]
#[Medium]
final class ValuesInRowsTest extends TestCase
{
    public function testDeriveBoundDerivesEveryTuple(): void
    {
        $alter = (new Inspection())->diagnose('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN ((1, b), (c, 2)))');

        self::assertCount(2, $alter->facts->diagnostics);
    }

    public function testRenderWritesTheTuples(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN ((1, 2), (3, 4)))', (new Inspection())->diagnose('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN ((1,2),(3,4)))')->toString());
    }
}
