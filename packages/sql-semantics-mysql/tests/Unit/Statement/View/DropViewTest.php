<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior;
use SqlSemantics\Platform\MySql\Statement\View\DropView;

#[CoversClass(DropView::class)]
#[Medium]
final class DropViewTest extends TestCase
{
    public function testDeriveStatementDerivesNothing(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('DROP VIEW v CASCADE');
        $statement = $create->statement;
        self::assertInstanceOf(DropView::class, $statement);

        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('DROP VIEW IF EXISTS v, db.w RESTRICT', (new Semantics(Dialect::MySql))->analyze('DROP VIEW IF EXISTS v, db.w RESTRICT')->toString());
    }
}
