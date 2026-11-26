<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKind;

#[CoversClass(JoinKind::class)]
#[Small]
final class JoinKindTest extends TestCase
{
    public function testClosedSqlOperations(): void
    {
        self::assertSame(['INNER', 'CROSS', 'LEFT', 'RIGHT', 'FULL'], array_map(
            static fn (JoinKind $kind): string => $kind->value,
            JoinKind::cases(),
        ));
    }
}
