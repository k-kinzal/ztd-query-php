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
final class ExplainConnectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, array{string, int|null}, string}>
     */
    public static function providerConnections(): iterable
    {
        $legacy = str_starts_with((string) getenv('MYSQL_VERSION'), '5.6.');
        foreach (['native', 'memory'] as $kind) {
            foreach (['SELECT 1', 'ALTER USER CURRENT_USER PASSWORD EXPIRE'] as $previous) {
                yield $kind . ' idle after ' . $previous => [$kind, 'QUERY', $legacy ? ['42000', 1064] : ['00000', null], $previous];
                yield $kind . ' closed after ' . $previous => [$kind, 'CONNECTION', $legacy ? ['42000', 1064] : ['HY000', 1094], $previous];
            }
        }
    }

    /**
     * @param array{string, int|null} $error The expected SQLSTATE and server error number
     */
    #[DataProvider('providerConnections')]
    public function testExplainUsesOnlyLiveConnections(string $kind, string $kill, array $error, string $previous): void
    {
        [$target] = Servers::shared();
        self::assertNull($target->compare($previous)->difference);
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $connections = ['native' => [$target->native, $target->nativeUser, $target->nativePassword], 'memory' => [$target->memory, 'root', '']];
        [$dsn, $user, $password] = $connections[$kind];
        $observer = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
        $other = new PDO($dsn, $user, $password);
        $query = $other->query('SELECT CONNECTION_ID()');
        self::assertNotFalse($query);
        $id = $query->fetchColumn();
        self::assertIsInt($id);
        self::assertSame(0, $observer->exec('KILL ' . $kill . ' ' . $id));
        $observer->query('DESC FOR CONNECTION ' . $id);

        self::assertSame($error, array_slice($observer->errorInfo(), 0, 2));
    }
}
