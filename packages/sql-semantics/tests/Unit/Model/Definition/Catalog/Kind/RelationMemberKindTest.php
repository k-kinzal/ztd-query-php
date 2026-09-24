<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Catalog\Kind;

#[CoversClass(Kind\RelationMemberKind::class)]
#[Medium]
final class RelationMemberKindTest extends TestCase
{
    public function testSpellsEachObjectClassAsItsKeywords(): void
    {
        self::assertSame(['COLUMN', 'CONSTRAINT', 'POLICY', 'RULE', 'TRIGGER'], array_column(Kind\RelationMemberKind::cases(), 'value'));
    }
}
