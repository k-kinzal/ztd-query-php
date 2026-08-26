<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\ViewReflector;

#[CoversNothing]
final class ViewReflectorTest extends TestCase
{
    public function testReflectViewsAnswersTheViewsTheDatabaseHas(): void
    {
        $reflector = new class () implements ViewReflector {
            public function reflectViews(): array
            {
                return [];
            }
        };

        self::assertSame([], $reflector->reflectViews());
    }
}
