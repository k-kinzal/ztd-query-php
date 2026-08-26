<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\GenerationOrder;
use SqlFixture\Plan\PlanCursor;
use SqlFixture\Plan\PlanIntegrity;
use SqlFixture\Plan\PlanParser;
use SqlFixture\Plan\PlanPrinter;
use SqlFixture\Plan\PlanStatementReader;
use SqlFixture\Plan\PlanStatements;
use SqlFixture\Plan\PlanSyntaxException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;
use SqlFixture\Plan\TableName;

#[CoversClass(PlanPrinter::class)]
#[UsesClass(PlanParser::class)]
#[UsesClass(FixturePlan::class)]
#[UsesClass(Relation::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
#[UsesClass(PlanSyntaxException::class)]
#[UsesClass(GenerationOrder::class)]
#[UsesClass(PlanCursor::class)]
#[UsesClass(PlanIntegrity::class)]
#[UsesClass(PlanStatementReader::class)]
#[UsesClass(PlanStatements::class)]
#[UsesClass(TableName::class)]
final class PlanPrinterTest extends TestCase
{
    #[Test]
    public function testPrintsATableOnlyPlanAsItsTableName(): void
    {
        self::assertSame('order', (new PlanPrinter())->print(FixturePlan::table('order')));
    }

    #[Test]
    public function printsARelationWithSpacesAroundTheOperator(): void
    {
        $plan = (new PlanParser())->parse('order.id<order_detail.order_id');

        self::assertSame('order.id < order_detail.order_id', (new PlanPrinter())->print($plan));
    }

    #[Test]
    public function regroupsRelationsThatShareALeftEnd(): void
    {
        $plan = (new PlanParser())->parse(
            'order.id < order_detail.order_id, order.id < shipment.order_id'
        );

        self::assertSame(
            'order.id < [order_detail.order_id, shipment.order_id]',
            (new PlanPrinter())->print($plan)
        );
    }

    #[Test]
    public function doesNotGroupRelationsWithDifferentOperators(): void
    {
        $plan = (new PlanParser())->parse('order.id < order_detail.order_id, order.id - shipment.order_id');

        self::assertSame(
            'order.id < order_detail.order_id, order.id - shipment.order_id',
            (new PlanPrinter())->print($plan)
        );
    }

    #[Test]
    public function doesNotGroupRelationsWithDifferentOptionalMarkers(): void
    {
        $plan = (new PlanParser())->parse('order.id < order_detail.order_id, order.id <? shipment.order_id');

        self::assertSame(
            'order.id < order_detail.order_id, order.id <? shipment.order_id',
            (new PlanPrinter())->print($plan)
        );
    }

    #[Test]
    public function printsOptionalMarkersOnTheSideTheyBelongTo(): void
    {
        $plan = (new PlanParser())->parse('order.id ?< order_detail.order_id');

        self::assertSame('order.id ?< order_detail.order_id', (new PlanPrinter())->print($plan));
    }

    #[Test]
    #[DataProvider('providerRoundTrips')]
    public function readingBackWhatWasPrintedGivesTheSameText(string $plan, string $expected): void
    {
        $printed = (new PlanPrinter())->print((new PlanParser())->parse($plan));
        $reprinted = (new PlanPrinter())->print((new PlanParser())->parse($printed));

        self::assertSame($expected, $printed);
        self::assertSame($printed, $reprinted);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerRoundTrips(): array
    {
        return [
            'table only' => ['order', 'order'],
            'one to many' => ['order.id < order_detail.order_id', 'order.id < order_detail.order_id'],
            'many to one' => ['order_detail.order_id > order.id', 'order_detail.order_id > order.id'],
            'one to one' => ['order.id - order_shipping.order_id', 'order.id - order_shipping.order_id'],
            'grouped' => [
                'order.id < [order_detail.order_id, shipment.order_id]',
                'order.id < [order_detail.order_id, shipment.order_id]',
            ],
            'several relations' => [
                'order.id < order_detail.order_id, order.customer_id > customer.id',
                'order.id < order_detail.order_id, order.customer_id > customer.id',
            ],
            'composite' => [
                'order.(shop_id, no) < order_detail.(shop_id, order_no)',
                'order.(shop_id, no) < order_detail.(shop_id, order_no)',
            ],
            'optional parent' => ['order_detail.order_id >? order.id', 'order_detail.order_id >? order.id'],
            'newline separated' => [
                "order.id < order_detail.order_id\norder_detail.product_id > product.id",
                'order.id < order_detail.order_id, order_detail.product_id > product.id',
            ],
            'quoting is dropped' => ['`order`.`id` < "order_detail"."order_id"', 'order.id < order_detail.order_id'],
        ];
    }

    #[Test]
    public function testPrintRelationPrintsASingleRelationWithoutAPlan(): void
    {
        $relation = Relation::oneToMany('order.id', 'order_detail.order_id');

        self::assertSame('order.id < order_detail.order_id', (new PlanPrinter())->printRelation($relation));
    }

    #[Test]
    public function aStandaloneTableIsWrittenOutAlongsideTheRelations(): void
    {
        $plan = (new PlanParser())->parse('a.id < b.a_id, audit_log');

        self::assertSame('a.id < b.a_id, audit_log', (new PlanPrinter())->print($plan));
    }

    #[Test]
    public function severalStandaloneTablesKeepTheOrderTheyWereNamedIn(): void
    {
        $plan = (new PlanParser())->parse('audit_log, a.id < b.a_id, feature_flag');

        self::assertSame('a.id < b.a_id, audit_log, feature_flag', (new PlanPrinter())->print($plan));
    }

    #[Test]
    public function testStandaloneTablesNamesOnlyTheTablesNoRelationTouches(): void
    {
        $plan = (new PlanParser())->parse('order.id<order_detail.order_id, coupon');

        self::assertSame(['coupon'], (new PlanPrinter())->standaloneTables($plan));
    }

    #[Test]
    public function testGroupGathersTheRelationsSharingALeftEndAndOperator(): void
    {
        $plan = (new PlanParser())->parse('order.id<[order_detail.order_id, shipment.order_id], order.id>customer.id');

        $groups = (new PlanPrinter())->group($plan->relations);

        self::assertSame([2, 1], array_map('count', array_values($groups)));
    }

    #[Test]
    public function testGroupKeyIsTheLeftEndAndOperatorTheRelationWouldPrintAs(): void
    {
        $plan = (new PlanParser())->parse('order.id<?order_detail.order_id');

        self::assertSame('order.id <?', (new PlanPrinter())->groupKey($plan->relations[0]));
    }

    #[Test]
    public function testOperatorCarriesTheOptionalMarkersOnTheSideTheyWereWrittenOn(): void
    {
        $printer = new PlanPrinter();
        $plan = (new PlanParser())->parse('order.id?<?order_detail.order_id');

        self::assertSame('?<?', $printer->operator($plan->relations[0]));
    }

    #[Test]
    public function testPrintGroupFoldsSeveralTargetsIntoOneBracketedLine(): void
    {
        $plan = (new PlanParser())->parse('order.id<[order_detail.order_id, shipment.order_id]');

        self::assertSame(
            'order.id < [order_detail.order_id, shipment.order_id]',
            (new PlanPrinter())->printGroup([$plan->relations[0], $plan->relations[1]])
        );
    }

    #[Test]
    public function testPrintGroupWritesASingleTargetWithoutBrackets(): void
    {
        $plan = (new PlanParser())->parse('order.id<order_detail.order_id');

        self::assertSame(
            'order.id < order_detail.order_id',
            (new PlanPrinter())->printGroup([$plan->relations[0]])
        );
    }
}
