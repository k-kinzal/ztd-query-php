<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Value\LiteralKind;

#[CoversClass(LiteralKind::class)]
final class LiteralKindTest extends TestCase
{
    public function testRepresentsEveryLiteralCategory(): void
    {
        self::assertSame(
            ['number', 'text', 'boolean', 'null', 'bit-string', 'binary', 'date', 'time', 'timestamp', 'interval'],
            array_column(LiteralKind::cases(), 'value'),
        );
    }

    public function testResolvesACategoryFromItsName(): void
    {
        self::assertSame(LiteralKind::BitString, LiteralKind::from('bit-string'));
    }

    #[TestWith(['identifier'])]
    #[TestWith(['Number'])]
    public function testLeavesOtherNamesUnclassified(string $name): void
    {
        self::assertNull(LiteralKind::tryFrom($name));
    }
}
