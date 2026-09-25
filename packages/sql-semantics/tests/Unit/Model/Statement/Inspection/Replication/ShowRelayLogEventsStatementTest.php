<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowRelayLogEventsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowRelayLogEventsStatement::class)]
#[Medium]
final class ShowRelayLogEventsStatementTest extends TestCase
{
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testResultColumnsListTheEventFieldsAndOperandsSurviveRebindingAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SHOW RELAYLOG EVENTS IN 'relay.4' FROM 8 LIMIT 3 FOR CHANNEL 'east'");
        self::assertInstanceOf(ShowRelayLogEventsStatement::class, $statement);
        self::assertSame(['relay.4', '8', '3', 'east'], [$statement->log, $statement->position?->text, $statement->limit?->count->spelling(), $statement->channel]);
        self::assertSame('Log_name', $statement->resultColumns()[0]->name);
        self::assertSame("SHOW RELAYLOG EVENTS IN 'relay.4' FROM 8 LIMIT 3 FOR CHANNEL 'east'", $statement->toString());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testMySql56ListsTheDefaultChannelOnly(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SHOW RELAYLOG EVENTS FROM 4');
        self::assertInstanceOf(ShowRelayLogEventsStatement::class, $statement);
        self::assertNull($statement->channel);
        self::assertSame('SHOW RELAYLOG EVENTS FROM 4', $statement->toString());
    }

    public function testWithLogReadsAnotherFileImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW RELAYLOG EVENTS');
        self::assertInstanceOf(ShowRelayLogEventsStatement::class, $statement);
        $changed = $statement->withLog('r.1');
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->log);
        self::assertSame("SHOW RELAYLOG EVENTS IN 'r.1'", $changed->toString());
    }

    public function testWithPositionStartsElsewhereImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW RELAYLOG EVENTS FROM 4');
        self::assertInstanceOf(ShowRelayLogEventsStatement::class, $statement);
        $changed = $statement->withPosition(null);
        self::assertNotSame($statement, $changed);
        self::assertSame('4', $statement->position?->text);
        self::assertSame('SHOW RELAYLOG EVENTS', $changed->toString());
    }

    public function testWithLimitReplacesTheWindowImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW RELAYLOG EVENTS LIMIT 3');
        self::assertInstanceOf(ShowRelayLogEventsStatement::class, $statement);
        $changed = $statement->withLimit(null);
        self::assertNotSame($statement, $changed);
        self::assertNotNull($statement->limit);
        self::assertSame('SHOW RELAYLOG EVENTS', $changed->toString());
    }

    public function testWithChannelSelectsAnotherChannelImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW RELAYLOG EVENTS');
        self::assertInstanceOf(ShowRelayLogEventsStatement::class, $statement);
        $changed = $statement->withChannel('');
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->channel);
        self::assertSame("SHOW RELAYLOG EVENTS FOR CHANNEL ''", $changed->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW RELAYLOG EVENTS IN 'x' FOR CHANNEL 'c'");
        self::assertInstanceOf(ShowRelayLogEventsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->log, $statement->channel], [$copy->log, $copy->channel]);
    }

    public function testRejectsAChannelOnMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SHOW RELAYLOG EVENTS');
        $this->expectException(InvalidStructure::class);
        new ShowRelayLogEventsStatement($statement->origin, null, null, null, 'east');
    }
}
