<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\TemporalLiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TemporalLiteralBinder::class)]
#[Medium]
final class TemporalLiteralBinderTest extends TestCase
{
    #[TestWith(['{fn USER()}', 'SELECT USER()'])]
    #[TestWith(['{x 1 + 2}', 'SELECT (1 + 2)'])]
    #[TestWith(['{d 5}', 'SELECT 5'])]
    #[TestWith(["{d '2024-01-02'}", "SELECT DATE '2024-01-02'"])]
    public function testBindReadsAnOdbcEscapeAsItsExpression(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind('SELECT ' . $sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindLeavesOtherDialectsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse("SELECT DATE '2024-01-02'");
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        self::assertNull(TemporalLiteralBinder::bind($tree->find('temporal_literal')[0], $scope));
    }

    #[TestWith(['date', LiteralKind::Date])]
    #[TestWith(['TIME', LiteralKind::Time])]
    #[TestWith(['Timestamp', LiteralKind::Timestamp])]
    #[TestWith(['YEAR', null])]
    public function testCategoryClassifiesTheTemporalKeyword(string $keyword, ?LiteralKind $category): void
    {
        self::assertSame($category, TemporalLiteralBinder::category($keyword));
    }
}
