<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Baseline;
use Fuzz\Target\Differential;
use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class BaselineTest extends TestCase
{
    public function testCleanDropsImageSpecificRootProxyGrants(): void
    {
        [$target, , $server] = (new Servers())->start(true, true);
        $native = new PDO($target->native, $target->nativeUser, $target->nativePassword);
        $before = (new Servers())->rows($native, 'SHOW GRANTS');
        $native->exec('GRANT PROXY ON CURRENT_USER TO CURRENT_USER WITH GRANT OPTION');
        self::assertCount(count($before) + 1, (new Servers())->rows($native, 'SHOW GRANTS'));

        (new Servers())->clean($native, $target->version);

        self::assertSame($before, (new Servers())->rows($native, 'SHOW GRANTS'));
        $server->stop();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerObjects(): iterable
    {
        yield 'self proxy' => ['GRANT PROXY ON CURRENT_USER TO CURRENT_USER; SHOW GRANTS'];
        yield 'proxy grant option' => ['CREATE USER baseline_extra; GRANT PROXY ON root TO baseline_extra WITH GRANT OPTION; SHOW GRANTS FOR baseline_extra'];
        yield 'proxy option removal' => ['CREATE USER baseline_extra; GRANT PROXY ON root TO baseline_extra WITH GRANT OPTION; GRANT PROXY ON root TO baseline_extra; SHOW GRANTS FOR baseline_extra'];
        yield 'proxy survives revoke all' => ['CREATE USER baseline_extra; GRANT PROXY ON root TO baseline_extra WITH GRANT OPTION; REVOKE ALL, GRANT OPTION FROM baseline_extra; SHOW GRANTS FOR baseline_extra'];
        yield 'proxy revoked' => ['CREATE USER baseline_extra; GRANT PROXY ON root TO baseline_extra WITH GRANT OPTION; REVOKE PROXY ON root FROM baseline_extra; SHOW GRANTS FOR baseline_extra'];
        yield 'database' => ['CREATE DATABASE baseline_extra'];
        yield 'account' => ['CREATE USER baseline_extra'];
        yield 'server' => ["CREATE SERVER baseline_extra FOREIGN DATA WRAPPER mysql OPTIONS (USER 'text')"];
        yield 'named replication channel' => ["CREATE DATABASE baseline_extra; CHANGE REPLICATION SOURCE TO SOURCE_PORT=3306 FOR CHANNEL 'baseline_extra'"];
    }

    #[DataProvider('providerObjects')]
    public function testRestoreIsolatesCatalogObjects(string $sql): void
    {
        [$target, , $server] = (new Servers())->start(true, true);
        $first = $target->compare($sql);
        $second = $target->compare($sql);

        self::assertFalse($first->volatile, $sql);
        self::assertNull($first->difference, (string) $first->difference);
        self::assertFalse($second->volatile, $sql);
        self::assertNull($second->difference, (string) $second->difference);
        self::assertNotNull($target->baseline);
        self::assertNotEmpty($target->baseline->cleanup);
        $server->stop();
    }

    public function testRestoreIsolatesGlobalVariables(): void
    {
        [$target, , $server] = (new Servers())->start(true, true);
        $changed = $target->compare('SET GLOBAL max_connections=250');
        $read = $target->compare('SELECT @@global.max_connections');

        self::assertFalse($changed->volatile);
        self::assertNull($changed->difference, (string) $changed->difference);
        self::assertFalse($read->volatile);
        self::assertNull($read->difference, (string) $read->difference);
        self::assertNotNull($target->baseline);
        $original = $target->baseline->globals['max_connections'];
        $native = new PDO($target->native, $target->nativeUser, $target->nativePassword);
        $memory = new PDO($target->memory, 'root', '');
        self::assertSame([[$original]], (new Servers())->rows($native, 'SELECT @@global.max_connections'));
        self::assertSame([[$original]], (new Servers())->rows($memory, 'SELECT @@global.max_connections'));
        $server->stop();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerNullableGlobals(): iterable
    {
        yield 'monitor default' => ['innodb_monitor_reset', 'DEFAULT', "'all'"];
        yield 'explicit null' => ['character_set_results', 'NULL', "'latin1'"];
    }

    #[DataProvider('providerNullableGlobals')]
    public function testRestorePreservesNullOnBothServersAfterComparison(string $name, string $initial, string $changed): void
    {
        [$base, , $server] = (new Servers())->start();
        $native = new PDO($base->native, $base->nativeUser, $base->nativePassword);
        $memory = new PDO($base->memory, 'root', '');
        $original = new Baseline($native, $base->version);
        $native->exec('SET GLOBAL ' . $name . '=' . $initial);
        $memory->exec('SET GLOBAL ' . $name . '=' . $initial);
        $baseline = new Baseline($native, $base->version);
        $target = new Differential($base->native, $base->nativeUser, $base->nativePassword, $base->memory, version: $base->version, guardUser: $base->guardUser, baseline: $baseline);
        $result = $target->compare('SET GLOBAL ' . $name . '=' . $changed . '; SELECT @@GLOBAL.' . $name);
        $nativeValue = (new Servers())->globals($native, $base->version)[$name];
        $memoryValue = (new Servers())->globals($memory, $base->version)[$name];
        $original->restore($native, true);
        $original->restore($memory, false);
        $server->stop();

        self::assertNull($baseline->globals[$name]);
        self::assertFalse($result->volatile, (string) $result->referenceDifference);
        self::assertNull($result->difference, (string) $result->difference);
        self::assertNull($nativeValue);
        self::assertNull($memoryValue);
    }

    public function testRestoreRemovesGrantsFromTheTestAccountRatherThanTheGuard(): void
    {
        [$target, , $server] = (new Servers())->start(true, true);
        $before = $target->run($target->native, $target->nativeUser, $target->nativePassword, 'SHOW GRANTS');
        $target->compare('GRANT SELECT ON fz.* TO CURRENT_USER');
        self::assertNotNull($target->baseline);
        $target->baseline->restore($target->guard(), true);
        $after = $target->run($target->native, $target->nativeUser, $target->nativePassword, 'SHOW GRANTS');

        self::assertSame($before, $after);
        $server->stop();
    }
}
