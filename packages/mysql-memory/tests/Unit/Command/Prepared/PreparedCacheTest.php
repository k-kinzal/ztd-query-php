<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedCache;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreparedCache::class)]
#[Small]
final class PreparedCacheTest extends TestCase
{
    public function testChangedTracksViewDefinitionsAndPersistentDependencies(): void
    {
        $instance = new Instance(databases: ['d']);
        $session = $instance->connect(database: 'd');
        $administrator = $instance->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT); CREATE TABLE u (a INT); CREATE VIEW v AS SELECT a FROM t; CREATE VIEW w AS SELECT a FROM v');
        $administrator->query('ALTER TABLE t MODIFY a VARCHAR(12)');
        $cache = PreparedCache::capture($session->analyze('SELECT * FROM w', true), $session);
        $session->analyze('SELECT * FROM w');
        $session->query('CREATE TEMPORARY TABLE t (a INT); INSERT t VALUES (7); ALTER TABLE u ADD b INT');

        self::assertFalse($cache->changed($session));
        $administrator->query('ALTER TABLE t ADD b INT');
        self::assertTrue($cache->changed($session));

        $cache = PreparedCache::capture($session->analyze('SELECT * FROM w', true), $session);
        self::assertFalse($cache->changed($session));
        $administrator->query('CREATE OR REPLACE VIEW v AS SELECT a FROM u');
        self::assertTrue($cache->changed($session));
    }

    public function testChangedSeesAViewShadowInOnlyThePreparingSession(): void
    {
        $instance = new Instance(databases: ['d']);
        $first = $instance->connect(database: 'd');
        $second = $instance->connect(database: 'd');
        $first->query('CREATE VIEW v AS SELECT 1 AS a');
        $cache = PreparedCache::capture($first->analyze('SELECT a FROM v', true), $first);
        $second->query('CREATE TEMPORARY TABLE v (a INT)');
        self::assertFalse($cache->changed($first));
        $first->query('CREATE TEMPORARY TABLE v (a INT)');
        self::assertTrue($cache->changed($first));
    }

    public function testCaptureRecordsOnlyResolvedRelationDeclarations(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); CREATE TABLE u(b INT)');
        $operation = $session->analyze('SELECT a FROM t', true);

        $cache = PreparedCache::capture($operation, $session);

        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PreparedStatement::class, $cache->definition);
        self::assertSame($operation, $cache->definition->statement);
        self::assertCount(1, $cache->tables);
        self::assertSame(['d', 't'], array_slice($cache->tables[0], 0, 2));
    }

    public function testChangedIgnoresWritesAndUnrelatedDeclarations(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); CREATE TABLE u(b INT)');
        $cache = PreparedCache::capture($session->analyze('SELECT a FROM t', true), $session);
        $session->query('INSERT INTO t VALUES (1); ALTER TABLE u ADD c INT');

        self::assertFalse($cache->changed($session));

        $session->query('ALTER TABLE t ADD c INT');

        self::assertTrue($cache->changed($session));
    }

    public function testChangedRecognizesUnchangedTemporaryAndSystemTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TEMPORARY TABLE t(a INT)');
        $temporary = PreparedCache::capture($session->analyze('SELECT * FROM t', true), $session);
        $system = PreparedCache::capture($session->analyze('SELECT * FROM information_schema.CHARACTER_SETS', true), $session);

        self::assertFalse($temporary->changed($session));
        self::assertFalse($system->changed($session));
    }

    public function testRefreshRetainsTheObjectUntilMetadataInvalidatesThePreparation(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET sql_mode=''; CREATE DATABASE a; CREATE DATABASE b; USE a; CREATE TABLE t(a INT); INSERT INTO t VALUES (7)");
        $sql = 'SELECT *, "a", 1||2 FROM t';
        $operation = $session->analyze($sql, true);
        $input = (new \MySqlMemory\Session\Parse\Preparation\Alternatives())->read($sql, $session);
        $cache = PreparedCache::capture($operation, $session, $input);
        $types = ParameterBindings::capture($operation);
        $cache->refresh($session, $types, []);

        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PreparedStatement::class, $cache->definition);
        self::assertSame($operation, $cache->definition->statement);

        $session->query("ALTER TABLE t ADD b INT DEFAULT 9; USE b; SET sql_mode='ANSI_QUOTES,PIPES_AS_CONCAT'");
        $cache->refresh($session, $types, []);
        $result = $cache->definition->execute($session, 'unrelated invalid source', []);

        self::assertInstanceOf(\MySqlMemory\Command\Prepared\PreparedStatement::class, $cache->definition);
        self::assertNotSame($operation, $cache->definition->statement);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', '9', '7', '12']], $result->rows);
        self::assertSame('b', $session->variables->database);
        self::assertFalse($cache->changed($session));
    }
    public function testExecuteRetainsDerivedDomainsAndRestoresTheOuterBinding(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $statement = $session->analyze('SELECT ? / ? AS quotient', true);
        $cache = PreparedCache::capture($statement, $session);
        $types = ParameterBindings::capture($statement);
        $outer = [7 => \MySqlMemory\Typing\Domain::integer()];
        $session->preparation->domains = $outer;
        $reply = $cache->execute($session, 'generated preparation', [[1, \MySqlMemory\Typing\Domain::integer()], [6, \MySqlMemory\Typing\Domain::integer()]], $types);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['0.16666666666666666']], $reply->rows);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Double, $reply->columns[0]->type);
        self::assertSame($outer, $session->preparation->domains);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Double, $types->types[0][0]->kind);
    }

    public function testRunRecordsFailuresAndRestoresTheOuterBinding(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $statement = $session->analyze('SIGNAL SQLSTATE \'45000\'', true);
        $cache = PreparedCache::capture($statement, $session);
        $types = ParameterBindings::capture($statement);
        $outer = [7 => \MySqlMemory\Typing\Domain::integer()];
        $session->preparation->domains = $outer;
        $answers = $cache->run($session, 'generated preparation', [], $types);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $answers[0]);
        self::assertSame(1644, $answers[0]->getCode());
        self::assertSame($outer, $session->preparation->domains);
        $session->close();
        $closed = $cache->run($session, 'generated preparation', [], $types);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $closed[0]);
        self::assertSame(2006, $closed[0]->getCode());
    }

}
