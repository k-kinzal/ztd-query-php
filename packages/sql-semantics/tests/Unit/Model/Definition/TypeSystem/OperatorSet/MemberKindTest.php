<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\OperatorSet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\MemberKind;

#[CoversClass(MemberKind::class)]
final class MemberKindTest extends TestCase
{
    public function testSpellsTheSqlKeywords(): void
    {
        self::assertSame(['OPERATOR', 'FUNCTION'], array_map(static fn (MemberKind $kind): string => $kind->value, MemberKind::cases()));
    }
}
