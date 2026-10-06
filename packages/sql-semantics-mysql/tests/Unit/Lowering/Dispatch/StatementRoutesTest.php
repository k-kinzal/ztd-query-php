<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dispatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\Family;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\StatementRoutes;

#[CoversClass(StatementRoutes::class)]
#[Medium]
final class StatementRoutesTest extends TestCase
{
    public function testFamilyNamesTheOwnerOfAStatementRule(): void
    {
        $routes = new StatementRoutes();

        self::assertSame(Family::Query, $routes->family('statement: select'));
        self::assertSame(Family::Query, $routes->family('simple_statement: select_stmt'));
        self::assertSame(Family::Definition, $routes->family('statement: create'));
        self::assertSame(Family::Definition, $routes->family('simple_statement: create'));
        self::assertSame(Family::TableDefinition, $routes->family('simple_statement: create_table_stmt'));
        self::assertSame(Family::Replication, $routes->family('statement: slave'));
        self::assertSame(Family::Account, $routes->family('simple_statement: set_role_stmt'));
        self::assertSame(Family::Utility, $routes->family('statement: set'));
        self::assertNull($routes->family('create: CREATE opt_table_options TABLE_SYM opt_if_not_exists table_ident create2'));
        self::assertNull($routes->family('statement:'));
    }

    public function testFamilyRoutesEveryStatementAlternativeOfEveryRelease(): void
    {
        $directory = dirname(__DIR__, 4) . '/resources/productions/';
        $paths = glob($directory . 'mysql-*.php');
        self::assertIsArray($paths);
        $signatures = array_merge(...array_map(static fn (string $path): array => Productions::load($path)->all(), $paths));
        $statements = array_values(array_filter($signatures, static fn (string $signature): bool => str_starts_with($signature, 'statement: ') || str_starts_with($signature, 'simple_statement: ')));
        $unrouted = array_values(array_filter($statements, static fn (string $signature): bool => (new StatementRoutes())->family($signature) === null));

        self::assertCount(9, $paths);
        self::assertGreaterThan(180, count(array_unique($statements)));
        self::assertSame([], $unrouted);
    }
}
