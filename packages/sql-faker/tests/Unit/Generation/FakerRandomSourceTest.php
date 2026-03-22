<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\FakerRandomSource;

#[CoversClass(FakerRandomSource::class)]
final class FakerRandomSourceTest extends TestCase
{
    public function testDelegatesSeedAndNumberGenerationToFaker(): void
    {
        $faker = Factory::create();
        $random = new FakerRandomSource($faker);

        $random->seed(17);
        $first = $random->numberBetween(1, 1000);
        $random->seed(17);
        $second = $random->numberBetween(1, 1000);

        self::assertSame($first, $second);
    }

    public function testReturnsTheOnlyStringElementWithoutCallingRandomSelection(): void
    {
        $faker = new class () extends \Faker\Generator {
            public int $randomElementCalls = 0;

            public function __construct()
            {
                parent::__construct();
            }

            /**
             * @param mixed $array
             */
            public function randomElement($array = ['a', 'b']): mixed
            {
                $this->randomElementCalls++;

                return 'unexpected';
            }
        };
        $random = new FakerRandomSource($faker);

        self::assertSame('only', $random->stringElement(['only']));
        self::assertSame(0, $faker->randomElementCalls);
    }

    public function testSelectsAStringElementFromTheProvidedList(): void
    {
        $faker = Factory::create();
        $random = new FakerRandomSource($faker);

        $random->seed(7);
        self::assertContains($random->stringElement(['a', 'b', 'c']), ['a', 'b', 'c']);
    }
}
