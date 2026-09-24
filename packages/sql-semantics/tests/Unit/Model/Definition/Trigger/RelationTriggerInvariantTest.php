<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\RelationTriggerInvariant;
use SqlSemantics\Model\Definition\Trigger\TransitionTable;
use SqlSemantics\Model\Definition\Trigger\TriggerEvent;
use SqlSemantics\Model\Definition\Trigger\TriggerEvents;
use SqlSemantics\Model\Definition\Trigger\TriggerLevel;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RelationTriggerInvariant::class)]
#[Medium]
final class RelationTriggerInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'audit'])]
    #[TestWith([Dialect::PostgreSql, ''])]
    public function testIdentityRejectsAnotherLanguageOrAnEmptyName(Dialect $dialect, string $name): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RelationTriggerInvariant::identity($origin, $name, null);
    }

    public function testIdentityAcceptsAPostgreSqlTrigger(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        RelationTriggerInvariant::identity($origin, 'audit', null);
        self::assertSame(Dialect::PostgreSql, $origin->dialect);
    }

    #[TestWith([Timing::InsteadOf, TriggerEvent::Insert, TriggerLevel::Statement])]
    #[TestWith([Timing::InsteadOf, TriggerEvent::Truncate, TriggerLevel::Row])]
    #[TestWith([Timing::After, TriggerEvent::Truncate, TriggerLevel::Row])]
    public function testFiringRejectsUnsupportedGranularity(Timing $timing, TriggerEvent $event, TriggerLevel $level): void
    {
        $this->expectException(InvalidStructure::class);
        RelationTriggerInvariant::firing($timing, new TriggerEvents([$event]), $level, null);
    }

    public function testFiringRejectsColumnsOnAnInsteadOfTrigger(): void
    {
        $this->expectException(InvalidStructure::class);
        RelationTriggerInvariant::firing(Timing::InsteadOf, new TriggerEvents([TriggerEvent::Update], ['a']), TriggerLevel::Row, null);
    }

    #[TestWith([Timing::Before, TriggerEvent::Delete, RowVersion::Old])]
    #[TestWith([Timing::After, TriggerEvent::Insert, RowVersion::Old])]
    #[TestWith([Timing::After, TriggerEvent::Delete, RowVersion::New])]
    #[TestWith([Timing::After, TriggerEvent::Truncate, RowVersion::New])]
    public function testTransitionsRejectAnUnavailableRowVersion(Timing $timing, TriggerEvent $event, RowVersion $version): void
    {
        $this->expectException(InvalidStructure::class);
        RelationTriggerInvariant::transitions($timing, new TriggerEvents([$event]), [new TransitionTable($version, 'rows')]);
    }

    public function testTransitionsRejectSeveralChanges(): void
    {
        $this->expectException(InvalidStructure::class);
        RelationTriggerInvariant::transitions(Timing::After, new TriggerEvents([TriggerEvent::Insert, TriggerEvent::Update]), [new TransitionTable(RowVersion::New, 'rows')]);
    }

    public function testTransitionsRejectASharedName(): void
    {
        $this->expectException(InvalidStructure::class);
        RelationTriggerInvariant::transitions(Timing::After, new TriggerEvents([TriggerEvent::Update]), [new TransitionTable(RowVersion::New, 'rows'), new TransitionTable(RowVersion::Old, 'rows')]);
    }

    public function testTransitionsAcceptBothImagesOfAnUpdate(): void
    {
        $tables = [new TransitionTable(RowVersion::New, 'added'), new TransitionTable(RowVersion::Old, 'removed')];
        RelationTriggerInvariant::transitions(Timing::After, new TriggerEvents([TriggerEvent::Update]), $tables);
        self::assertCount(2, $tables);
    }

    /**
     * @param list<TriggerEvent> $events
     * @param list<RowVersion> $images
     */
    #[TestWith([[TriggerEvent::Insert], TriggerLevel::Row, [RowVersion::New]])]
    #[TestWith([[TriggerEvent::Delete], TriggerLevel::Row, [RowVersion::Old]])]
    #[TestWith([[TriggerEvent::Update], TriggerLevel::Row, [RowVersion::Old, RowVersion::New]])]
    #[TestWith([[TriggerEvent::Insert, TriggerEvent::Delete], TriggerLevel::Row, []])]
    #[TestWith([[TriggerEvent::Update], TriggerLevel::Statement, []])]
    public function testImagesFollowTheEventsAndGranularity(array $events, TriggerLevel $level, array $images): void
    {
        self::assertSame($images, RelationTriggerInvariant::images(new TriggerEvents($events), $level));
    }
}
