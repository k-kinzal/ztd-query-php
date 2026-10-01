<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\ReferenceKind;

#[CoversClass(ReferenceKind::class)]
#[Small]
final class ReferenceKindTest extends TestCase
{
    public function testCases(): void
    {
        self::assertCount(5, ReferenceKind::cases());
        self::assertSame('Undeclared', ReferenceKind::Undeclared->name);
        self::assertSame('CommonTableExpression', ReferenceKind::CommonTableExpression->name);
    }
}
