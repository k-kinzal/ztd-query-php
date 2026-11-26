<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateMaterializedView;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(CreateMaterializedView::class)]
#[Small]
final class CreateMaterializedViewTest extends TestCase
{
    public function testProfileIdUsesTheDefiningQueryRelease(): void
    {
        $name = new ViewName(new Identifier('app'), new Identifier('daily'));
        $row = new ValuesRow([new ValuesCell(new IntegerLiteral('8'))]);
        $query = new ValuesQuery([$row], GrammarRelease::PostgreSql166);
        $view = new CreateMaterializedView($name, $query, [new Identifier('entry')]);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $view->profileId());
    }

    public function testToStringKeepsTheSnapshotDefinitionAndPopulationPolicy(): void
    {
        $name = new ViewName(new Identifier('app'), new Identifier('daily "report"'));
        $row = new ValuesRow([new ValuesCell(new IntegerLiteral('8'))]);
        $query = new ValuesQuery([$row], GrammarRelease::PostgreSql166);
        $view = new CreateMaterializedView($name, $query, [new Identifier('entry')], true, false);

        self::assertSame('CREATE MATERIALIZED VIEW IF NOT EXISTS "app"."daily ""report""" ("entry")'
            . ' USING heap AS VALUES (CAST(8 AS pg_catalog.int4)) WITH NO DATA', $view->toString());
    }
}
