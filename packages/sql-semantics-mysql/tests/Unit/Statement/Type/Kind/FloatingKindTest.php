<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;

#[CoversClass(FloatingKind::class)]
#[Small]
final class FloatingKindTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEveryApproximateType(): void
    {
        self::assertSame(['Float', 'Real', 'Double'], array_column(FloatingKind::cases(), 'name'));
        self::assertSame(['FLOAT', 'REAL', 'DOUBLE'], array_column(FloatingKind::cases(), 'value'));
    }
}
