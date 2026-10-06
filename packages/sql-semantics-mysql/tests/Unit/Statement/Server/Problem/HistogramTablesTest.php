<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables;

#[CoversClass(HistogramTables::class)]
#[Small]
final class HistogramTablesTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('A histogram request names one table, not 3.', (new HistogramTables(3))->message());
    }
}
