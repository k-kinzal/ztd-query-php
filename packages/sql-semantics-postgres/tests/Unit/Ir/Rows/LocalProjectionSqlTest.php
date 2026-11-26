<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjectionSql;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;

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
}
