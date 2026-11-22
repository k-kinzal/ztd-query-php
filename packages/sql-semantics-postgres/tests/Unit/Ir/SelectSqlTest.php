<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseArm;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\SelectSql;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(SelectSql::class)]
#[Small]
final class SelectSqlTest extends TestCase
{
    public function testRenderAddsOutputAliases(): void
    {
        $select = new Select([new ResultColumn(new IntegerLiteral('2'), new Identifier('"'))]);
        self::assertSame('SELECT CAST(2 AS pg_catalog.int4) AS """"', (new SelectSql())->render($select));
    }

    public function testExpressionKeepsNegativeValues(): void
    {
        self::assertSame('CAST(-2 AS pg_catalog.int4)', (new SelectSql())->expression(new IntegerLiteral('-2'), null));
    }

    public function testAliasSeparatesJoinedInputOrdinals(): void
    {
        $sql = new SelectSql();

        self::assertSame('"__ztd_source_1"', $sql->alias(1));
    }

    public function testFromKeepsTheJoinAssociation(): void
    {
        $a = new TableSource(new Identifier('public'), new Identifier('a'), []);
        $b = new TableSource(new Identifier('public'), new Identifier('b'), []);
        $joined = new Join(JoinKind::Cross, $a, $b);

        self::assertSame('("public"."a" AS "__ztd_source" CROSS JOIN "public"."b" AS "__ztd_source_1")', (new SelectSql())->from($joined));
    }

    public function testPredicateEmitsNullInItsOwnRole(): void
    {
        self::assertSame('NULL', (new SelectSql())->predicate(new NullLiteral(), null));
    }

    public function testIntegerCaseKeepsArmOrderAndAbsentElse(): void
    {
        $value = new IntegerCase([
            new IntegerCaseArm(new BooleanLiteral(true), new NullLiteral()),
            new IntegerCaseArm(new BooleanLiteral(false), new IntegerLiteral('2')),
        ]);

        self::assertSame(
            '(CASE WHEN TRUE THEN CAST(NULL AS pg_catalog.int4) WHEN FALSE THEN CAST(2 AS pg_catalog.int4) END)',
            (new SelectSql())->integerCase($value, null, 0, true)
        );
    }

    public function testCaseResultTypesExplicitNull(): void
    {
        self::assertSame('CAST(NULL AS pg_catalog.int4)', (new SelectSql())->caseResult(new NullLiteral(), null, 0, true));
    }
    public function testLocalColumnNamesTheResultOrdinal(): void
    {
        self::assertSame('"__ztd_c3"', (new SelectSql())->localColumn(3));
    }

}
