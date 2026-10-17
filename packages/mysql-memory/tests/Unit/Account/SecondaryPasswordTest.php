<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Account\SecondaryPassword;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecondaryPassword::class)]
#[Small]
final class SecondaryPasswordTest extends TestCase
{
    public function testChangeRetainsThePreviousPrimaryAndReplacesAnOlderSecondary(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER u IDENTIFIED BY 'first'");
        $account = $session->instance->accounts->find(new Identity('u', '%'));
        self::assertNotNull($account);
        $first = $account->hash;
        $session->query("ALTER USER u IDENTIFIED BY 'second' RETAIN CURRENT PASSWORD");
        self::assertSame([$first, 'first'], $account->secondary);
        $second = $account->hash;
        $session->query("SET PASSWORD FOR u = 'third' RETAIN CURRENT PASSWORD");
        self::assertSame([$second, 'second'], $account->secondary);
    }

    public function testChangeKeepsTheSecondaryUntilExplicitlyDiscarded(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER u IDENTIFIED BY 'first'; ALTER USER u IDENTIFIED BY 'second' RETAIN CURRENT PASSWORD");
        $account = $session->instance->accounts->find(new Identity('u', '%'));
        self::assertNotNull($account);
        $secondary = $account->secondary;
        $session->query("SET PASSWORD FOR u = 'third'");
        self::assertSame($secondary, $account->secondary);
        $session->query('ALTER USER u DISCARD OLD PASSWORD');
        self::assertNull($account->secondary);
        self::assertSame('third', $account->password);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerRejected(): iterable
    {
        yield 'empty old' => ['', "ALTER USER u IDENTIFIED BY 'next' RETAIN CURRENT PASSWORD", 3878];
        yield 'empty new' => ['first', "ALTER USER u IDENTIFIED BY '' RETAIN CURRENT PASSWORD", 3895];
        yield 'plugin change' => ['first', "ALTER USER u IDENTIFIED WITH sha256_password BY 'next' RETAIN CURRENT PASSWORD", 3894];
        yield 'set empty new' => ['first', "SET PASSWORD FOR u = '' RETAIN CURRENT PASSWORD", 3895];
    }

    #[DataProvider('providerRejected')]
    public function testChangeRejectsInvalidRetentionWithoutChangingTheAccount(string $password, string $sql, int $code): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE USER u IDENTIFIED BY '" . $password . "'");
        $before = $session->instance->accounts->find(new Identity('u', '%'))?->hash;
        $error = $session->run($sql)[0];
        $account = $session->instance->accounts->find(new Identity('u', '%'));

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame($code, $error->getCode());
        self::assertNotNull($account);
        self::assertSame([$before, $password, null], [$account->hash, $account->password, $account->secondary]);
    }
}
