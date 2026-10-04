<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Overriding::class)]
#[Small]
final class OverridingTest extends TestCase
{
    public function testValueIsTheKeywordBeforeValue(): void
    {
        self::assertSame('SYSTEM', \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Overriding::System->value);
    }
}
