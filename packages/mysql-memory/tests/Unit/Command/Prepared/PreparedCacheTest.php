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
    public function testCaptureRecordsOnlyResolvedRelationDeclarations(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a INT); CREATE TABLE u(b INT)');
        $operation = $session->analyze('SELECT a FROM t', true);

        $cache = PreparedCache::capture($operation, $session);

        self::assertSame($operation->statement, $cache->definition->statement);
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
        $cache = PreparedCache::capture($operation, $session);
        $types = ParameterBindings::capture($operation);
        $cache->refresh($session, 'unrelated invalid source', $types, []);

        self::assertSame($operation->statement, $cache->definition->statement);

        $session->query("ALTER TABLE t ADD b INT DEFAULT 9; USE b; SET sql_mode='ANSI_QUOTES,PIPES_AS_CONCAT'");
        $cache->refresh($session, $sql, $types, []);
        $result = $cache->definition->execute($session, 'unrelated invalid source', []);

        self::assertNotSame($operation->statement, $cache->definition->statement);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', '9', '7', '12']], $result->rows);
        self::assertSame('b', $session->variables->database);
        self::assertFalse($cache->changed($session));
    }
}
