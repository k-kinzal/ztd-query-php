<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Selection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem::class)]
#[Medium]
final class DeferredOrderItemTest extends TestCase
{
    public function testRenderRetainsTheCompleteOperandAndDirection(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT a FROM t ORDER BY (a, 2) DESC', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT a FROM t ORDER BY (a,2) DESC')->toString());
    }
}
