<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewProjectionAliases;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(ViewProjectionAliases::class)]
#[Medium]
final class ViewProjectionAliasesTest extends TestCase
{
    public function testCheckAdmitsASixtyFourCharacterRequestedAlias(): void
    {
        $alias = ' ' . str_repeat('漢', 63);
        (new ViewProjectionAliases())->check($alias);
        $view = (new Semantics())->analyze('CREATE VIEW app.checked (visible) AS SELECT 1 AS `' . $alias . '`');

        self::assertInstanceOf(CreateView::class, $view);
        self::assertSame('visible', $view->columns[0]->name->value);
        self::assertStringEndsWith('SELECT 1 AS `' . $alias . '`', $view->toString());
    }
    public function testValidUsesNativeAsciiWhitespace(): void
    {
        $rule = new ViewProjectionAliases();

        self::assertFalse($rule->valid("name\t"));
        self::assertTrue($rule->valid("ends\u{00a0}"));
    }
}
