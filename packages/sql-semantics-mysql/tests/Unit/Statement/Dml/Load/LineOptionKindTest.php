<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOptionKind;

#[CoversClass(LineOptionKind::class)]
#[Small]
final class LineOptionKindTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['TERMINATED', 'STARTING'], array_map(static fn (LineOptionKind $kind): string => $kind->value, LineOptionKind::cases()));
    }
}
