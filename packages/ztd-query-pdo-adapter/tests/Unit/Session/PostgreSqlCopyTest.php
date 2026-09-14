<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Session\PostgreSqlCopy;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(PostgreSqlCopy::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\CopyArguments::class)]
final class PostgreSqlCopyTest extends TestCase
{
    public function testGuardRawLetsAStatementThatIsNotACopyThrough(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        $this->expectNotToPerformAssertions();

        $copy->guardRaw('SELECT 1');
    }

    public function testGuardRawRefusesACopyWrittenAsRawSql(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage(
            'ZTD Write Protection: Raw PostgreSQL COPY cannot preserve shadow isolation; '
            . 'use the pgsqlCopyToArray(), pgsqlCopyFromArray(), pgsqlCopyToFile(), or pgsqlCopyFromFile() methods.',
        );

        $copy->guardRaw('COPY copy_target FROM STDIN');
    }

    public function testGuardRawLetsEverythingThroughWhereTheDialectHasNoCopy(): void
    {
        $copy = new PostgreSqlCopy(new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection(new PDO('sqlite::memory:'))));

        $this->expectNotToPerformAssertions();

        $copy->guardRaw('COPY copy_target FROM STDIN');
    }

    public function testToArrayAnswersOneEncodedLinePerRow(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        self::assertSame(["1\tada\n", "2\tgrace\n"], $copy->toArray($pdo, 'copy_target'));
    }


    public function testFromArrayWritesEveryLineItIsGiven(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        $written = $copy->fromArray($pdo, 'copy_target', ["3\tlinus\n"]);

        $statement = $pdo->query('SELECT id, value FROM copy_target WHERE id = 3');
        $result1 = $pdo->query('SELECT COUNT(*) FROM copy_target');
        self::assertNotFalse($result1);
        self::assertSame(
            [true, 3, [['id' => 3, 'value' => 'linus']]],
            [$written, $result1->fetchColumn(), $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC)],
        );
    }

    public function testFromArrayWritesNothingWhereItIsGivenNothing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        $written = $copy->fromArray($pdo, 'copy_target', []);

        $result2 = $pdo->query('SELECT COUNT(*) FROM copy_target');
        self::assertNotFalse($result2);
        self::assertSame([true, 2], [$written, $result2->fetchColumn()]);
    }


    public function testFromArrayRefusesALineThatDoesNotFitTheTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        $this->expectExceptionMessage('PostgreSQL COPY row has 1 fields, but 2 fields are required.');

        $copy->fromArray($pdo, 'copy_target', ["3\n"]);
    }

    public function testToFileWritesEveryEncodedLineToTheFile(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);
        $path = tempnam(sys_get_temp_dir(), 'ztd');

        $written = $copy->toFile($pdo, 'copy_target', $path === false ? '' : $path);

        self::assertSame([true, "1\tada\n2\tgrace\n"], [$written, file_get_contents($path === false ? '' : $path)]);
    }

    public function testFromFileWritesEveryLineTheFileHolds(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);
        $path = tempnam(sys_get_temp_dir(), 'ztd');
        file_put_contents($path === false ? '' : $path, "3\tlinus\n4\tdennis\n");

        $written = $copy->fromFile($pdo, 'copy_target', $path === false ? '' : $path);

        $result3 = $pdo->query('SELECT COUNT(*) FROM copy_target');
        self::assertNotFalse($result3);
        self::assertSame([true, 4], [$written, $result3->fetchColumn()]);
    }

    public function testFromFileWritesNothingWhereThereIsNoFileToRead(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        self::assertFalse($copy->fromFile($pdo, 'copy_target', sys_get_temp_dir() . '/ztd-no-such-file'));
    }

    public function testTargetAnswersWhatTheCopyIsWrittenAgainst(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        [, $target] = $copy->target('copy_target', null);

        self::assertSame(['id', 'value'], $target->columns);
    }

    public function testTargetRefusesADialectThatHasNoCopy(): void
    {
        $copy = new PostgreSqlCopy(new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection(new PDO('sqlite::memory:'))));

        $this->expectExceptionMessage('PostgreSQL COPY methods require the PDO PostgreSQL driver.');

        $copy->target('copy_target', null);
    }

    public function testTargetRefusesATableNothingHasDescribed(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE copy_target (id INTEGER, value TEXT)');
        $pdo->exec("INSERT INTO copy_target (id, value) VALUES (1, 'ada'), (2, 'grace')");
        $registry = new TableDefinitionRegistry();
        $registry->register('copy_target', new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], [], []));
        $session = new Session(self::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), new PdoConnection($pdo), null, $registry, new \ZtdQuery\Platform\Postgres\PgSqlCopySupport());
        $copy = new PostgreSqlCopy($session);

        $this->expectExceptionMessage('PostgreSQL COPY cannot resolve the schema for table "absent".');

        $copy->target('absent', null);
    }





}
