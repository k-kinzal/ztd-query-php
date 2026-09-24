<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Catalog\Kind;

#[CoversClass(Kind\OperatorSetKind::class)]
#[Medium]
final class OperatorSetKindTest extends TestCase
{
    public function testSpellsEachObjectClassAsItsKeywords(): void
    {
        self::assertSame(['OPERATOR CLASS', 'OPERATOR FAMILY'], array_column(Kind\OperatorSetKind::cases(), 'value'));
    }
}
