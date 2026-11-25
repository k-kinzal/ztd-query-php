<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjectionSql;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(LocalProjectionSql::class)]
#[Small]
final class LocalProjectionSqlTest extends TestCase
{
    public function testRenderNamesEachLevelByItsOutputPosition(): void
    {
        $first = new Identifier('first_source');
        $second = new Identifier('second_source');
        $answer = new Identifier('answer');
        $leaf = new Select([new ResultColumn(new IntegerLiteral('5'), $first), new ResultColumn(new IntegerLiteral('9'), $second)]);
        $inner = new LocalProjection($leaf, [new LocalResultColumn(1, $answer)]);
        $outer = new LocalProjection($inner, [new LocalResultColumn(0, $answer)]);

        $sql = (new LocalProjectionSql())->render($outer);

        self::assertStringContainsString('__ztd_c1', $sql);
        self::assertStringContainsString('answer', $sql);
        self::assertStringNotContainsString('first_source', $sql);
        self::assertStringNotContainsString('second_source', $sql);
    }

    public function testRenderPlacesExportedNamesOutsideTheRequestedAlias(): void
    {
        $source = new Select([new ResultColumn(new IntegerLiteral('5'), new RequestedResultAlias(' value'))]);
        $value = new LocalProjection($source, [new LocalResultColumn(0, new Identifier('result'))]);

        self::assertSame(
            'SELECT `__ztd_local`.`__ztd_c0` AS `result` FROM (SELECT 5 AS ` value`) AS `__ztd_local` (`__ztd_c0`)',
            (new LocalProjectionSql())->render($value)
        );
    }
}
