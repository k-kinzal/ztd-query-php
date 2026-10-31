<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\StatementAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementAccess::class)]
#[Small]
final class StatementAccessTest extends TestCase
{
    public function testCheckRejectsQueriesWhileTableCreationWaitsForCommit(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3977);
        $session->query('SELECT 1');
    }

    #[DataProvider('providerLockedViewDefinition')]
    public function testLockedViewDefinitionChecksInputBeforeRefusingObjectAccess(string $version, string $lock, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT, KEY idx(id)); LOCK TABLE t ' . $lock);
        $error = $session->run($sql)[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame($code, $error->getCode());
        self::assertSame([$code], array_column($session->diagnostics->conditions, 1));
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerLockedViewDefinition(): iterable
    {
        $cases = [
            'CREATE VIEW v AS SELECT * FROM missing' => 1192,
            'CREATE VIEW missing_db.v AS SELECT 1' => 1192,
            'CREATE DEFINER=absent@localhost VIEW v AS SELECT 1' => 1192,
            'ALTER VIEW absent AS SELECT * FROM missing' => 1192,
            'ALTER DEFINER=absent@localhost VIEW absent AS SELECT 1' => 1192,
            'CREATE VIEW v AS SELECT @x' => 1351,
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['READ', 'WRITE'] as $lock) {
                foreach ($cases as $sql => $code) {
                    yield $version . ':' . $lock . ':' . $sql => [$version, $lock, $sql, $code];
                }
            }
        }
    }
}
