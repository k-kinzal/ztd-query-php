<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Value\StringGenerator as Subject;
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

    public function testGenerateBinaryRespectsDeclaredLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateBinary($faker, new ColumnDefinition('code', 'BINARY', length: 12));
        self::assertNotSame('', $value);
        self::assertLessThanOrEqual(12, strlen($value));
    }

    public function testGenerateVarbinaryRespectsDeclaredLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $value = (new Subject())->generateVarbinary($faker, new ColumnDefinition('code', 'VARBINARY', length: 12));
        self::assertNotSame('', $value);
        self::assertLessThanOrEqual(12, strlen($value));
    }

    public function testGenerateEnumChoosesADeclaredMember(): void
    {
        $generator = new Subject();
        $faker = Factory::create();
        self::assertContains($generator->generateEnum($faker, new ColumnDefinition('status', 'ENUM', enumValues: ['open', 'closed'])), ['open', 'closed']);
        self::assertNull($generator->generateEnum($faker, new ColumnDefinition('status', 'ENUM')));
    }

    public function testGenerateSetChoosesOnlyDeclaredMembers(): void
    {
        $generator = new Subject();
        $faker = Factory::create();
        $value = $generator->generateSet($faker, new ColumnDefinition('flags', 'SET', enumValues: ['a', 'b', 'c']));
        self::assertNotNull($value);
        self::assertSame([], array_diff(explode(',', $value), ['a', 'b', 'c']));
        self::assertNull($generator->generateSet($faker, new ColumnDefinition('flags', 'SET')));
    }
}
