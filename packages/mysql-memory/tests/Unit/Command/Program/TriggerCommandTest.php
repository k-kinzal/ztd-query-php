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
    #[\PHPUnit\Framework\Attributes\TestWith(['5.6.51', 1146])]
    #[\PHPUnit\Framework\Attributes\TestWith(['5.7.44', 1146])]
    #[\PHPUnit\Framework\Attributes\TestWith(['8.4.7', 1049])]
    public function testTargetPreservesTheVersionedMissingSchemaCondition(string $release, int $code): void
    {
        $session = (new Instance($release))->connect();
        $create = new CreateTrigger(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('tr'), new \SqlSemantics\Statement\Identifier\Name('x')), \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime::Before, \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent::Update, new \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block([], []));
        $session->text = 'unrelated invalid input';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode($code);
        (new TriggerCommand())->target($create, $session);
    }

    public function testRelationResolvesTypedIdentitiesWithoutReadingInput(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated invalid input';
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('tr'));
        $table = new \SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $resolved = (new TriggerCommand())->relation($name, $table, $session);
        self::assertSame(['d', 't'], array_slice($resolved, 0, 2));
        self::assertSame($session->instance->dictionary->schema('d'), $resolved[2]);
        self::assertSame('unrelated invalid input', $session->text);
    }

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
