<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Value\ContextValueKind;

#[CoversClass(ContextValueKind::class)]
final class ContextValueKindTest extends TestCase
{
    public function testRepresentsEveryClockAndSessionRequest(): void
    {
        self::assertSame(
            ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'LOCALTIME', 'LOCALTIMESTAMP', 'CURRENT_USER', 'SESSION_USER', 'SYSTEM_USER', 'USER', 'CURRENT_ROLE', 'CURRENT_SCHEMA', 'CURRENT_CATALOG', 'UTC_DATE', 'UTC_TIME', 'UTC_TIMESTAMP', 'SYSDATE'],
            array_column(ContextValueKind::cases(), 'value'),
        );
    }

    public function testResolvesARequestFromItsKeyword(): void
    {
        self::assertSame(ContextValueKind::LocalTime, ContextValueKind::from('LOCALTIME'));
    }

    #[TestWith(['NOW'])]
    #[TestWith(['current_date'])]
    public function testLeavesOtherRequestsUnclassified(string $keyword): void
    {
        self::assertNull(ContextValueKind::tryFrom($keyword));
    }
}
