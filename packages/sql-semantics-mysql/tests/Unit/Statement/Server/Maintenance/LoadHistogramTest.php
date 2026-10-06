<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\LoadHistogram;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(LoadHistogram::class)]
#[Medium]
final class LoadHistogramTest extends TestCase
{
    public function testRenderWritesTheData(): void
    {
        self::assertSame("ANALYZE TABLE t UPDATE HISTOGRAM ON a USING DATA '{}'", (new Semantics(Dialect::MySql))->analyze("analyze table t update histogram on a using data '{}'")->toString());
    }

    public function testColumnsAnswersTheColumnNames(): void
    {
        self::assertSame('a', (new LoadHistogram([new Name('a')], new Text('{}')))->columns()[0]->value);
    }
}
