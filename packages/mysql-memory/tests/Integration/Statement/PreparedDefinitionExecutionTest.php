<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use ArrayObject;
use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedCache;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Parse\Preparation\Alternatives;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class PreparedDefinitionExecutionTest extends TestCase
{
    public function testBinaryPreparationRebindsInItsOriginalDatabaseAfterMetadataChanges(): void
    {
        $sent = new ArrayObject();
        $client = new \MySqlMemory\Server\Client(new Instance('8.4.7'), 1, static function (string $bytes) use ($sent): void {
            $sent->append(substr($bytes, 4));
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $session = $client->session();
        $session->query("SET sql_mode=''; CREATE DATABASE d; CREATE DATABASE e; USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES (7); CREATE TABLE e.t(a INT); INSERT INTO e.t VALUES (99)");
        self::assertTrue($client->statements->prepare('SELECT "a" FROM t'));
        $session->query("ALTER TABLE t ADD b INT; USE e; SET sql_mode='ANSI_QUOTES'");
        $start = $sent->count();
        self::assertTrue($client->statements->execute(new \MySqlMemory\Protocol\PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00")));
        $packets = array_slice($sent->getArrayCopy(), $start);
        self::assertContains("\x00\x00\x07\x00\x00\x00", $packets);
        self::assertNotContains("\x00\x00\x63\x00\x00\x00", $packets);
        self::assertSame('e', $session->variables->database);
    }

    public function testNamedExecutionDoesNotPublishParameterChangesWhenRebindingFails(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query("SET sql_mode=''; CREATE DATABASE d; USE d; CREATE TABLE t(a INT); SET @sql='SELECT ?, \"missing\" FROM t'; PREPARE p FROM @sql");
        $bindings = $session->preparation->named['p'][2];
        $types = $bindings->types;
        $cache = $session->preparation->named['p'][3];
        $definition = $cache->definition;
        $session->query("SET @value=42; ALTER TABLE t ADD b INT; SET sql_mode='ANSI_QUOTES'");
        try {
            $session->query('EXECUTE p USING @value');
            self::fail('Rebinding a missing column must fail.');
        } catch (SqlError $error) {
            self::assertSame(1054, $error->getCode());
        }
        self::assertSame($types, $bindings->types);
        self::assertSame($definition, $cache->definition);
        $session->query('ALTER TABLE t ADD missing INT');
        $retry = $session->query('EXECUTE p USING @value');
        self::assertInstanceOf(ResultSet::class, $retry[0]);
        self::assertNotSame($definition, $cache->definition);
        self::assertFalse($cache->changed($session));
    }

    public function testRefreshSelectsAFormerlyQuotedClauseAndOnlyItsActiveDependencies(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query("SET sql_mode=''; CREATE DATABASE d; USE d; CREATE TABLE t(a INT); CREATE TABLE u(a INT); INSERT INTO t VALUES (7); INSERT INTO u VALUES (1),(2)");
        $sql = "SELECT 'left\\' AS changed FROM u -- ' FROM t";
        $operation = $session->analyze($sql, true);
        $input = (new Alternatives())->read($sql, $session);
        $cache = PreparedCache::capture($operation, $session, $input);
        $bindings = ParameterBindings::capture($operation);
        $original = $cache->definition;
        $session->query("ALTER TABLE u ADD b INT; SET sql_mode='NO_BACKSLASH_ESCAPES'");
        $cache->refresh($session, $bindings, []);
        self::assertSame($original, $cache->definition);
        self::assertSame('t', $cache->tables[0][1]);

        $session->query('ALTER TABLE t ADD b INT');
        $cache->refresh($session, $bindings, []);
        $reply = $cache->definition->execute($session, 'label that is not SQL', []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['left\\'], ['left\\']], $reply->rows);
        self::assertSame('u', $cache->tables[0][1]);
        self::assertFalse($cache->changed($session));
        $session->query('ALTER TABLE t ADD c INT');
        self::assertFalse($cache->changed($session));
    }

    public function testRefreshKeepsTheCacheAndBindingsOnSyntaxRefusalAndCanRetry(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query("SET sql_mode=''; CREATE DATABASE d; USE d; CREATE TABLE t(a INT); INSERT INTO t VALUES (7)");
        $sql = "SELECT 'a\\'b' FROM t";
        $operation = $session->analyze($sql, true);
        $cache = PreparedCache::capture($operation, $session, (new Alternatives())->read($sql, $session));
        $bindings = ParameterBindings::capture($operation);
        $original = $cache->definition;
        $tables = $cache->tables;
        $types = $bindings->types;
        $session->query("ALTER TABLE t ADD b INT; SET sql_mode='NO_BACKSLASH_ESCAPES'");
        try {
            $cache->refresh($session, $bindings, []);
            self::fail('The selected syntax refusal must abort replacement.');
        } catch (SqlError $error) {
            self::assertSame(1064, $error->getCode());
        }
        self::assertSame($original, $cache->definition);
        self::assertSame($tables, $cache->tables);
        self::assertSame($types, $bindings->types);
        self::assertTrue($cache->changed($session));
        $session->query("SET sql_mode=''");
        $cache->refresh($session, $bindings, []);
        self::assertNotSame($original, $cache->definition);
        self::assertFalse($cache->changed($session));
        $reply = $cache->definition->execute($session, 'label that is not SQL', []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([["a'b"]], $reply->rows);
    }

    public function testRefreshKeepsThePreviousValueWhenBindingTheAlternativeFails(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->query("SET sql_mode=''; CREATE DATABASE d; USE d; CREATE TABLE t(a INT)");
        $sql = 'SELECT "missing" FROM t';
        $operation = $session->analyze($sql, true);
        $cache = PreparedCache::capture($operation, $session, (new Alternatives())->read($sql, $session));
        $bindings = ParameterBindings::capture($operation);
        $original = $cache->definition;
        $tables = $cache->tables;
        $session->query("ALTER TABLE t ADD b INT; SET sql_mode='ANSI_QUOTES'");
        try {
            $cache->refresh($session, $bindings, []);
            self::fail('Binding a missing column must abort replacement.');
        } catch (SqlError $error) {
            self::assertSame(1054, $error->getCode());
        }
        self::assertSame($original, $cache->definition);
        self::assertSame($tables, $cache->tables);
        self::assertSame('d', $session->variables->database);
        self::assertSame([], $session->preparation->domains);
        $session->query('ALTER TABLE t ADD missing INT');
        $cache->refresh($session, $bindings, []);
        self::assertFalse($cache->changed($session));
    }
}
