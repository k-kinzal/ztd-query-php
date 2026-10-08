<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\EventCommand;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use PHPUnit\Framework\Attributes\CoversClass;
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
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new EventCommand())->clearsDiagnostics());
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

        (new EventCommand())->alter(new AlterEvent(new QualifiedName(new Name('e')), null, null, new QualifiedName(new Name('f'), new Name('sys')), EventStatus::Disable), $event, $session, null);

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
}
