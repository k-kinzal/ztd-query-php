<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Catalog\Kind;

#[CoversClass(Kind\TypeKind::class)]
#[Medium]
final class TypeKindTest extends TestCase
{
    public function testSpellsEachObjectClassAsItsKeywords(): void
    {
        self::assertSame(['TYPE', 'DOMAIN'], array_column(Kind\TypeKind::cases(), 'value'));
    }
}
