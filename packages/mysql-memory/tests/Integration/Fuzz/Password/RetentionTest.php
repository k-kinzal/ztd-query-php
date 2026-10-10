<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz\Password;

use Fuzz\Target\Observer;
use Fuzz\Target\Password\Retention;
use Fuzz\Target\RandomPasswords;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class RetentionTest extends TestCase
{
    public function testCaptureRequiresANonemptyPrimary(): void
    {
        $server = Server::start();
        $pdo = new PDO($server->dsn(), 'root', '');
        self::assertNull(Retention::capture($pdo));
        self::assertNull(RandomPasswords::capture($pdo, true));
        $pdo->exec("SET PASSWORD='first'");
        $capture = Retention::capture($pdo);
        self::assertNotNull($capture);
        self::assertSame([$capture->primary, null], Retention::read($pdo));
        self::assertFalse($capture->retained($pdo));
    }

    public function testRetainedRequiresThePreviousPrimaryAndDiscardPreservesTheNewOne(): void
    {
        $server = Server::start();
        $pdo = new PDO($server->dsn(), 'root', '');
        $pdo->exec("SET PASSWORD='first'");
        $capture = Retention::capture($pdo);
        self::assertNotNull($capture);
        $pdo->exec("SET PASSWORD='second' RETAIN CURRENT PASSWORD");
        self::assertTrue($capture->retained($pdo));
        self::assertFalse((new Retention('unrelated-hash'))->retained($pdo));
        self::assertFalse((new Retention(''))->retained($pdo));
        self::assertTrue($capture->discarded($pdo));
        self::assertFalse($capture->discarded($pdo));
    }

    public function testComparableDoesNotAcceptAStoredRandomPasswordWithoutRetention(): void
    {
        $server = Server::start();
        $pdo = new PDO($server->dsn(), 'root', '');
        $pdo->exec("SET PASSWORD='first'");
        $capture = RandomPasswords::capture($pdo, true);
        self::assertNotNull($capture);
        $observation = (new Observer())->observe($pdo, 'SET PASSWORD TO RANDOM', false);

        self::assertSame($observation, $capture->comparable($pdo, $observation));
    }

    public function testComparableRejectsRetentionOfAnotherPrimary(): void
    {
        $server = Server::start();
        $pdo = new PDO($server->dsn(), 'root', '');
        $pdo->exec("SET PASSWORD='first'");
        $capture = RandomPasswords::capture($pdo, true);
        self::assertNotNull($capture);
        $pdo->exec("SET PASSWORD='second'");
        $observation = (new Observer())->observe($pdo, 'SET PASSWORD TO RANDOM RETAIN CURRENT PASSWORD', false);

        self::assertSame($observation, $capture->comparable($pdo, $observation));
    }
}
