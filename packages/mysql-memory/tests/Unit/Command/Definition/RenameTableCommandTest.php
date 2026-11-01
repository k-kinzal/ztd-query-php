<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\RenameTableCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;

#[CoversClass(RenameTableCommand::class)]
#[Small]
final class RenameTableCommandTest extends TestCase
{
    public function testLocksRejectsATemporaryHandleWithoutRenamingThePersistentNamesake(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TEMPORARY TABLE t(a INT); LOCK TABLES t WRITE');
        $error = $session->run('RENAME TABLE t TO x')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1100, $error->getCode());
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
        self::assertNull($session->instance->dictionary->table('d', 'x'));
    }

    #[DataProvider('providerLockedRenames')]
    public function testLocksChecksAllSourcesBeforeChangingAnyName(string $version, string $locks, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT); LOCK TABLES ' . $locks);
        $before = $session->locks;
        $error = $session->run($sql)[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame($code, $error->getCode());
        self::assertSame($before, $session->locks);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
        self::assertNotNull($session->instance->dictionary->table('d', 'u'));
        self::assertNull($session->instance->dictionary->table('d', 'x'));
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerLockedRenames(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach ([['t READ', 't TO t', 1099], ['t READ', 't TO absent.x', 1099], ['u WRITE', 't TO x', 1100], ['t WRITE', 'missing TO t', 1100], ['t WRITE', 't TO x, u TO t', 1100], ['t WRITE, u READ', 't TO t, u TO x', 1099], ['t WRITE', 't TO t', 1050], ['t READ', 't TO ` `', 1103]] as [$locks, $pairs, $code]) {
                yield $version . '/' . $locks . '/' . $pairs => [$version, $locks, 'RENAME TABLE ' . $pairs, str_starts_with($version, '5.') && $code !== 1103 ? 1192 : $code];
            }
        }
    }

    #[DataProvider('providerSuccessfulLockedRenames')]
    public function testLocksRetargetsChainedNamesAndPreservesExplicitAliases(string $version, string $lock, string $read, string $alias): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(7); LOCK TABLES ' . $lock);
        $session->query('RENAME TABLE t TO x, x TO y');
        $result = $session->query('SELECT a FROM ' . $read)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertEquals([new \MySqlMemory\Session\State\TableLock('d', 'y', $alias, true)], $session->locks);
        $session->query('RENAME TABLE y TO t');
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerSuccessfulLockedRenames(): iterable
    {
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version . '/implicit alias' => [$version, 't WRITE', 'y', 'y'];
            yield $version . '/explicit alias' => [$version, 't AS a WRITE', 'y AS a', 'a'];
        }
    }

    #[DataProvider('providerMissingLegacyDestinations')]
    public function testPrepareReportsLegacyDestinationFailuresAsEngineRenames(string $version, string $message): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');

        $this->expectExceptionCode(1025);
        $this->expectExceptionMessage($message);
        $session->query('RENAME TABLE t TO absent.x');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerMissingLegacyDestinations(): iterable
    {
        yield '5.6' => ['5.6.51', "Error on rename of './d/t' to './absent/x' (errno: -1 - Unknown error -1)"];
        yield '5.7' => ['5.7.44', "Error on rename of './d/t' to './absent/x' (errno: 168 - Unknown (generic) error from engine)"];
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new RenameTableCommand())->clearsDiagnostics());
    }

    public function testExecuteSwapsTwoTablesThroughAThirdName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (b INT); INSERT INTO t VALUES (1)');

        $session->query('RENAME TABLE t TO x, u TO t, x TO u');
        $rows = $session->query('SELECT * FROM u')[0];
        $tables = $session->query('SHOW TABLES')[0];

        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertSame([['1']], $rows->rows);
        self::assertInstanceOf(ResultSet::class, $tables);
        self::assertSame([['t'], ['u']], $tables->rows);
    }

    public function testExecutePreservesViewDeclarationAndRefreshStateAcrossRenamings(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t(a DATE); INSERT INTO t VALUES('2024-01-01'); CREATE ALGORITHM=MERGE VIEW v AS SELECT a FROM t; ALTER TABLE t ADD b INT");
        $session->query('SELECT * FROM v');
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        $original = $schema->views['v'];
        $session->query('RENAME TABLE v TO x, x TO u');
        $renamed = $schema->views['u'];
        self::assertSame($original->created, $renamed->created);
        self::assertSame($original->definition, $renamed->definition);
        self::assertSame($original->declaration->columns, $renamed->declaration->columns);
        self::assertSame('u', $renamed->declaration->name->name->value);
        self::assertTrue($renamed->declaration->columns[0]->expression);
        self::assertArrayNotHasKey('v', $schema->views);
        self::assertArrayNotHasKey('x', $schema->views);
        $result = $session->query('SELECT * FROM u')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['2024-01-01']], $result->rows);
        $failure = $session->run('RENAME TABLE u TO x, missing TO z')[0];
        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame($renamed, $schema->views['u']);
        self::assertArrayNotHasKey('x', $schema->views);
    }

    public function testPrepareResolvesChainedViewNamesWithoutPublishingThem(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); CREATE VIEW v AS SELECT a FROM t');
        $statement = $session->analyze('RENAME TABLE v TO x, x TO u');
        self::assertInstanceOf(RenameTable::class, $statement);
        [$names, $moves] = (new RenameTableCommand())->prepare($statement, $session->instance->dictionary, 'd', '8.4.7');
        self::assertCount(2, $moves);
        self::assertInstanceOf(\MySqlMemory\Dictionary\View::class, $names["d\0u"]);
        self::assertSame('u', $names["d\0u"]->name);
        self::assertArrayNotHasKey("d\0v", $names);
        self::assertArrayHasKey('v', $session->instance->dictionary->schemas['d']->views);
        self::assertArrayNotHasKey('u', $session->instance->dictionary->schemas['d']->views);
    }

    public function testExecuteRenamesNothingWhenARenameFails(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $error = $session->run('RENAME TABLE t TO x, nope TO z')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame([1146, "Table 'd.nope' doesn't exist"], [$error->getCode(), $error->getMessage()]);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
    }

    public function testExecuteChecksTheNewNameBeforeTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE u (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1050);
        $this->expectExceptionMessage("Table 'u' already exists");

        $session->query('RENAME TABLE nope TO u');
    }

    public function testExecuteDoesNotRenameATemporaryTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE tt (a INT)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);

        $session->query('RENAME TABLE tt TO tt2');
    }

    public function testWrittenChecksEveryNewNameBeforeTheDatabase(): void
    {
        $statement = (new Instance())->connect()->analyze('RENAME TABLE t TO u, v TO ` `');
        self::assertInstanceOf(RenameTable::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1103);

        (new RenameTableCommand())->written($statement, '');
    }

    public function testWrittenRefusesAnUnqualifiedNameWithoutADatabase(): void
    {
        $statement = (new Instance())->connect()->analyze('RENAME TABLE d.t TO u');
        self::assertInstanceOf(RenameTable::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        (new RenameTableCommand())->written($statement, '');
    }

    public function testValidRefusesANameEndingWithASpace(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1103);
        $this->expectExceptionMessage("Incorrect table name 'x '");

        (new RenameTableCommand())->valid('x ');
    }

    public function testValidRefusesALongName(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1059);

        (new RenameTableCommand())->valid(str_repeat('a', 65));
    }

    public function testVacantRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);

        (new RenameTableCommand())->vacant($session, 'abc', 'x');
    }

    public function testRenamedDeclaresTheTableUnderItsNewName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        $definition = (new RenameTableCommand())->renamed($session, $context, new Connection($session->variables, $context), $table, 'd', 'u');

        self::assertSame(['d', 'u', 'u'], [$definition->schema, $definition->name, $definition->declaration->name->name->value]);
    }

    public function testNamesLeavesTemporaryTablesOut(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TEMPORARY TABLE u (a INT)');

        self::assertSame(["d\0t"], array_keys((new RenameTableCommand())->names($session->instance->dictionary)));
    }

    public function testExecuteReportsAMissingTableAsAMissingFileIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1017);
        $this->expectExceptionMessage("Can't find file: './d/nosuch.frm' (errno: 2 - No such file or directory)");

        $session->query('RENAME TABLE nosuch TO other');
    }
}
