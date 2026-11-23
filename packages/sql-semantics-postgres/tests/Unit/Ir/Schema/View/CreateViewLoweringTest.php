<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewCheckOption;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(CreateViewLowering::class)]
#[Medium]
final class CreateViewLoweringTest extends TestCase
{
    public function testLowerCompletesAHeadingAndResolvesTheUnqualifiedCheck(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.entries (id int, active boolean)']);
        $view = $semantics->analyze('CREATE VIEW available (item) AS SELECT id, active FROM entries WHERE id > 0 WITH CHECK OPTION', $context);

        self::assertInstanceOf(CreateView::class, $view);
        self::assertSame(['item', 'active'], array_column($view->columns, 'value'));
        self::assertSame(ViewCheckOption::Cascaded, $view->checkOption);
    }
}
