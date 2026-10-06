<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;

#[CoversClass(ShowWhere::class)]
#[Medium]
final class ShowWhereTest extends TestCase
{
    public function testRenderWritesTheCondition(): void
    {
        self::assertSame('SHOW STATUS WHERE Variable_name = 1', (new Semantics(Dialect::MySql))->analyze('SHOW STATUS WHERE Variable_name = 1')->toString());
    }
}
