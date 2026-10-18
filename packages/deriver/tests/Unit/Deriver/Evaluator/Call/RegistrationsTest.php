<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Call\Registrations;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RegistrationsTest extends TestCase
{
    public function testDefaultsContributeNothing(): void
    {
        $contributions = new Registrations();
        self::assertSame([], $contributions->evaluators);
        self::assertSame([], $contributions->providers);
        self::assertSame([], $contributions->domains);
        self::assertSame([], $contributions->intrinsics);
        self::assertSame([], $contributions->stateSlots);
    }
}
