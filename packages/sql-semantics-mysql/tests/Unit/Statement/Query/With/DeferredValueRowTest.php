<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValueRow;

#[CoversClass(DeferredValueRow::class)]
#[Medium]
final class DeferredValueRowTest extends TestCase
{
    public function testRenderKeepsEveryPositionOfTheUnusedTemplate(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (VALUES ROW(7, ROW(1,2), DEFAULT)) SELECT 9');
        self::assertSame('WITH c AS (VALUES ROW(7, ROW(1, 2), DEFAULT)) SELECT 9', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }
}
