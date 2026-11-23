<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithDefinition;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithMaterialization;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithRead;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithSql;
use SqlSemantics\Platform\PostgreSql\Ir\Select;

#[CoversClass(WithSql::class)]
#[Small]
final class WithSqlTest extends TestCase
{
    public function testNameUsesTheDeclarationOrdinal(): void
    {
        self::assertSame('"__ztd_cte_4"', (new WithSql())->name(4));
    }

    public function testReadUsesTheAddressedField(): void
    {
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);

        self::assertSame('SELECT "__ztd_cte_0"."__ztd_c0" AS "result" FROM "__ztd_cte_0"', (new WithSql())->read($read, false));
    }

    public function testQueryUsesOrdinalHeadingsInsideDefinitions(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);

        self::assertSame('SELECT CAST(1 AS pg_catalog.int4) AS "__ztd_c0"', (new WithSql())->query($body, true));
    }

    public function testRenderRetainsTheMaterializationPolicy(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);
        $query = new WithProjection([new WithDefinition($body, WithMaterialization::Materialized)], $read);

        self::assertStringStartsWith('WITH "__ztd_cte_0" AS MATERIALIZED (', (new WithSql())->render($query));
    }
}
