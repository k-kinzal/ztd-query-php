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
use SqlFixture\Plan\PlanParser;
use SqlFixture\Plan\PlanSyntaxException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;

#[CoversClass(PlanParser::class)]
#[UsesClass(FixturePlan::class)]
#[UsesClass(Relation::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
#[UsesClass(PlanSyntaxException::class)]
#[CoversClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[CoversClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[CoversClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class PlanParserTest extends TestCase
{
    #[Test]
    public function testParseABareTableNameIsAPlanWithNoRelations(): void
    {
        $plan = (new PlanParser())->parse('order');

        self::assertSame([], $plan->relations);
        self::assertSame(['order'], $plan->tables);
    }

    #[Test]
    public function testReadsAOneToManyRelation(): void
    {
        $plan = (new PlanParser())->parse('order.id < order_detail.order_id');

        self::assertCount(1, $plan->relations);
        self::assertSame(RelationKind::OneToMany, $plan->relations[0]->kind);
        self::assertSame('order.id', $plan->relations[0]->left->toString());
        self::assertSame('order_detail.order_id', $plan->relations[0]->right->toString());
    }

    #[Test]
    public function testReadsAManyToOneRelation(): void
    {
        $plan = (new PlanParser())->parse('order_detail.order_id > order.id');

        self::assertSame(RelationKind::ManyToOne, $plan->relations[0]->kind);
        self::assertSame('order', $plan->relations[0]->parent()->table);
    }

    #[Test]
    public function testReadsAOneToOneRelation(): void
    {
        $plan = (new PlanParser())->parse('order.id - order_shipping.order_id');

        self::assertSame(RelationKind::OneToOne, $plan->relations[0]->kind);
        self::assertFalse($plan->relations[0]->childIsCollection());
    }

    #[Test]
    public function testReadsACompositeEndpoint(): void
    {
        $plan = (new PlanParser())->parse('order.(shop_id, no) < order_detail.(shop_id, order_no)');

        self::assertSame(['shop_id', 'no'], $plan->relations[0]->left->columns);
        self::assertSame(['shop_id', 'order_no'], $plan->relations[0]->right->columns);
    }

    #[Test]
    public function testAGroupedTargetExpandsToOneRelationPerEndpoint(): void
    {
        $plan = (new PlanParser())->parse('order.id < [order_detail.order_id, shipment.order_id]');

        self::assertCount(2, $plan->relations);
        self::assertSame('order_detail', $plan->relations[0]->right->table);
        self::assertSame('shipment', $plan->relations[1]->right->table);
        self::assertSame('order.id', $plan->relations[1]->left->toString());
    }

    #[Test]
    public function testCommasSeparateRelations(): void
    {
        $plan = (new PlanParser())->parse('order.id < order_detail.order_id, order.customer_id > customer.id');

        self::assertCount(2, $plan->relations);
        self::assertSame(['order', 'order_detail', 'customer'], $plan->tables);
    }

    #[Test]
    public function testNewlinesSeparateRelations(): void
    {
        $plan = (new PlanParser())->parse("order.id < order_detail.order_id\norder_detail.product_id > product.id");

        self::assertCount(2, $plan->relations);
    }

    #[Test]
    public function testSemicolonsSeparateRelations(): void
    {
        $plan = (new PlanParser())->parse('order.id < order_detail.order_id; order.customer_id > customer.id');

        self::assertCount(2, $plan->relations);
    }

    #[Test]
    public function testCommasInsideBracketsDoNotSeparateRelations(): void
    {
        $plan = (new PlanParser())->parse('order.(a, b) < [x.(a, b), y.(a, b)]');

        self::assertCount(2, $plan->relations);
    }

    #[Test]
    public function testTablesAreListedInFirstMentionedOrderWithoutRepeats(): void
    {
        $plan = (new PlanParser())->parse(
            'order.id < order_detail.order_id, order_detail.product_id > product.id'
        );

        self::assertSame(['order', 'order_detail', 'product'], $plan->tables);
    }

    #[Test]
    public function testAMarkerBeforeTheOperatorMarksTheLeftSideOptional(): void
    {
        $plan = (new PlanParser())->parse('order.id ?< order_detail.order_id');

        self::assertTrue($plan->relations[0]->leftOptional);
        self::assertFalse($plan->relations[0]->rightOptional);
    }

    #[Test]
    public function testAMarkerAfterTheOperatorMarksTheRightSideOptional(): void
    {
        $plan = (new PlanParser())->parse('order_detail.order_id >? order.id');

        self::assertTrue($plan->relations[0]->rightOptional);
        self::assertTrue($plan->relations[0]->parentIsOptional());
    }

    #[Test]
    #[DataProvider('providerQuotedForms')]
    public function identifiersMayBeQuoted(string $plan): void
    {
        $parsed = (new PlanParser())->parse($plan);

        self::assertSame('order.id', $parsed->relations[0]->left->toString());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerQuotedForms(): array
    {
        return [
            'backticks' => ['`order`.`id` < order_detail.order_id'],
            'double quotes' => ['"order"."id" < order_detail.order_id'],
            'mixed' => ['`order`."id" < order_detail.order_id'],
        ];
    }

    #[Test]
    public function testSurroundingWhitespaceIsIgnored(): void
    {
        $plan = (new PlanParser())->parse('   order.id   <   order_detail.order_id   ');

        self::assertSame('order_detail.order_id', $plan->relations[0]->right->toString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedPlans(): array
    {
        return [
            'target without a column' => ['order.id < order_detail', "'.' after the table name"],
            'unknown operator' => ['order.id !! order_detail.order_id', "one of '<', '>' or '-'"],
            'missing operator' => ['order.id order_detail.order_id', "one of '<', '>' or '-'"],
            'unclosed group' => ['order.id < [a.x, b.x', "',' or ']'"],
            'unclosed composite' => ['order.(a, b < x.y', "',' or ')'"],
            'trailing junk' => ['order.id < order_detail.order_id extra', 'the end of the relation'],
            'arity mismatch' => ['order.(a, b) < order_detail.(c)', 'names 2 columns on one side'],
        ];
    }

    #[Test]
    public function testAGroupFollowedByAnotherRelationStillSplitsCorrectly(): void
    {
        $plan = (new PlanParser())->parse('a.id < [b.a_id, c.a_id], d.id < e.d_id');

        self::assertCount(3, $plan->relations);
        self::assertSame(['a', 'b', 'c', 'd', 'e'], $plan->tables);
    }

    #[Test]
    public function testAnEmptyStatementBetweenTwoRelationsIsIgnored(): void
    {
        $plan = (new PlanParser())->parse('a.id < b.a_id,, c.id < d.c_id');

        self::assertCount(2, $plan->relations);
    }

    #[Test]
    public function testATableNameFollowedByRelationsKeepsThemAll(): void
    {
        $plan = (new PlanParser())->parse('audit_log, a.id < b.a_id');

        self::assertCount(1, $plan->relations);
        self::assertSame(['audit_log', 'a', 'b'], $plan->tables);
    }

    #[Test]
    public function testWhitespaceInsideAGroupIsSkipped(): void
    {
        $plan = (new PlanParser())->parse('a.id < [ b.a_id , c.a_id ]');

        self::assertCount(2, $plan->relations);
        self::assertSame('c.a_id', $plan->relations[1]->right->toString());
    }

    #[Test]
    public function testAStrayClosingBracketDoesNotStopLaterRelationsSplitting(): void
    {
        $plan = (new PlanParser())->parse('a.id < [b.a_id], c.id < d.c_id');

        self::assertCount(2, $plan->relations);
    }

    #[Test]
    public function testWhitespaceBetweenAnEndpointAndTheOperatorIsSkipped(): void
    {
        $plan = (new PlanParser())->parse('a.(x , y)   <   b.(p , q)');

        self::assertSame(['x', 'y'], $plan->relations[0]->left->columns);
        self::assertSame(['p', 'q'], $plan->relations[0]->right->columns);
    }

    #[Test]
    public function testAStrayOpeningBracketDoesNotSwallowLaterRelations(): void
    {
        $plan = (new PlanParser())->parse('a.id < b.a_id; c.id < d.c_id');

        self::assertCount(2, $plan->relations);
    }

}
