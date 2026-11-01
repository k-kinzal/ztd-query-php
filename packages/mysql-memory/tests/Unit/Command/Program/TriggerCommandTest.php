<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Program;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Command\Program\TriggerCommand;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\Trigger;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TriggerCommand::class)]
#[Small]
final class TriggerCommandTest extends TestCase
{
    #[DataProvider('providerLockedTargets')]
    public function testExecuteChecksPersistentTargetLocksBeforeExistingTriggers(string $version, string $locks, string $target, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT); CREATE VIEW v AS SELECT a FROM t; CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @a=1; LOCK TABLES ' . $locks);
        $reply = $session->run('CREATE TRIGGER tr AFTER INSERT ON ' . $target . ' FOR EACH ROW SET @a=1')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame($code, $reply->getCode());
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerLockedTargets(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['t READ' => 1099, 't AS x READ' => 1099, 't WRITE' => 1359, 't AS x WRITE' => 1359, 't AS x READ,t AS y WRITE' => 1359, 't READ,t AS y WRITE' => 1359, 'u WRITE' => 1100] as $locks => $code) {
                yield $version . '/' . $locks => [$version, $locks, 't', $code];
            }
            foreach (['absent', 'v', 'absent_db.t'] as $target) {
                yield $version . '/' . $target => [$version, 't WRITE', $target, 1100];
            }
            yield $version . '/system table' => [$version, 't WRITE', 'mysql.user', 1465];
            yield $version . '/missing system table' => [$version, 't WRITE', 'mysql.absent', 1465];
        }
    }

    public function testRelationRejectsATemporaryShadowBeforeCheckingThePersistentLock(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); LOCK TABLES t READ; CREATE TEMPORARY TABLE t(a INT)');

        $this->expectExceptionCode(1361);
        $this->expectExceptionMessage("Trigger's 't' is view or temporary table");
        (new TriggerCommand())->relation(new QualifiedName(new Name('tr')), new TriggerTable(new QualifiedName(new Name('t'))), $session);
    }

    #[TestWith(['5.6.51', 1146])]
    #[TestWith(['5.7.44', 1146])]
    #[TestWith(['8.4.7', 1049])]
    public function testTargetPreservesTheVersionedMissingSchemaCondition(string $release, int $code): void
    {
        $session = (new Instance($release))->connect();
        $create = new CreateTrigger(new QualifiedName(new Name('tr'), new Name('x')), TriggerTime::Before, TriggerEvent::Update, new TriggerTable(new QualifiedName(new Name('t'))), new Block([], []));
        $session->text = 'unrelated invalid input';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new TriggerCommand())->target($create, $session);
    }

    public function testRelationResolvesTypedIdentitiesWithoutReadingInput(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->text = 'unrelated invalid input';
        $name = new QualifiedName(new Name('tr'));
        $table = new TriggerTable(new QualifiedName(new Name('t')));
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
        $precedes = $session->analyze('CREATE TRIGGER z3 BEFORE INSERT ON t FOR EACH ROW PRECEDES Z2 SET @a = 3');
        $last = $session->analyze('CREATE TRIGGER z3 BEFORE INSERT ON t FOR EACH ROW SET @a = 3');
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
        $statement = $session->analyze('CREATE TRIGGER z3 BEFORE INSERT ON t FOR EACH ROW FOLLOWS nope SET @a = 3');
        self::assertInstanceOf(CreateTrigger::class, $statement);

        $this->expectExceptionCode(3011);
        $this->expectExceptionMessage("Referenced trigger 'nope' for the given action time and event type does not exist.");

        (new TriggerCommand())->position($statement, new Schema('d'), 't');
    }

    /**
     * @return iterable<string, array{string, TriggerTime, TriggerEvent}>
     */
    public static function providerTriggerStateHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (TriggerTime::cases() as $time) {
                foreach (TriggerEvent::cases() as $event) {
                    yield $release . ':' . $time->value . ':' . $event->value => [$release, $time, $event];
                }
            }
        }
    }

    #[DataProvider('providerTriggerStateHistories')]
    public function testExecuteOneDefinitionSurvivesTheDatabaseAndPopulatedTableTransitions(string $release, TriggerTime $time, TriggerEvent $event): void
    {
        $create = new CreateTrigger(new QualifiedName(new Name('tr'), new Name('x')), $time, $event, new TriggerTable(new QualifiedName(new Name('t'))), new Block([], []));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $statement1 = $create;
        $parameters1 = [];
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, \SqlSemantics\Contract\ParameterStyle::Native));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $absent = $result1;
        self::assertSame(str_starts_with($release, '5.') ? 1146 : 1049, ($absent instanceof SqlError ? [$absent->getCode(), $absent->sqlState(), $absent->getMessage()] : null)[0] ?? null);
        $session->query('CREATE DATABASE x');
        $session->text = 'unrelated invalid input';
        $statement2 = $create;
        $parameters2 = [];
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation2 = (new Binding())->operation($session, $statement2, $parameters2, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, \SqlSemantics\Contract\ParameterStyle::Native));
            $result2 = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2), $parameters2);
        } catch (SqlError $executionError) {
            $result2 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $missing = $result2;
        self::assertSame(1146, ($missing instanceof SqlError ? [$missing->getCode(), $missing->sqlState(), $missing->getMessage()] : null)[0] ?? null);
        $session->query('CREATE TABLE x.t(a INT); INSERT INTO x.t VALUES(1),(2)');
        $session->text = 'unrelated invalid input';
        $statement3 = $create;
        $parameters3 = [];
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation3 = (new Binding())->operation($session, $statement3, $parameters3, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, \SqlSemantics\Contract\ParameterStyle::Native));
            $result3 = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3), $parameters3);
        } catch (SqlError $executionError) {
            $result3 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        self::assertNotInstanceOf(SqlError::class, $result3);
        $statement4 = $create;
        $parameters4 = [];
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation4 = (new Binding())->operation($session, $statement4, $parameters4, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, \SqlSemantics\Contract\ParameterStyle::Native));
            $result4 = (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4), $parameters4);
        } catch (SqlError $executionError) {
            $result4 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $duplicate = $result4;
        self::assertSame($release === '5.6.51' ? 1235 : 1359, ($duplicate instanceof SqlError ? [$duplicate->getCode(), $duplicate->sqlState(), $duplicate->getMessage()] : null)[0] ?? null);
        self::assertSame('unrelated invalid input', $session->text);
    }
}
