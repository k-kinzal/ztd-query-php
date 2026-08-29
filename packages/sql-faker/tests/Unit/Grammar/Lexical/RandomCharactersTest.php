<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Lexical;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\RandomCharacters;

#[CoversClass(RandomCharacters::class)]
final class RandomCharactersTest extends TestCase
{
    public function testStringDrawsAsManyCharactersAsWereAskedFor(): void
    {
        self::assertSame(8, strlen((new RandomCharacters(Factory::create()))->string('abcdef', 8)));
    }

    public function testStringDrawsOnlyFromTheAlphabetItWasGiven(): void
    {
        self::assertSame('aaaa', (new RandomCharacters(Factory::create()))->string('a', 4));
    }

    public function testStringDrawsNothingWhenNoCharactersWereAskedFor(): void
    {
        self::assertSame('', (new RandomCharacters(Factory::create()))->string('abcdef', 0));
    }

    public function testStringIsRepeatableFromOneSeed(): void
    {
        $faker = Factory::create();
        $characters = new RandomCharacters($faker);

        $faker->seed(20_260_825);
        $first = $characters->string('abcdefghij', 16);
        $faker->seed(20_260_825);

        self::assertSame($first, $characters->string('abcdefghij', 16));
    }

    public function testCharacterDrawsOneOfTheAlphabet(): void
    {
        self::assertStringContainsString(
            (new RandomCharacters(Factory::create()))->character('abcdef'),
            'abcdef',
        );
    }
}
