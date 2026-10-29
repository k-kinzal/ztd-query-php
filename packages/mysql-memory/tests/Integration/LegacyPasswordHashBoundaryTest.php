<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class LegacyPasswordHashBoundaryTest extends TestCase
{
    #[DataProvider('providerStatements')]
    public function testExecutionRefusesTheRetainedCredentialWithoutReadingSql(string $sql): void
    {
        $session = (new Instance('5.7.44', databases: ['d']))->connect(database: 'd');
        $statement = $session->analyze($sql);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1064);
        (new Execution($session))->statement($statement);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $sql = "ALTER USER u IDENTIFIED BY PASSWORD 'hash'";
        yield 'direct' => [$sql];
        yield 'stored' => ['CREATE PROCEDURE d.p() ' . $sql];
        yield 'unreachable' => ['CREATE PROCEDURE d.p() BEGIN IF FALSE THEN ' . $sql . '; END IF; END'];
    }
}
