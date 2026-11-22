<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(SelectSql::class)]
#[Small]
final class SelectSqlTest extends TestCase
{
    public function testRenderAddsOutputAliases(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('2'), new Identifier('`'))]);
        self::assertSame('SELECT 2 AS ````', (new SelectSql())->render($select));
    }

    public function testRenderUsesAnIndependentRowForSourceFreeFiltersOnMySql5(): void
    {
        $select = new Select(
            [new ResultColumn(new IntegerLiteral('1'), new Identifier('a'))],
            release: GrammarRelease::MySql5651,
            where: new BooleanLiteral(false),
        );

        self::assertSame('SELECT 1 AS `a` FROM (SELECT 1) AS `__ztd_unit` WHERE FALSE', (new SelectSql())->render($select));
    }

    public function testExpressionKeepsNegativeValues(): void
    {
        self::assertSame('-2', (new SelectSql())->expression(new IntegerLiteral('-2'), null));
    }

    public function testAliasSeparatesJoinedInputOrdinals(): void
    {
        $sql = new SelectSql();

        self::assertSame('`__ztd_source_1`', $sql->alias(1));
    }

    public function testFromKeepsTheJoinAssociation(): void
    {
        $a = new TableSource(new Identifier('ir_fixture'), new Identifier('a'), []);
        $b = new TableSource(new Identifier('ir_fixture'), new Identifier('b'), []);
        $joined = new Join(JoinKind::Cross, $a, $b);

        self::assertSame('(`ir_fixture`.`a` AS `__ztd_source` CROSS JOIN `ir_fixture`.`b` AS `__ztd_source_1`)', (new SelectSql())->from($joined));
    }

    public function testPredicateEmitsNullInItsOwnRole(): void
    {
        self::assertSame('NULL', (new SelectSql())->predicate(new NullLiteral(), null));
    }
    public function testLocalColumnNamesTheResultOrdinal(): void
    {
        self::assertSame('`__ztd_c3`', (new SelectSql())->localColumn(3));
    }

}
