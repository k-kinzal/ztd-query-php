<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Value\StringGenerator as Subject;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
final class StringGeneratorTest extends TestCase
{
    public function testGenerateCharRespectsDeclaredLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateChar($faker, new ColumnDefinition('code', 'CHAR', length: 12));
        self::assertNotSame('', $value);
        self::assertLessThanOrEqual(12, strlen($value));
    }

    public function testGenerateVarcharRespectsDeclaredLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateVarchar($faker, new ColumnDefinition('code', 'VARCHAR', length: 12));
        self::assertNotSame('', $value);
        self::assertLessThanOrEqual(12, strlen($value));
    }
}
