<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UpdateHistogram::class)]
#[Medium]
final class UpdateHistogramTest extends TestCase
{
    public function testRenderWritesBucketsAndUpdate(): void
    {
        self::assertSame('ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 4 BUCKETS AUTO UPDATE', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('analyze table t update histogram on a with 4 buckets auto update')->toString());
    }

    public function testColumnsAnswersTheColumnNames(): void
    {
        self::assertSame('a', (new UpdateHistogram([new Name('a')]))->columns()[0]->value);
    }
}
