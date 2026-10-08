<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use MySqlMemory\Command\DatabaseCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;

#[CoversClass(DatabaseCommand::class)]
#[Small]
final class DatabaseCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new DatabaseCommand())->clearsDiagnostics());
    }

    public function testExecuteCreatesADatabaseWithOneAffectedRow(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('CREATE DATABASE d')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 0, 0, ''], [$reply->affectedRows, $reply->lastInsertId, $reply->warnings, $reply->info]);
        self::assertSame('utf8mb4_0900_ai_ci', $session->instance->dictionary->schema('d')?->collation);
    }

    public function testExecuteCreatesADatabaseWithTheCollationItNames(): void
    {
        $session = (new Instance())->connect();

        $session->query('CREATE DATABASE d COLLATE UTF8MB4_BIN');

        self::assertSame('utf8mb4_bin', $session->instance->dictionary->schema('d')?->collation);
    }

    public function testExecuteCreatesADatabaseWithTheDefaultCollationOfTheCharacterSetItNames(): void
    {
        $session = (new Instance())->connect();

        $session->query('CREATE DATABASE l CHARACTER SET latin1; CREATE DATABASE b COLLATE latin1_bin CHARACTER SET latin1');

        self::assertSame(['latin1_swedish_ci', 'latin1_bin'], [$session->instance->dictionary->schema('l')?->collation, $session->instance->dictionary->schema('b')?->collation]);
    }

    public function testCollationPrefersTheCollationItNamesToTheCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $named = $session->analyze('CREATE DATABASE b COLLATE latin1_bin CHARACTER SET latin1')->statement;
        $charset = $session->analyze('CREATE DATABASE l CHARACTER SET latin1')->statement;
        $plain = $session->analyze('CREATE DATABASE p')->statement;
        self::assertInstanceOf(CreateDatabase::class, $named);
        self::assertInstanceOf(CreateDatabase::class, $charset);
        self::assertInstanceOf(CreateDatabase::class, $plain);

        self::assertSame(['latin1_bin', 'latin1_swedish_ci', 'utf8mb4_0900_ai_ci'], [(new DatabaseCommand())->collation($named, $session), (new DatabaseCommand())->collation($charset, $session), (new DatabaseCommand())->collation($plain, $session)]);
    }

    public function testExecuteRefusesToCreateAnExistingDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1007);
        $this->expectExceptionMessage("Can't create database 'd'; database exists");

        $session->query('CREATE DATABASE d');
    }

    public function testExecuteCreatesAnExistingDatabaseIfNotExistsWithANote(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        $reply = $session->query('CREATE DATABASE IF NOT EXISTS d')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 1], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '1007', "Can't create database 'd'; database exists"]], $warnings->rows);
    }

    public function testExecuteUsesADatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        $reply = $session->query('USE d')[0];
        $current = $session->query('SELECT DATABASE()')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $current);
        self::assertSame([['d']], $current->rows);
    }

    public function testExecuteDropsADatabaseCountingItsTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');

        $reply = $session->query('DROP DATABASE d')[0];
        $current = $session->query('SELECT DATABASE()')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(2, $reply->affectedRows);
        self::assertNull($session->instance->dictionary->schema('d'));
        self::assertInstanceOf(ResultSet::class, $current);
        self::assertSame([[null]], $current->rows);
    }

    public function testExecuteRefusesToDropAMissingDatabase(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1008);
        $this->expectExceptionMessage("Can't drop database 'd'; database doesn't exist");

        $session->query('DROP DATABASE d');
    }

    public function testExecuteDropsAMissingDatabaseIfExistsWithANote(): void
    {
        $session = (new Instance())->connect();

        $reply = $session->query('DROP DATABASE IF EXISTS d')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, 1], [$reply->affectedRows, $reply->warnings]);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Note', '1008', "Can't drop database 'd'; database doesn't exist"]], $warnings->rows);
    }
}
