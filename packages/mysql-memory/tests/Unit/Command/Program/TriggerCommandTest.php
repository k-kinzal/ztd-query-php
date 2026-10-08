<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Program\TriggerCommand;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\Trigger;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;

#[CoversClass(TriggerCommand::class)]
#[Small]
final class TriggerCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new TriggerCommand())->clearsDiagnostics());
    }

    public function testExecutePlacesTriggersByFollowsAndPrecedes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE TRIGGER z1 BEFORE INSERT ON t FOR EACH ROW SET @a = 1');
        $session->query('CREATE TRIGGER a2 BEFORE INSERT ON t FOR EACH ROW PRECEDES z1 SET @a = 2');
        $session->query('CREATE TRIGGER c4 BEFORE INSERT ON t FOR EACH ROW FOLLOWS a2 SET @a = 4');

        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame(['a2', 'c4', 'z1'], array_map(static fn (Trigger $trigger): string => $trigger->name, $schema->triggers));
    }

    public function testExecuteRefusesAReferencedTriggerOfAnotherEvent(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TRIGGER b5 AFTER UPDATE ON t FOR EACH ROW SET @a = 5');

        $this->expectExceptionCode(3011);
        $this->expectExceptionMessage("Referenced trigger 'b5' for the given action time and event type does not exist.");

        $session->query('CREATE TRIGGER q BEFORE INSERT ON t FOR EACH ROW FOLLOWS b5 SET @a = 1');
    }

    public function testExecuteRefusesAnExistingNameOnAnotherTableWithIfNotExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TABLE b (a INT)');
        $session->query('CREATE TRIGGER z1 BEFORE INSERT ON t FOR EACH ROW SET @a = 1');

        $this->expectExceptionCode(4100);

        $session->query('CREATE TRIGGER IF NOT EXISTS z1 BEFORE INSERT ON b FOR EACH ROW SET @a = 1');
    }

    public function testExecuteRefusesATableInAnotherDatabaseThanTheTrigger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionCode(1435);
        $this->expectExceptionMessage('Trigger in wrong schema');

        $session->query('CREATE TRIGGER sys.y BEFORE INSERT ON d.t FOR EACH ROW SET @a = 1');
    }

    public function testExecuteRefusesAView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE VIEW v AS SELECT 1 AS x');

        $this->expectExceptionCode(1347);
        $this->expectExceptionMessage("'d.v' is not BASE TABLE");

        $session->query('CREATE TRIGGER x BEFORE INSERT ON v FOR EACH ROW SET @a = 1');
    }

    public function testPositionPlacesATriggerBeforeTheOneItPrecedes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE TRIGGER z1 BEFORE INSERT ON t FOR EACH ROW SET @a = 1');
        $session->query('CREATE TRIGGER z2 BEFORE INSERT ON t FOR EACH ROW SET @a = 2');
        $precedes = $session->analyze('CREATE TRIGGER z3 BEFORE INSERT ON t FOR EACH ROW PRECEDES Z2 SET @a = 3')->statement;
        $last = $session->analyze('CREATE TRIGGER z3 BEFORE INSERT ON t FOR EACH ROW SET @a = 3')->statement;
        self::assertInstanceOf(CreateTrigger::class, $precedes);
        self::assertInstanceOf(CreateTrigger::class, $last);
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);

        self::assertSame([1, 2], [(new TriggerCommand())->position($precedes, $schema, 't'), (new TriggerCommand())->position($last, $schema, 't')]);
    }

    public function testPositionRefusesATriggerItFollowsThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $statement = $session->analyze('CREATE TRIGGER z3 BEFORE INSERT ON t FOR EACH ROW FOLLOWS nope SET @a = 3')->statement;
        self::assertInstanceOf(CreateTrigger::class, $statement);

        $this->expectExceptionCode(3011);
        $this->expectExceptionMessage("Referenced trigger 'nope' for the given action time and event type does not exist.");

        (new TriggerCommand())->position($statement, new Schema('d'), 't');
    }
}
