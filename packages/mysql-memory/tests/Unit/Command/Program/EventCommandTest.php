<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\EventCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(EventCommand::class)]
#[Small]
final class EventCommandTest extends TestCase
{
    public function testLapseKeepsPinnedClockDefinitionsEnabledWhenTheSchedulerIsOff(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET GLOBAL event_scheduler=OFF; SET timestamp=1700000000; CREATE EVENT e ON SCHEDULE EVERY 1 DAY ENDS '2024-01-01' DO SELECT 1");

        self::assertSame('ENABLED', $session->instance->dictionary->schema('d')?->events['e']->status);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testFoundRejectsTheSameNameBeforeTheMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1551);

        $session->query('ALTER EVENT missing.event RENAME TO missing.EVENT');
    }

    public function testFoundChecksTheRenameDatabaseFirstInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $session->query('ALTER EVENT absent.missing RENAME TO absent.missing');
    }

    public function testIntervalNumberKeepsPartialSecondTextQuietInMySql57(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect();
        $session->query("CREATE EVENT d.e ON SCHEDULE EVERY '2x' SECOND DO SELECT 1");

        self::assertSame(['2', 'SECOND'], $session->instance->dictionary->schema('d')?->events['e']->every);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new EventCommand())->clearsDiagnostics());
    }

    public function testIntervalNumberReadsComputedTextAsDecimal(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        try {
            $session->query('ALTER EVENT missing ON SCHEDULE EVERY USER() SECOND');
            self::fail('An interval with no numeric prefix must be refused.');
        } catch (SqlError $error) {
            self::assertSame(1542, $error->getCode());
        }

        self::assertSame(['Warning', 1366, "Incorrect DECIMAL value: '0' for column '' at row -1"], $session->diagnostics->conditions[0]);
    }

    public function testIntervalNumberTruncatesTextForDayUnits(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $session->query("CREATE EVENT e ON SCHEDULE EVERY '1.5' DAY DO SELECT 1");
        self::assertSame(['1', 'DAY'], $session->instance->dictionary->schema('d')?->events['e']->every);
        self::assertSame(['Warning', 1292, "Truncated incorrect INTEGER value: '1.5'"], $session->diagnostics->conditions[0]);
    }

    public function testExecuteCreatesARecurringEventStartingNow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $session->query('CREATE EVENT e ON SCHEDULE EVERY 1.5 HOUR DO SELECT 1');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $event = $schema->events['e'];

        self::assertSame([['2', 'HOUR'], 'ENABLED', false, 'SELECT 1'], [$event->every, $event->status, $event->preserve, $event->body]);
    }

    public function testExecuteDropsAOneTimeEventInThePastAtOnce(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $session->query("CREATE EVENT e ON SCHEDULE AT '2000-01-01 00:00:00' DO SELECT 1");

        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame([[], [['Note', 1588, 'Event execution time is in the past and ON COMPLETION NOT PRESERVE is set. The event was dropped immediately after creation.']]], [$schema->events, $session->diagnostics->conditions]);
    }

    public function testExecuteEvaluatesTheScheduleBeforeLookingUpTheEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect AT value: 'garbage'");

        $session->query("ALTER EVENT nope ON SCHEDULE AT 'garbage'");
    }

    public function testExecuteRefusesAMissingEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1539);
        $this->expectExceptionMessage("Unknown event 'ACTION'");

        $session->query('ALTER EVENT ACTION ENABLE');
    }

    public function testAlterRenamesTheEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $event = $schema->events['e'];

        (new EventCommand())->alter(new AlterEvent(new QualifiedName(new Name('e')), null, null, new QualifiedName(new Name('f'), new Name('sys')), EventStatus::Disable), $event, $session, null, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(['sys', 'f', 'DISABLED', []], [$event->schema, $event->name, $event->status, $schema->events]);
    }

    public function testAlterRefusesTheSameName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1');

        $this->expectExceptionCode(1551);

        $session->query('ALTER EVENT e RENAME TO e');
    }

    public function testFoundAnswersTheEventOfTheName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SELECT 1');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        $found = (new EventCommand())->found(new AlterEvent(new QualifiedName(new Name('E')), status: EventStatus::Enable), 'd', $session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame($schema->events['e'], $found);
    }

    public function testFoundRefusesAnEventOfAMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1539);
        $this->expectExceptionMessage("Unknown event 'e'");

        (new EventCommand())->found(new AlterEvent(new QualifiedName(new Name('e')), status: EventStatus::Enable), 'nodb', $session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
    }

    public function testLapseDisablesAPreservedEventWhoseTimeHasPassed(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE EVENT e ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION PRESERVE DO SELECT 1");
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $event = $schema->events['e'];
        $event->at = '2000-01-01 00:00:00';

        (new EventCommand())->lapse($event, true, $session, new Context($session->modes(), $session->diagnostics, $session->variables, 1700000000.0));

        self::assertSame(['DISABLED', [['Note', 1544, 'Event execution time is in the past. Event has been disabled']]], [$schema->events['e']->status, $session->diagnostics->conditions]);
    }

    public function testLapseKeepsAnEventWhoseTimeIsAhead(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE EVENT e ON SCHEDULE AT '2030-01-01 00:00:00' DO SELECT 1");
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        (new EventCommand())->lapse($schema->events['e'], true, $session, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(['ENABLED', []], [$schema->events['e']->status, $session->diagnostics->conditions]);
    }

    public function testStatusAnswersTheStatusShowEventsShows(): void
    {
        $command = new EventCommand();

        self::assertSame(['ENABLED', 'DISABLED', 'REPLICA_SIDE_DISABLED'], [$command->status(null), $command->status(EventStatus::Disable), $command->status(EventStatus::DisableOnReplica)]);
    }

    public function testScheduleRefusesAnIntervalThatIsNotPositive(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1542);
        $this->expectExceptionMessage('INTERVAL is either not positive or too big');

        $session->query('CREATE EVENT e ON SCHEDULE EVERY 0 HOUR DO SELECT 1');
    }

    public function testScheduleRefusesAnEndBeforeTheStart(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1543);

        $session->query("CREATE EVENT e ON SCHEDULE EVERY 1 DAY STARTS '2031-01-01' ENDS '2030-01-01' DO SELECT 1");
    }

    public function testTimeTruncatesTheFraction(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("CREATE EVENT e ON SCHEDULE AT '2030-01-01 00:00:00.5' DO SELECT 1");
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context, 'root', 'localhost', 1, []), $session->instance->dictionary);
        $statement = $operation->statement;

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule::class, $statement->schedule);
        self::assertSame('2030-01-01 00:00:00', (new EventCommand())->time($statement->schedule->at, 'AT', $planner, $context));
    }

    public function testCompositeCarriesThePartsIntoTheLargerUnits(): void
    {
        $command = new EventCommand();

        self::assertSame(["'2:30'", "'0 0:3:4'", "'2-2'", "'0 5'"], [$command->composite('1:90', IntervalUnit::HourMinute), $command->composite('3:4', IntervalUnit::DaySecond), $command->composite('1-14', IntervalUnit::YearMonth), $command->composite('5', IntervalUnit::DayHour)]);
    }

    public function testCompositeRefusesTooManyParts(): void
    {
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect INTERVAL value: '1:2:3:4'");

        (new EventCommand())->composite('1:2:3:4', IntervalUnit::HourSecond);
    }

    public function testExecuteKeepsTheEventInTheDictionary(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $session->query("CREATE EVENT e2 ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION PRESERVE DISABLE COMMENT 'c' DO SELECT 2");

        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame(['2030-01-01 00:00:00', 'DISABLED', true, 'c'], [$schema->events['e2']->at, $schema->events['e2']->status, $schema->events['e2']->preserve, $schema->events['e2']->comment]);
    }

    public function testExecuteNotesTheDefinerAndRefusesTheRenamedDatabaseBeforeAMissingEvent(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $error = $session->run('ALTER DEFINER = nobody EVENT e RENAME TO nodb.x')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '1449', "The user specified as a definer ('nobody'@'%') does not exist"], ['Error', '1049', "Unknown database 'nodb'"]], $warnings->rows);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInvalidInstants(): iterable
    {
        yield '5.6 upper bound' => ['5.6.51', '2038-01-19 03:14:08'];
        yield '5.7 upper bound' => ['5.7.44', '2038-01-19 03:14:08'];
        yield 'modern upper bound' => ['8.4.7', '3001-01-19 00:00:00'];
        yield 'modern zero instant' => ['8.4.7', '1970-01-01 00:00:00'];
    }

    #[DataProvider('providerInvalidInstants')]
    public function testTimeRejectsInstantsOutsideTheReleaseRange(string $version, string $time): void
    {
        $session = (new Instance($version))->connect();
        $session->query("CREATE DATABASE d;USE d;SET time_zone='+00:00';SET timestamp=1600000000");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect AT value: '" . $time . "'");

        $session->query("CREATE EVENT e ON SCHEDULE AT '" . $time . "' ON COMPLETION PRESERVE DISABLE DO SELECT 1");
    }

    public function testTimeRejectsAnEndOutsideTheEpochRangeWithTheEndCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d;USE d;SET time_zone='+00:00';SET timestamp=1600000000");
        $error = $session->run("CREATE EVENT e ON SCHEDULE EVERY 1 YEAR ENDS '3001-01-19' DO SELECT 1")[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1543, $error->getCode());
    }

    public function testLapseDoesNotWarnWhenCreationAlreadyDisablesAPreservedEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d;USE d;SET timestamp=1600000000;CREATE EVENT e ON SCHEDULE AT '1970-01-02' ON COMPLETION PRESERVE DISABLE DO SELECT 1");

        self::assertSame([], $session->diagnostics->conditions);
        self::assertSame('DISABLED', $session->instance->dictionary->schema('d')?->events['e']->status);
    }

    public function testChangingRejectsAnExpiredReplacementBeforeMutatingTheEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d;USE d;SET timestamp=1600000000;CREATE EVENT e ON SCHEDULE AT '2099-01-01' DO SELECT 1");
        $error = $session->run("ALTER EVENT e ON SCHEDULE AT '1970-01-02' RENAME TO changed DISABLE")[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1589, $error->getCode());
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame('2099-01-01 00:00:00', $schema->events['e']->at);
        self::assertSame('ENABLED', $schema->events['e']->status);
    }
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerScopeConditions(): iterable
    {
        yield 'legacy conversion' => ['5.7.44', [1238, 1543]];
        yield 'modern contextualization' => ['8.4.7', [1238]];
    }

    /**
     * @param list<int> $conditions Ordered diagnostic codes
     */
    #[DataProvider('providerScopeConditions')]
    public function testExpressionPreservesLegacyScopeConversionConditions(string $release, array $conditions): void
    {
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $error = $session->run('ALTER EVENT e ON SCHEDULE EVERY 1 DAY ENDS @@session.version')[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1238, $error->getCode());
        self::assertSame($conditions, array_column($session->diagnostics->conditions, 1));
    }

}
