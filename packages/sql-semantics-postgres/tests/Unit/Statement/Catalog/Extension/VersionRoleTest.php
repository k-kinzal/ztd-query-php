<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\VersionRole::class)]
#[Small]
final class VersionRoleTest extends TestCase
{
    public function testSourceIsSpelledFrom(): void
    {
        self::assertSame('FROM', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\VersionRole::Source->value);
    }
}
