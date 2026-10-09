<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

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
        yield 'database' => ['CREATE DATABASE baseline_extra'];
        yield 'account' => ['CREATE USER baseline_extra'];
        yield 'server' => ["CREATE SERVER baseline_extra FOREIGN DATA WRAPPER mysql OPTIONS (USER 'text')"];
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
        $server->stop();
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
