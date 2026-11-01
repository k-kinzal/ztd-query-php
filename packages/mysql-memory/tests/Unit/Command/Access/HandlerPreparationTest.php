<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\HandlerPreparation;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(HandlerPreparation::class)]
#[Medium]
final class HandlerPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerConditions(): iterable
    {
        yield '5.6 database' => ['5.6.51', 'DO 0', 'HANDLER h READ NEXT WHERE f() LIMIT bad', 1305];
        yield '5.7 database' => ['5.7.44', 'DO 0', 'HANDLER h READ NEXT WHERE f() LIMIT bad', 1046];
        yield 'modern database' => ['8.4.7', 'DO 0', 'HANDLER h READ NEXT WHERE f() LIMIT bad', 1046];
        yield 'qualified before lookup' => ['8.4.7', 'DO 0', 'HANDLER h READ NEXT WHERE d.f()', 1235];
        yield 'limit before restriction' => ['8.4.7', 'CREATE DATABASE d; USE d', 'HANDLER h READ NEXT WHERE f() LIMIT bad', 1327];
        yield 'stored before lookup' => ['8.4.7', 'CREATE DATABASE d; USE d', 'HANDLER h READ NEXT WHERE ABS(f())', 1235];
        yield 'native after lookup' => ['8.4.7', 'CREATE DATABASE d; USE d', 'HANDLER h READ NEXT WHERE ABS(1)', 1109];
        yield 'key before lookup' => ['8.4.7', 'CREATE DATABASE d; USE d', 'HANDLER h READ i = (f())', 1235];
    }

    #[DataProvider('providerConditions')]
    public function testDatabaseOrdersCallsBeforeLimitNames(string $release, string $fixture, string $sql, int $code): void
    {
        $session = (new Instance($release))->connect();
        $session->query($fixture);
        $session->run($sql);

        self::assertSame($code, $session->diagnostics->conditions[0][1]);
    }

    public function testCheckKeepsOrdinaryPredicatesAfterHandlerLookup(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $session->run('HANDLER h READ NEXT WHERE absent_column');

        self::assertSame(1109, $session->diagnostics->conditions[0][1]);
    }

}
