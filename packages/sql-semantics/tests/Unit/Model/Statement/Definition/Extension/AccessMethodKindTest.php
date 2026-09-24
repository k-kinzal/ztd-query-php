<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Definition\Extension\AccessMethodKind;

#[CoversClass(AccessMethodKind::class)]
#[Small]
final class AccessMethodKindTest extends TestCase
{
    public function testSpellsEachRelationKindAsItsKeyword(): void
    {
        self::assertSame(['TABLE', 'INDEX'], array_column(AccessMethodKind::cases(), 'value'));
    }
}
