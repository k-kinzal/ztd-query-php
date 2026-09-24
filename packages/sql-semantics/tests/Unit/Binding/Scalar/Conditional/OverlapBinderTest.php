<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Conditional\OverlapBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\PeriodOverlap;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OverlapBinder::class)]
#[Medium]
final class OverlapBinderTest extends TestCase
{
    #[TestWith(['SELECT (1, 2) OVERLAPS (3, 4)'])]
    #[TestWith(['SELECT ROW(1, 2) OVERLAPS ROW(3, 4)'])]
    public function testBindReadsTwoPeriods(string $sql): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $overlap = $query->outputs[0]->expression;
        self::assertInstanceOf(PeriodOverlap::class, $overlap);
        self::assertSame(['1', '2', '3', '4'], array_map(static fn ($bound): ?string => $bound->spelling(), $overlap->inputs()));
    }

    #[TestWith(['SELECT (1, 2, 3) OVERLAPS (3, 4)', InputViolation::OverlapsWidth])]
    #[TestWith(['SELECT ROW(1) OVERLAPS ROW(3, 4)', InputViolation::OverlapsWidth])]
    #[TestWith(['SELECT UNIQUE (SELECT 1)', InputViolation::UniquePredicate])]
    #[TestWith(['select unique (select 1)', InputViolation::UniquePredicate])]
    public function testBindDiagnosesRequestsPostgreSqlRejects(string $sql, InputViolation $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage($violation->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    #[TestWith([Dialect::PostgreSql, 'SELECT EXISTS (SELECT 1)', 'SELECT EXISTS(SELECT 1)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT (1, 2) = (3, 4)', 'SELECT (ROW(1, 2) = ROW(3, 4))'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT ARRAY(SELECT 1)', 'SELECT ARRAY(SELECT 1)'])]
    #[TestWith([Dialect::PostgreSql, 'SELECT (1, 2) overlaps (3, 4) AND true', 'SELECT (((1, 2) OVERLAPS(3, 4)) AND true)'])]
    #[TestWith([Dialect::MySql, 'SELECT (1, 2) = (3, 4)', 'SELECT ((1, 2) = (3, 4))'])]
    public function testBindLeavesOtherRowsAndSubqueries(Dialect $dialect, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql)->toString());
    }

    public function testBindIgnoresOtherDialects(): void
    {
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT 1');
        self::assertNull(OverlapBinder::bind($source, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql))));
    }
}
