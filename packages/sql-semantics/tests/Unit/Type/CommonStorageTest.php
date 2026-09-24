<?php

declare(strict_types=1);

namespace Tests\Unit\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Type\CommonStorage;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CommonStorage::class)]
final class CommonStorageTest extends TestCase
{
    public function testResolveReturnsTheSharedAlternativeUnchanged(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        self::assertSame($integer, CommonStorage::resolve(Dialect::PostgreSql, [$integer, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')]));
    }

    public function testResolveWithoutAlternativesFallsBackToTextInPostgreSqlAndUnknownElsewhere(): void
    {
        self::assertSame('text', CommonStorage::resolve(Dialect::PostgreSql, [])->name);
        self::assertSame('unknown', CommonStorage::resolve(Dialect::MySql, [])->name);
        self::assertSame('unknown', CommonStorage::resolve(Dialect::Sqlite, [])->name);
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('providerMixedAlternatives')]
    public function testResolveSelectsTheWidestFamilyOfMixedAlternatives(Dialect $dialect, array $names, string $expected): void
    {
        $types = array_map(static fn (string $name): TypeDescriptor => TypeDescriptor::builtin($dialect, $name), $names);
        $resolved = CommonStorage::resolve($dialect, $types);
        self::assertSame($expected, $resolved->name);
        self::assertSame($dialect, $resolved->dialect);
    }

    /**
     * @return iterable<string, array{Dialect, list<string>, string}>
     */
    public static function providerMixedAlternatives(): iterable
    {
        yield 'numeric rank' => [Dialect::PostgreSql, ['integer', 'numeric', 'smallint'], 'numeric'];
        yield 'floating rank' => [Dialect::MySql, ['real', 'smallint'], 'real'];
        yield 'character families' => [Dialect::PostgreSql, ['varchar', 'text', 'char'], 'text'];
        yield 'unrelated families' => [Dialect::PostgreSql, ['integer', 'boolean'], 'unknown'];
        yield 'sqlite is dynamic' => [Dialect::Sqlite, ['integer', 'text'], 'dynamic'];
    }
}
