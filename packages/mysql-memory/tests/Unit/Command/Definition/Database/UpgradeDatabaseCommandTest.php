<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Database;

use MySqlMemory\Command\Definition\Database\UpgradeDatabaseCommand;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName;
use SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Embedding;

#[CoversClass(UpgradeDatabaseCommand::class)]
#[Medium]
final class UpgradeDatabaseCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        yield '5.6' => ['5.6.51'];
        yield '5.7' => ['5.7.44'];
    }
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function providerErrors(): iterable
    {
        yield 'prefix' => ['a', 1221, 'Incorrect usage of ALTER DATABASE UPGRADE DATA DIRECTORY NAME and name'];
        yield 'empty suffix' => ['#mysql50#', 1102, "Incorrect database name '#mysql50#'"];
        yield 'missing' => ['#mysql50#missing', 1049, "Unknown database '#mysql50#missing'"];
        yield 'unencoded directory alias' => ['#mysql50#a', 1007, "Can't create database 'a'; database exists"];
    }
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new UpgradeDatabaseCommand())->clearsDiagnostics());
    }
    #[DataProvider('providerReleases')]
    public function testExecuteMovesRowsAndSelectedDatabaseFromADirectStructure(string $release): void
    {
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE `#mysql50#old-name` COLLATE latin1_bin; CREATE TABLE `#mysql50#old-name`.t(i INT); INSERT INTO `#mysql50#old-name`.t VALUES (2); USE `#mysql50#old-name`');
        $statement1 = (new Embedding())->statement(new UpgradeDatabaseName(new LegacyDatabaseName(new Name('old-name'))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->affectedRows);
        self::assertSame($release === '5.7.44' ? 1 : 0, $reply->warnings);
        self::assertSame('old-name', $session->variables->database);
        self::assertNull($session->instance->dictionary->schema('#mysql50#old-name'));
        self::assertSame('latin1_bin', $session->instance->dictionary->schema('old-name')?->collation);
        $rows = $session->query('SELECT * FROM t')[0];
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['2']], $rows->rows);
    }
    #[DataProvider('providerErrors')]
    public function testSourceReportsTheNativeErrorInOrder(string $name, int $code, string $message): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE a');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);
        (new UpgradeDatabaseCommand())->source($name, $session);
    }
    public function testSourceFindsTheLegacySchema(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE `#mysql50#old-name`');
        self::assertSame($session->instance->dictionary->schema('#mysql50#old-name'), (new UpgradeDatabaseCommand())->source('#mysql50#old-name', $session));
    }
    public function testMoveDropsRoutinesAndEventsWhileKeepingTriggers(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE `#mysql50#old-name`; CREATE TABLE `#mysql50#old-name`.t(i INT); CREATE PROCEDURE `#mysql50#old-name`.p() SELECT 42; CREATE FUNCTION `#mysql50#old-name`.f() RETURNS INT DETERMINISTIC RETURN 42; CREATE EVENT `#mysql50#old-name`.e ON SCHEDULE EVERY 1 DAY DISABLE DO SELECT 1; CREATE TRIGGER `#mysql50#old-name`.tr BEFORE INSERT ON `#mysql50#old-name`.t FOR EACH ROW SET NEW.i = 7');
        $source = (new UpgradeDatabaseCommand())->source('#mysql50#old-name', $session);
        self::assertCount(1, $source->procedures);
        self::assertCount(1, $source->functions);
        self::assertCount(1, $source->events);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        (new UpgradeDatabaseCommand())->move($source, $session, $context, new Connection($session->variables, $context));
        $target = $session->instance->dictionary->schema('old-name');
        self::assertNotNull($target);
        self::assertSame([[], [], [], $source->triggers], [$target->procedures, $target->functions, $target->events, $target->triggers]);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database '#mysql50#old-name'");
        $session->query('INSERT INTO `old-name`.t VALUES (2)');
    }
    public function testViewRetainsItsBodyReferencesUnderTheNewDeclaration(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE `#mysql50#old-name`; CREATE TABLE `#mysql50#old-name`.t(i INT); CREATE VIEW `#mysql50#old-name`.v AS SELECT i FROM `#mysql50#old-name`.t');
        $original = $session->instance->dictionary->schema('#mysql50#old-name')?->views['v'];
        self::assertNotNull($original);
        $view = (new UpgradeDatabaseCommand())->view($original, 'old-name');
        self::assertSame('old-name', $view->schema);
        self::assertSame('old-name', $view->declaration->name->schema?->value);
        self::assertSame($original->definition, $view->definition);
        self::assertSame($original->created, $view->created);
        $session->query('ALTER DATABASE `#mysql50#old-name` UPGRADE DATA DIRECTORY NAME');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1356);
        $this->expectExceptionMessage("View 'old-name.v'");
        $session->query('SELECT * FROM `old-name`.v');
    }
    public function testSourceRefusesTheSameDirectoryWhenTheLegacySpellingWasCreatedFirst(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE `#mysql50#x`');
        $this->expectExceptionCode(1007);
        $this->expectExceptionMessage("Can't create database 'x'; database exists");
        (new UpgradeDatabaseCommand())->source('#mysql50#x', $session);
    }
}
