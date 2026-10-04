<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView;

#[CoversClass(ShowCreateView::class)]
#[Medium]
final class ShowCreateViewTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW CREATE VIEW db.v');
        self::assertInstanceOf(ShowCreateView::class, $show->statement);
        self::assertSame('Create View', $show->field(1)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE VIEW db.v', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW CREATE VIEW db.v')->toString());
    }
}
