<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation;

#[CoversClass(ShowCollation::class)]
#[Medium]
final class ShowCollationTest extends TestCase
{
    public function testDeriveStatementResolvesTheCondition(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW COLLATION WHERE Id > 1');
        self::assertInstanceOf(ShowCollation::class, $show->statement);
        self::assertSame([], $show->facts->diagnostics);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW COLLATION WHERE Id > 1');
        self::assertInstanceOf(ShowCollation::class, $show->statement);
        self::assertSame('Pad_attribute', $show->facts->relation($show->statement)->shape->slots[6]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW COLLATION WHERE Id > 1', (new Semantics(Dialect::MySql))->analyze('SHOW COLLATION WHERE Id > 1')->toString());
    }
}
