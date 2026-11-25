<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateViewLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewAlgorithm;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewSecurity;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateViewLowering::class)]
#[Medium]
final class CreateViewLoweringTest extends TestCase
{
    public function testLowerResolvesTheDeclaringOptionsAndExactHeading(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.events (id INT)');
        $definition = $inspection->inspect('CREATE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW app.active (event) AS SELECT id FROM app.events', [$table]);
        self::assertInstanceOf(CreateView::class, $definition->statement);
        $view = (new CreateViewLowering())->lower($definition->binding(), $definition->statement);

        self::assertSame(ViewAlgorithm::Merge, $view->algorithm);
        self::assertSame(ViewAlgorithm::Merge, $view->installedAlgorithm());
        self::assertSame(ViewSecurity::Invoker, $view->security);
        self::assertSame('event', $view->columns[0]->name->value);
    }
}
