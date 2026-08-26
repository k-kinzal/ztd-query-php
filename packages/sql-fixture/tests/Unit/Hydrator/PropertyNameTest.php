<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\PropertyName;

#[CoversClass(PropertyName::class)]
final class PropertyNameTest extends TestCase
{
    #[DataProvider('providerName')]
    public function testToSnakeCaseWritesANameAsAColumnWouldSpellIt(string $property, string $column): void
    {
        self::assertSame($column, PropertyName::toSnakeCase($property));
    }

    #[DataProvider('providerName')]
    public function testToCamelCaseWritesANameAsAPropertyWouldSpellIt(string $property, string $column): void
    {
        self::assertSame($property, PropertyName::toCamelCase($column));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerName(): iterable
    {
        yield 'one word' => ['id', 'id'];
        yield 'two words' => ['userId', 'user_id'];
        yield 'three words' => ['createdAtUtc', 'created_at_utc'];
    }

    public function testToSnakeCaseLeavesALeadingCapitalWhereItIs(): void
    {
        self::assertSame('id', PropertyName::toSnakeCase('Id'));
    }

    public function testToCamelCaseLowercasesTheFirstWord(): void
    {
        self::assertSame('userId', PropertyName::toCamelCase('User_id'));
    }
}
