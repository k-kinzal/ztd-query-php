<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Catalog\Kind;

#[CoversClass(Kind\RelationKind::class)]
#[Medium]
final class RelationKindTest extends TestCase
{
    public function testSpellsEachObjectClassAsItsKeywords(): void
    {
        self::assertSame(['TABLE', 'SEQUENCE', 'VIEW', 'MATERIALIZED VIEW', 'INDEX', 'FOREIGN TABLE'], array_column(Kind\RelationKind::cases(), 'value'));
    }
}
