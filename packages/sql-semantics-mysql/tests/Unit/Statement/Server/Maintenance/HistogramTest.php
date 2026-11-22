<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\Histogram;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Histogram::class)]
#[Medium]
final class HistogramTest extends TestCase
{
    public function testColumnsAnswersTheColumnNames(): void
    {
        $analyze = (new Inspection())->inspect('ANALYZE TABLE t DROP HISTOGRAM ON a, b');
        self::assertInstanceOf(AnalyzeTable::class, $analyze->statement);

        self::assertSame('b', $analyze->statement->histogram?->columns()[1]->value);
    }
}
