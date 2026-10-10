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
final class PasswordExpiryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'expire current connection' => ['ALTER USER CURRENT_USER PASSWORD EXPIRE'];
        foreach (['SELECT 1', 'SHOW WARNINGS', 'SET @a=1', "SET sql_mode=''", 'SET NAMES utf8mb4', 'COMMIT', 'ROLLBACK', 'USE missing', 'SELECT missing FROM missing', 'SELECT RAND(1,2)', 'SELECT @@unknown_variable', 'ALTER USER CURRENT_USER PASSWORD EXPIRE NEVER', "ALTER USER missing IDENTIFIED BY 'new'"] as $sql) {
            yield $sql => ['ALTER USER CURRENT_USER PASSWORD EXPIRE; ' . $sql];
        }
        yield 'reset own credentials' => ["ALTER USER CURRENT_USER PASSWORD EXPIRE; ALTER USER CURRENT_USER IDENTIFIED BY 'root'; SELECT 1"];
        yield 'set own password' => ["ALTER USER CURRENT_USER PASSWORD EXPIRE; SET PASSWORD = 'root'; SELECT 1"];
        yield 'change expired account with another account missing' => ["ALTER USER CURRENT_USER PASSWORD EXPIRE; ALTER USER CURRENT_USER IDENTIFIED BY 'root', missing IDENTIFIED BY 'new'"];
    }

    #[DataProvider('providerStatements')]
    public function testExpirationMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);
        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    public function testComparisonRepairsExpiredCredentialsBeforeAnotherClientConnects(): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare('ALTER USER CURRENT_USER PASSWORD EXPIRE');
        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
        $native = new PDO($target->native, $target->nativeUser, $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $memory = new PDO($target->memory, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $expected = $native->query('SELECT 1');
        $actual = $memory->query('SELECT 1');
        self::assertNotFalse($expected);
        self::assertNotFalse($actual);

        self::assertSame($expected->fetchAll(PDO::FETCH_NUM), $actual->fetchAll(PDO::FETCH_NUM));
    }
}
