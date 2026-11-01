<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin\Foreign;

use MySqlMemory\Command\Admin\Foreign\ServerLocks;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerLocks::class)]
#[Small]
final class ServerLocksTest extends TestCase
{
    #[DataProvider('providerTableLocks')]
    public function testCheckRequiresAWritableServersTable(string $version, string $lock, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query("CREATE TABLE t(id INT); CREATE SERVER existing FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'localhost'); " . $lock);
        $result = $session->run($sql)[0];

        self::assertSame($code, $result instanceof SqlError ? $result->getCode() : 0);
        self::assertSame($code === 0 ? [] : [$code], array_column($session->diagnostics->conditions, 1));
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerTableLocks(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['LOCK TABLE t WRITE' => 1100, 'LOCK TABLE mysql.servers READ' => 1099, 'LOCK TABLE mysql.servers WRITE' => 0] as $lock => $code) {
                foreach (["CREATE SERVER fresh FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'localhost')" => 0, "CREATE SERVER existing FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'localhost')" => 1476, "ALTER SERVER existing OPTIONS (HOST 'elsewhere')" => 0, "ALTER SERVER absent OPTIONS (HOST 'elsewhere')" => 1477, 'DROP SERVER existing' => 0, 'DROP SERVER absent' => 1477, 'DROP SERVER IF EXISTS absent' => 0] as $sql => $unlocked) {
                    $cached = $version === '5.6.51' && ($unlocked === 1476 || (str_starts_with($sql, 'ALTER') && $unlocked === 1477));
                    yield $version . ':' . $lock . ':' . $sql => [$version, $lock, $sql, $cached || $code === 0 ? $unlocked : $code];
                }
            }
        }
    }
}
