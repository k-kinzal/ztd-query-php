<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithDefinition;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithRead;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithSql;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(WithSql::class)]
#[Small]
final class WithSqlTest extends TestCase
{
    public function testNameUsesTheDeclarationOrdinal(): void
    {
        self::assertSame('`__ztd_cte_4`', (new WithSql())->name(4));
    }

    public function testReadUsesTheAddressedField(): void
    {
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);

        self::assertSame('SELECT `__ztd_cte_0`.`__ztd_c0` AS `result` FROM `__ztd_cte_0`', (new WithSql())->read($read, false));
    }

    public function testQueryUsesOrdinalHeadingsInsideDefinitions(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);

        self::assertSame('SELECT 1 AS `__ztd_c0`', (new WithSql())->query($body, true));
    }

    public function testRenderIntroducesTheOwnedDefinition(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('value'))]);
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);
        $query = new WithProjection([new WithDefinition($body)], $read);

        self::assertStringStartsWith('WITH `__ztd_cte_0` AS (', (new WithSql())->render($query));
    }

    public function testRenderKeepsTheRequestedAliasUnderAnExplicitLocalName(): void
    {
        $body = new Select([new ResultColumn(new IntegerLiteral('1'), new RequestedResultAlias(' value'))]);
        $read = new WithRead(0, [new LocalResultColumn(0, new Identifier('result'))]);
        $query = new WithProjection([new WithDefinition($body)], $read);

        self::assertSame(
            'WITH `__ztd_cte_0` (`__ztd_c0`) AS (SELECT 1 AS ` value`) '
            . 'SELECT `__ztd_cte_0`.`__ztd_c0` AS `result` FROM `__ztd_cte_0`',
            (new WithSql())->render($query)
        );
    }
}
