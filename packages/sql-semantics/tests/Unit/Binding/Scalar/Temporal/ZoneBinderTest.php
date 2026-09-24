<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Temporal\ZoneBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\ZoneConversion;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ZoneBinder::class)]
#[Medium]
final class ZoneBinderTest extends TestCase
{
    public function testBindReadsTheZoneOrTheSessionZone(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a TIMESTAMP, z TEXT)'));
        $query = $binder->bind('SELECT a AT TIME ZONE z AT LOCAL FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $outer = $query->outputs[0]->expression;
        self::assertInstanceOf(ZoneConversion::class, $outer);
        self::assertNull($outer->zone);
        self::assertInstanceOf(ZoneConversion::class, $outer->value);
        self::assertSame('z', $outer->value->zone?->columnBinding()?->column->name);
        self::assertSame('SELECT (("a" AT TIME ZONE "z") AT LOCAL) FROM "public"."t"', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testBindLeavesOtherDialectsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT 1');
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql));
        self::assertNull(ZoneBinder::bind($tree->find('simple_expr')[0], $scope));
    }
}
