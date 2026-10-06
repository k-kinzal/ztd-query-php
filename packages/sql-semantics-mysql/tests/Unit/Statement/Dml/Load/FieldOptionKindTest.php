<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOptionKind;

#[CoversClass(FieldOptionKind::class)]
#[Small]
final class FieldOptionKindTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['TERMINATED', 'OPTIONALLY ENCLOSED', 'ENCLOSED', 'ESCAPED'], array_map(static fn (FieldOptionKind $kind): string => $kind->value, FieldOptionKind::cases()));
    }
}
