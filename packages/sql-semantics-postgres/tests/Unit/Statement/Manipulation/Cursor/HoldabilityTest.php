<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Holdability::class)]
#[Small]
final class HoldabilityTest extends TestCase
{
    public function testValueIsTheFirstKeyword(): void
    {
        self::assertSame('WITHOUT', \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Holdability::Without->value);
    }
}
