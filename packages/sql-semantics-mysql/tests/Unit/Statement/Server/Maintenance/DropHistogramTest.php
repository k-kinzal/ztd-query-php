<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DropHistogram::class)]
#[Medium]
final class DropHistogramTest extends TestCase
{
    public function testRenderWritesTheColumns(): void
    {
        self::assertSame('ANALYZE TABLE t DROP HISTOGRAM ON a, `b c`', (new Semantics(Dialect::MySql))->analyze('analyze table t drop histogram on a, `b c`')->toString());
    }

    public function testColumnsAnswersTheColumnNames(): void
    {
        self::assertSame('a', (new DropHistogram([new Name('a')]))->columns()[0]->value);
    }
}
