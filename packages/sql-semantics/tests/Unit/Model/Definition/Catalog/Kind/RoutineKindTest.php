<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Catalog\Kind;

#[CoversClass(Kind\RoutineKind::class)]
#[Medium]
final class RoutineKindTest extends TestCase
{
    public function testSpellsEachObjectClassAsItsKeywords(): void
    {
        self::assertSame(['FUNCTION', 'PROCEDURE', 'ROUTINE'], array_column(Kind\RoutineKind::cases(), 'value'));
    }
}
