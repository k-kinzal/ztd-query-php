<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberKind;

#[CoversClass(MemberKind::class)]
#[Small]
final class MemberKindTest extends TestCase
{
    public function testCasesSpellTheKind(): void
    {
        self::assertSame(['OPERATOR', 'FUNCTION'], [MemberKind::Operator->value, MemberKind::Function->value]);
    }
}
