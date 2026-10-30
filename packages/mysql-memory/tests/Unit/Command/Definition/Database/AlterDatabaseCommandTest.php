<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Database;

use MySqlMemory\Command\Definition\Database\AlterDatabaseCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(AlterDatabaseCommand::class)]
#[Small]
final class AlterDatabaseCommandTest extends TestCase
{
    public function testExecuteDoesNotCountRetainedLegacyConditionsAsNewWarnings(): void
    {
        $session = (new Instance('5.6.51', databases: ['test']))->connect(database: 'test');
        $session->run('ALTER DATABASE absent COLLATE latin1_bin');
        $previous = $session->diagnostics->conditions;

        $replies = $session->query('ALTER DATABASE test COLLATE latin1_bin');

        self::assertInstanceOf(Completion::class, $replies[0]);
        self::assertSame(0, $replies[0]->warnings);
        self::assertSame($previous, $session->diagnostics->conditions);
    }

    #[DataProvider('providerLockedDatabases')]
    public function testSchemaChecksLocksAfterOptionsAndNames(string $version, ?string $database, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['pbt', 'existing']))->connect(database: $database);
        $session->query('CREATE TABLE pbt.t (id INT); LOCK TABLES pbt.t WRITE');
        $schema = $session->instance->dictionary->schema('existing');
        self::assertNotNull($schema);
        $collation = $schema->collation;
        $replies = $session->run($sql);

        self::assertInstanceOf(SqlError::class, $replies[0]);
        self::assertSame($code, $replies[0]->getCode());
        self::assertSame($collation, $schema->collation);
        self::assertSame($database ?? '', $session->variables->database);
    }

    /**
     * @return iterable<string, array{string, string|null, string, int}>
     */
    public static function providerLockedDatabases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach ([
                ['pbt', 'ALTER DATABASE existing COLLATE latin1_bin', 1192],
                ['pbt', 'ALTER DATABASE absent COLLATE latin1_bin', 1192],
                ['pbt', 'ALTER DATABASE absent CHARACTER SET missing', 1115],
                ['pbt', 'ALTER DATABASE existing CHARACTER SET latin1 COLLATE utf8mb4_bin', 1253],
                ['pbt', 'ALTER DATABASE information_schema COLLATE latin1_bin', 1044],
                ['pbt', 'ALTER DATABASE `` COLLATE latin1_bin', 1102],
                [null, 'ALTER DATABASE COLLATE latin1_bin', 1046],
                [null, 'ALTER DATABASE CHARACTER SET missing', 1115],
            ] as [$database, $sql, $code]) {
                yield $version . '/' . $sql => [$version, $database, $sql, $code];
            }
        }
    }

    public function testCollationBoundsTheDiagnosticNameWithoutChangingItsValue(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $this->expectExceptionCode(1273);
        $this->expectExceptionMessage("Unknown collation: '" . str_repeat('x', 64) . "'");

        $session->query("ALTER DATABASE COLLATE '" . str_repeat('x', 100) . "'");
    }

    public function testExecuteEndsEncryptionDiagnosticTextAtNul(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect argument (should be Y or N) value: 'a'");

        $session->query("ALTER DATABASE d ENCRYPTION = 'a\\0b'");
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new AlterDatabaseCommand())->clearsDiagnostics());
    }

    public function testExecuteChangesTheDefaultCollationOfLaterTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $reply = $session->query('ALTER DATABASE CHARACTER SET latin1')[0];
        $session->query('CREATE TABLE t (a VARCHAR(5))');
        $session->query("INSERT INTO t VALUES ('x')");
        $collation = $session->query('SELECT COLLATION(a) FROM t')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
        self::assertInstanceOf(ResultSet::class, $collation);
        self::assertSame([['latin1_swedish_ci']], $collation->rows);
    }

    public function testExecuteWarnsOfTheUtf8Alias(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        $session->query('ALTER DATABASE d CHARACTER SET utf8');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3719', "'utf8' is currently an alias for the character set UTF8MB3, but will be an alias for UTF8MB4 in a future release. Please consider using UTF8MB4 in order to be unambiguous."]], $warnings->rows);
        self::assertSame('utf8mb3_general_ci', $session->instance->dictionary->schema('d')?->collation);
    }

    public function testExecuteRefusesACollationOfAnotherCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1253);
        $this->expectExceptionMessage("COLLATION 'utf8mb4_bin' is not valid for CHARACTER SET 'latin1'");

        $session->query('ALTER DATABASE d CHARACTER SET latin1 COLLATE utf8mb4_bin');
    }

    public function testExecuteRefusesTwoCharacterSets(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1302);
        $this->expectExceptionMessage("Conflicting declarations: 'CHARACTER SET utf8mb4' and 'CHARACTER SET latin1'");

        $session->query('ALTER DATABASE d CHARACTER SET utf8mb4 CHARACTER SET latin1');
    }

    public function testExecuteRefusesAnEncryptionOtherThanYOrN(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect argument (should be Y or N) value: 'x'");

        $session->query("ALTER DATABASE d ENCRYPTION 'x'");
    }

    public function testExecuteRefusesADatabaseThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3503);
        $this->expectExceptionMessage("Database 'nope' doesn't exist");

        $session->query('ALTER DATABASE nope CHARACTER SET latin1');
    }

    public function testExecuteRefusesInformationSchema(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1044);

        $session->query('ALTER DATABASE information_schema CHARACTER SET latin1');
    }

    public function testCollationAnswersTheDefaultCollationOfACharacterSetAlone(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('ALTER DATABASE d CHARACTER SET latin1');
        self::assertInstanceOf(AlterDatabase::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('latin1_swedish_ci', (new AlterDatabaseCommand())->collation($statement, GrammarRelease::MySql847, $context)?->name);
    }

    public function testCollationAnswersNullWithoutACharacterSetOrCollation(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('ALTER DATABASE d READ ONLY = 0');
        self::assertInstanceOf(AlterDatabase::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertNull((new AlterDatabaseCommand())->collation($statement, GrammarRelease::MySql847, $context));
    }

    public function testCharsetRefusesAnUnknownCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('ALTER DATABASE d CHARACTER SET nope');
        self::assertInstanceOf(AlterDatabase::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(DatabaseCharset::class, $option);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1115);
        $this->expectExceptionMessage("Unknown character set: 'nope'");

        (new AlterDatabaseCommand())->charset($option, null, $context);
    }

    public function testCharsetAnswersTheNamedCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('ALTER DATABASE d CHARACTER SET latin1');
        self::assertInstanceOf(AlterDatabase::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(DatabaseCharset::class, $option);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame('latin1', (new AlterDatabaseCommand())->charset($option, Charset::named('latin1'), $context)->name);
    }

    public function testSchemaAnswersTheCurrentDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $statement = $session->analyze('ALTER DATABASE CHARACTER SET latin1');
        self::assertInstanceOf(AlterDatabase::class, $statement);

        self::assertSame($session->instance->dictionary->schema('d'), (new AlterDatabaseCommand())->schema($statement, $session));
    }

    public function testSchemaRefusesNoCurrentDatabase(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('ALTER DATABASE CHARACTER SET latin1');
        self::assertInstanceOf(AlterDatabase::class, $statement);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        (new AlterDatabaseCommand())->schema($statement, $session);
    }

    public function testSchemaUsesTheLegacyEncodedOptionsPathForAnAbsentDatabase(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $this->expectExceptionCode(1);
        $this->expectExceptionMessage("Can't create/write to file './a@0020b/db.opt' (Errcode: 2 - No such file or directory)");
        $session->query('ALTER DATABASE `a b` COLLATE DEFAULT');
    }

    public function testCollationResolvesAnExplicitDefaultFromTheServerSetting(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_bin; ALTER DATABASE d COLLATE DEFAULT');
        self::assertSame('latin1_swedish_ci', $session->instance->dictionary->schema('d')?->collation);
        $session->query('ALTER DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_bin; ALTER DATABASE d CHARACTER SET DEFAULT');
        self::assertSame('latin1_swedish_ci', $session->instance->dictionary->schema('d')->collation);
    }
}
