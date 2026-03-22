<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\RandomSource;

#[CoversNothing]
final class RandomSourceTest extends TestCase
{
    public function testAnonymousImplementationCanSatisfyTheRandomSourceContract(): void
    {
        $random = new class () implements RandomSource {
            public function seed(int $seed): void
            {
            }

            public function numberBetween(int $min, int $max): int
            {
                return $min;
            }

            public function stringElement(array $elements): string
            {
                return $elements[0];
            }
        };

        self::assertSame(1, $random->numberBetween(1, 9));
        self::assertSame('x', $random->stringElement(['x', 'y']));
    }
}
