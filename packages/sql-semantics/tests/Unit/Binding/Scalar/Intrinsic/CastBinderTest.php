<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\CastBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Operator\CastExpression;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CastBinder::class)]
#[Medium]
final class CastBinderTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'CONVERT(1, SIGNED)', 'SELECT CAST(1 AS SIGNED)'])]
    #[TestWith(['mysql-5.7.44', 'CAST(1 AS UNSIGNED INT)', 'SELECT CAST(1 AS UNSIGNED)'])]
    #[TestWith(['mysql-8.0.44', 'CONVERT((USER() IS NOT NULL) IS UNKNOWN, SIGNED)', 'SELECT CAST(((USER() IS NOT NULL) IS UNKNOWN) AS SIGNED)'])]
    #[TestWith(['mysql-8.4.7', "CONVERT('x', CHAR(2))", "SELECT CAST('x' AS CHAR(2))"])]
    #[TestWith(['mysql-9.1.0', 'CAST(1 AS SIGNED INTEGER)', 'SELECT CAST(1 AS SIGNED)'])]
    public function testBindReadsCastAndConvertTargets(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind('SELECT ' . $sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(CastExpression::class, $query->outputs[0]->expression);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRejectsCastAtLocal(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        try {
            $binder->bind('SELECT CAST(1 AT LOCAL AS SIGNED)');
            self::fail('AT LOCAL is not supported by MySQL.');
        } catch (InvalidSql $invalid) {
            self::assertSame(InputViolation::CastConversion, $invalid->violation);
        }
    }

    public function testBindLeavesOtherDialectsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT CAST(1 AS SIGNED)');
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        self::assertNull(CastBinder::bind($tree->find('simple_expr')[0], $scope));
    }

    #[TestWith(['SIGNED', 'bigint', false])]
    #[TestWith(['UNSIGNED INT', 'bigint unsigned', true])]
    #[TestWith(['DECIMAL(4, 1)', 'numeric', false])]
    public function testTargetReadsSignednessAsA64BitInteger(string $target, string $type, bool $unsigned): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT CAST(1 AS ' . $target . ')');
        $descriptor = CastBinder::target($tree->find('cast_type')[0]);
        self::assertSame($type, $descriptor->name);
        self::assertSame($unsigned, $descriptor->identity instanceof \SqlSemantics\Type\Identity\Numeric\IntegerStorage && $descriptor->identity->unsigned);
    }

    #[TestWith(["SELECT CONVERT('a' USING utf8mb4)", 'utf8mb4'])]
    #[TestWith(["SELECT convert('a' USING 'Latin1')", 'latin1'])]
    #[TestWith(["SELECT CONVERT('a' USING `UTF8MB4`)", 'utf8mb4'])]
    public function testBindReadsTheCharacterSetOfAConversion(string $sql, string $characterSet): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql);
        $conversion = CastBinder::bind($tree->find('simple_expr')[0], new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\CharacterSetConversion::class, $conversion);
        self::assertSame($characterSet, $conversion->characterSet);
    }

    #[TestWith(["SELECT CAST(NOW() AT TIME ZONE 'UTC' AS DATETIME(3))", false, 3, "'UTC'"])]
    #[TestWith(["SELECT CAST(NOW() AT TIME ZONE INTERVAL '+00:00' AS DATETIME)", true, null, "'+00:00'"])]
    #[TestWith(["SELECT CAST(NOW() AT TIME ZONE 'UTC' AS DATETIME)", false, null, "'UTC'"])]
    public function testBindReadsATimeZoneCast(string $sql, bool $interval, ?int $precision, string $zone): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql);
        $cast = CastBinder::bind($tree->find('simple_expr')[0], new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\TimeZoneCast::class, $cast);
        self::assertSame([$interval, $precision, $zone], [$cast->interval, $cast->precision, $cast->zone->text]);
    }

    #[TestWith(["SELECT CAST('[1]' AS UNSIGNED ARRAY)", \SqlSemantics\Model\Scalar\Operator\ArrayCast::class, 'bigint unsigned'])]
    #[TestWith(['SELECT cast(1 as signed)', CastExpression::class, 'bigint'])]
    #[TestWith(['SELECT CONVERT(1, unsigned)', CastExpression::class, 'bigint unsigned'])]
    public function testBindDistinguishesArrayCasts(string $sql, string $class, string $type): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql);
        $cast = CastBinder::bind($tree->find('simple_expr')[0], new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $cast);
        self::assertSame([$class, $type], [$cast::class, $cast->type->name]);
    }
}
