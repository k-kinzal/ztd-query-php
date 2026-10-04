<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ProfileSection;

#[CoversClass(ProfileSection::class)]
#[Small]
final class ProfileSectionTest extends TestCase
{
    public function testPositionsCoverTheLayout(): void
    {
        self::assertSame(range(2, 15), ProfileSection::All->positions());
        self::assertSame([], ProfileSection::Memory->positions());
        self::assertSame([12], ProfileSection::Swaps->positions());
    }
}
