<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind;

#[CoversClass(NumberOptionKind::class)]
#[Small]
final class NumberOptionKindTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('AVG_ROW_LENGTH', NumberOptionKind::AverageRowLength->value);
    }

    public function testDefaultableTellsWhichOptionsAcceptDefault(): void
    {
        self::assertTrue(NumberOptionKind::PackKeys->defaultable());
        self::assertTrue(NumberOptionKind::StatsSamplePages->defaultable());
        self::assertFalse(NumberOptionKind::MaxRows->defaultable());
    }
}
