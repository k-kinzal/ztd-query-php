<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior;

#[CoversClass(DropBehavior::class)]
#[Small]
final class DropBehaviorTest extends TestCase
{
    public function testCasesSpellRestrictAndCascade(): void
    {
        self::assertSame(['RESTRICT', 'CASCADE'], array_column(DropBehavior::cases(), 'value'));
    }
}
