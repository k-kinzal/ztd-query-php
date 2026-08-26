<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\PlanCursor;
use SqlFixture\Plan\PlanStatementReader;
use SqlFixture\Plan\PlanSyntaxException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;

#[CoversClass(PlanStatementReader::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(PlanCursor::class)]
#[UsesClass(PlanSyntaxException::class)]
#[UsesClass(Relation::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
final class PlanStatementReaderTest extends TestCase
{
    public function testReadAnswersTheTableWhenTheStatementNamesOneOnItsOwn(): void
    {
        self::assertSame('order', (new PlanStatementReader())->read(new PlanCursor('order')));
    }

    public function testReadAnswersOneRelationForAStatementWritingOne(): void
    {
        $read = (new PlanStatementReader())->read(new PlanCursor('order.id < detail.order_id'));

        self::assertIsArray($read);
        self::assertCount(1, $read);
        self::assertSame('order', $read[0]->parent()->table);
        self::assertSame('detail', $read[0]->child()->table);
    }

    public function testReadAnswersOneRelationPerMemberOfAGroup(): void
    {
        $read = (new PlanStatementReader())->read(new PlanCursor('order.id < [a.order_id, b.order_id]'));

        self::assertIsArray($read);
        self::assertSame(['a', 'b'], array_map(static fn (Relation $r): string => $r->child()->table, $read));
    }

    public function testReadCarriesTheOptionalMarkerWrittenOnEitherEnd(): void
    {
        $read = (new PlanStatementReader())->read(new PlanCursor('detail.order_id >? order.id'));

        self::assertIsArray($read);
        self::assertTrue($read[0]->parentIsOptional());
    }

    public function testReadRefusesAStatementCarryingMoreThanARelation(): void
    {
        $this->expectException(PlanSyntaxException::class);

        (new PlanStatementReader())->read(new PlanCursor('order.id < detail.order_id rest'));
    }

    public function testEndpointReadsATableAndTheOneColumnItBinds(): void
    {
        $endpoint = (new PlanStatementReader())->endpoint(new PlanCursor('order.id'));

        self::assertSame('order', $endpoint->table);
        self::assertSame(['id'], $endpoint->columns);
    }

    public function testEndpointReadsACompositeEndpoint(): void
    {
        $endpoint = (new PlanStatementReader())->endpoint(new PlanCursor('order.(shop_id, no)'));

        self::assertSame(['shop_id', 'no'], $endpoint->columns);
    }

    public function testEndpointRefusesATableWithNoColumnAfterIt(): void
    {
        $this->expectException(PlanSyntaxException::class);

        (new PlanStatementReader())->endpoint(new PlanCursor('order'));
    }

    public function testEndpointRefusesACompositeEndpointThatIsNeverClosed(): void
    {
        $this->expectException(PlanSyntaxException::class);

        (new PlanStatementReader())->endpoint(new PlanCursor('order.(shop_id no'));
    }

    public function testTargetsReadsOneEndpointWhereNoGroupIsWritten(): void
    {
        $targets = (new PlanStatementReader())->targets(new PlanCursor('detail.order_id'));

        self::assertCount(1, $targets);
    }

    public function testTargetsRefusesAGroupThatIsNeverClosed(): void
    {
        $this->expectException(PlanSyntaxException::class);

        (new PlanStatementReader())->targets(new PlanCursor('[a.order_id b.order_id'));
    }

    #[DataProvider('providerOperator')]
    public function testOperatorReadsWhichWayTheRelationRuns(string $written, RelationKind $kind): void
    {
        self::assertSame($kind, (new PlanStatementReader())->operator(new PlanCursor($written)));
    }

    /**
     * @return iterable<string, array{string, RelationKind}>
     */
    public static function providerOperator(): iterable
    {
        yield 'one to many' => ['<', RelationKind::OneToMany];
        yield 'many to one' => ['>', RelationKind::ManyToOne];
        yield 'one to one' => ['-', RelationKind::OneToOne];
    }

    public function testOperatorRefusesTextThatNamesNoRelation(): void
    {
        $this->expectException(PlanSyntaxException::class);

        (new PlanStatementReader())->operator(new PlanCursor('~'));
    }
}
