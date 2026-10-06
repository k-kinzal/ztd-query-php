<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dispatch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\DefinitionRoutes;
use SqlSemantics\Platform\MySql\Lowering\Dispatch\Family;

#[CoversClass(DefinitionRoutes::class)]
#[Medium]
final class DefinitionRoutesTest extends TestCase
{
    public function testFamilyRoutesByTheWholeLeadingSymbols(): void
    {
        $routes = new DefinitionRoutes();

        self::assertSame(Family::TableDefinition, $routes->family('create: CREATE opt_table_options TABLE_SYM opt_if_not_exists table_ident create2'));
        self::assertSame(Family::Definition, $routes->family('create: CREATE view_or_trigger_or_sp_or_event'));
        self::assertSame(Family::Server, $routes->family('create: CREATE TABLESPACE_SYM tablespace_info'));
        self::assertSame(Family::Server, $routes->family('create: CREATE TABLESPACE tablespace_info'));
        self::assertSame(Family::Account, $routes->family('alter: alter_user_command user_func IDENTIFIED_SYM BY TEXT_STRING'));
        self::assertSame(Family::TableChange, $routes->family('drop: DROP opt_temporary table_or_tables if_exists table_list opt_restrict'));
        self::assertSame(Family::Routine, $routes->family('drop: DROP TRIGGER_SYM if_exists sp_name'));
        self::assertNull($routes->family('drop: DROP TRIGGERS_SYM if_exists sp_name'));
        self::assertNull($routes->family('statement: create'));
    }

    public function testFamilyRoutesEveryCreateAlterAndDropAlternativeOfEveryRelease(): void
    {
        $directory = dirname(__DIR__, 4) . '/resources/productions/';
        $paths = glob($directory . 'mysql-*.php');
        self::assertIsArray($paths);
        $signatures = array_merge(...array_map(static fn (string $path): array => Productions::load($path)->all(), $paths));
        $definitions = array_values(array_filter($signatures, static fn (string $signature): bool => str_starts_with($signature, 'create: ') || str_starts_with($signature, 'alter: ') || str_starts_with($signature, 'drop: ')));
        $unrouted = array_values(array_filter($definitions, static fn (string $signature): bool => (new DefinitionRoutes())->family($signature) === null));

        self::assertGreaterThan(50, count(array_unique($definitions)));
        self::assertSame([], $unrouted);
    }
}
