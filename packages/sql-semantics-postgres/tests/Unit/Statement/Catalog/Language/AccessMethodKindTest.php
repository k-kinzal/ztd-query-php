<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\AccessMethodKind::class)]
#[Small]
final class AccessMethodKindTest extends TestCase
{
    public function testIndexIsSpelledIndex(): void
    {
        self::assertSame('INDEX', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\AccessMethodKind::Index->value);
    }
}
