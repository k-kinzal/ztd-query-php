<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\FixtureSet;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\PlanGenerator;
use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Fixture\PlanSchemaValidator;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Fixture\TableOverrides;
use SqlFixture\FixtureGenerator;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\PlanParser;
use SqlFixture\Plan\PlanPrinter;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaNotFoundException;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;
use Tests\Fixture\Fixture\ShopSchemas;

#[CoversClass(PlanGenerator::class)]
#[UsesClass(GenerationRun::class)]
#[UsesClass(PlanSchemaValidator::class)]
#[UsesClass(PlanSchemaException::class)]
#[UsesClass(RowSpec::class)]
#[UsesClass(FixtureSet::class)]
#[UsesClass(TableOverrides::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(FixturePlan::class)]
#[UsesClass(PlanParser::class)]
#[UsesClass(PlanPrinter::class)]
#[UsesClass(Relation::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(StaticSchemaResolver::class)]
#[UsesClass(SchemaNotFoundException::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
final class PlanGeneratorTest extends TestCase
{
    #[Test]
    public function theTableThePlanIsAboutGetsOneRow(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id')
        );

        self::assertArrayHasKey('id', (array) $set->row('order'));
    }

    #[Test]
    public function theSubjectIsOneRowEvenWhenSomethingReferencesIt(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id, order.customer_id > customer.id')
        );

        self::assertArrayHasKey('id', (array) $set->row('order'));
        self::assertArrayHasKey('id', (array) $set->row('customer'));
    }

    #[Test]
    public function childRowsCarryTheParentKey(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order' => ['id' => 100], 'order_detail' => 3]
        );

        self::assertCount(3, $set->rows('order_detail'));
        self::assertSame([100, 100, 100], array_column($set->rows('order_detail'), 'order_id'));
    }

    #[Test]
    public function aParentIsGeneratedAndLinkedTo(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.customer_id > customer.id')
        );

        self::assertSame($set->rows('customer')[0]['id'], $set->rows('order')[0]['customer_id']);
    }

    #[Test]
    public function aCountOfZeroGeneratesNoRows(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order_detail' => []]
        );

        self::assertSame([], $set->rows('order_detail'));
    }

    #[Test]
    public function oneSetOfValuesAppliesToEveryGeneratedRow(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order_detail' => ['quantity' => 2]]
        );

        self::assertNotSame([], $set->rows('order_detail'));
        $quantities = array_map(
            static fn (array $row): mixed => $row['quantity'] ?? null,
            $set->rows('order_detail')
        );

        self::assertSame([2], array_values(array_unique($quantities, SORT_REGULAR)));
    }

    #[Test]
    public function aListGivesOneRowPerEntry(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order_detail' => [['quantity' => 1], ['quantity' => 2]]]
        );

        self::assertSame([1, 2], array_column($set->rows('order_detail'), 'quantity'));
    }

    #[Test]
    public function anUnmentionedChildIsStillGenerated(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id')
        );

        self::assertGreaterThanOrEqual(1, count($set->rows('order_detail')));
    }

    #[Test]
    public function anOptionalChildMayGenerateNoneAtAll(): void
    {
        self::assertContains(0, ShopSchemas::childCountsOverSeeds('order.id <? order_detail.order_id'));
    }

    #[Test]
    public function aRequiredChildNeverGeneratesNone(): void
    {
        self::assertNotContains(0, ShopSchemas::childCountsOverSeeds('order.id < order_detail.order_id'));
    }

    #[Test]
    public function aOneToOneChildIsASingleRow(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id - order_shipping.order_id'),
            ['order' => ['id' => 5]]
        );

        self::assertSame(5, $set->rows('order_shipping')[0]['order_id']);
    }

    #[Test]
    public function anOptionalOneToOneAskedForNoneIsNull(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id -? order_shipping.order_id'),
            ['order_shipping' => []]
        );

        self::assertNull($set->row('order_shipping'));
    }

    #[Test]
    public function everyChildOfAListGetsItsOwnParent(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id, order_detail.product_id > product.id'),
            ['order_detail' => 3]
        );

        self::assertCount(3, $set->rows('product'));
        self::assertSame(
            array_column($set->rows('product'), 'id'),
            array_column($set->rows('order_detail'), 'product_id')
        );
    }

    #[Test]
    public function fixingTheLinkingColumnLeavesTheParentAlone(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.customer_id > customer.id'),
            ['order' => ['customer_id' => 77]]
        );

        self::assertSame(77, $set->rows('order')[0]['customer_id']);
        self::assertNull($set->row('customer'));
    }

    #[Test]
    public function tablesThatStandAloneAreGeneratedToo(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id, audit_log')
        );

        self::assertArrayHasKey('message', (array) $set->row('audit_log'));
    }

    #[Test]
    public function entriesComeBackInTheOrderThePlanNamesThem(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id, order.customer_id > customer.id'),
            ['order' => ['id' => 1], 'order_detail' => 2]
        );

        [$order, $details, $customer] = $set;

        self::assertSame(1, ((array) $order)['id']);
        self::assertCount(2, (array) $details);
        self::assertArrayHasKey('tier', (array) $customer);
    }

    #[Test]
    public function overridesMayBeGivenAsTableOverrides(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order' => TableOverrides::of(['status' => 'paid'])]
        );

        self::assertSame('paid', $set->rows('order')[0]['status']);
    }

    #[Test]
    public function anUnknownTableIsReported(): void
    {
        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('Schema not found for table: nope');

        ShopSchemas::generator()->generate(FixturePlan::from('nope'));
    }

    #[Test]
    public function aPlanNamingAColumnTheTableLacksIsRejectedBeforeGenerating(): void
    {
        $this->expectException(PlanSchemaException::class);
        $this->expectExceptionMessage('order_detail has no column oder_id');

        ShopSchemas::generator()->generate(FixturePlan::from('order.id < order_detail.oder_id'));
    }

    #[Test]
    public function theSubjectHonoursACountItWasGiven(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order' => 2, 'order_detail' => 1]
        );

        self::assertCount(2, $set->rows('order'));
    }

    #[Test]
    public function aRowCarriesBothItsInheritedKeyAndItsOwnParentKey(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id, order_detail.product_id > product.id'),
            ['order' => ['id' => 42], 'order_detail' => 1]
        );

        $detail = $set->rows('order_detail')[0];

        self::assertSame(42, $detail['order_id']);
        self::assertSame($set->rows('product')[0]['id'], $detail['product_id']);
    }

    #[Test]
    public function everyOtherRelationOfAParentIsStillFollowed(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.customer_id > customer.id, customer.id < audit_log.customer_id')
        );

        self::assertNotSame([], $set->rows('audit_log'));
    }

    #[Test]
    public function anOptionalParentIsGeneratedWhenTheCallerAsksForIt(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.customer_id >? customer.id'),
            ['customer' => ['tier' => 'gold']]
        );

        self::assertSame('gold', $set->rows('customer')[0]['tier']);
        self::assertSame($set->rows('customer')[0]['id'], $set->rows('order')[0]['customer_id']);
    }

    #[Test]
    public function aCompositeRelationCarriesEveryColumn(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('shop_order.(shop_id, no) < shop_order_line.(shop_id, order_no)'),
            ['shop_order' => ['shop_id' => 7, 'no' => 9], 'shop_order_line' => 1]
        );

        $line = $set->rows('shop_order_line')[0];

        self::assertSame(7, $line['shop_id']);
        self::assertSame(9, $line['order_no']);
    }

    #[Test]
    public function aOneToOneChildIsGeneratedExactlyOnce(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id - order_shipping.order_id')
        );

        self::assertCount(1, $set->rows('order_shipping'));
    }

    #[Test]
    public function keysAreStoodInForEveryColumnARelationReads(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('twin.id < twin_child.twin_id, twin.other_id < twin_other.twin_other_id')
        );

        self::assertSame(1, $set->rows('twin')[0]['id']);
        self::assertSame(1, $set->rows('twin')[0]['other_id']);
    }

    #[Test]
    public function aRequiredParentIsGeneratedWhetherOrNotItWasAskedFor(): void
    {
        $set = ShopSchemas::generator()->generate(FixturePlan::from('order.customer_id > customer.id'));

        self::assertNotSame([], $set->rows('customer'));
    }

    #[Test]
    public function anOptionalParentNobodyAskedForIsLeftOut(): void
    {
        $set = ShopSchemas::generator()->generate(FixturePlan::from('order.customer_id >? customer.id'));

        self::assertSame([], $set->rows('customer'));
    }

    #[Test]
    public function anUnboundedChildCountCanExceedItsMinimum(): void
    {
        self::assertNotSame(
            [1],
            array_values(array_unique(ShopSchemas::childCountsOverSeeds('order.id < order_detail.order_id')))
        );
    }

    #[Test]
    public function aOneToOneChildIsNeverGeneratedMoreThanOnce(): void
    {
        self::assertSame(
            [1],
            array_values(array_unique(
                ShopSchemas::rowCountsOverSeeds('order.id - order_shipping.order_id', 'order_shipping')
            ))
        );
    }

    #[Test]
    public function aTableReachableOnlyBackwardsAlongARelationIsStillPartOfTheWalk(): void
    {
        $set = ShopSchemas::generator()->generate(
            FixturePlan::from('order.id < order_detail.order_id, product.id ?< order_detail.product_id')
        );

        self::assertSame([], $set->rows('product'));
    }
}
