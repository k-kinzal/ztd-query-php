<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTextKind::class)]
#[Small]
final class CopyTextKindTest extends TestCase
{
    public function testValueIsTheKeyword(): void
    {
        self::assertSame('ESCAPE', \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTextKind::Escape->value);
    }
}
