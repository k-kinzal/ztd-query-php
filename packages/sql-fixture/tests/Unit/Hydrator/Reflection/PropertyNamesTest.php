<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator\Reflection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\Reflection\PropertyNames as Subject;

#[CoversClass(Subject::class)]
final class PropertyNamesTest extends TestCase
{
    public function testToSnakeCaseSeparatesCapitalLetters(): void
    {
        self::assertSame('first_name', (new Subject())->toSnakeCase('firstName'));
        self::assertSame('name', (new Subject())->toSnakeCase('name'));
    }

    public function testToCamelCaseJoinsUnderscoreWords(): void
    {
        self::assertSame('firstName', (new Subject())->toCamelCase('first_name'));
        self::assertSame('name', (new Subject())->toCamelCase('name'));
    }
}
