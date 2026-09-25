<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\ExtractBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExtractBinder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ExtractBinderTest extends TestCase
{
    public function testBindRetainsTheIntrinsicOperands(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\Extract::class, $query->outputs[0]->expression);
    }

    public function testBindLeavesOrdinaryFunctionsToSignatureResolution(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT "position"(1, 2), "extract"(1)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $query->outputs[0]->expression);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $query->outputs[1]->expression);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRetainsUnitsAcrossGrammarReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind("SELECT EXTRACT(DAY_SECOND FROM '2020-01-01')");
        self::assertInstanceOf(BoundSelect::class, $query);
        $extract = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Temporal\Extract::class, $extract);
        self::assertSame(\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::DaySecond, $extract->field);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    #[TestWith(['SELECT EXTRACT(nonsense FROM CURRENT_TIMESTAMP)'])]
    public function testBindRejectsInvalidFieldRequestsEvenWhenNonStrict(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, false);
    }
}
