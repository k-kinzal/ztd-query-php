<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\View\ViewSql;

#[CoversClass(ViewSql::class)]
#[Small]
final class ViewSqlTest extends TestCase
{
    public function testHeaderQuotesTheTargetAndNamesInOrder(): void
    {
        $prefix = (new ViewSql())->header(new Identifier('main'), new Identifier('view "name'), [new Identifier('y'), new Identifier('x')], true);

        self::assertSame('CREATE VIEW IF NOT EXISTS "main"."view ""name" ("y", "x") AS ', $prefix);
    }
}
