<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\RandomPasswords;
use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class RandomPasswordsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerStatements(): iterable
    {
        yield 'alter current' => ['ALTER USER CURRENT_USER IDENTIFIED BY RANDOM PASSWORD', '8.4.7', true];
        yield 'alter user function' => ['ALTER USER USER ( ) IDENTIFIED BY RANDOM PASSWORD', '8.4.7', true];
        yield 'set implicit' => ['SET PASSWORD TO RANDOM', '8.4.7', true];
        yield 'set explicit' => ['SET PASSWORD FOR CURRENT_USER TO RANDOM', '8.4.7', true];
        yield 'whitespace and terminator' => [" set\n password for current_user ( ) to random ; ", '8.0.44', true];
        yield 'legacy' => ['SET PASSWORD TO RANDOM', '5.7.44', false];
        yield 'retention needs another contract' => ['ALTER USER USER() IDENTIFIED BY RANDOM PASSWORD RETAIN CURRENT PASSWORD', '8.4.7', false];
        yield 'another account' => ["SET PASSWORD FOR 'u'@'%' TO RANDOM", '8.4.7', false];
        yield 'multiple statements' => ['SET PASSWORD TO RANDOM; SELECT 1', '8.4.7', false];
        yield 'preceding settings' => ['SET generated_random_password_length=5; SET PASSWORD TO RANDOM', '8.4.7', false];
        yield 'explicit replacement' => ["SET PASSWORD TO RANDOM REPLACE 'root'", '8.4.7', false];
    }

    #[DataProvider('providerStatements')]
    public function testHandlesOnlyIsolatedPrimaryChanges(string $sql, string $version, bool $handled): void
    {
        self::assertSame($handled, RandomPasswords::handles($sql, $version));
    }

    /**
     * @return iterable<string, array{array<mixed>, int, bool}>
     */
    public static function providerPasswords(): iterable
    {
        yield 'valid minimum' => [['root', '%', 'abcDE', 1], 5, true];
        yield 'valid maximum' => [['root', '%', str_repeat('x', 255), 1], 255, true];
        yield 'wrong account' => [['other', '%', 'abcDE', 1], 5, false];
        yield 'wrong host' => [['root', 'localhost', 'abcDE', 1], 5, false];
        yield 'wrong factor' => [['root', '%', 'abcDE', 2], 5, false];
        yield 'wrong length' => [['root', '%', 'abcD', 1], 5, false];
        yield 'nontext' => [['root', '%', 12345, 1], 5, false];
        yield 'null' => [['root', '%', null, 1], 5, false];
        yield 'control character' => [['root', '%', "abc\nD", 1], 5, false];
        yield 'setting below minimum' => [['root', '%', 'abcD', 1], 4, false];
        yield 'setting above maximum' => [['root', '%', str_repeat('x', 256), 1], 256, false];
        yield 'missing factor' => [['root', '%', 'abcDE'], 5, false];
    }

    /**
     * @param array<mixed> $row
     */
    #[DataProvider('providerPasswords')]
    public function testPasswordRejectsMalformedOrUnrelatedRows(array $row, int $length, bool $valid): void
    {
        $observation = ['results' => [['columns' => [['user', ''], ['host', ''], ['generated password', ''], ['auth_factor', '']], 'rows' => [$row]]]];

        self::assertSame($valid, (new RandomPasswords($length, 'root@%'))->password($observation) !== null);
    }

    public function testPasswordRejectsErrorsAndUnrelatedResultShapes(): void
    {
        $contract = new RandomPasswords(5, 'root@%');

        self::assertNull($contract->password(['error' => [3891], 'results' => []]));
        self::assertNull($contract->password(['results' => [['affected' => 0]]]));
        self::assertNull($contract->password(['results' => [['columns' => [['user', ''], ['host', ''], ['generated password', 'table'], ['auth_factor', '']], 'rows' => [['root', '%', 'abcDE', 1]]]]]));
    }

    /**
     * @return iterable<string, array{string, array<string>}>
     */
    public static function providerChanges(): iterable
    {
        if (str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'legacy explicit password' => ["SET PASSWORD = PASSWORD('root')", []];

            return;
        }
        $contracts = [RandomPasswords::CONTRACT];
        yield 'alter current user' => ['ALTER USER CURRENT_USER IDENTIFIED BY RANDOM PASSWORD', $contracts];
        yield 'alter user function' => ['ALTER USER USER ( ) IDENTIFIED BY RANDOM PASSWORD', $contracts];
        yield 'set implicit' => ['SET PASSWORD TO RANDOM', $contracts];
        yield 'set explicit' => ['SET PASSWORD FOR CURRENT_USER TO RANDOM', $contracts];
    }

    /**
     * @param array<string> $contracts
     */
    #[DataProvider('providerChanges')]
    public function testComparableRequiresTheReturnedPasswordToBeStored(string $sql, array $contracts): void
    {
        [$target] = Servers::shared();
        $result = $target->compare($sql);

        self::assertFalse($result->volatile);
        self::assertNull($result->difference, (string) $result->difference);
        self::assertSame($contracts, $result->contracts);
    }
    public function testComparableRetainsAnUnstoredPassword(): void
    {
        $server = \MySqlMemory\Server\Server::start();
        $pdo = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("SET PASSWORD = 'original'");
        $original = ['results' => [['columns' => [['user', ''], ['host', ''], ['generated password', ''], ['auth_factor', '']], 'rows' => [['root', '%', 'not-stored-password!', 1]]]], 'warnings' => [], 'tables' => []];

        self::assertSame($original, (new RandomPasswords(20, 'root@%'))->comparable($pdo, $original));
    }

}
