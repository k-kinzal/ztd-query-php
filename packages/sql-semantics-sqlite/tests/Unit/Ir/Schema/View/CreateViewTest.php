<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\CreateView;

#[CoversClass(CreateView::class)]
#[Small]
final class CreateViewTest extends TestCase
{
    public function testToStringWritesTheEffectiveNamesAndGuard(): void
    {
        $query = new ValuesQuery([new ValueRow([new IntegerLiteral('1')])]);
        $view = new CreateView(new Identifier('temp'), new Identifier('example'), [new Identifier('a"b')], $query, true);

        self::assertSame('CREATE VIEW IF NOT EXISTS "temp"."example" ("a""b") AS VALUES (1)', $view->toString());
    }

    public function testProfileIdUsesTheCheckedQueryProfile(): void
    {
        $query = new ValuesQuery([new ValueRow([new IntegerLiteral('1')])]);
        $view = new CreateView(new Identifier('main'), new Identifier('example'), [new Identifier('a')], $query);

        self::assertSame($query->profileId(), $view->profileId());
    }
}
