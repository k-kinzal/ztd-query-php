<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(CreateView::class)]
#[Small]
final class CreateViewTest extends TestCase
{
    public function testProfileIdUsesTheNativeBodyRelease(): void
    {
        $row = new ValuesRow([new ValuesCell(new BooleanLiteral(true))]);
        $name = new ViewName(new Identifier('public'), new Identifier('report'));
        $view = new CreateView($name, new ValuesQuery([$row], GrammarRelease::PostgreSql166), [new Identifier('ready')]);

        self::assertStringStartsWith('pg-16.6/', $view->profileId());
    }

    public function testToStringPreservesAnExplicitFalseOptionAndNativeHeading(): void
    {
        $row = new ValuesRow([new ValuesCell(new BooleanLiteral(true))]);
        $name = new ViewName(new Identifier('public'), new Identifier('report'));
        $view = new CreateView($name, new ValuesQuery([$row]), [new Identifier('ready')], securityInvoker: false);

        self::assertSame('CREATE VIEW "public"."report" ("ready") WITH (security_invoker=false) AS VALUES (TRUE)', $view->toString());
    }
}
