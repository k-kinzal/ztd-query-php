<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForceKind::class)]
#[Small]
final class CopyForceKindTest extends TestCase
{
    public function testCasesAreTheThreeForceOptions(): void
    {
        self::assertCount(3, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyForceKind::cases());
    }
}
