<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\DropViewCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DropViewCommand::class)]
#[Small]
final class DropViewCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new DropViewCommand())->clearsDiagnostics());
    }

    public function testExecuteNamesTheMissingViewsTogetherAndDropsNothing(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE VIEW v AS SELECT 1 AS x');

        $error = $session->run('DROP VIEW nope, v, nope2')[0];
        self::assertInstanceOf(SqlError::class, $error);

        self::assertSame([1051, "Unknown table 'd.nope,d.nope2'"], [$error->getCode(), $error->getMessage()]);
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertArrayHasKey('v', $schema->views);
    }

    public function testExecuteRefusesABaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionCode(1347);
        $this->expectExceptionMessage("'d.t' is not VIEW");

        $session->query('DROP VIEW nope, t');
    }

    public function testExecuteNotesWithIfExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW v AS SELECT 1 AS x');

        $session->query('DROP VIEW IF EXISTS nope, t, v');

        self::assertSame([['Note', 1051, "Unknown table 'd.nope'"], ['Note', 1347, "'d.t' is not VIEW"]], $session->diagnostics->conditions);
        $schema = $session->instance->dictionary->schema('d');
        self::assertNotNull($schema);
        self::assertSame([], $schema->views);
    }

    public function testExecuteRefusesANameWrittenTwice(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');

        $this->expectExceptionCode(1066);
        $this->expectExceptionMessage("Not unique table/alias: 'nope'");

        $session->query('DROP VIEW nope, nope');
    }

    #[DataProvider('providerLockedViewDrop')]
    public function testLockedViewDropChecksInputBeforeRefusingObjectAccess(string $version, string $lock, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT, KEY idx(id)); LOCK TABLE t ' . $lock);
        $error = $session->run($sql)[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame($code, $error->getCode());
        self::assertSame([$code], array_column($session->diagnostics->conditions, 1));
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerLockedViewDrop(): iterable
    {
        $cases = [
            'DROP VIEW absent' => 1192,
            'DROP VIEW IF EXISTS absent' => 1192,
            'DROP VIEW missing_db.absent' => 1192,
            'DROP VIEW absent, absent' => 1066,
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['READ', 'WRITE'] as $lock) {
                foreach ($cases as $sql => $code) {
                    yield $version . ':' . $lock . ':' . $sql => [$version, $lock, $sql, $code];
                }
            }
        }
    }
}
