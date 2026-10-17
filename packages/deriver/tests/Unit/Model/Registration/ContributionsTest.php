<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Registration;

use Deriver\Model\Registration\Contributions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ContributionsTest extends TestCase
{
    public function testDefaultsContributeNothing(): void
    {
        $contributions = new Contributions();
        self::assertSame([], $contributions->models);
        self::assertSame([], $contributions->providers);
        self::assertSame([], $contributions->domains);
        self::assertSame([], $contributions->intrinsics);
        self::assertSame([], $contributions->stateSlots);
    }
}
