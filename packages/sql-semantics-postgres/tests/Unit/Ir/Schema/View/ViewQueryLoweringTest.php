<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewQueryLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(ViewQueryLowering::class)]
#[Medium]
final class ViewQueryLoweringTest extends TestCase
{
    public function testLowerKeepsNativeNamesAfterLeadingOverrides(): void
    {
        $view = (new Semantics())->analyze('CREATE VIEW shaped (first) AS (VALUES (1, true))');
        self::assertInstanceOf(CreateView::class, $view);
        self::assertSame(['first', 'column2'], array_column($view->columns, 'value'));
    }
}
