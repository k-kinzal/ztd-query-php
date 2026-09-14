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

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSeeds')]
    public function testGenerateSetSupportsASingleMember(int $seed): void
    {
        $generator = new Subject();
        $faker = Factory::create();
        $column = new ColumnDefinition('status', 'SET', enumValues: ['active']);
        $faker->seed($seed);
        self::assertSame('active', $generator->generateSet($faker, $column));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSeeds')]
    public function testSingleByteBinaryColumnsProduceExactlyOneByte(int $seed): void
    {
        $generator = new Subject();
        $faker = Factory::create();
        $faker->seed($seed);
        self::assertSame(1, strlen($generator->generateBinary($faker, new ColumnDefinition('value', 'BINARY', length: 1))));
        self::assertSame(1, strlen($generator->generateVarbinary($faker, new ColumnDefinition('value', 'VARBINARY', length: 1))));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerShortVarchars')]
    public function testGenerateVarcharSupportsColumnsShorterThanAFakerWord(int $length): void
    {
        $value = (new Subject())->generateVarchar(Factory::create(), new ColumnDefinition('code', 'VARCHAR', length: $length));
        self::assertNotSame('', $value);
        self::assertLessThanOrEqual($length, strlen($value));
    }

    public function testGenerateVarcharSupportsAZeroLengthColumn(): void
    {
        self::assertSame('', (new Subject())->generateVarchar(Factory::create(), new ColumnDefinition('code', 'VARCHAR', length: 0)));
    }

    /**
     * @return list<array{int}>
     */
    public static function providerShortVarchars(): array
    {
        return [[1], [2], [3], [4]];
    }
    /**
     * @return list<array{int}>
     */
    public static function providerSeeds(): array
    {
        return array_map(static fn (int $seed): array => [$seed], range(1, 16));
    }
}
